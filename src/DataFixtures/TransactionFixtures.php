<?php

namespace App\DataFixtures;

// Порядок загрузки: CourseFixtures → UserFixtures → TransactionFixtures (задаётся в тесте)

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Repository\UserRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Генерирует историю транзакций для покрытия граничных случаев:
 *
 *  - Бесплатный курс (course-0, course-3)   — транзакций нет
 *  - Покупка курса  (course-2)               — TYPE_PAYMENT, validUntil = null
 *  - Активная аренда (course-1)              — истекает через ~12 часов (для теста уведомлений)
 *  - Ещё одна активная аренда (course-4)     — истекает через ~5 дней (не попадает в уведомление)
 *  - Истёкшая аренда (course-4 — повтор)    — validUntil в прошлом (3 дня назад)
 *  - Оплата прошлого месяца (course-2)       — для теста ежемесячного отчёта
 */
class TransactionFixtures extends Fixture
{
    public function __construct(
        private CourseRepository $courseRepository,
        private UserRepository   $userRepository
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $user  = $this->userRepository->findOneBy(['email' => 'user@intaro.ru']);
        $admin = $this->userRepository->findOneBy(['email' => 'admin@intaro.ru']);

        $courseRent1 = $this->courseRepository->findByCode('course-1'); // аренда 99.90
        $courseBuy   = $this->courseRepository->findByCode('course-2'); // покупка 159.00
        $courseRent4 = $this->courseRepository->findByCode('course-4'); // аренда 149.90

        $now = new \DateTimeImmutable();

        // ──────────────────────────────────────────────────────────────────────
        // 1. Аренда course-1 у user@intaro.ru, истекает через ~12 часов
        //    → должна попасть в payment:ending:notification
        // ──────────────────────────────────────────────────────────────────────
        $t1 = $this->makePayment($user, $courseRent1,
            createdAt: $now->modify('-6 days'),
            validUntil: $now->modify('+12 hours')
        );
        $manager->persist($t1);

        // ──────────────────────────────────────────────────────────────────────
        // 2. Аренда course-4 у user@intaro.ru, истекает через 5 дней
        //    → НЕ должна попасть в уведомление (срок > 1 суток)
        // ──────────────────────────────────────────────────────────────────────
        $t2 = $this->makePayment($user, $courseRent4,
            createdAt: $now->modify('-2 days'),
            validUntil: $now->modify('+5 days')
        );
        $manager->persist($t2);

        // ──────────────────────────────────────────────────────────────────────
        // 3. Истёкшая аренда course-4 у user@intaro.ru (3 дня назад)
        //    → в skip_expired=1 должна быть скрыта
        // ──────────────────────────────────────────────────────────────────────
        $t3 = $this->makePayment($user, $courseRent4,
            createdAt: $now->modify('-10 days'),
            validUntil: $now->modify('-3 days')
        );
        $manager->persist($t3);

        // ──────────────────────────────────────────────────────────────────────
        // 4. Покупка course-2 у user@intaro.ru — в прошлом месяце
        //    → должна попасть в отчёт payment:report
        // ──────────────────────────────────────────────────────────────────────
        $lastMonthDate = (new \DateTimeImmutable('first day of last month'))->modify('+10 days');
        $t4 = $this->makePayment($user, $courseBuy,
            createdAt: $lastMonthDate,
            validUntil: null
        );
        $manager->persist($t4);

        // ──────────────────────────────────────────────────────────────────────
        // 5. Покупка course-2 у admin@intaro.ru — в прошлом месяце
        //    → тоже попадает в отчёт (2 покупки по 159.00)
        // ──────────────────────────────────────────────────────────────────────
        $t5 = $this->makePayment($admin, $courseBuy,
            createdAt: $lastMonthDate->modify('+3 days'),
            validUntil: null
        );
        $manager->persist($t5);

        // ──────────────────────────────────────────────────────────────────────
        // 6. Аренда course-1 у admin@intaro.ru — в прошлом месяце
        //    → попадает в отчёт как «Аренда»
        // ──────────────────────────────────────────────────────────────────────
        $t6 = $this->makePayment($admin, $courseRent1,
            createdAt: $lastMonthDate->modify('+1 day'),
            validUntil: $lastMonthDate->modify('+8 days')
        );
        $manager->persist($t6);

        // ──────────────────────────────────────────────────────────────────────
        // Обновляем балансы пользователей с учётом выполненных списаний
        // ──────────────────────────────────────────────────────────────────────
        // user: initial 1000 − 99.90 (rent1) − 149.90 (rent4) − 149.90 (rent4 expired) − 159.00 (buy) = 441.30
        $user->setBalance(441.30);
        $manager->persist($user);

        // admin: initial 1000 − 159.00 (buy) − 99.90 (rent1) = 741.10
        $admin->setBalance(741.10);
        $manager->persist($admin);

        $manager->flush();
    }

    private function makePayment(
        User $user,
        Course $course,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $validUntil
    ): Transaction {
        $t = new Transaction();
        $t->setUser($user);
        $t->setCourse($course);
        $t->setType(Transaction::TYPE_PAYMENT);
        $t->setValue($course->getPrice() ?? 0.0);
        $t->setCreatedAt($createdAt);
        $t->setValidUntil($validUntil);

        return $t;
    }
}
