<?php

namespace App\Controller;

use App\Entity\PlanificacionSemanal;
use App\Entity\PlanificacionPersonalizada;
use App\Entity\FranjaHoraria;
use App\Form\PlanificacionSemanalType;
use App\Form\FranjaHorariaType;
use App\Repository\PlanificacionSemanalRepository;
use App\Repository\PlanificacionPersonalizadaRepository;
use App\Repository\FranjaHorariaRepository;
use App\Repository\UserRepository;
use App\Service\PlanificacionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/planificacion-semanal')]
#[IsGranted('ROLE_PROFESOR')]
class PlanificacionSemanalController extends AbstractController
{
    #[Route('/', name: 'app_planificacion_semanal_index', methods: ['GET'])]
    public function index(
        Request $request,
        PlanificacionSemanalRepository $planificacionRepository,
        UserRepository $userRepository
    ): Response {
        $filtroNombre = $request->query->get('nombre', '');
        $filtroEstado = $request->query->get('estado', '');
        $filtroUsuario = $request->query->get('usuario', '');
        
        $planificaciones = $planificacionRepository->findConFiltros(
            $filtroNombre ?: null, 
            $filtroEstado ?: null, 
            $filtroUsuario ? (int)$filtroUsuario : null
        );
        
        // Obtener lista de usuarios para el filtro
        $usuarios = $userRepository->createQueryBuilder('u')
            ->where('u.activo = :activo')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('activo', true)
            ->setParameter('role', '%ROLE_USER%')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
        
        return $this->render('planificacion_semanal/index.html.twig', [
            'planificaciones' => $planificaciones,
            'usuarios' => $usuarios,
            'filtroNombre' => $filtroNombre,
            'filtroEstado' => $filtroEstado,
            'filtroUsuario' => $filtroUsuario,
        ]);
    }

    #[Route('/new', name: 'app_planificacion_semanal_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        PlanificacionService $planificacionService
    ): Response {
        $planificacion = new PlanificacionSemanal();
        $planificacion->setCreadoPor($this->getUser());
        
        $form = $this->createForm(PlanificacionSemanalType::class, $planificacion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($planificacion);
            $entityManager->flush();

            $this->addFlash('success', 'Planificación semanal creada correctamente. Ahora puedes añadir las franjas horarias desde la vista de la planificación.');
            return $this->redirectToRoute('app_planificacion_semanal_show', ['id' => $planificacion->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('planificacion_semanal/new.html.twig', [
            'planificacion' => $planificacion,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_planificacion_semanal_show', methods: ['GET'])]
    public function show(
        PlanificacionSemanal $planificacion, 
        PlanificacionPersonalizadaRepository $personalizadaRepository,
        FranjaHorariaRepository $franjaHorariaRepository
    ): Response {
        $usuariosConPlanificacion = $personalizadaRepository->findByPlanificacionBase($planificacion);
        $franjas = $franjaHorariaRepository->findByPlanificacionOrdenadas($planificacion);
        
        // Agrupar franjas por día
        $franjasPorDia = [];
        foreach ($franjas as $franja) {
            $dia = $franja->getDiaSemana();
            if (!isset($franjasPorDia[$dia])) {
                $franjasPorDia[$dia] = [];
            }
            $franjasPorDia[$dia][] = $franja;
        }
        
        return $this->render('planificacion_semanal/show.html.twig', [
            'planificacion' => $planificacion,
            'usuariosConPlanificacion' => $usuariosConPlanificacion,
            'franjasPorDia' => $franjasPorDia,
        ]);
    }

    #[Route('/{id}/franja/new', name: 'app_planificacion_semanal_franja_new', methods: ['GET', 'POST'])]
    public function newFranja(
        Request $request,
        PlanificacionSemanal $planificacion,
        EntityManagerInterface $entityManager,
        PlanificacionService $planificacionService
    ): Response {
        $franja = new FranjaHoraria();
        $franja->setPlanificacion($planificacion);
        
        $form = $this->createForm(FranjaHorariaType::class, $franja);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Calcular orden
            $franjasDelDia = $planificacion->getFranjasHorarias()->filter(function($f) use ($franja) {
                return $f->getDiaSemana() === $franja->getDiaSemana();
            })->toArray();
            
            $franjasDelDia[] = $franja;
            usort($franjasDelDia, function($a, $b) {
                return $a->getHoraInicio() <=> $b->getHoraInicio();
            });
            
            $orden = 1;
            foreach ($franjasDelDia as $f) {
                $f->setOrden($orden++);
            }

            // Validar
            $errores = $planificacionService->validarFranjas($franjasDelDia);
            if (!empty($errores)) {
                foreach ($errores as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->render('planificacion_semanal/franja_new.html.twig', [
                    'planificacion' => $planificacion,
                    'form' => $form,
                ]);
            }

            $entityManager->persist($franja);
            $entityManager->flush();

            $this->addFlash('success', 'Franja horaria añadida correctamente.');
            return $this->redirectToRoute('app_planificacion_semanal_show', ['id' => $planificacion->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('planificacion_semanal/franja_new.html.twig', [
            'planificacion' => $planificacion,
            'form' => $form,
        ]);
    }

    #[Route('/franja/{id}/edit', name: 'app_planificacion_semanal_franja_edit', methods: ['GET', 'POST'])]
    public function editFranja(
        Request $request,
        FranjaHoraria $franja,
        EntityManagerInterface $entityManager,
        PlanificacionService $planificacionService
    ): Response {
        $planificacion = $franja->getPlanificacion();
        
        $form = $this->createForm(FranjaHorariaType::class, $franja);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Recalcular orden
            $franjasDelDia = $planificacion->getFranjasHorarias()->filter(function($f) use ($franja) {
                return $f->getDiaSemana() === $franja->getDiaSemana();
            })->toArray();
            
            usort($franjasDelDia, function($a, $b) {
                return $a->getHoraInicio() <=> $b->getHoraInicio();
            });
            
            $orden = 1;
            foreach ($franjasDelDia as $f) {
                $f->setOrden($orden++);
            }

            // Validar
            $errores = $planificacionService->validarFranjas($franjasDelDia);
            if (!empty($errores)) {
                foreach ($errores as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->render('planificacion_semanal/franja_edit.html.twig', [
                    'planificacion' => $planificacion,
                    'franja' => $franja,
                    'form' => $form,
                ]);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Franja horaria actualizada correctamente.');
            return $this->redirectToRoute('app_planificacion_semanal_show', ['id' => $planificacion->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('planificacion_semanal/franja_edit.html.twig', [
            'planificacion' => $planificacion,
            'franja' => $franja,
            'form' => $form,
        ]);
    }

    #[Route('/franja/{id}', name: 'app_planificacion_semanal_franja_delete', methods: ['POST'])]
    public function deleteFranja(
        Request $request,
        FranjaHoraria $franja,
        EntityManagerInterface $entityManager
    ): Response {
        $planificacion = $franja->getPlanificacion();
        
        if ($this->isCsrfTokenValid('delete'.$franja->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($franja);
            $entityManager->flush();
            $this->addFlash('success', 'Franja horaria eliminada correctamente.');
        }

        return $this->redirectToRoute('app_planificacion_semanal_show', ['id' => $planificacion->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/edit', name: 'app_planificacion_semanal_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        PlanificacionSemanal $planificacion,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(PlanificacionSemanalType::class, $planificacion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Planificación semanal actualizada correctamente.');
            return $this->redirectToRoute('app_planificacion_semanal_show', ['id' => $planificacion->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('planificacion_semanal/edit.html.twig', [
            'planificacion' => $planificacion,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/asignar', name: 'app_planificacion_semanal_asignar', methods: ['GET', 'POST'])]
    public function asignar(
        Request $request,
        PlanificacionSemanal $planificacion,
        UserRepository $userRepository,
        PlanificacionPersonalizadaRepository $personalizadaRepository,
        PlanificacionService $planificacionService,
        EntityManagerInterface $entityManager
    ): Response {
        if ($request->isMethod('POST')) {
            $usuarioIds = $request->request->all('usuarios');
            
            if (empty($usuarioIds)) {
                $this->addFlash('error', 'Debes seleccionar al menos un usuario.');
            } else {
                $asignados = 0;
                $errores = 0;
                foreach ($usuarioIds as $usuarioId) {
                    $usuario = $userRepository->find($usuarioId);
                    if ($usuario) {
                        // Verificar si ya tiene esta misma planificación asignada
                        $planificacionExistente = $personalizadaRepository->createQueryBuilder('p')
                            ->where('p.usuario = :usuario')
                            ->andWhere('p.planificacionBase = :planificacion')
                            ->setParameter('usuario', $usuario)
                            ->setParameter('planificacion', $planificacion)
                            ->getQuery()
                            ->getOneOrNullResult();
                        
                        if ($planificacionExistente) {
                            $this->addFlash('info', "El usuario {$usuario->getUsername()} ya tiene esta planificación asignada.");
                            continue;
                        }
                        
                        try {
                            $planificacionService->crearDesdePlantilla($planificacion, $usuario);
                            $entityManager->flush(); // Flush después de cada creación para evitar conflictos
                            $asignados++;
                        } catch (\Exception $e) {
                            $errores++;
                            $this->addFlash('error', "Error al asignar planificación a {$usuario->getUsername()}: " . $e->getMessage());
                        }
                    }
                }
                
                if ($asignados > 0) {
                    $this->addFlash('success', "Planificación asignada a {$asignados} usuario(s).");
                }
                if ($errores > 0) {
                    $this->addFlash('error', "Hubo {$errores} error(es) al asignar planificaciones.");
                }
                
                return $this->redirectToRoute('app_planificacion_semanal_show', ['id' => $planificacion->getId()], Response::HTTP_SEE_OTHER);
            }
        }

        $usuarios = $userRepository->createQueryBuilder('u')
            ->where('u.activo = :activo')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('activo', true)
            ->setParameter('role', '%ROLE_USER%')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('planificacion_semanal/asignar.html.twig', [
            'planificacion' => $planificacion,
            'usuarios' => $usuarios,
        ]);
    }

    #[Route('/{id}/desasignar/{usuarioId}', name: 'app_planificacion_semanal_desasignar', methods: ['POST'])]
    public function desasignarUsuario(
        Request $request,
        PlanificacionSemanal $planificacion,
        int $usuarioId,
        UserRepository $userRepository,
        PlanificacionPersonalizadaRepository $personalizadaRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('desasignar'.$planificacion->getId().$usuarioId, $request->getPayload()->getString('_token'))) {
            $usuario = $userRepository->find($usuarioId);
            if (!$usuario) {
                $this->addFlash('error', 'Usuario no encontrado.');
                return $this->redirectToRoute('app_planificacion_semanal_show', ['id' => $planificacion->getId()], Response::HTTP_SEE_OTHER);
            }
            
            $planificacionPersonalizada = $personalizadaRepository->createQueryBuilder('p')
                ->where('p.planificacionBase = :planificacion')
                ->andWhere('p.usuario = :usuario')
                ->setParameter('planificacion', $planificacion)
                ->setParameter('usuario', $usuario)
                ->getQuery()
                ->getOneOrNullResult();
            
            if ($planificacionPersonalizada) {
                $entityManager->remove($planificacionPersonalizada);
                $entityManager->flush();
                $this->addFlash('success', 'Usuario desasignado correctamente.');
            } else {
                $this->addFlash('error', 'No se encontró la asignación del usuario.');
            }
        }

        return $this->redirectToRoute('app_planificacion_semanal_show', ['id' => $planificacion->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_planificacion_semanal_delete', methods: ['POST'])]
    public function delete(Request $request, PlanificacionSemanal $planificacion, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$planificacion->getId(), $request->getPayload()->getString('_token'))) {
            // Verificar si está en uso
            if ($planificacion->getPlanificacionesPersonalizadas()->count() > 0) {
                $this->addFlash('error', 'No se puede eliminar una planificación que tiene usuarios asignados. Desasigna primero a los usuarios.');
            } else {
                $entityManager->remove($planificacion);
                $entityManager->flush();
                $this->addFlash('success', 'Planificación eliminada correctamente.');
            }
        }

        return $this->redirectToRoute('app_planificacion_semanal_index', [], Response::HTTP_SEE_OTHER);
    }
}

