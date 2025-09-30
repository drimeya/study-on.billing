<?php

namespace App\Command;

use App\Entity\Course;
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
    name: 'payment:report',
    description: 'Генерирует ежемесячный отчёт по оплатам курсов и отправляет его на служебный email',
)]
class PaymentReportCommand extends Command
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private MailerInterface $mailer,
        private Twig $twig,
        private string $reportEmail,
        private string $fromEmail,
        private string $fromName,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Отчёт за прошлый месяц
        $now        = new \DateTimeImmutable();
        $periodFrom = new \DateTimeImmutable('first day of last month midnight');
        $periodTo   = new \DateTimeImmutable('first day of this month midnight');

        $io->writeln(sprintf(
            'Формирование отчёта за период <info>%s</info> – <info>%s</info>',
            $periodFrom->format('d.m.Y'),
            $periodTo->modify('-1 second')->format('d.m.Y')
        ));

        $transactions = $this->transactionRepository->findPaymentsByPeriod($periodFrom, $periodTo);

        // Агрегируем по курсу
        /** @var array<string, array{title: string, type: string, count: int, total: float}> $summary */
        $summary = [];
        foreach ($transactions as $transaction) {
            $course = $transaction->getCourse();
            if ($course === null) {
                continue;
            }

            $code = $course->getCode();
            if (!isset($summary[$code])) {
                $summary[$code] = [
                    'title' => $course->getTitle(),
                    'type'  => $course->getTypeName(),
                    'count' => 0,
                    'total' => 0.0,
                ];
            }

            $summary[$code]['count']++;
            $summary[$code]['total'] += $transaction->getValue();
        }

        // Итоговая сумма
        $grandTotal = array_sum(array_column($summary, 'total'));

        // Выводим в консоль
        if (empty($summary)) {
            $io->warning('За указанный период оплат не найдено.');
        } else {
            $io->table(
                ['Курс', 'Тип', 'Кол-во', 'Сумма'],
                array_map(fn ($row) => [
                    $row['title'],
                    $row['type'],
                    $row['count'],
                    number_format($row['total'], 2, '.', ' ') . ' ₽',
                ], $summary)
            );
            $io->writeln(sprintf('<info>Итого: %s ₽</info>', number_format($grandTotal, 2, '.', ' ')));
        }

        // Рендерим шаблон и отправляем письмо
        $displayTo = $periodTo->modify('-1 second');
        $html = $this->twig->render('email/payment_report.html.twig', [
            'rows'        => array_values($summary),
            'grand_total' => $grandTotal,
            'period_from' => $periodFrom,
            'period_to'   => $displayTo,
        ]);

        $subject = sprintf(
            'Отчёт об оплаченных курсах за %s – %s',
            $periodFrom->format('d.m.Y'),
            $displayTo->format('d.m.Y')
        );

        $email = (new Email())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
            ->to($this->reportEmail)
            ->subject($subject)
            ->html($html);

        $this->mailer->send($email);

        $io->success(sprintf('Отчёт отправлен на адрес %s', $this->reportEmail));

        return Command::SUCCESS;
    }
}
