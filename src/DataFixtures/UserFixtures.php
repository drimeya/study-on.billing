<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Service\PaymentService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private PaymentService $paymentService
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('user@intaro.ru');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
        $manager->persist($user);
        $manager->flush();

        // Начальное пополнение баланса через PaymentService
        $this->paymentService->deposit($user, $this->paymentService->getInitialDeposit());

        $admin = new User();
        $admin->setEmail('admin@intaro.ru');
        $admin->setRoles(['ROLE_SUPER_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'adminpassword'));
        $manager->persist($admin);
        $manager->flush();

        // Администратору тоже начисляем стартовый депозит
        $this->paymentService->deposit($admin, $this->paymentService->getInitialDeposit());
    }
}
