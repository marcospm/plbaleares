<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Listener para mantener la sesión activa durante exámenes y operaciones largas
 */
class SessionKeepAliveListener implements EventSubscriberInterface
{
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
        
        // Rutas relacionadas con exámenes semanales que necesitan mantener la sesión activa
        $examenRoutes = [
            'app_examen_semanal',
            'app_examen_semanal_alumno',
            'app_examen_pregunta',
            'app_examen_continuar',
            'app_examen_iniciar',
            'app_examen_resultado',
            'app_examen_historial',
        ];
        
        $route = $request->attributes->get('_route', '');
        
        // Si es una ruta de examen, tocar la sesión para mantenerla activa
        $isExamenRoute = false;
        foreach ($examenRoutes as $examenRoute) {
            if (str_starts_with($route, $examenRoute)) {
                $isExamenRoute = true;
                break;
            }
        }
        
        // Mantener la sesión activa si:
        // 1. Es una ruta de examen
        // 2. Hay datos de examen en la sesión
        if ($isExamenRoute || $session->has('examen_preguntas') || $session->has('examen_config')) {
            // Tocar la sesión para actualizar su tiempo de última actividad
            // Usar get() en lugar de migrate() para evitar regenerar el ID de sesión
            // Esto actualiza el tiempo de última actividad sin cambiar el ID
            $session->get('_keep_alive', time());
        }
    }
}

