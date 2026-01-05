<?php

namespace App\Controller;

use App\Entity\TareaAsignada;
use App\Repository\PlanificacionPersonalizadaRepository;
use App\Repository\TareaAsignadaRepository;
use App\Service\NotificacionService;
use App\Service\PlanificacionService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
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

        // Si no hay planificaciones, mostrar la página con un mensaje
        if (empty($planificaciones)) {
            return $this->render('planificacion_alumno/index.html.twig', [
                'planificaciones' => [],
                'franjasPorDia' => [],
                'tareasPendientesPorSemana' => [],
                'lunesSemana' => new \DateTime('monday this week'),
                'lunesActual' => new \DateTime('monday this week'),
                'semanaAnterior' => null,
                'semanaSiguiente' => null,
                'esSemanaActual' => true,
                'resumen' => [
                    'total' => 0,
                    'completadas' => 0,
                    'pendientes' => 0,
                    'vencidas' => 0,
                ],
                'sinPlanificacion' => true,
            ]);
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
            $this->addFlash('warning', 'No tienes planificaciones asignadas. Contacta con tu profesor para empezar con una planificación.');
            return $this->redirectToRoute('app_planificacion_alumno_index');
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

    #[Route('/pdf-mensual', name: 'app_planificacion_alumno_pdf_mensual', methods: ['GET'])]
    public function pdfMensual(
        Request $request,
        PlanificacionPersonalizadaRepository $planificacionRepository,
        PlanificacionService $planificacionService
    ): Response {
        $usuario = $this->getUser();
        $planificaciones = $planificacionRepository->findAllByUsuario($usuario);

        if (empty($planificaciones)) {
            $this->addFlash('error', 'No tienes planificaciones asignadas.');
            return $this->redirectToRoute('app_planificacion_alumno_index');
        }

        $mes = $request->query->getInt('mes');
        $anio = $request->query->getInt('anio');

        if ($mes < 1 || $mes > 12) {
            $this->addFlash('error', 'Mes inválido.');
            return $this->redirectToRoute('app_planificacion_alumno_index');
        }

        if ($anio < 2000 || $anio > 2100) {
            $this->addFlash('error', 'Año inválido.');
            return $this->redirectToRoute('app_planificacion_alumno_index');
        }

        // Obtener primer y último día del mes
        $primerDiaMes = new \DateTime("$anio-$mes-01");
        $ultimoDiaMes = clone $primerDiaMes;
        $ultimoDiaMes->modify('last day of this month');

        // Obtener todas las franjas del mes
        $franjasPorDia = [];
        $fechaActual = clone $primerDiaMes;
        
        while ($fechaActual <= $ultimoDiaMes) {
            $franjasDelDia = $planificacionService->obtenerFranjasDelDia($usuario, $fechaActual);
            
            // Ordenar por hora de inicio
            usort($franjasDelDia, function($a, $b) {
                return $a->getHoraInicio() <=> $b->getHoraInicio();
            });
            
            $franjasPorDia[$fechaActual->format('Y-m-d')] = $franjasDelDia;
            $fechaActual->modify('+1 day');
        }

        // Obtener tareas del mes
        $tareasPendientes = $planificacionService->obtenerTareasPendientes($usuario);
        $tareasDelMes = [];
        foreach ($tareasPendientes as $tareaAsignada) {
            $fechaTarea = $tareaAsignada->getTarea()->getSemanaAsignacion();
            // Obtener lunes de la semana de la tarea
            $lunesTarea = clone $fechaTarea;
            if ($lunesTarea->format('N') != '1') {
                $lunesTarea->modify('monday this week');
            }
            // Obtener domingo de la semana de la tarea
            $domingoTarea = clone $lunesTarea;
            $domingoTarea->modify('+6 days');
            
            // Si la semana de la tarea se solapa con el mes, incluirla
            if (($lunesTarea >= $primerDiaMes && $lunesTarea <= $ultimoDiaMes) ||
                ($domingoTarea >= $primerDiaMes && $domingoTarea <= $ultimoDiaMes) ||
                ($lunesTarea < $primerDiaMes && $domingoTarea > $ultimoDiaMes)) {
                $tareasDelMes[] = $tareaAsignada;
            }
        }

        // Generar HTML para el PDF
        $html = $this->renderView('planificacion_alumno/pdf_mensual.html.twig', [
            'usuario' => $usuario,
            'mes' => $mes,
            'anio' => $anio,
            'primerDiaMes' => $primerDiaMes,
            'ultimoDiaMes' => $ultimoDiaMes,
            'franjasPorDia' => $franjasPorDia,
            'tareasDelMes' => $tareasDelMes,
            'planificaciones' => $planificaciones,
        ]);

        // Configurar DomPDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        $options->set('isPhpEnabled', true);
        $options->set('isFontSubsettingEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Generar nombre del archivo
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        $nombreMes = $meses[$mes] ?? 'Mes';
        $nombreArchivo = "Planificacion_{$nombreMes}_{$anio}.pdf";

        // Retornar PDF
        $response = new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $nombreArchivo . '"',
            ]
        );
        
        return $response;
    }
}

