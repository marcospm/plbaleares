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
class NotificacionController extends AbstractController
{
    #[Route('/no-leidas', name: 'app_notificacion_no_leidas', methods: ['GET'])]
    #[IsGranted('ROLE_PROFESOR')]
    public function noLeidas(NotificacionRepository $notificacionRepository, Request $request): JsonResponse
    {
        $profesor = $this->getUser();
        $notificaciones = $notificacionRepository->findNoLeidasByProfesor($profesor);

        $data = [];
        $timezone = new \DateTimeZone('Europe/Madrid');
        foreach ($notificaciones as $notificacion) {
            $fechaCreacion = clone $notificacion->getFechaCreacion();
            $fechaCreacion->setTimezone($timezone);
            
            $data[] = [
                'id' => $notificacion->getId(),
                'tipo' => $notificacion->getTipo(),
                'titulo' => $notificacion->getTitulo(),
                'mensaje' => $notificacion->getMensaje(),
                'alumno' => $notificacion->getAlumno()->getUsername(),
                'fechaCreacion' => $fechaCreacion->format('d/m/Y H:i'),
                'examenId' => $notificacion->getExamen()?->getId(),
                'tareaId' => $notificacion->getTareaAsignada()?->getId(),
                'articuloId' => $notificacion->getArticulo()?->getId(),
                'leida' => $notificacion->isLeida(),
                'token' => $this->container->get('security.csrf.token_manager')->getToken('marcar_leida' . $notificacion->getId())->getValue(),
            ];
        }

        return new JsonResponse([
            'notificaciones' => $data,
            'total' => count($data),
            'tokenTodas' => $this->container->get('security.csrf.token_manager')->getToken('marcar_todas_leidas')->getValue(),
        ]);
    }

    #[Route('/todas', name: 'app_notificacion_todas', methods: ['GET'])]
    #[IsGranted('ROLE_PROFESOR')]
    public function todas(NotificacionRepository $notificacionRepository): Response
    {
        $profesor = $this->getUser();
        $notificaciones = $notificacionRepository->findAllByProfesor($profesor);
        $noLeidas = $notificacionRepository->countNoLeidasByProfesor($profesor);

        return $this->render('notificacion/index.html.twig', [
            'notificaciones' => $notificaciones,
            'noLeidas' => $noLeidas,
        ]);
    }

    #[Route('/contador', name: 'app_notificacion_contador', methods: ['GET'])]
    #[IsGranted('ROLE_PROFESOR')]
    public function contador(NotificacionRepository $notificacionRepository): JsonResponse
    {
        $profesor = $this->getUser();
        $contador = $notificacionRepository->countNoLeidasByProfesor($profesor);

        return new JsonResponse([
            'contador' => $contador,
        ]);
    }

    #[Route('/{id}/marcar-leida', name: 'app_notificacion_marcar_leida', methods: ['POST'])]
    #[IsGranted('ROLE_PROFESOR')]
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
    #[IsGranted('ROLE_PROFESOR')]
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

    #[Route('/{id}/marcar-leida-get', name: 'app_notificacion_marcar_leida_get', methods: ['GET'])]
    #[IsGranted('ROLE_PROFESOR')]
    public function marcarLeidaGet(int $id, NotificacionRepository $notificacionRepository, EntityManagerInterface $entityManager, Request $request): Response
    {
        $profesor = $this->getUser();
        $notificacion = $notificacionRepository->find($id);

        if (!$notificacion) {
            $this->addFlash('error', 'Notificación no encontrada.');
            return $this->redirectToRoute('app_notificacion_todas');
        }

        // Verificar que la notificación pertenece al profesor
        if ($notificacion->getProfesor()->getId() !== $profesor->getId()) {
            $this->addFlash('error', 'No tienes permiso para esta acción.');
            return $this->redirectToRoute('app_notificacion_todas');
        }

        if ($this->isCsrfTokenValid('marcar_leida'.$notificacion->getId(), $request->query->get('_token'))) {
            $notificacion->setLeida(true);
            $entityManager->flush();
            $this->addFlash('success', 'Notificación marcada como leída.');
        } else {
            $this->addFlash('error', 'Token inválido.');
        }

        return $this->redirectToRoute('app_notificacion_todas');
    }

    // ========== MÉTODOS PARA ALUMNOS ==========

    #[Route('/alumno/no-leidas', name: 'app_notificacion_alumno_no_leidas', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function noLeidasAlumno(NotificacionRepository $notificacionRepository, Request $request): JsonResponse
    {
        // Verificar que no sea profesor ni admin
        if ($this->isGranted('ROLE_PROFESOR') || $this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Esta ruta es solo para alumnos.'], 403);
        }

        $alumno = $this->getUser();
        $notificaciones = $notificacionRepository->findNoLeidasByAlumno($alumno);

        $data = [];
        $timezone = new \DateTimeZone('Europe/Madrid');
        foreach ($notificaciones as $notificacion) {
            $fechaCreacion = clone $notificacion->getFechaCreacion();
            $fechaCreacion->setTimezone($timezone);
            
            $data[] = [
                'id' => $notificacion->getId(),
                'tipo' => $notificacion->getTipo(),
                'titulo' => $notificacion->getTitulo(),
                'mensaje' => $notificacion->getMensaje(),
                'fechaCreacion' => $fechaCreacion->format('d/m/Y H:i'),
                'planificacionId' => $notificacion->getPlanificacionSemanal()?->getId(),
                'tareaId' => $notificacion->getTarea()?->getId(),
                'leida' => $notificacion->isLeida(),
                'token' => $this->container->get('security.csrf.token_manager')->getToken('marcar_leida_alumno' . $notificacion->getId())->getValue(),
            ];
        }

        return new JsonResponse([
            'notificaciones' => $data,
            'total' => count($data),
            'tokenTodas' => $this->container->get('security.csrf.token_manager')->getToken('marcar_todas_leidas_alumno')->getValue(),
        ]);
    }

    #[Route('/alumno/todas', name: 'app_notificacion_alumno_todas', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function todasAlumno(NotificacionRepository $notificacionRepository): Response
    {
        // Verificar que no sea profesor ni admin
        if ($this->isGranted('ROLE_PROFESOR') || $this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Esta ruta es solo para alumnos.');
        }

        $alumno = $this->getUser();
        $notificaciones = $notificacionRepository->findAllByAlumno($alumno);
        $noLeidas = $notificacionRepository->countNoLeidasByAlumno($alumno);

        return $this->render('notificacion/alumno_index.html.twig', [
            'notificaciones' => $notificaciones,
            'noLeidas' => $noLeidas,
        ]);
    }

    #[Route('/alumno/contador', name: 'app_notificacion_alumno_contador', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function contadorAlumno(NotificacionRepository $notificacionRepository): JsonResponse
    {
        // Verificar que no sea profesor ni admin
        if ($this->isGranted('ROLE_PROFESOR') || $this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['contador' => 0]);
        }

        $alumno = $this->getUser();
        $contador = $notificacionRepository->countNoLeidasByAlumno($alumno);

        return new JsonResponse([
            'contador' => $contador,
        ]);
    }

    #[Route('/alumno/{id}/marcar-leida', name: 'app_notificacion_alumno_marcar_leida', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function marcarLeidaAlumno(int $id, NotificacionRepository $notificacionRepository, EntityManagerInterface $entityManager, Request $request): JsonResponse
    {
        // Verificar que no sea profesor ni admin
        if ($this->isGranted('ROLE_PROFESOR') || $this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['success' => false, 'message' => 'No tienes permiso para esta acción.'], 403);
        }

        $alumno = $this->getUser();
        $notificacion = $notificacionRepository->find($id);

        if (!$notificacion) {
            return new JsonResponse(['success' => false, 'message' => 'Notificación no encontrada.'], 404);
        }

        // Verificar que la notificación pertenece al alumno
        if ($notificacion->getAlumno()->getId() !== $alumno->getId()) {
            return new JsonResponse(['success' => false, 'message' => 'No tienes permiso para esta acción.'], 403);
        }

        if ($this->isCsrfTokenValid('marcar_leida_alumno'.$notificacion->getId(), $request->getPayload()->getString('_token'))) {
            $notificacion->setLeida(true);
            $entityManager->flush();

            return new JsonResponse(['success' => true, 'message' => 'Notificación marcada como leída.']);
        }

        return new JsonResponse(['success' => false, 'message' => 'Token inválido.'], 400);
    }

    #[Route('/alumno/marcar-todas-leidas', name: 'app_notificacion_alumno_marcar_todas_leidas', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function marcarTodasLeidasAlumno(NotificacionRepository $notificacionRepository, EntityManagerInterface $entityManager, Request $request): JsonResponse
    {
        // Verificar que no sea profesor ni admin
        if ($this->isGranted('ROLE_PROFESOR') || $this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['success' => false, 'message' => 'No tienes permiso para esta acción.'], 403);
        }

        $alumno = $this->getUser();
        
        if ($this->isCsrfTokenValid('marcar_todas_leidas_alumno', $request->getPayload()->getString('_token'))) {
            $notificaciones = $notificacionRepository->findNoLeidasByAlumno($alumno);
            
            foreach ($notificaciones as $notificacion) {
                $notificacion->setLeida(true);
            }
            
            $entityManager->flush();

            return new JsonResponse(['success' => true, 'message' => 'Todas las notificaciones marcadas como leídas.']);
        }

        return new JsonResponse(['success' => false, 'message' => 'Token inválido.'], 400);
    }

    #[Route('/alumno/{id}/marcar-leida-get', name: 'app_notificacion_alumno_marcar_leida_get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function marcarLeidaGetAlumno(int $id, NotificacionRepository $notificacionRepository, EntityManagerInterface $entityManager, Request $request): Response
    {
        // Verificar que no sea profesor ni admin
        if ($this->isGranted('ROLE_PROFESOR') || $this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Esta ruta es solo para alumnos.');
        }

        $alumno = $this->getUser();
        $notificacion = $notificacionRepository->find($id);

        if (!$notificacion) {
            $this->addFlash('error', 'Notificación no encontrada.');
            return $this->redirectToRoute('app_notificacion_alumno_todas');
        }

        // Verificar que la notificación pertenece al alumno
        if ($notificacion->getAlumno()->getId() !== $alumno->getId()) {
            $this->addFlash('error', 'No tienes permiso para esta acción.');
            return $this->redirectToRoute('app_notificacion_alumno_todas');
        }

        if ($this->isCsrfTokenValid('marcar_leida_alumno'.$notificacion->getId(), $request->query->get('_token'))) {
            $notificacion->setLeida(true);
            $entityManager->flush();
            $this->addFlash('success', 'Notificación marcada como leída.');
        } else {
            $this->addFlash('error', 'Token inválido.');
        }

        return $this->redirectToRoute('app_notificacion_alumno_todas');
    }
}

