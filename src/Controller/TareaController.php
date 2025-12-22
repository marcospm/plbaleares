<?php

namespace App\Controller;

use App\Entity\Tarea;
use App\Entity\TareaAsignada;
use App\Entity\FranjaHorariaPersonalizada;
use App\Form\TareaType;
use App\Form\AsignarFranjaType;
use App\Repository\TareaRepository;
use App\Repository\TareaAsignadaRepository;
use App\Repository\FranjaHorariaPersonalizadaRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tarea')]
#[IsGranted('ROLE_PROFESOR')]
class TareaController extends AbstractController
{
    #[Route('/', name: 'app_tarea_index', methods: ['GET'])]
    public function index(TareaRepository $tareaRepository, Request $request): Response
    {
        $search = $request->query->get('search', '');
        $semana = $request->query->get('semana');
        $usuarioId = $request->query->getInt('usuario');
        $completadas = $request->query->get('completadas');

        $tareas = $tareaRepository->findAll();

        if (!empty($search)) {
            $tareas = array_filter($tareas, function($tarea) use ($search) {
                return stripos($tarea->getNombre(), $search) !== false ||
                       stripos($tarea->getDescripcion(), $search) !== false;
            });
        }

        if ($semana) {
            $semanaDate = new \DateTime($semana);
            $tareas = array_filter($tareas, function($tarea) use ($semanaDate) {
                return $tarea->getSemanaAsignacion()->format('Y-m-d') === $semanaDate->format('Y-m-d');
            });
        }

        if ($usuarioId > 0) {
            $tareas = array_filter($tareas, function($tarea) use ($usuarioId) {
                foreach ($tarea->getAsignaciones() as $asignacion) {
                    if ($asignacion->getUsuario()->getId() === $usuarioId) {
                        return true;
                    }
                }
                return false;
            });
        }

        if ($completadas !== null) {
            $completadasBool = $completadas === 'true';
            $tareas = array_filter($tareas, function($tarea) use ($completadasBool) {
                foreach ($tarea->getAsignaciones() as $asignacion) {
                    if ($asignacion->isCompletada() === $completadasBool) {
                        return true;
                    }
                }
                return false;
            });
        }

        return $this->render('tarea/index.html.twig', [
            'tareas' => $tareas,
            'search' => $search,
            'semana' => $semana,
            'usuarioId' => $usuarioId,
            'completadas' => $completadas,
        ]);
    }

    #[Route('/new', name: 'app_tarea_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        $tarea = new Tarea();
        $form = $this->createForm(TareaType::class, $tarea);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $tarea->setCreadoPor($this->getUser());
            
            // Crear TareaAsignada para cada usuario seleccionado
            $usuarios = $form->get('usuarios')->getData();
            foreach ($usuarios as $usuario) {
                $tareaAsignada = new TareaAsignada();
                $tareaAsignada->setTarea($tarea);
                $tareaAsignada->setUsuario($usuario);
                $entityManager->persist($tareaAsignada);
            }

            $entityManager->persist($tarea);
            $entityManager->flush();

            $this->addFlash('success', 'Tarea creada correctamente.');
            return $this->redirectToRoute('app_tarea_show', ['id' => $tarea->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tarea/new.html.twig', [
            'tarea' => $tarea,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_tarea_show', methods: ['GET'])]
    public function show(Tarea $tarea): Response
    {
        return $this->render('tarea/show.html.twig', [
            'tarea' => $tarea,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_tarea_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Tarea $tarea, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        // Pre-cargar usuarios actuales en el formulario
        $usuariosActuales = [];
        foreach ($tarea->getAsignaciones() as $asignacion) {
            $usuariosActuales[] = $asignacion->getUsuario();
        }

        $form = $this->createForm(TareaType::class, $tarea);
        $form->get('usuarios')->setData($usuariosActuales);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Obtener usuarios seleccionados
            $usuariosSeleccionados = $form->get('usuarios')->getData();
            $usuariosSeleccionadosIds = array_map(function($u) { return $u->getId(); }, $usuariosSeleccionados);
            
            // Eliminar asignaciones de usuarios que ya no están seleccionados
            foreach ($tarea->getAsignaciones() as $asignacion) {
                if (!in_array($asignacion->getUsuario()->getId(), $usuariosSeleccionadosIds)) {
                    $entityManager->remove($asignacion);
                }
            }
            
            // Agregar asignaciones para usuarios nuevos
            $usuariosActualesIds = array_map(function($u) { return $u->getId(); }, $usuariosActuales);
            foreach ($usuariosSeleccionados as $usuario) {
                if (!in_array($usuario->getId(), $usuariosActualesIds)) {
                    $tareaAsignada = new TareaAsignada();
                    $tareaAsignada->setTarea($tarea);
                    $tareaAsignada->setUsuario($usuario);
                    $entityManager->persist($tareaAsignada);
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Tarea actualizada correctamente.');
            return $this->redirectToRoute('app_tarea_show', ['id' => $tarea->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tarea/edit.html.twig', [
            'tarea' => $tarea,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_tarea_delete', methods: ['POST'])]
    public function delete(Request $request, Tarea $tarea, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$tarea->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($tarea);
            $entityManager->flush();
            $this->addFlash('success', 'Tarea eliminada correctamente.');
        }

        return $this->redirectToRoute('app_tarea_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/asignar-franja/{asignacionId}', name: 'app_tarea_asignar_franja', methods: ['GET', 'POST'])]
    public function asignarFranja(
        Request $request,
        Tarea $tarea,
        int $asignacionId,
        TareaAsignadaRepository $tareaAsignadaRepository,
        FranjaHorariaPersonalizadaRepository $franjaRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $tareaAsignada = $tareaAsignadaRepository->find($asignacionId);
        
        if (!$tareaAsignada || $tareaAsignada->getTarea() !== $tarea) {
            $this->addFlash('error', 'Asignación no encontrada.');
            return $this->redirectToRoute('app_tarea_show', ['id' => $tarea->getId()], Response::HTTP_SEE_OTHER);
        }

        $usuario = $tareaAsignada->getUsuario();
        
        // Obtener todas las franjas del usuario (de todos los días) de tipo estudio_tareas
        $franjasDisponibles = [];
        for ($dia = 1; $dia <= 7; $dia++) {
            $franjasDelDia = $franjaRepository->findByUsuarioYdia($usuario, $dia);
            foreach ($franjasDelDia as $franja) {
                if ($franja->getTipoActividad() === 'estudio_tareas') {
                    $franjasDisponibles[] = $franja;
                }
            }
        }

        $form = $this->createForm(AsignarFranjaType::class, $tareaAsignada, [
            'masiva' => false,
            'franjas_disponibles' => $franjasDisponibles,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Franja horaria asignada correctamente.');
            return $this->redirectToRoute('app_tarea_show', ['id' => $tarea->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tarea/asignar_franja.html.twig', [
            'tarea' => $tarea,
            'tareaAsignada' => $tareaAsignada,
            'form' => $form,
        ]);
    }
}

