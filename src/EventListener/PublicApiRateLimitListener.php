<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Psr\Log\LoggerInterface;

/**
 * Rate Limiter für öffentliche API-Endpunkte
 * 
 * Limitiert öffentliche Routen auf 3 Requests pro Minute (alle 20 Sekunden)
 * nach Symfony Best Practices
 */
class PublicApiRateLimitListener implements EventSubscriberInterface
{
    private const PUBLIC_ROUTES = [
        '/api/login',
        '/api/token/refresh',
        '/api/token/revoke',
        '/api/health',
        '/api/models',
        '/api/docs',
    ];

    public function __construct(
        private readonly RateLimiterFactory $publicApiLimiter,
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10], // Before security
            KernelEvents::RESPONSE => ['onKernelResponse', -10], // After controller
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Nur öffentliche Routen limitieren
        if (!$this->isPublicRoute($path)) {
            return;
        }

        // Rate Limiter basierend auf IP-Adresse UND Endpunkt (pro Endpunkt separates Limit)
        $identifier = sprintf('%s_%s', $request->getClientIp() ?? 'anonymous', $path);
        $limiter = $this->publicApiLimiter->create($identifier);

        // Consume 1 token
        $limit = $limiter->consume(1);

        // Prüfe ob Limit überschritten wurde
        if (!$limit->isAccepted()) {
            $this->logger->warning('Rate limit exceeded for public API', [
                'ip' => $identifier,
                'path' => $path,
                'retry_after' => $limit->getRetryAfter()->getTimestamp(),
            ]);

            $response = new JsonResponse(
                [
                    'error' => 'Too Many Requests',
                    'message' => 'Rate limit exceeded. Please try again later.',
                    'retry_after' => $limit->getRetryAfter()->getTimestamp(),
                ],
                429 // Too Many Requests
            );

            $response->headers->set('X-RateLimit-Limit', (string) $limit->getLimit());
            $response->headers->set('X-RateLimit-Remaining', '0');
            $response->headers->set('X-RateLimit-Reset', (string) $limit->getRetryAfter()->getTimestamp());
            $response->headers->set('Retry-After', (string) $limit->getRetryAfter()->getTimestamp());

            $event->setResponse($response);
        } else {
            // Speichere Rate-Limit-Info für später (wird in Response-Event hinzugefügt)
            $request->attributes->set('_rate_limit', [
                'limit' => $limit->getLimit(),
                'remaining' => $limit->getRemainingTokens(),
                'reset' => $limit->getRetryAfter()->getTimestamp(),
            ]);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        // Füge Rate-Limit-Header zu erfolgreichen Responses hinzu
        if ($request->attributes->has('_rate_limit')) {
            $rateLimit = $request->attributes->get('_rate_limit');
            $response = $event->getResponse();

            $response->headers->set('X-RateLimit-Limit', (string) $rateLimit['limit']);
            $response->headers->set('X-RateLimit-Remaining', (string) $rateLimit['remaining']);
            $response->headers->set('X-RateLimit-Reset', (string) $rateLimit['reset']);
        }
    }

    private function isPublicRoute(string $path): bool
    {
        foreach (self::PUBLIC_ROUTES as $route) {
            if (str_starts_with($path, $route)) {
                return true;
            }
        }

        return false;
    }
}

