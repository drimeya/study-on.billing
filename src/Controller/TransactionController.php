<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Repository\TransactionRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[OA\Tag(name: 'Транзакции')]
class TransactionController extends AbstractController
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private CourseRepository $courseRepository
    ) {
    }

    #[OA\Get(
        path: '/api/v1/transactions',
        summary: 'История транзакций текущего пользователя',
        description: 'Возвращает список транзакций (начислений и списаний) с поддержкой фильтров. Требуется JWT-токен.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'filter[type]',
                in: 'query',
                required: false,
                description: 'Тип транзакции: payment или deposit',
                schema: new OA\Schema(type: 'string', enum: ['payment', 'deposit'])
            ),
            new OA\Parameter(
                name: 'filter[course_code]',
                in: 'query',
                required: false,
                description: 'Символьный код курса',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'filter[skip_expired]',
                in: 'query',
                required: false,
                description: 'Если true — отбросить истёкшие аренды',
                schema: new OA\Schema(type: 'boolean')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Список транзакций',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/TransactionResponse')
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Некорректный фильтр',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 401, description: 'Требуется аутентификация'),
        ]
    )]
    #[Route('/api/v1/transactions', name: 'api_transactions_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $filter = $request->query->all('filter');
        $filters = [];

        if (!empty($filter['type'])) {
            $typeMap = [
                'payment' => Transaction::TYPE_PAYMENT,
                'deposit' => Transaction::TYPE_DEPOSIT,
            ];
            if (!isset($typeMap[$filter['type']])) {
                return new JsonResponse(
                    ['code' => 400, 'message' => 'Недопустимое значение filter[type]. Допустимы: payment, deposit'],
                    Response::HTTP_BAD_REQUEST
                );
            }
            $filters['type'] = $typeMap[$filter['type']];
        }

        if (!empty($filter['course_code'])) {
            $course = $this->courseRepository->findByCode($filter['course_code']);
            if ($course === null) {
                return new JsonResponse(
                    ['code' => 400, 'message' => 'Курс с кодом "' . $filter['course_code'] . '" не найден'],
                    Response::HTTP_BAD_REQUEST
                );
            }
            $filters['course'] = $course;
        }

        if (!empty($filter['skip_expired'])) {
            $filters['skip_expired'] = true;
        }

        $transactions = $this->transactionRepository->findByUserWithFilters($user, $filters);

        $data = array_map(fn (Transaction $t) => $this->serializeTransaction($t), $transactions);

        return new JsonResponse($data, Response::HTTP_OK);
    }

    private function serializeTransaction(Transaction $transaction): array
    {
        $data = [
            'id' => $transaction->getId(),
            'created_at' => $transaction->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'type' => $transaction->getTypeName(),
            'amount' => $transaction->getValue(),
        ];

        if ($transaction->getCourse() !== null) {
            $data['course_code'] = $transaction->getCourse()->getCode();
        }

        if ($transaction->getValidUntil() !== null) {
            $data['expires_at'] = $transaction->getValidUntil()->format(\DateTimeInterface::ATOM);
        }

        return $data;
    }
}
