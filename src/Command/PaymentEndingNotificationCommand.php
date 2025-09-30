<?php

namespace App\Command;

use App\Repository\TransactionRepository;
use App\Service\Twig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'payment:ending:notification',
    description: 'Рассылает пользователям уведомления об аренде курсов, истекающей в течение суток',
)]
class PaymentEndingNotificationCommand extends Command
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private MailerInterface $mailer,
        private Twig $twig,
        private string $fromEmail,
        private string $fromName,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $now = new \DateTimeImmutable();
        $in24h = $now->modify('+1 day');

        // Арендные транзакции, срок которых истекает в ближайшие сутки
        $transactions = $this->transactionRepository->findExpiringRentals($now, $in24h);

        if (empty($transactions)) {
            $io->success('Нет аренд, истекающих в течение суток. Письма не отправлялись.');
            return Command::SUCCESS;
        }

        // Группируем транзакции по email пользователя
        $byUser = [];
        foreach ($transactions as $transaction) {
            $email = $transaction->getUser()->getEmail();
            $byUser[$email][] = $transaction;
        }

        $sent = 0;
        foreach ($byUser as $userEmail => $userTransactions) {
            $html = $this->twig->render('email/ending_notification.html.twig', [
                'transactions' => $userTransactions,
            ]);

            $email = (new Email())
                ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
                ->to($userEmail)
                ->subject('Напоминание об окончании аренды курсов')
                ->html($html);

            $this->mailer->send($email);
            $sent++;

            $io->writeln(sprintf(
                '  → Отправлено уведомление на <info>%s</info> (%d курс(ов))',
                $userEmail,
                count($userTransactions)
            ));
        }

        $io->success(sprintf('Отправлено %d уведомление(й) об окончании аренды.', $sent));

        return Command::SUCCESS;
    }
}
