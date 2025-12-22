<?php

namespace App\Controller;

use App\Repository\ExamenRepository;
use App\Repository\PlanificacionPersonalizadaRepository;
use App\Repository\TareaAsignadaRepository;
use App\Repository\MunicipioRepository;
use App\Repository\ConvocatoriaRepository;
use App\Repository\UserRepository;
use App\Repository\TemaRepository;
use App\Repository\PreguntaRepository;
use App\Repository\PreguntaMunicipalRepository;
use App\Repository\ArticuloRepository;
use App\Repository\TemaMunicipalRepository;
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
        ConvocatoriaRepository $convocatoriaRepository,
        UserRepository $userRepository,
        TemaRepository $temaRepository,
        PreguntaRepository $preguntaRepository,
        PreguntaMunicipalRepository $preguntaMunicipalRepository,
        ArticuloRepository $articuloRepository,
        TemaMunicipalRepository $temaMunicipalRepository
    ): Response {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_home');
        }

        // Si es profesor, mostrar dashboard de profesor
        if ($this->isGranted('ROLE_PROFESOR') || $this->isGranted('ROLE_ADMIN')) {
            // Estadísticas de alumnos
            $totalAlumnos = $userRepository->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u.activo = :activo')
                ->andWhere('u.roles NOT LIKE :roleProfesor')
                ->andWhere('u.roles NOT LIKE :roleAdmin')
                ->setParameter('activo', true)
                ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
                ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
                ->getQuery()
                ->getSingleScalarResult();

            // Estadísticas de exámenes
            $totalExamenes = $examenRepository->createQueryBuilder('e')
                ->select('COUNT(e.id)')
                ->getQuery()
                ->getSingleScalarResult();

            $promedioGeneral = $examenRepository->createQueryBuilder('e')
                ->select('AVG(e.nota)')
                ->getQuery()
                ->getSingleScalarResult();

            $hoy = new \DateTime('today');
            $examenesHoy = $examenRepository->createQueryBuilder('e')
                ->select('COUNT(e.id)')
                ->where('e.fecha >= :hoy')
                ->setParameter('hoy', $hoy)
                ->getQuery()
                ->getSingleScalarResult();

            $semanaPasada = new \DateTime('-7 days');
            $examenesSemana = $examenRepository->createQueryBuilder('e')
                ->select('COUNT(e.id)')
                ->where('e.fecha >= :semanaPasada')
                ->setParameter('semanaPasada', $semanaPasada)
                ->getQuery()
                ->getSingleScalarResult();

            // Estadísticas de contenido
            $totalTemas = $temaRepository->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->where('t.activo = :activo')
                ->setParameter('activo', true)
                ->getQuery()
                ->getSingleScalarResult();

            $totalPreguntas = $preguntaRepository->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->where('p.activo = :activo')
                ->setParameter('activo', true)
                ->getQuery()
                ->getSingleScalarResult();

            $totalPreguntasMunicipales = $preguntaMunicipalRepository->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->where('p.activo = :activo')
                ->setParameter('activo', true)
                ->getQuery()
                ->getSingleScalarResult();

            $totalArticulos = $articuloRepository->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->getQuery()
                ->getSingleScalarResult();

            $totalMunicipios = $municipioRepository->createQueryBuilder('m')
                ->select('COUNT(m.id)')
                ->where('m.activo = :activo')
                ->setParameter('activo', true)
                ->getQuery()
                ->getSingleScalarResult();

            $totalTemasMunicipales = $temaMunicipalRepository->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->where('t.activo = :activo')
                ->setParameter('activo', true)
                ->getQuery()
                ->getSingleScalarResult();

            $totalConvocatorias = $convocatoriaRepository->createQueryBuilder('c')
                ->select('COUNT(c.id)')
                ->where('c.activo = :activo')
                ->setParameter('activo', true)
                ->getQuery()
                ->getSingleScalarResult();

            // Últimos exámenes realizados
            $ultimosExamenes = $examenRepository->createQueryBuilder('e')
                ->join('e.usuario', 'u')
                ->where('u.roles NOT LIKE :roleProfesor')
                ->andWhere('u.roles NOT LIKE :roleAdmin')
                ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
                ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
                ->orderBy('e.fecha', 'DESC')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();

            // Alumnos más activos (top 5 por cantidad de exámenes)
            $alumnosActivos = $examenRepository->createQueryBuilder('e')
                ->select('u.id, u.username, COUNT(e.id) as totalExamenes, AVG(e.nota) as promedio')
                ->join('e.usuario', 'u')
                ->where('u.activo = :activo')
                ->andWhere('u.roles NOT LIKE :roleProfesor')
                ->andWhere('u.roles NOT LIKE :roleAdmin')
                ->setParameter('activo', true)
                ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
                ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
                ->groupBy('u.id', 'u.username')
                ->orderBy('totalExamenes', 'DESC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();

            return $this->render('dashboard/index.html.twig', [
                'isProfesor' => true,
                'totalAlumnos' => $totalAlumnos,
                'totalExamenes' => $totalExamenes,
                'promedioGeneral' => $promedioGeneral ? round((float) $promedioGeneral, 2) : 0,
                'examenesHoy' => $examenesHoy,
                'examenesSemana' => $examenesSemana,
                'totalTemas' => $totalTemas,
                'totalPreguntas' => $totalPreguntas + $totalPreguntasMunicipales,
                'totalArticulos' => $totalArticulos,
                'totalMunicipios' => $totalMunicipios,
                'totalTemasMunicipales' => $totalTemasMunicipales,
                'totalConvocatorias' => $totalConvocatorias,
                'ultimosExamenes' => $ultimosExamenes,
                'alumnosActivos' => $alumnosActivos,
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

