<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Auth\RefreshTokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * JWT Refresh Token Controller
 * 
 * Handles JWT token refresh using Redis-backed refresh tokens
 */
class RefreshTokenController extends AbstractController
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokenService,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserProviderInterface $userProvider,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/api/token/refresh', name: 'api_token_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['refresh_token'])) {
            return $this->json([
                'error' => 'Missing refresh_token',
                'message' => 'refresh_token is required in request body',
            ], 400);
        }

        $refreshToken = $data['refresh_token'];

        // Verify refresh token and get user ID
        $userId = $this->refreshTokenService->verifyToken($refreshToken);

        if ($userId === null) {
            $this->logger->warning('Invalid or expired refresh token attempt');
            
            return $this->json([
                'error' => 'Invalid refresh token',
                'message' => 'The provided refresh token is invalid or has expired',
            ], 401);
        }

        try {
            // Load user (in this simple case, we just use the email from the token)
            // In a real app with a database, you'd load the user entity here
            $user = $this->userProvider->loadUserByIdentifier($userId);

            if (!$user) {
                return $this->json([
                    'error' => 'User not found',
                    'message' => 'The user associated with this refresh token no longer exists',
                ], 404);
            }

            // Generate new access token
            $newAccessToken = $this->jwtManager->create($user);

            // Optional: Token Rotation - generate new refresh token
            $rotateTokens = $data['rotate'] ?? true;
            $newRefreshToken = null;

            if ($rotateTokens) {
                // Generate new refresh token
                $newRefreshToken = bin2hex(random_bytes(32));
                
                // Store new refresh token (7 days TTL)
                $this->refreshTokenService->storeToken(
                    $newRefreshToken,
                    $userId,
                    604800
                );

                // Revoke old refresh token
                $this->refreshTokenService->revokeToken($refreshToken);

                $this->logger->info('Refresh token rotated', [
                    'user_id' => $userId,
                ]);
            }

            $response = [
                'token' => $newAccessToken,
                'user' => [
                    'email' => $user->getUserIdentifier(),
                    'roles' => $user->getRoles(),
                ],
            ];

            if ($newRefreshToken) {
                $response['refresh_token'] = $newRefreshToken;
            }

            $this->logger->info('Access token refreshed', [
                'user_id' => $userId,
                'rotated' => $rotateTokens,
            ]);

            return $this->json($response);

        } catch (\Exception $e) {
            $this->logger->error('Token refresh failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'error' => 'Token refresh failed',
                'message' => 'An error occurred while refreshing your token',
            ], 500);
        }
    }

    #[Route('/api/token/revoke', name: 'api_token_revoke', methods: ['POST'])]
    public function revoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['refresh_token'])) {
            return $this->json([
                'error' => 'Missing refresh_token',
                'message' => 'refresh_token is required in request body',
            ], 400);
        }

        $refreshToken = $data['refresh_token'];
        $revoked = $this->refreshTokenService->revokeToken($refreshToken);

        if ($revoked) {
            return $this->json([
                'message' => 'Refresh token revoked successfully',
            ]);
        }

        return $this->json([
            'error' => 'Token not found',
            'message' => 'The refresh token does not exist or was already revoked',
        ], 404);
    }

    #[Route('/api/token/revoke-all', name: 'api_token_revoke_all', methods: ['POST'])]
    public function revokeAll(): JsonResponse
    {
        // This endpoint requires admin role
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->getUser();
        $userId = $user->getUserIdentifier();

        $revokedCount = $this->refreshTokenService->revokeAllUserTokens($userId);

        return $this->json([
            'message' => 'All refresh tokens revoked successfully',
            'revoked_count' => $revokedCount,
        ]);
    }
}

