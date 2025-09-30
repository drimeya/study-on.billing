<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Exception\InsufficientFundsException;
use App\Repository\CourseRepository;
use App\Repository\TransactionRepository;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[OA\Tag(name: 'Курсы')]
class CourseController extends AbstractController
{
    public function __construct(
        private CourseRepository $courseRepository,
        private TransactionRepository $transactionRepository,
        private PaymentService $paymentService,
        private EntityManagerInterface $entityManager
    ) {
    }

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

    #[OA\Post(
        path: '/api/v1/courses',
        summary: 'Создание курса',
        description: 'Создаёт новый курс. Доступно только администратору.',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code', 'title', 'type'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', example: 'new-course'),
                    new OA\Property(property: 'title', type: 'string', example: 'Новый курс'),
                    new OA\Property(property: 'type', type: 'string', enum: ['free', 'rent', 'buy'], example: 'rent'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', nullable: true, example: 99.90),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Курс создан',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'success', type: 'boolean', example: true)]
                )
            ),
            new OA\Response(response: 422, description: 'Ошибка валидации', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Требуется аутентификация'),
            new OA\Response(response: 403, description: 'Доступ запрещён'),
        ]
    )]
    #[Route('/api/v1/courses', name: 'api_courses_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $data  = json_decode($request->getContent(), true) ?? [];
        $code  = trim($data['code']  ?? '');
        $title = trim($data['title'] ?? '');
        $type  = trim($data['type']  ?? '');
        $price = isset($data['price']) ? (float) $data['price'] : null;

        if ($code === '' || $title === '' || $type === '') {
            return new JsonResponse(['code' => 422, 'message' => 'Обязательные поля: code, title, type'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $typeMap = array_flip(Course::TYPES);
        if (!isset($typeMap[$type])) {
            return new JsonResponse(['code' => 422, 'message' => 'Недопустимый тип курса. Допустимые значения: free, rent, buy'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->courseRepository->findByCode($code) !== null) {
            return new JsonResponse(['code' => 422, 'message' => 'Курс с таким кодом уже существует'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $course = new Course();
        $course->setCode($code);
        $course->setTitle($title);
        $course->setType($typeMap[$type]);
        $course->setPrice(($type === 'free') ? null : $price);

        $this->entityManager->persist($course);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true], Response::HTTP_CREATED);
    }

    #[OA\Post(
        path: '/api/v1/courses/{code}',
        summary: 'Редактирование курса',
        description: 'Обновляет данные курса. Доступно только администратору.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'code',
                in: 'path',
                required: true,
                description: 'Текущий символьный код курса',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code', 'title', 'type'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', example: 'updated-code'),
                    new OA\Property(property: 'title', type: 'string', example: 'Обновлённый курс'),
                    new OA\Property(property: 'type', type: 'string', enum: ['free', 'rent', 'buy'], example: 'buy'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', nullable: true, example: 159.00),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Курс обновлён',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'success', type: 'boolean', example: true)]
                )
            ),
            new OA\Response(response: 404, description: 'Курс не найден', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Ошибка валидации', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Требуется аутентификация'),
            new OA\Response(response: 403, description: 'Доступ запрещён'),
        ]
    )]
    #[Route('/api/v1/courses/{code}', name: 'api_courses_update', methods: ['POST'])]
    public function update(string $code, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $course = $this->courseRepository->findByCode($code);
        if ($course === null) {
            return new JsonResponse(['code' => 404, 'message' => 'Курс не найден'], Response::HTTP_NOT_FOUND);
        }

        $data    = json_decode($request->getContent(), true) ?? [];
        $newCode = trim($data['code']  ?? '');
        $title   = trim($data['title'] ?? '');
        $type    = trim($data['type']  ?? '');
        $price   = isset($data['price']) ? (float) $data['price'] : null;

        if ($newCode === '' || $title === '' || $type === '') {
            return new JsonResponse(['code' => 422, 'message' => 'Обязательные поля: code, title, type'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $typeMap = array_flip(Course::TYPES);
        if (!isset($typeMap[$type])) {
            return new JsonResponse(['code' => 422, 'message' => 'Недопустимый тип курса. Допустимые значения: free, rent, buy'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($newCode !== $code && $this->courseRepository->findByCode($newCode) !== null) {
            return new JsonResponse(['code' => 422, 'message' => 'Курс с таким кодом уже существует'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $course->setCode($newCode);
        $course->setTitle($title);
        $course->setType($typeMap[$type]);
        $course->setPrice(($type === 'free') ? null : $price);

        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

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
            new OA\Response(response: 406, description: 'Недостаточно средств', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Курс не найден', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
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

        if ($course->isFree()) {
            return new JsonResponse([
                'success' => true,
                'course_type' => Course::TYPES[Course::TYPE_FREE],
                'expires_at' => null,
            ]);
        }

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
            'code'  => $course->getCode(),
            'title' => $course->getTitle(),
            'type'  => $course->getTypeName(),
        ];

        if ($course->getPrice() !== null) {
            $data['price'] = $course->getPrice();
        }

        return $data;
    }
}
