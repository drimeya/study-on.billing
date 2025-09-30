<?php

namespace App\Controller;

use App\Dto\UserDto;
use App\Entity\User;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Annotation\Security;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthController extends AbstractController
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    #[OA\Post(
        path: '/api/v1/auth',
        summary: 'Авторизация пользователя',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'password123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешная авторизация',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'token', type: 'string', description: 'JWT access-токен'),
                        new OA\Property(property: 'refresh_token', type: 'string', description: 'JWT refresh-токен (действует 30 дней)'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Неверные учётные данные'),
        ]
    )]
    #[Route('/api/v1/auth', name: 'api_auth', methods: ['POST'])]
    public function auth(): JsonResponse
    {
        // Маршрут полностью обрабатывается фаерволом Symfony (json_login + LexikJWT).
        throw new \LogicException('Этот метод не вызывается напрямую.');
    }

    #[OA\Post(
        path: '/api/v1/register',
        summary: 'Регистрация нового пользователя',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UserDto')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Пользователь успешно создан',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'token', type: 'string', description: 'JWT access-токен'),
                        new OA\Property(property: 'refresh_token', type: 'string', description: 'JWT refresh-токен (действует 30 дней)'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Ошибка валидации'),
            new OA\Response(response: 409, description: 'Пользователь с таким email уже существует'),
        ]
    )]
    #[Route('/api/v1/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager,
        RefreshTokenGeneratorInterface $refreshTokenGenerator,
        RefreshTokenManagerInterface $refreshTokenManager,
        PaymentService $paymentService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        $userDto = new UserDto();
        $userDto->username = $data['username'] ?? '';
        $userDto->password = $data['password'] ?? '';

        $errors = $validator->validate($userDto);

        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->getMessage();
            }

            return new JsonResponse(['code' => 400, 'message' => implode(', ', $messages)], 400);
        }

        if ($entityManager->getRepository(User::class)->findOneBy(['email' => $userDto->username])) {
            return new JsonResponse(['code' => 409, 'message' => 'Пользователь с таким email уже существует'], 409);
        }

        $user = User::fromDto($userDto);
        $user->setRoles(['ROLE_USER']);

        try {
            $entityManager->wrapInTransaction(function () use ($entityManager, $user, $paymentService): void {
                $entityManager->persist($user);
                $entityManager->flush();
                $paymentService->deposit($user, $paymentService->getInitialDeposit());
            });
        } catch (\Throwable) {
            return new JsonResponse(['code' => 500, 'message' => 'Ошибка при создании пользователя'], 500);
        }

        $token = $this->jwtManager->create($user);

        $refreshToken = $refreshTokenGenerator->createForUserWithTtl(
            $user,
            (new \DateTime())->modify('+1 month')->getTimestamp()
        );
        $refreshTokenManager->save($refreshToken);

        return new JsonResponse([
            'token' => $token,
            'refresh_token' => $refreshToken->getRefreshToken(),
            'roles' => $user->getRoles(),
        ], 201);
    }

    #[OA\Get(
        path: '/api/v1/users/current',
        summary: 'Информация о текущем пользователе',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Данные авторизованного пользователя',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'username', type: 'string'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                        new OA\Property(property: 'balance', type: 'number'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Пользователь не авторизован'),
        ]
    )]
    #[Security(name: 'Bearer')]
    #[Route('/api/v1/users/current', name: 'api_current_user', methods: ['GET'])]
    public function getCurrentUser(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['code' => 401, 'message' => 'Пользователь не авторизован'], 401);
        }

        return new JsonResponse([
            'username' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'balance' => $user->getBalance(),
        ], 200);
    }
}
