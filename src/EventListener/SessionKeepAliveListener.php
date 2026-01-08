<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Listener para mantener la sesión activa en todas las rutas autenticadas
 */
class SessionKeepAliveListener implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        // Solo procesar si hay una sesión disponible
        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        
        // Verificar si el usuario está autenticado
        $token = $this->tokenStorage->getToken();
        $isAuthenticated = $token && $token->getUser() !== null && $token->getUser() !== 'anon.';
        
        // Rutas públicas que no necesitan mantener la sesión
        $publicRoutes = [
            'app_login',
            'app_register',
            'app_home',
            'app_contacto',
            'app_logout',
        ];
        
        $route = $request->attributes->get('_route', '');
        $isPublicRoute = in_array($route, $publicRoutes) || str_starts_with($route, '_');
        
        // Mantener la sesión activa si:
        // 1. El usuario está autenticado Y no es una ruta pública
        // 2. O hay datos de examen en la sesión (para mantener compatibilidad con exámenes)
        if (($isAuthenticated && !$isPublicRoute) || $session->has('examen_preguntas') || $session->has('examen_config')) {
            // Tocar la sesión para actualizar su tiempo de última actividad
            // Usar get() en lugar de migrate() para evitar regenerar el ID de sesión
            // Esto actualiza el tiempo de última actividad sin cambiar el ID
            $session->get('_keep_alive', time());
        }
    }
}

