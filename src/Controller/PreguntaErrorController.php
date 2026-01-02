<?php

namespace App\Controller;

use App\Entity\Pregunta;
use App\Entity\MensajePregunta;
use App\Repository\PreguntaRepository;
use App\Repository\MensajePreguntaRepository;
use App\Service\NotificacionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pregunta')]
class PreguntaErrorController extends AbstractController
{
    #[Route('/{id}/reportar-error', name: 'app_pregunta_reportar_error', methods: ['POST'], priority: 10)]
    #[IsGranted('ROLE_USER')]
    public function reportarError(
        Pregunta $pregunta,
        Request $request,
        NotificacionService $notificacionService,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // Validar token CSRF
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('reportar_error_pregunta_' . $pregunta->getId(), $token)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Token CSRF inválido.'
            ], Response::HTTP_FORBIDDEN);
        }
        
        $mensajeTexto = $request->request->get('mensaje');
        
        if (empty($mensajeTexto) || trim($mensajeTexto) === '') {
            return new JsonResponse([
                'success' => false,
                'error' => 'El mensaje no puede estar vacío.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $alumno = $this->getUser();
        
        if (!$alumno) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Debes estar autenticado para reportar un error.'
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        try {
            // Crear mensaje
            $mensaje = new MensajePregunta();
            $mensaje->setPregunta($pregunta);
            $mensaje->setAutor($alumno);
            $mensaje->setMensaje(trim($mensajeTexto));
            $mensaje->setEsRespuesta(false);
            
            $entityManager->persist($mensaje);
            $entityManager->flush();
            
            // Crear notificación para todos los profesores y administradores
            $notificacionService->crearNotificacionErrorPregunta($pregunta, $alumno, trim($mensajeTexto));
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Error reportado correctamente. Los profesores y administradores han sido notificados.'
            ]);
        } catch (\Exception $e) {
            error_log('Error al reportar error en pregunta: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'error' => 'Error al reportar el problema: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/mensajes', name: 'app_pregunta_mensajes', methods: ['GET'], priority: 10)]
    #[IsGranted('ROLE_USER')]
    public function obtenerMensajes(
        Pregunta $pregunta,
        MensajePreguntaRepository $mensajePreguntaRepository
    ): JsonResponse {
        try {
            $timezone = new \DateTimeZone('Europe/Madrid');
            $mensajesPrincipales = $mensajePreguntaRepository->findMensajesPrincipales($pregunta);
            
            $mensajesArray = [];
            
            foreach ($mensajesPrincipales as $mensaje) {
                $fechaCreacion = clone $mensaje->getFechaCreacion();
                $fechaCreacion->setTimezone($timezone);
                
                $autor = $mensaje->getAutor();
                $autorNombre = $autor ? $autor->getUsername() : 'Usuario eliminado';
                $esProfesor = $mensaje->esDeProfesor();
                
                // Obtener respuestas ordenadas por fecha
                $respuestas = [];
                foreach ($mensaje->getRespuestas() as $respuesta) {
                    $fechaRespuesta = clone $respuesta->getFechaCreacion();
                    $fechaRespuesta->setTimezone($timezone);
                    
                    $autorRespuesta = $respuesta->getAutor();
                    $autorRespuestaNombre = $autorRespuesta ? $autorRespuesta->getUsername() : 'Usuario eliminado';
                    $esRespuestaProfesor = $respuesta->esDeProfesor();
                    
                    $respuestas[] = [
                        'id' => $respuesta->getId(),
                        'autor' => $autorRespuestaNombre,
                        'esProfesor' => $esRespuestaProfesor,
                        'mensaje' => $respuesta->getMensaje(),
                        'fechaCreacion' => $fechaRespuesta->format('d/m/Y H:i'),
                    ];
                }
                
                // Ordenar respuestas por fecha (más antiguas primero)
                usort($respuestas, function($a, $b) {
                    return strtotime(str_replace('/', '-', $a['fechaCreacion'])) - strtotime(str_replace('/', '-', $b['fechaCreacion']));
                });
                
                $mensajesArray[] = [
                    'id' => $mensaje->getId(),
                    'autor' => $autorNombre,
                    'esProfesor' => $esProfesor,
                    'mensaje' => $mensaje->getMensaje(),
                    'fechaCreacion' => $fechaCreacion->format('d/m/Y H:i'),
                    'respuestas' => $respuestas,
                    'tieneRespuestas' => count($respuestas) > 0,
                ];
            }

            return new JsonResponse([
                'mensajes' => $mensajesArray,
                'total' => count($mensajesArray),
            ]);
        } catch (\Exception $e) {
            error_log('Error al obtener mensajes: ' . $e->getMessage());
            return new JsonResponse([
                'mensajes' => [],
                'total' => 0,
                'error' => 'Error al cargar los mensajes',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/contador-mensajes', name: 'app_pregunta_contador_mensajes', methods: ['GET'], priority: 10)]
    #[IsGranted('ROLE_USER')]
    public function contadorMensajes(
        Pregunta $pregunta,
        MensajePreguntaRepository $mensajePreguntaRepository
    ): JsonResponse {
        try {
            $contador = $mensajePreguntaRepository->countMensajesPrincipales($pregunta);

            return new JsonResponse([
                'contador' => $contador,
            ]);
        } catch (\Exception $e) {
            error_log('Error al contar mensajes: ' . $e->getMessage());
            return new JsonResponse([
                'contador' => 0,
            ]);
        }
    }

    #[Route('/mensaje/{id}/responder', name: 'app_pregunta_responder_mensaje', methods: ['POST'])]
    #[IsGranted('ROLE_PROFESOR')]
    public function responderMensaje(
        MensajePregunta $mensaje,
        Request $request,
        EntityManagerInterface $entityManager,
        NotificacionService $notificacionService
    ): JsonResponse {
        try {
            // Validar token CSRF
            $token = $request->request->get('_token');
            if (!$token || !$this->isCsrfTokenValid('responder_mensaje_pregunta_' . $mensaje->getId(), $token)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Token CSRF inválido.'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $respuestaTexto = $request->request->get('respuesta');
            
            if (empty($respuestaTexto) || trim($respuestaTexto) === '') {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'La respuesta no puede estar vacía.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $profesor = $this->getUser();
            
            if (!$profesor) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Debes estar autenticado para responder.'
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            // Verificar que el mensaje padre existe y no es una respuesta
            if ($mensaje->isEsRespuesta()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'No se puede responder a una respuesta.'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Crear respuesta
            $respuesta = new MensajePregunta();
            $respuesta->setPregunta($mensaje->getPregunta());
            $respuesta->setAutor($profesor);
            $respuesta->setMensaje(trim($respuestaTexto));
            $respuesta->setMensajePadre($mensaje);
            $respuesta->setEsRespuesta(true);
            
            // Agregar la respuesta a la colección del mensaje padre
            $mensaje->addRespuesta($respuesta);
            
            $entityManager->persist($respuesta);
            $entityManager->persist($mensaje);
            $entityManager->flush();
            
            // Notificar al alumno que su mensaje ha sido respondido
            $autorOriginal = $mensaje->getAutor();
            if ($autorOriginal && $autorOriginal->getId() !== $profesor->getId() && !in_array('ROLE_PROFESOR', $autorOriginal->getRoles()) && !in_array('ROLE_ADMIN', $autorOriginal->getRoles())) {
                $notificacionService->crearNotificacionRespuestaPregunta(
                    $mensaje->getPregunta(),
                    $autorOriginal,
                    $profesor,
                    trim($respuestaTexto)
                );
            }
            
            $timezone = new \DateTimeZone('Europe/Madrid');
            $fechaCreacion = clone $respuesta->getFechaCreacion();
            $fechaCreacion->setTimezone($timezone);
            
            return new JsonResponse([
                'success' => true,
                'respuesta' => [
                    'id' => $respuesta->getId(),
                    'autor' => $profesor->getUsername(),
                    'esProfesor' => true,
                    'mensaje' => $respuesta->getMensaje(),
                    'fechaCreacion' => $fechaCreacion->format('d/m/Y H:i'),
                ]
            ]);
        } catch (\Exception $e) {
            error_log('Error al responder mensaje: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return new JsonResponse([
                'success' => false,
                'error' => 'Error al guardar la respuesta: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

