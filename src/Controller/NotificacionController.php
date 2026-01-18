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
        if (!$profesor) {
            return new JsonResponse(['error' => 'Usuario no autenticado'], 401);
        }
        
        $notificaciones = $notificacionRepository->findNoLeidasByProfesor($profesor);

        $data = [];
        $timezone = new \DateTimeZone('Europe/Madrid');
        foreach ($notificaciones as $notificacion) {
            // Verificar que la notificación pertenece al profesor
            if ($notificacion->getProfesor() && $notificacion->getProfesor()->getId() !== $profesor->getId()) {
                continue; // Saltar notificaciones que no pertenecen a este profesor
            }
            
            $fechaCreacion = clone $notificacion->getFechaCreacion();
            $fechaCreacion->setTimezone($timezone);
            
            $data[] = [
                'id' => $notificacion->getId(),
                'tipo' => $notificacion->getTipo(),
                'titulo' => $notificacion->getTitulo(),
                'mensaje' => $notificacion->getMensaje(),
                'alumno' => $notificacion->getAlumno() ? $notificacion->getAlumno()->getUsername() : 'Usuario eliminado',
                'fechaCreacion' => $fechaCreacion->format('d/m/Y H:i'),
                'examenId' => $notificacion->getExamen()?->getId(),
                'tareaId' => $notificacion->getTareaAsignada()?->getId(),
                'articuloId' => $notificacion->getArticulo()?->getId(),
                'preguntaId' => $notificacion->getPregunta()?->getId(),
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
    public function todas(NotificacionRepository $notificacionRepository, EntityManagerInterface $entityManager): Response
    {
        $profesor = $this->getUser();
        if (!$profesor) {
            throw $this->createAccessDeniedException('Usuario no autenticado');
        }
        
        // Forzar refresh de las entidades para evitar problemas de caché
        $entityManager->clear();
        $profesor = $entityManager->getRepository(\App\Entity\User::class)->find($profesor->getId());
        
        $notificaciones = $notificacionRepository->findAllByProfesor($profesor);
        $noLeidas = $notificacionRepository->countNoLeidasByProfesor($profesor);

        return $this->render('notificacion/index.html.twig', [
            'notificaciones' => $notificaciones,
            'noLeidas' => (int) $noLeidas,
        ]);
    }

    #[Route('/contador', name: 'app_notificacion_contador', methods: ['GET'])]
    #[IsGranted('ROLE_PROFESOR')]
    public function contador(NotificacionRepository $notificacionRepository): JsonResponse
    {
        $profesor = $this->getUser();
        if (!$profesor) {
            return new JsonResponse(['contador' => 0]);
        }
        
        $contador = $notificacionRepository->countNoLeidasByProfesor($profesor);

        return new JsonResponse([
            'contador' => (int) $contador,
        ]);
    }

    #[Route('/{id}/marcar-leida', name: 'app_notificacion_marcar_leida', methods: ['POST'])]
    #[IsGranted('ROLE_PROFESOR')]
    public function marcarLeida(int $id, NotificacionRepository $notificacionRepository, EntityManagerInterface $entityManager, Request $request): JsonResponse
    {
        $profesor = $this->getUser();
        if (!$profesor) {
            return new JsonResponse(['success' => false, 'message' => 'Usuario no autenticado.'], 401);
        }
        
        // Buscar la notificación asegurándose de que pertenece al profesor
        $notificacion = $notificacionRepository->createQueryBuilder('n')
            ->where('n.id = :id')
            ->andWhere('n.profesor = :profesor')
            ->setParameter('id', $id)
            ->setParameter('profesor', $profesor)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$notificacion) {
            return new JsonResponse(['success' => false, 'message' => 'Notificación no encontrada o no tienes permiso.'], 404);
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
    public function marcarTodasLeidas(NotificacionRepository $notificacionRepository, EntityManagerInterface $entityManager, Request $request): Response
    {
        $profesor = $this->getUser();
        if (!$profesor) {
            $this->addFlash('error', 'Usuario no autenticado.');
            return $this->redirectToRoute('app_notificacion_todas');
        }
        
        if ($this->isCsrfTokenValid('marcar_todas_leidas', $request->request->get('_token'))) {
            // Usar query directa para marcar todas como leídas (más eficiente y seguro)
            $qb = $entityManager->createQueryBuilder();
            $qb->update(\App\Entity\Notificacion::class, 'n')
                ->set('n.leida', ':leida')
                ->where('n.profesor = :profesor')
                ->andWhere('n.leida = :noLeida')
                ->setParameter('leida', true)
                ->setParameter('profesor', $profesor)
                ->setParameter('noLeida', false);
            
            $result = $qb->getQuery()->execute();

            $this->addFlash('success', sprintf('%d notificación(es) marcada(s) como leída(s).', $result));
            return $this->redirectToRoute('app_notificacion_todas');
        }

        $this->addFlash('error', 'Token inválido.');
        return $this->redirectToRoute('app_notificacion_todas');
    }

    #[Route('/{id}/marcar-leida-get', name: 'app_notificacion_marcar_leida_get', methods: ['GET'])]
    #[IsGranted('ROLE_PROFESOR')]
    public function marcarLeidaGet(int $id, NotificacionRepository $notificacionRepository, EntityManagerInterface $entityManager, Request $request): Response
    {
        $profesor = $this->getUser();
        if (!$profesor) {
            $this->addFlash('error', 'Usuario no autenticado.');
            return $this->redirectToRoute('app_notificacion_todas');
        }
        
        // Buscar la notificación asegurándose de que pertenece al profesor
        $notificacion = $notificacionRepository->createQueryBuilder('n')
            ->where('n.id = :id')
            ->andWhere('n.profesor = :profesor')
            ->setParameter('id', $id)
            ->setParameter('profesor', $profesor)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$notificacion) {
            $this->addFlash('error', 'Notificación no encontrada o no tienes permiso.');
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
        if (!$alumno) {
            return new JsonResponse(['error' => 'Usuario no autenticado'], 401);
        }
        
        $notificaciones = $notificacionRepository->findNoLeidasByAlumno($alumno);

        $data = [];
        $timezone = new \DateTimeZone('Europe/Madrid');
        foreach ($notificaciones as $notificacion) {
            // Verificar que la notificación pertenece al alumno
            if ($notificacion->getAlumno() && $notificacion->getAlumno()->getId() !== $alumno->getId()) {
                continue; // Saltar notificaciones que no pertenecen a este alumno
            }
            
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
    public function todasAlumno(NotificacionRepository $notificacionRepository, EntityManagerInterface $entityManager): Response
    {
        // Verificar que no sea profesor ni admin
        if ($this->isGranted('ROLE_PROFESOR') || $this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Esta ruta es solo para alumnos.');
        }

        $alumno = $this->getUser();
        if (!$alumno) {
            throw $this->createAccessDeniedException('Usuario no autenticado');
        }
        
        // Forzar refresh de las entidades para evitar problemas de caché
        $entityManager->clear();
        $alumno = $entityManager->getRepository(\App\Entity\User::class)->find($alumno->getId());
        
        $notificaciones = $notificacionRepository->findAllByAlumno($alumno);
        $noLeidas = $notificacionRepository->countNoLeidasByAlumno($alumno);

        return $this->render('notificacion/alumno_index.html.twig', [
            'notificaciones' => $notificaciones,
            'noLeidas' => (int) $noLeidas,
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
        if (!$alumno) {
            return new JsonResponse(['contador' => 0]);
        }
        
        $contador = $notificacionRepository->countNoLeidasByAlumno($alumno);

        return new JsonResponse([
            'contador' => (int) $contador,
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
        if (!$alumno) {
            return new JsonResponse(['success' => false, 'message' => 'Usuario no autenticado.'], 401);
        }
        
        // Buscar la notificación asegurándose de que pertenece al alumno
        $notificacion = $notificacionRepository->createQueryBuilder('n')
            ->where('n.id = :id')
            ->andWhere('n.alumno = :alumno')
            ->setParameter('id', $id)
            ->setParameter('alumno', $alumno)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$notificacion) {
            return new JsonResponse(['success' => false, 'message' => 'Notificación no encontrada o no tienes permiso.'], 404);
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
    public function marcarTodasLeidasAlumno(NotificacionRepository $notificacionRepository, EntityManagerInterface $entityManager, Request $request): Response
    {
        // Verificar que no sea profesor ni admin
        if ($this->isGranted('ROLE_PROFESOR') || $this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'No tienes permiso para esta acción.');
            return $this->redirectToRoute('app_notificacion_alumno_todas');
        }

        $alumno = $this->getUser();
        if (!$alumno) {
            $this->addFlash('error', 'Usuario no autenticado.');
            return $this->redirectToRoute('app_notificacion_alumno_todas');
        }
        
        if ($this->isCsrfTokenValid('marcar_todas_leidas_alumno', $request->request->get('_token'))) {
            // Usar query directa para marcar todas como leídas (más eficiente y seguro)
            $qb = $entityManager->createQueryBuilder();
            $qb->update(\App\Entity\Notificacion::class, 'n')
                ->set('n.leida', ':leida')
                ->where('n.alumno = :alumno')
                ->andWhere('n.leida = :noLeida')
                ->andWhere('n.tipo IN (:tiposPermitidos)')
                ->setParameter('leida', true)
                ->setParameter('alumno', $alumno)
                ->setParameter('noLeida', false)
                ->setParameter('tiposPermitidos', [
                    \App\Entity\Notificacion::TIPO_PLANIFICACION_CREADA,
                    \App\Entity\Notificacion::TIPO_PLANIFICACION_EDITADA,
                    \App\Entity\Notificacion::TIPO_PLANIFICACION_ELIMINADA,
                    \App\Entity\Notificacion::TIPO_TAREA_CREADA,
                    \App\Entity\Notificacion::TIPO_TAREA_EDITADA,
                    \App\Entity\Notificacion::TIPO_TAREA_ELIMINADA,
                    \App\Entity\Notificacion::TIPO_EXAMEN_SEMANAL,
                    \App\Entity\Notificacion::TIPO_RESPUESTA_ARTICULO,
                    \App\Entity\Notificacion::TIPO_RESPUESTA_PREGUNTA,
                    \App\Entity\Notificacion::TIPO_GENERAL,
                ]);
            
            $result = $qb->getQuery()->execute();

            $this->addFlash('success', sprintf('%d notificación(es) marcada(s) como leída(s).', $result));
            return $this->redirectToRoute('app_notificacion_alumno_todas');
        }

        $this->addFlash('error', 'Token inválido.');
        return $this->redirectToRoute('app_notificacion_alumno_todas');
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
        if (!$alumno) {
            $this->addFlash('error', 'Usuario no autenticado.');
            return $this->redirectToRoute('app_notificacion_alumno_todas');
        }
        
        // Buscar la notificación asegurándose de que pertenece al alumno
        $notificacion = $notificacionRepository->createQueryBuilder('n')
            ->where('n.id = :id')
            ->andWhere('n.alumno = :alumno')
            ->setParameter('id', $id)
            ->setParameter('alumno', $alumno)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$notificacion) {
            $this->addFlash('error', 'Notificación no encontrada o no tienes permiso.');
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

