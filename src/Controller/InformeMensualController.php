<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\ExamenRepository;
use App\Repository\ExamenSemanalRepository;
use App\Repository\TareaAsignadaRepository;
use App\Repository\PreguntaRepository;
use App\Repository\PreguntaMunicipalRepository;
use App\Repository\TemaRepository;
use App\Repository\TemaMunicipalRepository;
use App\Service\PreguntaRiesgoService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/informe-mensual')]
#[IsGranted('ROLE_PROFESOR')]
class InformeMensualController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private ExamenRepository $examenRepository,
        private ExamenSemanalRepository $examenSemanalRepository,
        private TareaAsignadaRepository $tareaAsignadaRepository,
        private PreguntaRepository $preguntaRepository,
        private PreguntaMunicipalRepository $preguntaMunicipalRepository,
        private TemaRepository $temaRepository,
        private TemaMunicipalRepository $temaMunicipalRepository,
        private PreguntaRiesgoService $preguntaRiesgoService
    ) {
    }

    #[Route('/', name: 'app_informe_mensual_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $usuarioActual = $this->getUser();
        $esAdmin = $this->isGranted('ROLE_ADMIN');
        
        // Obtener alumnos asignados si no es admin
        $alumnosIds = [];
        if (!$esAdmin) {
            $alumnosIds = array_map(function($alumno) {
                return $alumno->getId();
            }, $usuarioActual->getAlumnos()->toArray());
            
            if (empty($alumnosIds)) {
                $alumnosIds = [-1]; // No mostrar nada si no tiene alumnos
            }
        }

        // Parámetros de paginación
        $itemsPerPage = 20;
        $page = max(1, $request->query->getInt('page', 1));
        $search = $request->query->get('search', '');

        // Construir query para alumnos
        $qb = $this->userRepository->createQueryBuilder('u')
            ->where('u.activo = :activo')
            ->andWhere('u.roles NOT LIKE :roleProfesor')
            ->andWhere('u.roles NOT LIKE :roleAdmin')
            ->setParameter('activo', true)
            ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
            ->setParameter('roleAdmin', '%"ROLE_ADMIN"%');

        // Filtrar por alumnos asignados si no es admin
        if (!$esAdmin && !empty($alumnosIds)) {
            $qb->andWhere('u.id IN (:alumnosIds)')
               ->setParameter('alumnosIds', $alumnosIds);
        }

        // Filtro de búsqueda
        if (!empty($search)) {
            $qb->andWhere('(u.username LIKE :search OR u.nombre LIKE :search)')
               ->setParameter('search', '%' . $search . '%');
        }

        // Contar total
        $totalQb = clone $qb;
        $totalItems = (int) $totalQb->select('COUNT(u.id)')
                                   ->getQuery()
                                   ->getSingleScalarResult();

        // Aplicar paginación
        $offset = ($page - 1) * $itemsPerPage;
        $alumnos = $qb->orderBy('u.username', 'ASC')
                     ->setFirstResult($offset)
                     ->setMaxResults($itemsPerPage)
                     ->getQuery()
                     ->getResult();

        // Calcular total de páginas
        $totalPages = max(1, ceil($totalItems / $itemsPerPage));
        $page = min($page, $totalPages);

        // Obtener año actual para el selector de mes
        $anioActual = (int) date('Y');
        $mesActual = (int) date('m');

        return $this->render('informe_mensual/index.html.twig', [
            'alumnos' => $alumnos,
            'search' => $search,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'itemsPerPage' => $itemsPerPage,
            'anioActual' => $anioActual,
            'mesActual' => $mesActual,
        ]);
    }

    #[Route('/{id}/generar', name: 'app_informe_mensual_generar', methods: ['GET'])]
    public function generarInforme(User $alumno, Request $request): Response
    {
        $usuarioActual = $this->getUser();
        $esAdmin = $this->isGranted('ROLE_ADMIN');

        // Verificar que el profesor tiene acceso a este alumno
        if (!$esAdmin) {
            $alumnosIds = array_map(function($a) {
                return $a->getId();
            }, $usuarioActual->getAlumnos()->toArray());
            
            if (!in_array($alumno->getId(), $alumnosIds)) {
                $this->addFlash('error', 'No tienes acceso a este alumno.');
                return $this->redirectToRoute('app_informe_mensual_index');
            }
        }

        // Obtener mes y año (por defecto mes actual)
        $mes = $request->query->getInt('mes', (int) date('m'));
        $anio = $request->query->getInt('anio', (int) date('Y'));

        // Validar mes y año
        if ($mes < 1 || $mes > 12) {
            $this->addFlash('error', 'Mes inválido.');
            return $this->redirectToRoute('app_informe_mensual_index');
        }

        if ($anio < 2000 || $anio > 2100) {
            $this->addFlash('error', 'Año inválido.');
            return $this->redirectToRoute('app_informe_mensual_index');
        }

        // Obtener primer y último día del mes
        $primerDiaMes = new \DateTime("$anio-$mes-01");
        $ultimoDiaMes = clone $primerDiaMes;
        $ultimoDiaMes->modify('last day of this month');
        $ultimoDiaMes->setTime(23, 59, 59);

        // Obtener exámenes del mes
        $examenes = $this->examenRepository->createQueryBuilder('e')
            ->where('e.usuario = :usuario')
            ->andWhere('e.fecha >= :fechaInicio')
            ->andWhere('e.fecha <= :fechaFin')
            ->setParameter('usuario', $alumno)
            ->setParameter('fechaInicio', $primerDiaMes)
            ->setParameter('fechaFin', $ultimoDiaMes)
            ->orderBy('e.fecha', 'DESC')
            ->getQuery()
            ->getResult();

        // Calcular estadísticas de exámenes
        $totalExamenes = count($examenes);
        
        // Sumar todos los aciertos, errores y en blanco para calcular nota media
        $totalAciertos = array_sum(array_map(function($examen) {
            return $examen->getAciertos();
        }, $examenes));
        
        $totalErrores = array_sum(array_map(function($examen) {
            return $examen->getErrores();
        }, $examenes));
        
        $totalEnBlanco = array_sum(array_map(function($examen) {
            return $examen->getEnBlanco();
        }, $examenes));
        
        // Calcular nota media sumando aciertos, errores y en blanco (mismo método que rankings)
        $notaMedia = $this->examenRepository->calcularNotaMediaDesdeExamenes($examenes) ?? 0;
        
        // Para mejor y peor nota, usar las notas individuales de los exámenes
        $notas = array_map(function($examen) {
            return (float) $examen->getNota();
        }, $examenes);
        
        $mejorNota = $totalExamenes > 0 ? max($notas) : 0;
        $peorNota = $totalExamenes > 0 ? min($notas) : 0;

        // Obtener exámenes semanales realizados en el mes
        $examenesSemanales = $this->examenRepository->createQueryBuilder('e')
            ->where('e.usuario = :usuario')
            ->andWhere('e.examenSemanal IS NOT NULL')
            ->andWhere('e.fecha >= :fechaInicio')
            ->andWhere('e.fecha <= :fechaFin')
            ->setParameter('usuario', $alumno)
            ->setParameter('fechaInicio', $primerDiaMes)
            ->setParameter('fechaFin', $ultimoDiaMes)
            ->orderBy('e.fecha', 'DESC')
            ->getQuery()
            ->getResult();

        // Obtener tareas del mes
        $tareas = $this->tareaAsignadaRepository->createQueryBuilder('ta')
            ->join('ta.tarea', 't')
            ->where('ta.usuario = :usuario')
            ->andWhere('t.semanaAsignacion >= :fechaInicio')
            ->andWhere('t.semanaAsignacion <= :fechaFin')
            ->setParameter('usuario', $alumno)
            ->setParameter('fechaInicio', $primerDiaMes)
            ->setParameter('fechaFin', $ultimoDiaMes)
            ->orderBy('t.semanaAsignacion', 'DESC')
            ->getQuery()
            ->getResult();

        $tareasCompletadas = array_filter($tareas, function($tarea) {
            return $tarea->isCompletada();
        });
        $tareasPendientes = array_filter($tareas, function($tarea) {
            return !$tarea->isCompletada();
        });

        // Calcular los 3 temas con más fallos
        $temasConMasFallos = $this->calcularTemasConMasFallos($alumno);

        // Calcular estadísticas por dificultad
        $estadisticasPorDificultad = $this->calcularEstadisticasPorDificultad($examenes);

        // Calcular porcentajes de acierto por tema usando preguntas de riesgo
        $porcentajesPorTemaRaw = $this->preguntaRiesgoService->calcularPorcentajesPorTema($alumno);
        $porcentajesPorTemaMunicipalRaw = $this->preguntaRiesgoService->calcularPorcentajesPorTemaMunicipal($alumno);
        
        // Obtener temas para mapear IDs a nombres
        $temaIds = array_keys($porcentajesPorTemaRaw);
        $temasMap = [];
        if (!empty($temaIds)) {
            $temas = $this->temaRepository->findBy(['id' => $temaIds]);
            foreach ($temas as $tema) {
                $temasMap[$tema->getId()] = $tema;
            }
        }
        
        $porcentajesPorTema = [];
        foreach ($porcentajesPorTemaRaw as $temaId => $porcentaje) {
            if (isset($temasMap[$temaId])) {
                $tema = $temasMap[$temaId];
                $porcentajesPorTema[] = [
                    'tema' => $tema,
                    'nombre' => $tema->getNombre(),
                    'porcentaje' => $porcentaje,
                ];
            }
        }
        // Ordenar por porcentaje (ascendente - menores primero para ver qué necesita más atención)
        usort($porcentajesPorTema, fn($a, $b) => $a['porcentaje'] <=> $b['porcentaje']);
        
        // Obtener temas municipales para mapear IDs a nombres
        $temaMunicipalIds = array_keys($porcentajesPorTemaMunicipalRaw);
        $temasMunicipalesMap = [];
        if (!empty($temaMunicipalIds)) {
            $temasMunicipales = $this->temaMunicipalRepository->findBy(['id' => $temaMunicipalIds]);
            foreach ($temasMunicipales as $temaMunicipal) {
                $temasMunicipalesMap[$temaMunicipal->getId()] = $temaMunicipal;
            }
        }
        
        $porcentajesPorTemaMunicipal = [];
        foreach ($porcentajesPorTemaMunicipalRaw as $temaMunicipalId => $porcentaje) {
            if (isset($temasMunicipalesMap[$temaMunicipalId])) {
                $temaMunicipal = $temasMunicipalesMap[$temaMunicipalId];
                $porcentajesPorTemaMunicipal[] = [
                    'tema' => $temaMunicipal,
                    'nombre' => $temaMunicipal->getNombre(),
                    'porcentaje' => $porcentaje,
                ];
            }
        }
        // Ordenar por porcentaje (ascendente - menores primero)
        usort($porcentajesPorTemaMunicipal, fn($a, $b) => $a['porcentaje'] <=> $b['porcentaje']);

        // Agrupar exámenes por tipo
        $examenesGenerales = array_filter($examenes, function($examen) {
            return $examen->getMunicipio() === null && $examen->getConvocatoria() === null && $examen->getExamenSemanal() === null;
        });
        
        $examenesMunicipales = array_filter($examenes, function($examen) {
            return $examen->getMunicipio() !== null && $examen->getExamenSemanal() === null;
        });
        
        $examenesConvocatoria = array_filter($examenes, function($examen) {
            return $examen->getConvocatoria() !== null && $examen->getExamenSemanal() === null;
        });

        // Generar HTML para el PDF
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        $nombreMes = $meses[$mes] ?? 'Mes';

        $html = $this->renderView('informe_mensual/pdf.html.twig', [
            'alumno' => $alumno,
            'mes' => $mes,
            'anio' => $anio,
            'nombreMes' => $nombreMes,
            'primerDiaMes' => $primerDiaMes,
            'ultimoDiaMes' => $ultimoDiaMes,
            'examenes' => $examenes,
            'totalExamenes' => $totalExamenes,
            'notaMedia' => $notaMedia,
            'mejorNota' => $mejorNota,
            'peorNota' => $peorNota,
            'totalAciertos' => $totalAciertos,
            'totalErrores' => $totalErrores,
            'totalEnBlanco' => $totalEnBlanco,
            'examenesSemanales' => $examenesSemanales,
            'examenesGenerales' => $examenesGenerales,
            'examenesMunicipales' => $examenesMunicipales,
            'examenesConvocatoria' => $examenesConvocatoria,
            'tareas' => $tareas,
            'tareasCompletadas' => count($tareasCompletadas),
            'tareasPendientes' => count($tareasPendientes),
            'temasConMasFallos' => $temasConMasFallos,
            'estadisticasPorDificultad' => $estadisticasPorDificultad,
            'porcentajesPorTema' => $porcentajesPorTema,
            'porcentajesPorTemaMunicipal' => $porcentajesPorTemaMunicipal,
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
        $nombreArchivo = "Informe_Mensual_{$alumno->getUsername()}_{$nombreMes}_{$anio}.pdf";

        // Retornar PDF
        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $nombreArchivo . '"',
            ]
        );
    }

    /**
     * Calcula los 3 temas con más fallos del alumno basándose en todos sus exámenes
     * @return array Array con los 3 temas, cada uno con 'tema', 'nombre', 'fallos'
     */
    private function calcularTemasConMasFallos(User $alumno): array
    {
        // Obtener todos los exámenes del alumno (no solo del mes)
        $todosExamenes = $this->examenRepository->createQueryBuilder('e')
            ->where('e.usuario = :usuario')
            ->setParameter('usuario', $alumno)
            ->getQuery()
            ->getResult();

        // Contador de fallos por tema
        $fallosPorTema = [];
        
        // Agrupar preguntas por tipo (municipal vs general) para optimizar consultas
        $preguntasIdsGenerales = [];
        $preguntasIdsMunicipales = [];
        $mapaRespuestas = []; // preguntaId => [examen, respuestas, esMunicipal]

        // Primera pasada: agrupar IDs de preguntas
        foreach ($todosExamenes as $examen) {
            $preguntasIds = $examen->getPreguntasIds() ?? [];
            $respuestas = $examen->getRespuestas() ?? [];
            $municipio = $examen->getMunicipio();
            $esMunicipal = $municipio !== null;

            if (empty($preguntasIds)) {
                continue;
            }

            foreach ($preguntasIds as $preguntaId) {
                $mapaRespuestas[$preguntaId] = [
                    'examen' => $examen,
                    'respuestas' => $respuestas,
                    'esMunicipal' => $esMunicipal,
                ];

                if ($esMunicipal) {
                    if (!in_array($preguntaId, $preguntasIdsMunicipales)) {
                        $preguntasIdsMunicipales[] = $preguntaId;
                    }
                } else {
                    if (!in_array($preguntaId, $preguntasIdsGenerales)) {
                        $preguntasIdsGenerales[] = $preguntaId;
                    }
                }
            }
        }

        // Obtener todas las preguntas generales de una vez
        $preguntasGeneralesMap = [];
        if (!empty($preguntasIdsGenerales)) {
            $preguntasGenerales = $this->preguntaRepository->findByIds($preguntasIdsGenerales);
            foreach ($preguntasGenerales as $pregunta) {
                $preguntasGeneralesMap[$pregunta->getId()] = $pregunta;
            }
        }

        // Obtener todas las preguntas municipales de una vez
        $preguntasMunicipalesMap = [];
        if (!empty($preguntasIdsMunicipales)) {
            $preguntasMunicipales = $this->preguntaMunicipalRepository->findByIds($preguntasIdsMunicipales);
            foreach ($preguntasMunicipales as $pregunta) {
                $preguntasMunicipalesMap[$pregunta->getId()] = $pregunta;
            }
        }

        // Procesar cada pregunta y contar fallos
        foreach ($mapaRespuestas as $preguntaId => $datos) {
            $respuestas = $datos['respuestas'];
            $esMunicipal = $datos['esMunicipal'];

            // Obtener la pregunta del mapa correspondiente
            if ($esMunicipal) {
                $pregunta = $preguntasMunicipalesMap[$preguntaId] ?? null;
            } else {
                $pregunta = $preguntasGeneralesMap[$preguntaId] ?? null;
            }

            if (!$pregunta) {
                continue;
            }

            // Obtener el tema
            if ($esMunicipal) {
                $tema = $pregunta->getTemaMunicipal();
                $temaId = $tema ? $tema->getId() : null;
                $temaNombre = $tema ? $tema->getNombre() : 'Sin tema';
            } else {
                $tema = $pregunta->getTema();
                $temaId = $tema ? $tema->getId() : null;
                $temaNombre = $tema ? $tema->getNombre() : 'Sin tema';
            }

            if (!$temaId) {
                continue;
            }

            // Verificar si la respuesta es incorrecta
            $respuestaAlumno = $respuestas[$preguntaId] ?? null;
            
            // Solo contar como fallo si hay respuesta y es incorrecta (no contar en blanco)
            if ($respuestaAlumno !== null && $respuestaAlumno !== '') {
                $respuestaCorrecta = $pregunta->getRespuestaCorrecta();
                if (strtoupper(trim($respuestaAlumno)) !== strtoupper(trim($respuestaCorrecta ?? ''))) {
                    // Es un error, contar el fallo para este tema
                    // Usar clave única para temas generales vs municipales
                    $claveTema = ($esMunicipal ? 'm_' : 'g_') . $temaId;
                    
                    if (!isset($fallosPorTema[$claveTema])) {
                        $fallosPorTema[$claveTema] = [
                            'tema' => $tema,
                            'nombre' => $temaNombre,
                            'fallos' => 0,
                        ];
                    }
                    $fallosPorTema[$claveTema]['fallos']++;
                }
            }
        }

        // Ordenar por número de fallos (descendente) y tomar los 3 primeros
        usort($fallosPorTema, function($a, $b) {
            return $b['fallos'] <=> $a['fallos'];
        });

        return array_slice($fallosPorTema, 0, 3);
    }

    /**
     * Calcula estadísticas de exámenes por nivel de dificultad
     * @param array $examenes Array de exámenes
     * @return array Array con estadísticas por dificultad ['facil' => [...], 'moderada' => [...], 'dificil' => [...]]
     */
    private function calcularEstadisticasPorDificultad(array $examenes): array
    {
        $estadisticas = [
            'facil' => ['total' => 0, 'notaMedia' => 0, 'totalAciertos' => 0, 'totalErrores' => 0, 'totalEnBlanco' => 0],
            'moderada' => ['total' => 0, 'notaMedia' => 0, 'totalAciertos' => 0, 'totalErrores' => 0, 'totalEnBlanco' => 0],
            'dificil' => ['total' => 0, 'notaMedia' => 0, 'totalAciertos' => 0, 'totalErrores' => 0, 'totalEnBlanco' => 0],
        ];

        foreach ($examenes as $examen) {
            $dificultad = strtolower($examen->getDificultad() ?? '');
            
            if (!isset($estadisticas[$dificultad])) {
                continue;
            }

            $estadisticas[$dificultad]['total']++;
            $estadisticas[$dificultad]['totalAciertos'] += $examen->getAciertos();
            $estadisticas[$dificultad]['totalErrores'] += $examen->getErrores();
            $estadisticas[$dificultad]['totalEnBlanco'] += $examen->getEnBlanco();
        }

        // Calcular nota media por dificultad usando el mismo método que rankings
        foreach (['facil', 'moderada', 'dificil'] as $dificultad) {
            $examenesDificultad = array_filter($examenes, function($examen) use ($dificultad) {
                return strtolower($examen->getDificultad() ?? '') === $dificultad;
            });
            
            if (!empty($examenesDificultad)) {
                $notaMedia = $this->examenRepository->calcularNotaMediaDesdeExamenes(array_values($examenesDificultad)) ?? 0;
                $estadisticas[$dificultad]['notaMedia'] = round($notaMedia, 2);
            }
        }

        return $estadisticas;
    }
}
