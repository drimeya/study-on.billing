<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Exception\InsufficientFundsException;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

class PaymentService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TransactionRepository $transactionRepository,
        private float $initialDeposit
    ) {
    }

    /**
     * Пополняет баланс пользователя и фиксирует транзакцию типа deposit.
     * Выполняется внутри Doctrine-транзакции — при исключении откатывается.
     *
     * @throws \Throwable
     */
    public function deposit(User $user, float $amount): Transaction
    {
        return $this->em->wrapInTransaction(function () use ($user, $amount): Transaction {
            $user->setBalance($user->getBalance() + $amount);

            $transaction = new Transaction();
            $transaction->setUser($user);
            $transaction->setType(Transaction::TYPE_DEPOSIT);
            $transaction->setValue($amount);

            $this->em->persist($user);
            $this->em->persist($transaction);

            return $transaction;
        });
    }

    /**
     * Оплачивает курс: проверяет баланс, создаёт транзакцию payment и списывает сумму.
     * Выполняется внутри Doctrine-транзакции — при исключении откатывается.
     *
     * @throws InsufficientFundsException если на счету недостаточно средств
     * @throws \Throwable
     */
    public function pay(User $user, Course $course): Transaction
    {
        return $this->em->wrapInTransaction(function () use ($user, $course): Transaction {
            $price = $course->getPrice() ?? 0.0;

            if ($user->getBalance() < $price) {
                throw new InsufficientFundsException();
            }

            $user->setBalance($user->getBalance() - $price);

            $transaction = new Transaction();
            $transaction->setUser($user);
            $transaction->setCourse($course);
            $transaction->setType(Transaction::TYPE_PAYMENT);
            $transaction->setValue($price);

            if ($course->isRent()) {
                $transaction->setValidUntil(new \DateTimeImmutable('+1 week'));
            }

            $this->em->persist($user);
            $this->em->persist($transaction);

            return $transaction;
        });
    }

    public function getInitialDeposit(): float
    {
        return $this->initialDeposit;
    }
}
