<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class IgnoreStaticFileErrorsListener implements EventSubscriberInterface
{
    /**
     * Lista de patrones de rutas que deben ser ignoradas
     */
    private const IGNORED_PATTERNS = [
        '/images/favicon',
        '/favicon',
        '/apple-touch-icon',
        '/robots.txt',
        '/.well-known/',
    ];

    public function __construct(
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Prioridad alta para interceptar antes de que se registre el error
            KernelEvents::EXCEPTION => ['onKernelException', 512],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        
        // Solo procesar NotFoundHttpException (404)
        if (!$exception instanceof NotFoundHttpException) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Verificar si la ruta coincide con algún patrón ignorado
        foreach (self::IGNORED_PATTERNS as $pattern) {
            if (str_contains($path, $pattern)) {
                // Crear una respuesta 404 silenciosa sin registrar el error
                $response = new Response('', 404);
                $event->setResponse($response);
                // Marcar el evento como manejado para prevenir el logging
                $event->stopPropagation();
                return;
            }
        }
    }
}
