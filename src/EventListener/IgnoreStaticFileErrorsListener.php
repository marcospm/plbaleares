<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class IgnoreStaticFileErrorsListener implements EventSubscriberInterface
{
    /**
     * Rutas de iconos y de sondas (xmlrpc, WordPress, .php) que no son de esta aplicación.
     */
    private const IGNORED_PATTERNS = [
        '/images/favicon',
        '/favicon',
        '/apple-touch-icon',
        '/robots.txt',
        '/.well-known/',
        'xmlrpc',
        'wp-login',
        'wp-admin',
        'wp-content',
        'wp-includes',
        'wlwmanifest',
        '.env',
        '.git/',
    ];

    public function __construct(
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Antes del enrutador, para que la sonda no llegue a lanzar la excepción.
            KernelEvents::REQUEST => ['onKernelRequest', 64],
            // Prioridad alta para interceptar antes de que se registre el error
            KernelEvents::EXCEPTION => ['onKernelException', 512],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->esSonda($event->getRequest()->getPathInfo())) {
            return;
        }

        $event->setResponse(new Response('', Response::HTTP_NOT_FOUND));
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Solo procesar NotFoundHttpException (404)
        if (!$exception instanceof NotFoundHttpException || !$this->esSonda($event->getRequest()->getPathInfo())) {
            return;
        }

        $event->setResponse(new Response('', Response::HTTP_NOT_FOUND));
        $event->stopPropagation();
    }

    private function esSonda(string $path): bool
    {
        $ruta = strtolower($path);
        if (str_ends_with($ruta, '.php')) {
            return true;
        }

        foreach (self::IGNORED_PATTERNS as $pattern) {
            if (str_contains($ruta, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
