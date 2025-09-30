<?php

namespace App\DataFixtures;

use App\Entity\Course;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CourseFixtures extends Fixture
{
    public const COURSE_FREE_REF   = 'course-free';
    public const COURSE_RENT_REF   = 'course-rent';
    public const COURSE_BUY_REF    = 'course-buy';

    public function load(ObjectManager $manager): void
    {
        // course-0: бесплатный
        $free1 = new Course();
        $free1->setCode('course-0');
        $free1->setTitle('Изучение Symfony');
        $free1->setType(Course::TYPE_FREE);
        $manager->persist($free1);
        $this->addReference(self::COURSE_FREE_REF, $free1);

        // course-1: аренда
        $rent1 = new Course();
        $rent1->setCode('course-1');
        $rent1->setTitle('Doctrine ORM');
        $rent1->setType(Course::TYPE_RENT);
        $rent1->setPrice(99.90);
        $manager->persist($rent1);
        $this->addReference(self::COURSE_RENT_REF, $rent1);

        // course-2: покупка
        $buy = new Course();
        $buy->setCode('course-2');
        $buy->setTitle('Модель данных');
        $buy->setType(Course::TYPE_FULL);
        $buy->setPrice(159.00);
        $manager->persist($buy);
        $this->addReference(self::COURSE_BUY_REF, $buy);

        // course-3: бесплатный
        $free2 = new Course();
        $free2->setCode('course-3');
        $free2->setTitle('Frontend в Symfony');
        $free2->setType(Course::TYPE_FREE);
        $manager->persist($free2);

        // course-4: аренда
        $rent2 = new Course();
        $rent2->setCode('course-4');
        $rent2->setTitle('Тестирование');
        $rent2->setType(Course::TYPE_RENT);
        $rent2->setPrice(149.90);
        $manager->persist($rent2);

        $manager->flush();
    }
}
