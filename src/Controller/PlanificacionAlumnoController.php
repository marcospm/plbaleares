<?php

namespace App\Controller;

use App\Entity\TareaAsignada;
use App\Repository\PlanificacionPersonalizadaRepository;
use App\Repository\TareaAsignadaRepository;
use App\Service\NotificacionService;
use App\Service\PlanificacionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mi-planificacion')]
#[IsGranted('ROLE_USER')]
class PlanificacionAlumnoController extends AbstractController
{
    public function __construct(
        private NotificacionService $notificacionService
    ) {
    }
    #[Route('/', name: 'app_planificacion_alumno_index', methods: ['GET'])]
    public function index(
        Request $request,
        PlanificacionPersonalizadaRepository $planificacionRepository,
        PlanificacionService $planificacionService
    ): Response {
        $usuario = $this->getUser();
        $planificaciones = $planificacionRepository->findAllByUsuario($usuario);

        if (empty($planificaciones)) {
            $this->addFlash('warning', 'No tienes planificaciones asignadas. Contacta con un administrador.');
            return $this->redirectToRoute('app_dashboard');
        }

        // Obtener semana (por defecto la actual, pero permitir navegar)
        $semanaParam = $request->query->get('semana');
        $lunesActual = new \DateTime('monday this week');
        
        if ($semanaParam) {
            $lunesSemana = new \DateTime($semanaParam);
            // Asegurar que es lunes
            if ($lunesSemana->format('N') != '1') {
                $lunesSemana->modify('monday this week');
            }
            // No permitir navegar al pasado (solo futuro o semana actual)
            if ($lunesSemana < $lunesActual) {
                $lunesSemana = clone $lunesActual;
            }
        } else {
            $lunesSemana = clone $lunesActual;
        }
        
        // Obtener franjas de la semana seleccionada de TODAS las planificaciones
        // Las franjas se filtran automáticamente por día de la semana
        $franjasPorDia = [];
        for ($dia = 1; $dia <= 7; $dia++) {
            $fechaDia = clone $lunesSemana;
            $fechaDia->modify('+' . ($dia - 1) . ' days');
            
            // obtenerFranjasDelDia ya filtra por día de la semana (1=Lunes, 7=Domingo)
            // y obtiene franjas de TODAS las planificaciones del usuario
            $franjasDelDia = $planificacionService->obtenerFranjasDelDia($usuario, $fechaDia);
            
            // Ordenar por hora de inicio
            usort($franjasDelDia, function($a, $b) {
                return $a->getHoraInicio() <=> $b->getHoraInicio();
            });
            
            $franjasPorDia[$dia] = $franjasDelDia;
        }

        // Obtener TODAS las tareas pendientes del usuario para mostrar en la sección de "Tareas Pendientes"
        // pero solo mostrar las de la semana seleccionada en la tabla principal
        $tareasPendientes = $planificacionService->obtenerTareasPendientes($usuario);

        // Agrupar tareas pendientes por semana
        $tareasPendientesPorSemana = [];
        foreach ($tareasPendientes as $tareaAsignada) {
            $semanaTarea = $tareaAsignada->getTarea()->getSemanaAsignacion();
            $lunesTarea = clone $semanaTarea;
            if ($lunesTarea->format('N') != '1') {
                $lunesTarea->modify('monday this week');
            }
            $semanaKey = $lunesTarea->format('Y-m-d');
            if (!isset($tareasPendientesPorSemana[$semanaKey])) {
                $tareasPendientesPorSemana[$semanaKey] = [];
            }
            $tareasPendientesPorSemana[$semanaKey][] = $tareaAsignada;
        }
        ksort($tareasPendientesPorSemana); // Ordenar por fecha de semana

        // Calcular resumen de la semana seleccionada
        $resumen = $planificacionService->calcularResumenSemanal($usuario, $lunesSemana);

        // Navegación de semanas (solo hacia el futuro)
        $semanaAnterior = null;
        if ($lunesSemana > $lunesActual) {
            $semanaAnterior = clone $lunesSemana;
            $semanaAnterior->modify('-7 days');
        }
        
        $semanaSiguiente = clone $lunesSemana;
        $semanaSiguiente->modify('+7 days');
        
        $esSemanaActual = ($lunesSemana->format('Y-m-d') === $lunesActual->format('Y-m-d'));

        return $this->render('planificacion_alumno/index.html.twig', [
            'planificaciones' => $planificaciones,
            'franjasPorDia' => $franjasPorDia,
            'tareasPendientesPorSemana' => $tareasPendientesPorSemana,
            'lunesSemana' => $lunesSemana,
            'lunesActual' => $lunesActual,
            'semanaAnterior' => $semanaAnterior,
            'semanaSiguiente' => $semanaSiguiente,
            'esSemanaActual' => $esSemanaActual,
            'resumen' => $resumen,
        ]);
    }

    #[Route('/dia/{fecha}', name: 'app_planificacion_alumno_dia', methods: ['GET'])]
    public function dia(
        string $fecha,
        PlanificacionPersonalizadaRepository $planificacionRepository,
        PlanificacionService $planificacionService
    ): Response {
        $usuario = $this->getUser();
        $planificaciones = $planificacionRepository->findAllByUsuario($usuario);

        if (empty($planificaciones)) {
            $this->addFlash('warning', 'No tienes planificaciones asignadas.');
            return $this->redirectToRoute('app_dashboard');
        }

        $fechaObj = new \DateTime($fecha);
        $diaSemana = (int) $fechaObj->format('N');
        
        // Obtener lunes de la semana
        $lunesSemana = clone $fechaObj;
        $lunesSemana->modify('monday this week');

        $franjasDelDia = $planificacionService->obtenerFranjasDelDia($usuario, $fechaObj);

        return $this->render('planificacion_alumno/dia.html.twig', [
            'planificaciones' => $planificaciones,
            'fecha' => $fechaObj,
            'diaSemana' => $diaSemana,
            'franjasDelDia' => $franjasDelDia,
            'lunesSemana' => $lunesSemana,
        ]);
    }

    #[Route('/tarea/{id}/marcar-completada', name: 'app_planificacion_alumno_marcar_completada', methods: ['POST'])]
    public function marcarCompletada(
        TareaAsignada $tareaAsignada,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        $usuario = $this->getUser();
        
        if ($tareaAsignada->getUsuario() !== $usuario) {
            return new JsonResponse(['success' => false, 'message' => 'No tienes permiso para esta acción.'], 403);
        }

        // Validar token CSRF
        $token = $request->getPayload()->get('_token');
        if (!$token || !$this->isCsrfTokenValid('marcar_completada'.$tareaAsignada->getId(), $token)) {
            return new JsonResponse(['success' => false, 'message' => 'Token inválido.'], 400);
        }

        $tareaAsignada->setCompletada(true);
        $entityManager->flush();

        // Crear notificación para el profesor asignado
        try {
            $this->notificacionService->crearNotificacionTarea($tareaAsignada);
        } catch (\Exception $e) {
            // Si hay error al crear la notificación, no fallar la operación principal
            // Solo loguear el error (en producción usar un logger)
            error_log('Error al crear notificación de tarea: ' . $e->getMessage());
        }

        return new JsonResponse(['success' => true, 'message' => 'Tarea marcada como completada.']);
    }

    #[Route('/tarea/{id}/marcar-pendiente', name: 'app_planificacion_alumno_marcar_pendiente', methods: ['POST'])]
    public function marcarPendiente(
        TareaAsignada $tareaAsignada,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        $usuario = $this->getUser();
        
        if ($tareaAsignada->getUsuario() !== $usuario) {
            return new JsonResponse(['success' => false, 'message' => 'No tienes permiso para esta acción.'], 403);
        }

        // Validar token CSRF
        $token = $request->getPayload()->get('_token');
        if (!$token || !$this->isCsrfTokenValid('marcar_pendiente'.$tareaAsignada->getId(), $token)) {
            return new JsonResponse(['success' => false, 'message' => 'Token inválido.'], 400);
        }

        $tareaAsignada->setCompletada(false);
        $entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => 'Tarea marcada como pendiente.']);
    }
}

