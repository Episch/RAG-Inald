<?php

declare(strict_types=1);

namespace App\Security;

use App\Service\Auth\RefreshTokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;

/**
 * Custom JWT Authentication Success Handler
 * 
 * Extends the default JWT handler to also issue refresh tokens
 */
class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();

        // Generate access token (JWT)
        $jwt = $this->jwtManager->create($user);

        // Generate refresh token (random string)
        $refreshToken = bin2hex(random_bytes(32));

        // Store refresh token in Redis (7 days TTL)
        $userId = $user->getUserIdentifier();
        $this->refreshTokenService->storeToken($refreshToken, $userId, 604800);

        $this->logger->info('User logged in successfully', [
            'user' => $userId,
        ]);

        return new JsonResponse([
            'token' => $jwt,
            'refresh_token' => $refreshToken,
            'user' => [
                'email' => $userId,
                'roles' => $user->getRoles(),
            ],
        ]);
    }
}

