<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function save(Transaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Transaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Найти все транзакции пользователя (с фильтрами)
     *
     * @param array{type?: int, course?: Course, skip_expired?: bool} $filters
     * @return Transaction[]
     */
    public function findByUserWithFilters(User $user, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC');

        if (isset($filters['type'])) {
            $qb->andWhere('t.type = :type')
               ->setParameter('type', $filters['type']);
        }

        if (isset($filters['course'])) {
            $qb->andWhere('t.course = :course')
               ->setParameter('course', $filters['course']);
        }

        if (!empty($filters['skip_expired'])) {
            $qb->andWhere('t.validUntil IS NULL OR t.validUntil > :now')
               ->setParameter('now', new \DateTimeImmutable());
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Проверить, является ли курс активным для пользователя
     * (куплен или арендован и срок аренды не истёк)
     */
    public function hasActivePayment(User $user, Course $course): bool
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.user = :user')
            ->andWhere('t.course = :course')
            ->andWhere('t.type = :type')
            ->andWhere('t.validUntil IS NULL OR t.validUntil > :now')
            ->setParameter('user', $user)
            ->setParameter('course', $course)
            ->setParameter('type', Transaction::TYPE_PAYMENT)
            ->setParameter('now', new \DateTimeImmutable());

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Найти все транзакции пополнения баланса для пользователя
     */
    public function findDepositsByUser(User $user): array
    {
        return $this->findByUserWithFilters($user, ['type' => Transaction::TYPE_DEPOSIT]);
    }

    /**
     * Найти все транзакции оплаты курсов для пользователя
     */
    public function findPaymentsByUser(User $user): array
    {
        return $this->findByUserWithFilters($user, ['type' => Transaction::TYPE_PAYMENT]);
    }
}
