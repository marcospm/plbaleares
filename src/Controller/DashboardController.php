<?php

namespace App\Controller;

use App\Repository\ExamenRepository;
use App\Repository\PlanificacionPersonalizadaRepository;
use App\Repository\TareaAsignadaRepository;
use App\Repository\MunicipioRepository;
use App\Repository\ConvocatoriaRepository;
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
        MunicipioRepository $municipioRepository,
        ConvocatoriaRepository $convocatoriaRepository
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

        // Obtener convocatorias activas del usuario
        $convocatorias = $user->getConvocatorias()->filter(function($convocatoria) {
            return $convocatoria->isActivo();
        })->toArray();

        // Obtener cantidad de exámenes para el ranking (por defecto 3)
        $cantidadRanking = $request->query->getInt('cantidad', 3);
        if ($cantidadRanking < 2) {
            $cantidadRanking = 2;
        }
        $rankings = [];
        $posicionesUsuario = [];
        $dificultades = ['facil', 'moderada', 'dificil'];
        
        // Rankings del temario general
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

        // Rankings por municipio
        $rankingsPorMunicipio = [];
        $posicionesPorMunicipio = [];
        $municipiosActivos = $user->getMunicipios();
        
        foreach ($municipiosActivos as $municipio) {
            if (!$municipio->isActivo()) {
                continue;
            }
            
            $rankingsMunicipio = [];
            $posicionesMunicipio = [];
            
            foreach ($dificultades as $dificultad) {
                $ranking = $examenRepository->getRankingPorMunicipioYDificultad($municipio, $dificultad, $cantidadRanking);
                $rankingsMunicipio[$dificultad] = $ranking;
                $posicion = $examenRepository->getPosicionUsuarioPorMunicipio($user, $municipio, $dificultad, $cantidadRanking);
                $notaMedia = $examenRepository->getNotaMediaUsuarioPorMunicipio($user, $municipio, $dificultad, $cantidadRanking);
                $posicionesMunicipio[$dificultad] = [
                    'posicion' => $posicion,
                    'notaMedia' => $notaMedia,
                    'totalUsuarios' => count($ranking),
                ];
            }
            
            $rankingsPorMunicipio[$municipio->getId()] = [
                'municipio' => $municipio,
                'rankings' => $rankingsMunicipio,
                'posiciones' => $posicionesMunicipio,
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
            'convocatorias' => $convocatorias,
            'posicionesUsuario' => $posicionesUsuario,
            'rankingsPorMunicipio' => $rankingsPorMunicipio,
            'cantidadRanking' => $cantidadRanking,
        ]);
    }
}

