<?php

namespace App\Tests;

use App\DataFixtures\CourseFixtures;
use App\DataFixtures\TransactionFixtures;
use App\DataFixtures\UserFixtures;
use Symfony\Component\HttpFoundation\Response;

class CourseControllerTest extends AbstractBillingTest
{
    private string $userToken;
    private string $adminToken;

    protected function getFixtures(): array
    {
        return [CourseFixtures::class, UserFixtures::class, TransactionFixtures::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->userToken  = $this->getAuthToken('user@intaro.ru',  'password123');
        $this->adminToken = $this->getAuthToken('admin@intaro.ru', 'adminpassword');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/courses — список курсов (публичный)
    // ─────────────────────────────────────────────────────────────────────────

    public function testGetCoursesIsPublic(): void
    {
        $data = $this->apiRequest('GET', '/api/v1/courses');

        $this->assertSame(Response::HTTP_OK, $this->getStatusCode());
        $this->assertIsArray($data);
        $this->assertCount(5, $data);
    }

    public function testGetCoursesContainsTitleField(): void
    {
        $data = $this->apiRequest('GET', '/api/v1/courses');

        foreach ($data as $course) {
            $this->assertArrayHasKey('title', $course);
            $this->assertArrayHasKey('code',  $course);
            $this->assertArrayHasKey('type',  $course);
        }
    }

    public function testGetCoursesContainsAllTypes(): void
    {
        $data  = $this->apiRequest('GET', '/api/v1/courses');
        $types = array_column($data, 'type');

        $this->assertContains('free', $types);
        $this->assertContains('rent', $types);
        $this->assertContains('buy',  $types);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/courses/{code} — один курс (публичный)
    // ─────────────────────────────────────────────────────────────────────────

    public function testGetCoursePublic(): void
    {
        $data = $this->apiRequest('GET', '/api/v1/courses/course-1');

        $this->assertSame(Response::HTTP_OK, $this->getStatusCode());
        $this->assertSame('course-1',    $data['code']);
        $this->assertSame('Doctrine ORM', $data['title']);
        $this->assertSame('rent',         $data['type']);
        $this->assertEqualsWithDelta(99.90, $data['price'], 0.01);
    }

    public function testGetCourseNotFound(): void
    {
        $this->apiRequest('GET', '/api/v1/courses/nonexistent-xyz');

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->getStatusCode());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/v1/courses — создание курса (только ROLE_SUPER_ADMIN)
    // ─────────────────────────────────────────────────────────────────────────

    public function testCreateCourseRequiresAuth(): void
    {
        $this->apiRequest('POST', '/api/v1/courses', [
            'code' => 'test-new', 'title' => 'Тест', 'type' => 'free',
        ]);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->getStatusCode());
    }

    public function testCreateCourseForbiddenForRegularUser(): void
    {
        $this->apiRequest('POST', '/api/v1/courses', [
            'code' => 'test-new', 'title' => 'Тест', 'type' => 'free',
        ], $this->userToken);

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->getStatusCode());
    }

    public function testCreateCourseSuccessForAdmin(): void
    {
        $data = $this->apiRequest('POST', '/api/v1/courses', [
            'code'  => 'new-course',
            'title' => 'Новый тестовый курс',
            'type'  => 'rent',
            'price' => 79.90,
        ], $this->adminToken);

        $this->assertSame(Response::HTTP_CREATED, $this->getStatusCode());
        $this->assertTrue($data['success']);

        // Проверяем, что курс появился в списке
        $courses = $this->apiRequest('GET', '/api/v1/courses');
        $codes   = array_column($courses, 'code');
        $this->assertContains('new-course', $codes);
    }

    public function testCreateCourseDuplicateCodeReturns422(): void
    {
        $data = $this->apiRequest('POST', '/api/v1/courses', [
            'code' => 'course-0', // уже существует
            'title' => 'Дубликат',
            'type'  => 'free',
        ], $this->adminToken);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->getStatusCode());
        $this->assertStringContainsString('существует', $data['message']);
    }

    public function testCreateCourseWithMissingFieldsReturns422(): void
    {
        $data = $this->apiRequest('POST', '/api/v1/courses', [
            'title' => 'Без кода',
            'type'  => 'free',
            // code отсутствует
        ], $this->adminToken);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->getStatusCode());
    }

    public function testCreateCourseWithInvalidTypeReturns422(): void
    {
        $data = $this->apiRequest('POST', '/api/v1/courses', [
            'code'  => 'bad-type-course',
            'title' => 'Плохой тип',
            'type'  => 'unknown',
        ], $this->adminToken);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->getStatusCode());
    }

    public function testCreateFreeCourseHasNoPrice(): void
    {
        $this->apiRequest('POST', '/api/v1/courses', [
            'code'  => 'free-no-price',
            'title' => 'Бесплатный',
            'type'  => 'free',
            'price' => 50.0, // должна быть проигнорирована
        ], $this->adminToken);

        $this->assertSame(Response::HTTP_CREATED, $this->getStatusCode());

        $course = $this->apiRequest('GET', '/api/v1/courses/free-no-price');
        $this->assertArrayNotHasKey('price', $course);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/v1/courses/{code} — редактирование курса (только ROLE_SUPER_ADMIN)
    // ─────────────────────────────────────────────────────────────────────────

    public function testUpdateCourseRequiresAuth(): void
    {
        $this->apiRequest('POST', '/api/v1/courses/course-0', [
            'code' => 'course-0', 'title' => 'Новое название', 'type' => 'free',
        ]);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->getStatusCode());
    }

    public function testUpdateCourseForbiddenForRegularUser(): void
    {
        $this->apiRequest('POST', '/api/v1/courses/course-0', [
            'code' => 'course-0', 'title' => 'Новое название', 'type' => 'free',
        ], $this->userToken);

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->getStatusCode());
    }

    public function testUpdateCourseSuccessForAdmin(): void
    {
        $data = $this->apiRequest('POST', '/api/v1/courses/course-0', [
            'code'  => 'course-0',
            'title' => 'Обновлённое название',
            'type'  => 'rent',
            'price' => 49.90,
        ], $this->adminToken);

        $this->assertSame(Response::HTTP_OK, $this->getStatusCode());
        $this->assertTrue($data['success']);

        // Проверяем обновлённые данные
        $course = $this->apiRequest('GET', '/api/v1/courses/course-0');
        $this->assertSame('Обновлённое название', $course['title']);
        $this->assertSame('rent', $course['type']);
        $this->assertEqualsWithDelta(49.90, $course['price'], 0.01);
    }

    public function testUpdateCourseChangesCode(): void
    {
        $this->apiRequest('POST', '/api/v1/courses/course-3', [
            'code'  => 'course-3-renamed',
            'title' => 'Frontend (переименован)',
            'type'  => 'free',
        ], $this->adminToken);

        $this->assertSame(Response::HTTP_OK, $this->getStatusCode());

        // Старый код не работает
        $this->apiRequest('GET', '/api/v1/courses/course-3');
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->getStatusCode());

        // Новый код работает
        $this->apiRequest('GET', '/api/v1/courses/course-3-renamed');
        $this->assertSame(Response::HTTP_OK, $this->getStatusCode());
    }

    public function testUpdateCourseNotFound(): void
    {
        $this->apiRequest('POST', '/api/v1/courses/nonexistent-xyz', [
            'code' => 'nonexistent-xyz', 'title' => 'X', 'type' => 'free',
        ], $this->adminToken);

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->getStatusCode());
    }

    public function testUpdateCourseCodeConflictReturns422(): void
    {
        // Пытаемся переименовать course-0 в course-1 (course-1 уже существует)
        $data = $this->apiRequest('POST', '/api/v1/courses/course-0', [
            'code'  => 'course-1',
            'title' => 'Конфликт кода',
            'type'  => 'free',
        ], $this->adminToken);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->getStatusCode());
        $this->assertStringContainsString('существует', $data['message']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/v1/courses/{code}/pay — оплата (требует авторизации любого пользователя)
    // ─────────────────────────────────────────────────────────────────────────

    public function testPayCourseRequiresAuth(): void
    {
        $this->apiRequest('POST', '/api/v1/courses/course-2/pay');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->getStatusCode());
    }

    public function testPayFreeCourseReturnsSuccess(): void
    {
        $data = $this->apiRequest('POST', '/api/v1/courses/course-0/pay', [], $this->userToken);

        $this->assertSame(Response::HTTP_OK, $this->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame('free', $data['course_type']);
    }

    public function testPayRentCourseCreatesActiveTransaction(): void
    {
        // course-4 (аренда, 149.90) — у user уже есть истёкшая аренда,
        // но активных нет, поэтому оплата должна пройти
        $data = $this->apiRequest('POST', '/api/v1/courses/course-4/pay', [], $this->userToken);

        $this->assertSame(Response::HTTP_OK, $this->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame('rent', $data['course_type']);
        $this->assertNotNull($data['expires_at']);
    }

    public function testPayAlreadyActiveRentReturnsExistingExpiry(): void
    {
        // course-1 у user уже арендован и активен (fixtures: +12 hours)
        $data = $this->apiRequest('POST', '/api/v1/courses/course-1/pay', [], $this->userToken);

        $this->assertSame(Response::HTTP_OK, $this->getStatusCode());
        $this->assertTrue($data['success']);
        // Повторного списания нет — expires_at из существующей транзакции
        $this->assertNotNull($data['expires_at']);
    }
}
