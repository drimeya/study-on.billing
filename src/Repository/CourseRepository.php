<?php

namespace App\Repository;

use App\Entity\Course;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Course>
 */
class CourseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Course::class);
    }

    public function save(Course $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Course $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Найти курс по символьному коду
     */
    public function findByCode(string $code): ?Course
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * Найти все платные курсы
     */
    public function findPaid(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.type != :free')
            ->setParameter('free', Course::TYPE_FREE)
            ->orderBy('c.code', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
