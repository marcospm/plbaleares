<?php

namespace App\Controller;

use App\Repository\ExamenRepository;
use App\Repository\FechasPruebasRepository;
use App\Repository\PlanificacionPersonalizadaRepository;
use App\Repository\TareaAsignadaRepository;
use App\Service\PlanificacionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        Request $request,
        ExamenRepository $examenRepository,
        PlanificacionPersonalizadaRepository $planificacionRepository,
        TareaAsignadaRepository $tareaAsignadaRepository,
        PlanificacionService $planificacionService,
        FechasPruebasRepository $fechasPruebasRepository
    ): Response {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_home');
        }

        // Si es profesor, mostrar dashboard de profesor
        if ($this->isGranted('ROLE_PROFESOR') || $this->isGranted('ROLE_ADMIN')) {
            return $this->render('dashboard/index.html.twig', [
                'isProfesor' => true,
            ]);
        }

        // Si es usuario normal, mostrar dashboard con exámenes, tareas y planificaciones
        $ultimosExamenes = $examenRepository->findByUsuario($user, 10);
        $estadisticas = $examenRepository->getEstadisticasUsuario($user);
        
        // Obtener planificación del usuario
        $planificacion = $planificacionRepository->findByUsuario($user);
        
        // Obtener tareas pendientes
        $tareasPendientes = $tareaAsignadaRepository->findPendientesByUsuario($user);
        
        // Obtener resumen de tareas de la semana actual
        $lunesSemana = new \DateTime('monday this week');
        $resumenTareas = $planificacionService->calcularResumenSemanal($user, $lunesSemana);
        
        // Obtener próximas tareas (próximas 3 semanas)
        $proximasTareas = [];
        for ($i = 0; $i < 3; $i++) {
            $semana = clone $lunesSemana;
            $semana->modify('+' . ($i * 7) . ' days');
            $tareasSemana = $tareaAsignadaRepository->findByUsuarioYsemana($user, $semana);
            if (!empty($tareasSemana)) {
                $proximasTareas[] = [
                    'semana' => $semana,
                    'tareas' => $tareasSemana,
                ];
            }
        }

        // Obtener fechas de pruebas
        $fechasPruebas = $fechasPruebasRepository->findActivas();

        // Obtener cantidad de exámenes para el ranking (por defecto 3)
        $cantidadRanking = $request->query->getInt('cantidad', 3);
        if ($cantidadRanking < 2) {
            $cantidadRanking = 2;
        }
        $rankings = [];
        $posicionesUsuario = [];
        $dificultades = ['facil', 'moderada', 'dificil'];
        
        foreach ($dificultades as $dificultad) {
            $ranking = $examenRepository->getRankingPorDificultad($dificultad, $cantidadRanking);
            $rankings[$dificultad] = $ranking;
            $posicion = $examenRepository->getPosicionUsuario($user, $dificultad, $cantidadRanking);
            $notaMedia = $examenRepository->getNotaMediaUsuario($user, $dificultad, $cantidadRanking);
            $posicionesUsuario[$dificultad] = [
                'posicion' => $posicion,
                'notaMedia' => $notaMedia,
                'totalUsuarios' => count($ranking),
            ];
        }

        return $this->render('dashboard/index.html.twig', [
            'isProfesor' => false,
            'ultimosExamenes' => $ultimosExamenes,
            'estadisticas' => $estadisticas,
            'planificacion' => $planificacion,
            'tareasPendientes' => array_slice($tareasPendientes, 0, 5), // Primeras 5 tareas pendientes
            'resumenTareas' => $resumenTareas,
            'proximasTareas' => $proximasTareas,
            'fechasPruebas' => $fechasPruebas,
            'posicionesUsuario' => $posicionesUsuario,
            'cantidadRanking' => $cantidadRanking,
        ]);
    }
}

