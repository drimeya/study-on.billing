<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use App\Dto\UserDto;
use JMS\Serializer\SerializerBuilder;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use OpenApi\Attributes as OA;
use Symfony\Component\Security\Core\Annotation\Security;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use App\Service\PaymentService;

class AuthController extends AbstractController
{
    private JWTTokenManagerInterface $jwtManager;

    public function __construct(JWTTokenManagerInterface $jwtManager)
    {
        $this->jwtManager = $jwtManager;
    }

    #[OA\Post(
        path: "/api/v1/auth",
        summary: "Authenticate user",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                properties: [
                    new OA\Property(property: "username", type: "string", example: "user@example.com"),
                    new OA\Property(property: "password", type: "string", example: "password123")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Successful authentication",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "token", type: "string", description: "JWT access token"),
                        new OA\Property(property: "refresh_token", type: "string", description: "JWT refresh token (valid 30 days)")
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Invalid credentials"
            )
        ]
    )]
    #[Route('/api/v1/auth', name: 'api_auth', methods: ['POST'])]
    public function auth(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        try {
            $content = json_decode($request->getContent(), true);

            if (!$content || !isset($content['username']) || !isset($content['password'])) {
                throw new AuthenticationException('Missing username or password');
            }

            $email = $content['username'];
            $password = $content['password'];

            $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

            if (!$user) {
                throw new AuthenticationException('Invalid username');
            }

            if (!$passwordHasher->isPasswordValid($user, $password)) {
                throw new AuthenticationException('Invalid password');
            }
        } catch (AuthenticationException $e) {
            return new JsonResponse(['code' => 401, 'message' => $e->getMessage()], 401);
        }

        $token = $this->jwtManager->create($user);

        return new JsonResponse(['token' => $token], 200);
    }

    #[OA\Post(
        path: "/api/v1/register",
        summary: "Register a new user",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/UserDto")
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "User created successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "token", type: "string", description: "JWT access token"),
                        new OA\Property(property: "refresh_token", type: "string", description: "JWT refresh token (valid 30 days)"),
                        new OA\Property(property: "roles", type: "array", items: new OA\Items(type: "string"))
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Validation error or user already exists"
            )
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
    ): JsonResponse
    {
        $serializer = SerializerBuilder::create()->build();
        $userDto = $serializer->deserialize($request->getContent(), UserDto::class, 'json');
        $errors = $validator->validate($userDto);

        try {
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                throw new AuthenticationException(implode(', ', $errorMessages));
            }

            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $userDto->username]);
            if ($existingUser) {
                throw new AuthenticationException('User with this email already exists');
            }
        } catch (AuthenticationException $e) {
            return new JsonResponse(['code' => 401, 'message' => $e->getMessage()], 401);
        }

        $user = User::fromDto($userDto);
        $user->setRoles(['ROLE_USER']);
        $entityManager->persist($user);
        $entityManager->flush();

        // Начисляем стартовый депозит новому пользователю
        $paymentService->deposit($user, $paymentService->getInitialDeposit());

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
        path: "/api/v1/users/current",
        summary: "Get current authenticated user",
        security: [["Bearer" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Returns the current authenticated user",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "username", type: "string"),
                        new OA\Property(property: "roles", type: "array", items: new OA\Items(type: "string")),
                        new OA\Property(property: "balance", type: "number")
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "User not authenticated"
            )
        ]
    )]
    #[Security(name: "Bearer")]
    #[Route('/api/v1/users/current', name: 'api_current_user', methods: ['GET'])]
    public function getCurrentUser(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['code' => 401, 'message' => 'User not authenticated'], 401);
        }

        return new JsonResponse([
            'username' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'balance' => $user->getBalance(),
        ], 200);
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $response = new JsonResponse([
            'code' => $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500,
            'message' => $exception->getMessage(),
        ]);

        if ($exception instanceof HttpExceptionInterface) {
            $response->setStatusCode($exception->getStatusCode());
        } else {
            $response->setStatusCode(500);
        }

        $event->setResponse($response);
    }
}
