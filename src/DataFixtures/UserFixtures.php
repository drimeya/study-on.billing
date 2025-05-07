<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Создание пользователя с ролью ROLE_USER
        $user = new User();
        $user->setEmail('user@intaro.ru');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
        $user->setBalance(1000.0); // Установите начальный баланс для пользователя
        $manager->persist($user);

        // Создание пользователя с ролью ROLE_SUPER_ADMIN
        $admin = new User();
        $admin->setEmail('admin@intaro.ru');
        $admin->setRoles(['ROLE_SUPER_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'adminpassword'));
        $manager->persist($admin);

        $manager->flush();
    }
}
