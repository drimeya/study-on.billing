<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Exception\InsufficientFundsException;
use App\Repository\CourseRepository;
use App\Repository\TransactionRepository;
use App\Service\PaymentService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[OA\Tag(name: 'Courses')]
class CourseController extends AbstractController
{
    public function __construct(
        private CourseRepository $courseRepository,
        private TransactionRepository $transactionRepository,
        private PaymentService $paymentService
    ) {
    }

    // -------------------------------------------------------------------------
    // Список курсов
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/v1/courses',
        summary: 'Список всех курсов',
        description: 'Возвращает список всех курсов с типом и стоимостью. Аутентификация не требуется.',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Список курсов',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/CourseResponse')
                )
            ),
        ]
    )]
    #[Route('/api/v1/courses', name: 'api_courses_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $courses = $this->courseRepository->findAll();

        $data = array_map(fn (Course $c) => $this->serializeCourse($c), $courses);

        return new JsonResponse($data, Response::HTTP_OK);
    }

    // -------------------------------------------------------------------------
    // Получение отдельного курса
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/v1/courses/{code}',
        summary: 'Информация об отдельном курсе',
        description: 'Возвращает данные курса по символьному коду. Аутентификация не требуется.',
        parameters: [
            new OA\Parameter(
                name: 'code',
                in: 'path',
                required: true,
                description: 'Символьный код курса',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Данные курса',
                content: new OA\JsonContent(ref: '#/components/schemas/CourseResponse')
            ),
            new OA\Response(
                response: 404,
                description: 'Курс не найден',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    #[Route('/api/v1/courses/{code}', name: 'api_courses_show', methods: ['GET'])]
    public function show(string $code): JsonResponse
    {
        $course = $this->courseRepository->findByCode($code);

        if ($course === null) {
            return new JsonResponse(
                ['code' => 404, 'message' => 'Курс не найден'],
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse($this->serializeCourse($course), Response::HTTP_OK);
    }

    // -------------------------------------------------------------------------
    // Оплата курса
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/v1/courses/{code}/pay',
        summary: 'Оплата курса',
        description: 'Списывает стоимость курса с баланса пользователя и создаёт транзакцию. Требуется JWT-токен.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'code',
                in: 'path',
                required: true,
                description: 'Символьный код курса',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Оплата прошла успешно',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'course_type', type: 'string', example: 'rent'),
                        new OA\Property(
                            property: 'expires_at',
                            type: 'string',
                            format: 'date-time',
                            nullable: true,
                            example: '2019-05-27T13:46:07+00:00'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 406,
                description: 'Недостаточно средств',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 404,
                description: 'Курс не найден',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 401, description: 'Требуется аутентификация'),
        ]
    )]
    #[Route('/api/v1/courses/{code}/pay', name: 'api_courses_pay', methods: ['POST'])]
    public function pay(string $code): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $course = $this->courseRepository->findByCode($code);
        if ($course === null) {
            return new JsonResponse(
                ['code' => 404, 'message' => 'Курс не найден'],
                Response::HTTP_NOT_FOUND
            );
        }

        // Бесплатный курс — сразу успех, транзакция не нужна
        if ($course->isFree()) {
            return new JsonResponse([
                'success' => true,
                'course_type' => Course::TYPES[Course::TYPE_FREE],
                'expires_at' => null,
            ]);
        }

        // Уже есть активная оплата — возвращаем дату окончания без повторного списания
        $existingTransactions = $this->transactionRepository->findByUserWithFilters($user, [
            'course' => $course,
            'type' => Transaction::TYPE_PAYMENT,
            'skip_expired' => true,
        ]);
        if (!empty($existingTransactions)) {
            $existing = $existingTransactions[0];
            return new JsonResponse([
                'success' => true,
                'course_type' => $course->getTypeName(),
                'expires_at' => $existing->getValidUntil()?->format(\DateTimeInterface::ATOM),
            ]);
        }

        try {
            $transaction = $this->paymentService->pay($user, $course);
        } catch (InsufficientFundsException $e) {
            return new JsonResponse(
                ['code' => 406, 'message' => $e->getMessage()],
                Response::HTTP_NOT_ACCEPTABLE
            );
        }

        return new JsonResponse([
            'success' => true,
            'course_type' => $course->getTypeName(),
            'expires_at' => $transaction->getValidUntil()?->format(\DateTimeInterface::ATOM),
        ]);
    }

    private function serializeCourse(Course $course): array
    {
        $data = [
            'code' => $course->getCode(),
            'type' => $course->getTypeName(),
        ];

        if ($course->getPrice() !== null) {
            $data['price'] = $course->getPrice();
        }

        return $data;
    }
}
