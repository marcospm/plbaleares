<?php

namespace App\Controller;

use App\Entity\PlanificacionPersonalizada;
use App\Form\PlanificacionFechaEspecificaType;
use App\Repository\PlanificacionPersonalizadaRepository;
use App\Repository\UserRepository;
use App\Service\NotificacionService;
use App\Service\PlanificacionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/planificacion')]
#[IsGranted('ROLE_PROFESOR')]
class PlanificacionController extends AbstractController
{
    #[Route('/', name: 'app_planificacion_index', methods: ['GET'])]
    public function index(
        Request $request,
        PlanificacionPersonalizadaRepository $planificacionRepository,
        UserRepository $userRepository
    ): Response {
        $filtroNombre = $request->query->get('nombre', '');
        $filtroUsuario = $request->query->get('usuario', '');
        
        $qb = $planificacionRepository->createQueryBuilder('p')
            ->orderBy('p.fechaCreacion', 'DESC');
        
        if ($filtroNombre) {
            $qb->andWhere('p.nombre LIKE :nombre')
               ->setParameter('nombre', '%' . $filtroNombre . '%');
        }
        
        if ($filtroUsuario) {
            $qb->andWhere('p.usuario = :usuario')
               ->setParameter('usuario', $filtroUsuario);
        }
        
        $planificaciones = $qb->getQuery()->getResult();
        
        $usuarios = $userRepository->createQueryBuilder('u')
            ->where('u.activo = :activo')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('activo', true)
            ->setParameter('role', '%ROLE_USER%')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
        
        return $this->render('planificacion/index.html.twig', [
            'planificaciones' => $planificaciones,
            'usuarios' => $usuarios,
            'filtroNombre' => $filtroNombre,
            'filtroUsuario' => $filtroUsuario,
        ]);
    }

    #[Route('/clonar', name: 'app_planificacion_clonar', methods: ['GET', 'POST'])]
    public function clonar(
        Request $request,
        PlanificacionPersonalizadaRepository $planificacionRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        NotificacionService $notificacionService
    ): Response {
        $usuarios = $userRepository->createQueryBuilder('u')
            ->where('u.activo = :activo')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('activo', true)
            ->setParameter('role', '%ROLE_USER%')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();

        if ($request->isMethod('POST')) {
            $alumnoOrigenId = $request->request->get('alumno_origen');
            $alumnoDestinoId = $request->request->get('alumno_destino');

            if (!$alumnoOrigenId || !$alumnoDestinoId) {
                $this->addFlash('error', 'Debes seleccionar tanto el alumno origen como el alumno destino.');
                return $this->render('planificacion/clonar.html.twig', [
                    'usuarios' => $usuarios,
                ]);
            }

            if ($alumnoOrigenId === $alumnoDestinoId) {
                $this->addFlash('error', 'El alumno origen y destino no pueden ser el mismo.');
                return $this->render('planificacion/clonar.html.twig', [
                    'usuarios' => $usuarios,
                ]);
            }

            $alumnoOrigen = $userRepository->find($alumnoOrigenId);
            $alumnoDestino = $userRepository->find($alumnoDestinoId);

            if (!$alumnoOrigen || !$alumnoDestino) {
                $this->addFlash('error', 'Uno o ambos alumnos no fueron encontrados.');
                return $this->render('planificacion/clonar.html.twig', [
                    'usuarios' => $usuarios,
                ]);
            }

            // Obtener todas las planificaciones del alumno origen
            $planificacionesOrigen = $planificacionRepository->findBy(['usuario' => $alumnoOrigen]);

            if (empty($planificacionesOrigen)) {
                $this->addFlash('error', 'El alumno origen no tiene planificaciones para clonar.');
                return $this->render('planificacion/clonar.html.twig', [
                    'usuarios' => $usuarios,
                ]);
            }

            // Obtener todas las planificaciones del alumno destino y eliminarlas
            $planificacionesDestino = $planificacionRepository->findBy(['usuario' => $alumnoDestino]);
            $planificacionesEliminadas = count($planificacionesDestino);

            foreach ($planificacionesDestino as $planificacionDestino) {
                // Eliminar también las tareas asignadas relacionadas
                foreach ($planificacionDestino->getFranjasHorarias() as $franja) {
                    foreach ($franja->getTareasAsignadas() as $tareaAsignada) {
                        $entityManager->remove($tareaAsignada);
                    }
                }
                $entityManager->remove($planificacionDestino);
            }

            // Clonar todas las planificaciones del origen al destino
            $planificacionesClonadas = 0;
            $creadoPor = $this->getUser();

            foreach ($planificacionesOrigen as $planificacionOrigen) {
                try {
                    // Crear nueva planificación para el destino
                    $nuevaPlanificacion = new PlanificacionPersonalizada();
                    $nuevaPlanificacion->setUsuario($alumnoDestino);
                    $nuevaPlanificacion->setCreadoPor($creadoPor);
                    $nuevaPlanificacion->setNombre($planificacionOrigen->getNombre());
                    $nuevaPlanificacion->setDescripcion($planificacionOrigen->getDescripcion());
                    $nuevaPlanificacion->setFechaInicio($planificacionOrigen->getFechaInicio());
                    $nuevaPlanificacion->setFechaFin($planificacionOrigen->getFechaFin());

                    // Clonar todas las franjas horarias
                    foreach ($planificacionOrigen->getFranjasHorarias() as $franjaOrigen) {
                        $nuevaFranja = new \App\Entity\FranjaHorariaPersonalizada();
                        $nuevaFranja->setPlanificacion($nuevaPlanificacion);
                        $nuevaFranja->setFranjaBase($franjaOrigen->getFranjaBase());
                        $nuevaFranja->setFechaEspecifica($franjaOrigen->getFechaEspecifica());
                        $nuevaFranja->setHoraInicio($franjaOrigen->getHoraInicio());
                        $nuevaFranja->setHoraFin($franjaOrigen->getHoraFin());
                        $nuevaFranja->setTipoActividad($franjaOrigen->getTipoActividad());
                        $nuevaFranja->setDescripcionRepaso($franjaOrigen->getDescripcionRepaso());
                        $nuevaFranja->setTemas($franjaOrigen->getTemas());
                        $nuevaFranja->setRecursos($franjaOrigen->getRecursos());
                        $nuevaFranja->setEnlaces($franjaOrigen->getEnlaces());
                        $nuevaFranja->setNotas($franjaOrigen->getNotas());
                        $nuevaFranja->setOrden($franjaOrigen->getOrden());

                        $nuevaPlanificacion->addFranjaHoraria($nuevaFranja);
                    }

                    $entityManager->persist($nuevaPlanificacion);
                    $planificacionesClonadas++;

                    // Crear notificación para el alumno destino
                    try {
                        $notificacionService->crearNotificacionPlanificacionCreada(
                            $nuevaPlanificacion,
                            $alumnoDestino,
                            $creadoPor
                        );
                    } catch (\Exception $e) {
                        error_log('Error al crear notificación: ' . $e->getMessage());
                    }
                } catch (\Exception $e) {
                    error_log('Error al clonar planificación: ' . $e->getMessage());
                }
            }

            $entityManager->flush();

            $mensaje = sprintf(
                'Planificación clonada correctamente. Se eliminaron %d planificación(es) del alumno destino y se clonaron %d planificación(es) del alumno origen.',
                $planificacionesEliminadas,
                $planificacionesClonadas
            );
            $this->addFlash('success', $mensaje);

            return $this->redirectToRoute('app_planificacion_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('planificacion/clonar.html.twig', [
            'usuarios' => $usuarios,
        ]);
    }

    #[Route('/new', name: 'app_planificacion_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        PlanificacionService $planificacionService,
        NotificacionService $notificacionService,
        EntityManagerInterface $entityManager
    ): Response {
        // Crear objeto temporal para el formulario
        $planificacionTemp = new PlanificacionPersonalizada();
        $planificacionTemp->setCreadoPor($this->getUser());
        
        $form = $this->createForm(PlanificacionFechaEspecificaType::class, $planificacionTemp);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Obtener los usuarios del formulario (campo personalizado)
            $usuarios = $form->get('usuarios')->getData();
            
            // Obtener datos de la planificación temporal
            $nombre = $planificacionTemp->getNombre();
            $descripcion = $planificacionTemp->getDescripcion();
            $fechaInicio = $planificacionTemp->getFechaInicio();
            $fechaFin = $planificacionTemp->getFechaFin();
            $franjasHorarias = $planificacionTemp->getFranjasHorarias();

            // Validar que se hayan seleccionado alumnos
            if (empty($usuarios)) {
                $this->addFlash('error', 'Debes seleccionar al menos un alumno.');
                return $this->render('planificacion/new.html.twig', [
                    'form' => $form,
                ]);
            }

            // Validar fechas
            if ($fechaInicio > $fechaFin) {
                $this->addFlash('error', 'La fecha de inicio debe ser anterior o igual a la fecha de fin.');
                return $this->render('planificacion/new.html.twig', [
                    'form' => $form,
                ]);
            }

            // Validar que haya al menos una actividad
            if (empty($franjasHorarias) || (is_array($franjasHorarias) && count($franjasHorarias) === 0)) {
                $this->addFlash('error', 'Debes agregar al menos una actividad.');
                return $this->render('planificacion/new.html.twig', [
                    'form' => $form,
                ]);
            }

            $creadoPor = $this->getUser();
            $planificacionesCreadas = [];
            $errores = [];

            // Crear una planificación para cada alumno
            foreach ($usuarios as $usuario) {
                try {
                    $planificacion = new PlanificacionPersonalizada();
                    $planificacion->setUsuario($usuario);
                    $planificacion->setCreadoPor($creadoPor);
                    $planificacion->setNombre($nombre);
                    $planificacion->setDescripcion($descripcion);
                    $planificacion->setFechaInicio($fechaInicio);
                    $planificacion->setFechaFin($fechaFin);

                    // Copiar las franjas horarias para este usuario
                    foreach ($franjasHorarias as $franjaData) {
                        $franja = new \App\Entity\FranjaHorariaPersonalizada();
                        $franja->setPlanificacion($planificacion);
                        $franja->setFechaEspecifica($franjaData->getFechaEspecifica());
                        $franja->setHoraInicio($franjaData->getHoraInicio());
                        $franja->setHoraFin($franjaData->getHoraFin());
                        $franja->setTipoActividad($franjaData->getTipoActividad());
                        $franja->setDescripcionRepaso($franjaData->getDescripcionRepaso());
                        $franja->setTemas($franjaData->getTemas());
                        $franja->setRecursos($franjaData->getRecursos());
                        $franja->setEnlaces($franjaData->getEnlaces());
                        $franja->setNotas($franjaData->getNotas());
                        $franja->setOrden($franjaData->getOrden());
                        $planificacion->addFranjaHoraria($franja);
                    }

                    // Validar fechas de actividades dentro del rango
                    $erroresFechas = $planificacionService->validarFechasEnRango($planificacion);
                    if (!empty($erroresFechas)) {
                        $errores[] = "Alumno {$usuario->getUsername()}: " . implode(', ', $erroresFechas);
                        continue;
                    }

                    // Validar solapamientos
                    $franjasArray = $planificacion->getFranjasHorarias()->toArray();
                    $erroresValidacion = $planificacionService->validarFranjas($franjasArray, $usuario);
                    if (!empty($erroresValidacion)) {
                        $errores[] = "Alumno {$usuario->getUsername()}: " . implode(', ', $erroresValidacion);
                        continue;
                    }

                    $entityManager->persist($planificacion);
                    $planificacionesCreadas[] = $planificacion;

                    // Crear notificación
                    try {
                        $notificacionService->crearNotificacionPlanificacionCreada(
                            $planificacion,
                            $usuario,
                            $creadoPor
                        );
                    } catch (\Exception $e) {
                        error_log('Error al crear notificación: ' . $e->getMessage());
                    }
                } catch (\Exception $e) {
                    $errores[] = "Error al crear planificación para {$usuario->getUsername()}: " . $e->getMessage();
                }
            }

            $entityManager->flush();

            if (!empty($planificacionesCreadas)) {
                $this->addFlash('success', sprintf(
                    'Planificación creada correctamente para %d alumno(s).',
                    count($planificacionesCreadas)
                ));
            }

            if (!empty($errores)) {
                foreach ($errores as $error) {
                    $this->addFlash('error', $error);
                }
            }

            return $this->redirectToRoute('app_planificacion_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('planificacion/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_planificacion_show', methods: ['GET'])]
    public function show(PlanificacionPersonalizada $planificacion): Response
    {
        // Agrupar franjas por fecha
        $franjasPorFecha = [];
        foreach ($planificacion->getFranjasHorarias() as $franja) {
            $fechaKey = $franja->getFechaEspecifica()->format('Y-m-d');
            if (!isset($franjasPorFecha[$fechaKey])) {
                $franjasPorFecha[$fechaKey] = [];
            }
            $franjasPorFecha[$fechaKey][] = $franja;
        }
        
        // Ordenar por fecha
        ksort($franjasPorFecha);
        
        // Ordenar franjas dentro de cada fecha por orden
        foreach ($franjasPorFecha as &$franjas) {
            usort($franjas, function($a, $b) {
                return $a->getOrden() <=> $b->getOrden();
            });
        }

        return $this->render('planificacion/show.html.twig', [
            'planificacion' => $planificacion,
            'franjasPorFecha' => $franjasPorFecha,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_planificacion_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        PlanificacionPersonalizada $planificacion,
        PlanificacionService $planificacionService,
        NotificacionService $notificacionService,
        EntityManagerInterface $entityManager
    ): Response {
        // Refrescar la entidad desde la base de datos para asegurar que tenemos todas las franjas
        $entityManager->refresh($planificacion);
        
        // Crear formulario solo para los campos básicos de la planificación (sin actividades)
        $form = $this->createFormBuilder($planificacion)
            ->add('nombre', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
                'label' => 'Nombre de la Planificación',
                'required' => true,
            ])
            ->add('descripcion', \Symfony\Component\Form\Extension\Core\Type\TextareaType::class, [
                'label' => 'Descripción',
                'required' => false,
            ])
            ->add('fechaInicio', \Symfony\Component\Form\Extension\Core\Type\DateType::class, [
                'label' => 'Fecha de Inicio',
                'required' => true,
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('fechaFin', \Symfony\Component\Form\Extension\Core\Type\DateType::class, [
                'label' => 'Fecha de Fin',
                'required' => true,
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->getForm();
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Validar fechas
                if ($planificacion->getFechaInicio() > $planificacion->getFechaFin()) {
                    $this->addFlash('error', 'La fecha de inicio debe ser anterior o igual a la fecha de fin.');
                    return $this->redirectToRoute('app_planificacion_edit', ['id' => $planificacion->getId()], Response::HTTP_SEE_OTHER);
                }

                $planificacion->setFechaModificacion(new \DateTimeImmutable());
                $entityManager->flush();

                // Crear notificación de edición
                try {
                    $notificacionService->crearNotificacionPlanificacionEditada($planificacion, $this->getUser());
                    $entityManager->flush();
                } catch (\Exception $e) {
                    error_log('Error al crear notificación: ' . $e->getMessage());
                }

                $this->addFlash('success', 'Planificación actualizada correctamente.');
                return $this->redirectToRoute('app_planificacion_edit', ['id' => $planificacion->getId()], Response::HTTP_SEE_OTHER);
            } catch (\Exception $e) {
                error_log('Error al editar planificación: ' . $e->getMessage());
                $this->addFlash('error', 'Hubo un error al guardar la planificación. Por favor, inténtalo de nuevo.');
                return $this->redirectToRoute('app_planificacion_edit', ['id' => $planificacion->getId()], Response::HTTP_SEE_OTHER);
            }
        }

        // Cargar actividades existentes
        $actividadesExistentes = $planificacion->getFranjasHorarias()->toArray();
        usort($actividadesExistentes, function($a, $b) {
            if ($a->getFechaEspecifica() == $b->getFechaEspecifica()) {
                return $a->getHoraInicio() <=> $b->getHoraInicio();
            }
            return $a->getFechaEspecifica() <=> $b->getFechaEspecifica();
        });

        // Formulario para agregar nueva actividad
        $nuevaActividad = new \App\Entity\FranjaHorariaPersonalizada();
        $nuevaActividad->setPlanificacion($planificacion);
        $formNuevaActividad = $this->createForm(\App\Form\ActividadFechaEspecificaType::class, $nuevaActividad);

        return $this->render('planificacion/edit.html.twig', [
            'planificacion' => $planificacion,
            'form' => $form,
            'actividadesExistentes' => $actividadesExistentes,
            'formNuevaActividad' => $formNuevaActividad,
        ]);
    }

    #[Route('/{planificacionId}/actividad/new', name: 'app_planificacion_actividad_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PROFESOR')]
    public function nuevaActividad(
        Request $request,
        int $planificacionId,
        PlanificacionService $planificacionService,
        EntityManagerInterface $entityManager
    ): Response {
        $planificacion = $entityManager->getRepository(PlanificacionPersonalizada::class)->find($planificacionId);
        if (!$planificacion) {
            throw $this->createNotFoundException('Planificación no encontrada');
        }

        $nuevaActividad = new \App\Entity\FranjaHorariaPersonalizada();
        $nuevaActividad->setPlanificacion($planificacion);
        $form = $this->createForm(\App\Form\ActividadFechaEspecificaType::class, $nuevaActividad);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Validar que la fecha esté en el rango de la planificación
            if ($nuevaActividad->getFechaEspecifica() < $planificacion->getFechaInicio() || 
                $nuevaActividad->getFechaEspecifica() > $planificacion->getFechaFin()) {
                $this->addFlash('error', 'La fecha de la actividad debe estar dentro del rango de la planificación.');
                return $this->redirectToRoute('app_planificacion_edit', ['id' => $planificacionId], Response::HTTP_SEE_OTHER);
            }

            // Validar solapamientos
            $franjasArray = [$nuevaActividad];
            $errores = $planificacionService->validarFranjas($franjasArray, $planificacion->getUsuario());
            if (!empty($errores)) {
                foreach ($errores as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->redirectToRoute('app_planificacion_edit', ['id' => $planificacionId], Response::HTTP_SEE_OTHER);
            }

            $entityManager->persist($nuevaActividad);
            $planificacion->setFechaModificacion(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Actividad agregada correctamente.');
            return $this->redirectToRoute('app_planificacion_edit', ['id' => $planificacionId], Response::HTTP_SEE_OTHER);
        }

        // Si no se envió el formulario, redirigir a la página de edición
        return $this->redirectToRoute('app_planificacion_edit', ['id' => $planificacionId], Response::HTTP_SEE_OTHER);
    }

    #[Route('/actividad/{id}/edit', name: 'app_planificacion_actividad_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PROFESOR')]
    public function editarActividad(
        Request $request,
        \App\Entity\FranjaHorariaPersonalizada $actividad,
        PlanificacionService $planificacionService,
        EntityManagerInterface $entityManager
    ): Response {
        $planificacion = $actividad->getPlanificacion();
        $form = $this->createForm(\App\Form\ActividadFechaEspecificaType::class, $actividad);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Validar que la fecha esté en el rango de la planificación
            if ($actividad->getFechaEspecifica() < $planificacion->getFechaInicio() || 
                $actividad->getFechaEspecifica() > $planificacion->getFechaFin()) {
                $this->addFlash('error', 'La fecha de la actividad debe estar dentro del rango de la planificación.');
                return $this->render('planificacion/edit_actividad.html.twig', [
                    'actividad' => $actividad,
                    'planificacion' => $planificacion,
                    'form' => $form,
                ]);
            }

            // Validar solapamientos (excluyendo esta actividad)
            $franjasArray = $planificacion->getFranjasHorarias()->filter(function($f) use ($actividad) {
                return $f->getId() !== $actividad->getId();
            })->toArray();
            $franjasArray[] = $actividad;
            $errores = $planificacionService->validarFranjas($franjasArray, $planificacion->getUsuario());
            if (!empty($errores)) {
                foreach ($errores as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->render('planificacion/edit_actividad.html.twig', [
                    'actividad' => $actividad,
                    'planificacion' => $planificacion,
                    'form' => $form,
                ]);
            }

            $planificacion->setFechaModificacion(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Actividad actualizada correctamente.');
            return $this->redirectToRoute('app_planificacion_edit', ['id' => $planificacion->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('planificacion/edit_actividad.html.twig', [
            'actividad' => $actividad,
            'planificacion' => $planificacion,
            'form' => $form,
        ]);
    }

    #[Route('/actividad/{id}', name: 'app_planificacion_actividad_delete', methods: ['POST'])]
    #[IsGranted('ROLE_PROFESOR')]
    public function eliminarActividad(
        Request $request,
        \App\Entity\FranjaHorariaPersonalizada $actividad,
        EntityManagerInterface $entityManager
    ): Response {
        $planificacion = $actividad->getPlanificacion();
        
        if ($this->isCsrfTokenValid('delete' . $actividad->getId(), $request->request->get('_token'))) {
            $entityManager->remove($actividad);
            $planificacion->setFechaModificacion(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', 'Actividad eliminada correctamente.');
        }

        return $this->redirectToRoute('app_planificacion_edit', ['id' => $planificacion->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_planificacion_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        PlanificacionPersonalizada $planificacion,
        NotificacionService $notificacionService,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$planificacion->getId(), $request->getPayload()->getString('_token'))) {
            $nombrePlanificacion = $planificacion->getNombre();
            $alumno = $planificacion->getUsuario();
            $profesor = $this->getUser();
            
            // Crear notificación antes de eliminar
            try {
                $notificacionService->crearNotificacionPlanificacionEliminada($nombrePlanificacion, [$alumno], $profesor);
            } catch (\Exception $e) {
                error_log('Error al crear notificación de planificación eliminada: ' . $e->getMessage());
            }
            
            $entityManager->remove($planificacion);
            $entityManager->flush();
            $this->addFlash('success', 'Planificación eliminada correctamente.');
        }

        return $this->redirectToRoute('app_planificacion_index', [], Response::HTTP_SEE_OTHER);
    }
}

