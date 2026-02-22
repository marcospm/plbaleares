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
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private ?CacheItemPoolInterface $cache = null
    ) {
    }

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
            // Determinar si es admin (ve todos los alumnos) o profesor (solo sus alumnos)
            $esAdmin = $this->isGranted('ROLE_ADMIN');
            $alumnosIds = [];
            
            if (!$esAdmin) {
                // Si es profesor, obtener solo sus alumnos asignados con eager loading
                $userWithAlumnos = $userRepository->createQueryBuilder('u')
                    ->leftJoin('u.alumnos', 'a')
                    ->addSelect('a')
                    ->where('u.id = :userId')
                    ->setParameter('userId', $user->getId())
                    ->getQuery()
                    ->getOneOrNullResult();
                
                $alumnosIds = array_map(function($alumno) {
                    return $alumno->getId();
                }, $userWithAlumnos ? $userWithAlumnos->getAlumnos()->toArray() : []);
                
                if (empty($alumnosIds)) {
                    // Si no tiene alumnos asignados, usar un ID que no existe para que no muestre nada
                    $alumnosIds = [-1];
                }
            }

            // Estadísticas de alumnos
            $qbAlumnos = $userRepository->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u.activo = :activo')
                ->andWhere('u.eliminado = :eliminado')
                ->andWhere('u.roles NOT LIKE :roleProfesor')
                ->andWhere('u.roles NOT LIKE :roleAdmin')
                ->setParameter('activo', true)
                ->setParameter('eliminado', false)
                ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
                ->setParameter('roleAdmin', '%"ROLE_ADMIN"%');
            
            if (!$esAdmin && !empty($alumnosIds)) {
                $qbAlumnos->andWhere('u.id IN (:alumnosIds)')
                    ->setParameter('alumnosIds', $alumnosIds);
            }
            
            $totalAlumnos = $qbAlumnos->getQuery()->getSingleScalarResult();

            // Estadísticas de exámenes
            $qbExamenes = $examenRepository->createQueryBuilder('e')
                ->select('COUNT(e.id)');
            
            if (!$esAdmin && !empty($alumnosIds)) {
                $qbExamenes->join('e.usuario', 'u')
                    ->where('u.id IN (:alumnosIds)')
                    ->setParameter('alumnosIds', $alumnosIds);
            }
            
            $totalExamenes = $qbExamenes->getQuery()->getSingleScalarResult();

            $qbPromedio = $examenRepository->createQueryBuilder('e')
                ->select('AVG(e.nota)');
            
            if (!$esAdmin && !empty($alumnosIds)) {
                $qbPromedio->join('e.usuario', 'u')
                    ->where('u.id IN (:alumnosIds)')
                    ->setParameter('alumnosIds', $alumnosIds);
            }
            
            $promedioGeneral = $qbPromedio->getQuery()->getSingleScalarResult();

            $hoy = new \DateTime('today');
            $qbExamenesHoy = $examenRepository->createQueryBuilder('e')
                ->select('COUNT(e.id)')
                ->where('e.fecha >= :hoy')
                ->setParameter('hoy', $hoy);
            
            if (!$esAdmin && !empty($alumnosIds)) {
                $qbExamenesHoy->join('e.usuario', 'u')
                    ->andWhere('u.id IN (:alumnosIds)')
                    ->setParameter('alumnosIds', $alumnosIds);
            }
            
            $examenesHoy = $qbExamenesHoy->getQuery()->getSingleScalarResult();

            $semanaPasada = new \DateTime('-7 days');
            $qbExamenesSemana = $examenRepository->createQueryBuilder('e')
                ->select('COUNT(e.id)')
                ->where('e.fecha >= :semanaPasada')
                ->setParameter('semanaPasada', $semanaPasada);
            
            if (!$esAdmin && !empty($alumnosIds)) {
                $qbExamenesSemana->join('e.usuario', 'u')
                    ->andWhere('u.id IN (:alumnosIds)')
                    ->setParameter('alumnosIds', $alumnosIds);
            }
            
            $examenesSemana = $qbExamenesSemana->getQuery()->getSingleScalarResult();

            // Estadísticas de contenido (con caché - 1 hora)
            $cacheKey = 'dashboard_metrics_global';
            $metrics = null;
            
            if ($this->cache) {
                $item = $this->cache->getItem($cacheKey);
                if ($item->isHit()) {
                    $metrics = $item->get();
                }
            }
            
            if ($metrics === null) {
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
                
                $metrics = [
                    'totalTemas' => $totalTemas,
                    'totalPreguntas' => $totalPreguntas,
                    'totalPreguntasMunicipales' => $totalPreguntasMunicipales,
                    'totalArticulos' => $totalArticulos,
                    'totalMunicipios' => $totalMunicipios,
                    'totalTemasMunicipales' => $totalTemasMunicipales,
                    'totalConvocatorias' => $totalConvocatorias,
                ];
                
                // Guardar en caché (1 hora)
                if ($this->cache) {
                    $item->set($metrics);
                    $item->expiresAfter(3600); // 1 hora
                    $this->cache->save($item);
                }
            } else {
                $totalTemas = $metrics['totalTemas'];
                $totalPreguntas = $metrics['totalPreguntas'];
                $totalPreguntasMunicipales = $metrics['totalPreguntasMunicipales'];
                $totalArticulos = $metrics['totalArticulos'];
                $totalMunicipios = $metrics['totalMunicipios'];
                $totalTemasMunicipales = $metrics['totalTemasMunicipales'];
                $totalConvocatorias = $metrics['totalConvocatorias'];
            }

            // Últimos exámenes realizados (con caché - 5 minutos)
            $cacheKeyUltimosExamenes = 'dashboard_ultimos_examenes_' . ($esAdmin ? 'admin' : 'prof_' . $user->getId());
            $ultimosExamenes = null;
            
            if ($this->cache) {
                $item = $this->cache->getItem($cacheKeyUltimosExamenes);
                if ($item->isHit()) {
                    $ultimosExamenes = $item->get();
                }
            }
            
            if ($ultimosExamenes === null) {
                $qbUltimosExamenes = $examenRepository->createQueryBuilder('e')
                    ->leftJoin('e.usuario', 'u')
                    ->addSelect('u')
                    ->where('u.roles NOT LIKE :roleProfesor')
                    ->andWhere('u.roles NOT LIKE :roleAdmin')
                    ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
                    ->setParameter('roleAdmin', '%"ROLE_ADMIN"%');
                
                if (!$esAdmin && !empty($alumnosIds)) {
                    $qbUltimosExamenes->andWhere('u.id IN (:alumnosIds)')
                        ->setParameter('alumnosIds', $alumnosIds);
                }
                
                $ultimosExamenes = $qbUltimosExamenes->orderBy('e.fecha', 'DESC')
                    ->setMaxResults(10)
                    ->getQuery()
                    ->getResult();
                
                // Guardar en caché (5 minutos)
                if ($this->cache) {
                    $item->set($ultimosExamenes);
                    $item->expiresAfter(300); // 5 minutos
                    $this->cache->save($item);
                }
            }

            // Alumnos más activos (con caché - 15 minutos)
            $cacheKeyAlumnosActivos = 'dashboard_alumnos_activos_' . ($esAdmin ? 'admin' : 'prof_' . $user->getId());
            $alumnosActivos = null;
            
            if ($this->cache) {
                $item = $this->cache->getItem($cacheKeyAlumnosActivos);
                if ($item->isHit()) {
                    $alumnosActivos = $item->get();
                }
            }
            
            if ($alumnosActivos === null) {
                $qbAlumnosActivos = $examenRepository->createQueryBuilder('e')
                    ->select('u.id, u.username, COUNT(e.id) as totalExamenes, AVG(e.nota) as promedio')
                    ->join('e.usuario', 'u')
                    ->where('u.activo = :activo')
                    ->andWhere('u.eliminado = :eliminado')
                    ->andWhere('u.roles NOT LIKE :roleProfesor')
                    ->andWhere('u.roles NOT LIKE :roleAdmin')
                    ->setParameter('activo', true)
                    ->setParameter('eliminado', false)
                    ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
                    ->setParameter('roleAdmin', '%"ROLE_ADMIN"%');
                
                if (!$esAdmin && !empty($alumnosIds)) {
                    $qbAlumnosActivos->andWhere('u.id IN (:alumnosIds)')
                        ->setParameter('alumnosIds', $alumnosIds);
                }
                
                $alumnosActivos = $qbAlumnosActivos->groupBy('u.id', 'u.username')
                    ->orderBy('totalExamenes', 'DESC')
                    ->setMaxResults(5)
                    ->getQuery()
                    ->getResult();
                
                // Guardar en caché (15 minutos)
                if ($this->cache) {
                    $item->set($alumnosActivos);
                    $item->expiresAfter(900); // 15 minutos
                    $this->cache->save($item);
                }
            }

            // Obtener lista de alumnos asignados para mostrar en el dashboard
            $misAlumnos = [];
            if (!$esAdmin) {
                // Usar el usuario con alumnos ya cargados
                $misAlumnos = $userWithAlumnos ? $userWithAlumnos->getAlumnos()->toArray() : [];
            } else {
                // Si es admin, obtener todos los alumnos
                $misAlumnos = $userRepository->createQueryBuilder('u')
                    ->where('u.activo = :activo')
                    ->andWhere('u.eliminado = :eliminado')
                    ->andWhere('u.roles NOT LIKE :roleProfesor')
                    ->andWhere('u.roles NOT LIKE :roleAdmin')
                    ->setParameter('activo', true)
                    ->setParameter('eliminado', false)
                    ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
                    ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
                    ->orderBy('u.username', 'ASC')
                    ->getQuery()
                    ->getResult();
            }

            return $this->render('dashboard/index.html.twig', [
                'isProfesor' => true,
                'esAdmin' => $esAdmin,
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
                'misAlumnos' => $misAlumnos,
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
        
        // Obtener actividades de hoy y mañana
        $hoy = new \DateTime('today');
        $manana = clone $hoy;
        $manana->modify('+1 day');
        
        $franjasHoy = $planificacionService->obtenerFranjasDelDia($user, $hoy);
        $franjasManana = $planificacionService->obtenerFranjasDelDia($user, $manana);
        
        // Ordenar por hora de inicio
        usort($franjasHoy, function($a, $b) {
            return $a->getHoraInicio() <=> $b->getHoraInicio();
        });
        usort($franjasManana, function($a, $b) {
            return $a->getHoraInicio() <=> $b->getHoraInicio();
        });
        
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

        // Obtener convocatorias activas del usuario con eager loading
        $userWithConvocatorias = $userRepository->createQueryBuilder('u')
            ->leftJoin('u.convocatorias', 'c')
            ->addSelect('c')
            ->where('u.id = :userId')
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getOneOrNullResult();
        
        $convocatorias = $userWithConvocatorias ? $userWithConvocatorias->getConvocatorias()->filter(function($convocatoria) {
            return $convocatoria->isActivo();
        })->toArray() : [];

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

        // Rankings por convocatoria
        $rankingsPorConvocatoria = [];
        
        foreach ($convocatorias as $convocatoria) {
            $rankingsConvocatoria = [];
            $posicionesConvocatoria = [];
            
            // Rankings generales de la convocatoria
            foreach ($dificultades as $dificultad) {
                $ranking = $examenRepository->getRankingPorConvocatoriaYDificultad($convocatoria, $dificultad, $cantidadRanking);
                $rankingsConvocatoria[$dificultad] = $ranking;
                $posicion = $examenRepository->getPosicionUsuarioPorConvocatoria($user, $convocatoria, $dificultad, $cantidadRanking);
                $notaMedia = $examenRepository->getNotaMediaUsuarioPorConvocatoria($user, $convocatoria, $dificultad, $cantidadRanking);
                $posicionesConvocatoria[$dificultad] = [
                    'posicion' => $posicion,
                    'notaMedia' => $notaMedia,
                    'totalUsuarios' => count($ranking),
                ];
            }
            
            // Rankings por municipio dentro de la convocatoria (solo si tiene más de un municipio)
            $rankingsPorMunicipioConvocatoria = [];
            $municipiosConvocatoria = $convocatoria->getMunicipios();
            
            if ($municipiosConvocatoria->count() > 1) {
                foreach ($municipiosConvocatoria as $municipio) {
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
                    
                    $rankingsPorMunicipioConvocatoria[$municipio->getId()] = [
                        'municipio' => $municipio,
                        'rankings' => $rankingsMunicipio,
                        'posiciones' => $posicionesMunicipio,
                    ];
                }
            }
            
            $rankingsPorConvocatoria[$convocatoria->getId()] = [
                'convocatoria' => $convocatoria,
                'rankings' => $rankingsConvocatoria,
                'posiciones' => $posicionesConvocatoria,
                'rankingsPorMunicipio' => $rankingsPorMunicipioConvocatoria,
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
            'rankingsPorConvocatoria' => $rankingsPorConvocatoria,
            'cantidadRanking' => $cantidadRanking,
            'franjasHoy' => $franjasHoy,
            'franjasManana' => $franjasManana,
            'hoy' => $hoy,
            'manana' => $manana,
        ]);
    }
}

