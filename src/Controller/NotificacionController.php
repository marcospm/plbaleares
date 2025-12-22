<?php

namespace App\Controller;

use App\Repository\NotificacionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notificacion')]
#[IsGranted('ROLE_PROFESOR')]
class NotificacionController extends AbstractController
{
    #[Route('/no-leidas', name: 'app_notificacion_no_leidas', methods: ['GET'])]
    public function noLeidas(NotificacionRepository $notificacionRepository, Request $request): JsonResponse
    {
        $profesor = $this->getUser();
        $notificaciones = $notificacionRepository->findNoLeidasByProfesor($profesor);

        $data = [];
        foreach ($notificaciones as $notificacion) {
            $data[] = [
                'id' => $notificacion->getId(),
                'tipo' => $notificacion->getTipo(),
                'titulo' => $notificacion->getTitulo(),
                'mensaje' => $notificacion->getMensaje(),
                'alumno' => $notificacion->getAlumno()->getUsername(),
                'fechaCreacion' => $notificacion->getFechaCreacion()->format('d/m/Y H:i'),
                'examenId' => $notificacion->getExamen()?->getId(),
                'tareaId' => $notificacion->getTareaAsignada()?->getId(),
                'token' => $this->container->get('security.csrf.token_manager')->getToken('marcar_leida' . $notificacion->getId())->getValue(),
            ];
        }

        return new JsonResponse([
            'notificaciones' => $data,
            'total' => count($data),
            'tokenTodas' => $this->container->get('security.csrf.token_manager')->getToken('marcar_todas_leidas')->getValue(),
        ]);
    }

    #[Route('/{id}/marcar-leida', name: 'app_notificacion_marcar_leida', methods: ['POST'])]
    public function marcarLeida(int $id, NotificacionRepository $notificacionRepository, EntityManagerInterface $entityManager, Request $request): JsonResponse
    {
        $profesor = $this->getUser();
        $notificacion = $notificacionRepository->find($id);

        if (!$notificacion) {
            return new JsonResponse(['success' => false, 'message' => 'Notificación no encontrada.'], 404);
        }

        // Verificar que la notificación pertenece al profesor
        if ($notificacion->getProfesor()->getId() !== $profesor->getId()) {
            return new JsonResponse(['success' => false, 'message' => 'No tienes permiso para esta acción.'], 403);
        }

        if ($this->isCsrfTokenValid('marcar_leida'.$notificacion->getId(), $request->getPayload()->getString('_token'))) {
            $notificacion->setLeida(true);
            $entityManager->flush();

            return new JsonResponse(['success' => true, 'message' => 'Notificación marcada como leída.']);
        }

        return new JsonResponse(['success' => false, 'message' => 'Token inválido.'], 400);
    }

    #[Route('/marcar-todas-leidas', name: 'app_notificacion_marcar_todas_leidas', methods: ['POST'])]
    public function marcarTodasLeidas(NotificacionRepository $notificacionRepository, EntityManagerInterface $entityManager, Request $request): JsonResponse
    {
        $profesor = $this->getUser();
        
        if ($this->isCsrfTokenValid('marcar_todas_leidas', $request->getPayload()->getString('_token'))) {
            $notificaciones = $notificacionRepository->findNoLeidasByProfesor($profesor);
            
            foreach ($notificaciones as $notificacion) {
                $notificacion->setLeida(true);
            }
            
            $entityManager->flush();

            return new JsonResponse(['success' => true, 'message' => 'Todas las notificaciones marcadas como leídas.']);
        }

        return new JsonResponse(['success' => false, 'message' => 'Token inválido.'], 400);
    }
}

