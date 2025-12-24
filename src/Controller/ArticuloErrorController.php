<?php

namespace App\Controller;

use App\Entity\Articulo;
use App\Entity\Notificacion;
use App\Repository\ArticuloRepository;
use App\Repository\NotificacionRepository;
use App\Service\NotificacionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/articulo')]
class ArticuloErrorController extends AbstractController
{
    #[Route('/{id}/reportar-error', name: 'app_articulo_reportar_error', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reportarError(
        Articulo $articulo,
        Request $request,
        NotificacionService $notificacionService
    ): Response {
        $mensaje = $request->request->get('mensaje');
        
        if (empty($mensaje) || trim($mensaje) === '') {
            $this->addFlash('error', 'El mensaje no puede estar vacío.');
            return $this->redirectToRoute('app_articulo_publico_show', [
                'id' => $articulo->getId(),
                'ley' => $request->query->get('ley', 0),
                'search' => $request->query->get('search', '')
            ]);
        }

        $alumno = $this->getUser();
        
        try {
            $notificacionService->crearNotificacionErrorArticulo($articulo, $alumno, trim($mensaje));
            $this->addFlash('success', 'Error reportado correctamente. Los profesores y administradores han sido notificados.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error al reportar el problema. Por favor, inténtalo de nuevo.');
        }

        return $this->redirectToRoute('app_articulo_publico_show', [
            'id' => $articulo->getId(),
            'ley' => $request->query->get('ley', 0),
            'search' => $request->query->get('search', '')
        ]);
    }

    #[Route('/{id}/mensajes', name: 'app_articulo_mensajes', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function obtenerMensajes(
        Articulo $articulo,
        NotificacionRepository $notificacionRepository
    ): JsonResponse {
        // Obtener todas las notificaciones de error de este artículo
        $notificaciones = $notificacionRepository->createQueryBuilder('n')
            ->where('n.tipo = :tipo')
            ->andWhere('n.articulo = :articulo')
            ->setParameter('tipo', Notificacion::TIPO_ERROR_ARTICULO)
            ->setParameter('articulo', $articulo)
            ->orderBy('n.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();

        $timezone = new \DateTimeZone('Europe/Madrid');
        $mensajesUnicos = [];
        $mensajesVistos = []; // Para evitar duplicados
        
        foreach ($notificaciones as $notificacion) {
            $fechaCreacion = clone $notificacion->getFechaCreacion();
            $fechaCreacion->setTimezone($timezone);
            
            // Extraer el mensaje del usuario del texto completo
            // El formato es: "Usuario ha reportado un error o solicita corrección en el Artículo X. Mensaje: [mensaje del usuario]"
            $mensajeCompleto = $notificacion->getMensaje();
            $mensajeUsuario = $mensajeCompleto;
            if (strpos($mensajeCompleto, 'Mensaje: ') !== false) {
                $mensajeUsuario = substr($mensajeCompleto, strpos($mensajeCompleto, 'Mensaje: ') + 9);
            }
            
            $mensajeUsuarioTrim = trim($mensajeUsuario);
            $alumno = $notificacion->getAlumno() ? $notificacion->getAlumno()->getUsername() : 'Usuario eliminado';
            $alumnoId = $notificacion->getAlumno() ? $notificacion->getAlumno()->getId() : 0;
            
            // Crear una clave única basada en el alumno y el mensaje
            $claveUnica = md5($alumnoId . '|' . $mensajeUsuarioTrim . '|' . $fechaCreacion->format('Y-m-d H:i'));
            
            // Solo agregar si no hemos visto este mensaje antes
            if (!isset($mensajesVistos[$claveUnica])) {
                $mensajesUnicos[] = [
                    'id' => $notificacion->getId(),
                    'alumno' => $alumno,
                    'mensaje' => $mensajeUsuarioTrim,
                    'fechaCreacion' => $fechaCreacion->format('d/m/Y H:i'),
                ];
                $mensajesVistos[$claveUnica] = true;
            }
        }

        return new JsonResponse([
            'mensajes' => $mensajesUnicos,
            'total' => count($mensajesUnicos),
        ]);
    }

    #[Route('/{id}/contador-mensajes', name: 'app_articulo_contador_mensajes', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function contadorMensajes(
        Articulo $articulo,
        NotificacionRepository $notificacionRepository
    ): JsonResponse {
        // Obtener todas las notificaciones de error de este artículo
        $notificaciones = $notificacionRepository->createQueryBuilder('n')
            ->where('n.tipo = :tipo')
            ->andWhere('n.articulo = :articulo')
            ->setParameter('tipo', Notificacion::TIPO_ERROR_ARTICULO)
            ->setParameter('articulo', $articulo)
            ->getQuery()
            ->getResult();

        $timezone = new \DateTimeZone('Europe/Madrid');
        $mensajesVistos = []; // Para evitar duplicados
        
        foreach ($notificaciones as $notificacion) {
            $fechaCreacion = clone $notificacion->getFechaCreacion();
            $fechaCreacion->setTimezone($timezone);
            
            // Extraer el mensaje del usuario del texto completo
            $mensajeCompleto = $notificacion->getMensaje();
            $mensajeUsuario = $mensajeCompleto;
            if (strpos($mensajeCompleto, 'Mensaje: ') !== false) {
                $mensajeUsuario = substr($mensajeCompleto, strpos($mensajeCompleto, 'Mensaje: ') + 9);
            }
            
            $mensajeUsuarioTrim = trim($mensajeUsuario);
            $alumnoId = $notificacion->getAlumno() ? $notificacion->getAlumno()->getId() : 0;
            
            // Crear una clave única basada en el alumno y el mensaje
            $claveUnica = md5($alumnoId . '|' . $mensajeUsuarioTrim . '|' . $fechaCreacion->format('Y-m-d H:i'));
            
            // Solo contar si no hemos visto este mensaje antes
            if (!isset($mensajesVistos[$claveUnica])) {
                $mensajesVistos[$claveUnica] = true;
            }
        }

        return new JsonResponse([
            'contador' => count($mensajesVistos),
        ]);
    }
}






