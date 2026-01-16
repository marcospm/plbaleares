<?php

namespace App\Controller;

use App\Entity\Examen;
use App\Entity\ExamenSemanal;
use App\Entity\Pregunta;
use App\Repository\ExamenSemanalRepository;
use App\Repository\ExamenRepository;
use App\Repository\ExamenBorradorRepository;
use App\Repository\PreguntaRepository;
use App\Repository\PreguntaMunicipalRepository;
use App\Repository\TemaRepository;
use App\Repository\TemaMunicipalRepository;
use App\Repository\MunicipioRepository;
use App\Entity\ExamenBorrador;
use App\Form\ExamenSemanalPDFType;
use App\Repository\ConfiguracionExamenRepository;
use App\Service\NotificacionService;
use App\Service\ConfiguracionExamenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/examen-semanal-alumno')]
#[IsGranted('ROLE_USER')]
class ExamenSemanalAlumnoController extends AbstractController
{
    public function __construct(
        private ExamenSemanalRepository $examenSemanalRepository,
        private ExamenRepository $examenRepository,
        private ExamenBorradorRepository $examenBorradorRepository,
        private PreguntaRepository $preguntaRepository,
        private PreguntaMunicipalRepository $preguntaMunicipalRepository,
        private TemaRepository $temaRepository,
        private TemaMunicipalRepository $temaMunicipalRepository,
        private MunicipioRepository $municipioRepository,
        private ConfiguracionExamenRepository $configuracionExamenRepository,
        private ConfiguracionExamenService $configuracionExamenService,
        private EntityManagerInterface $entityManager,
        private NotificacionService $notificacionService
    ) {
    }

    #[Route('/', name: 'app_examen_semanal_alumno_index', methods: ['GET'])]
    public function index(): Response
    {
        // Verificar que no sea profesor ni admin
        if ($this->isGranted('ROLE_PROFESOR') || $this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Esta ruta es solo para alumnos.');
        }

        $alumno = $this->getUser();
        $ahora = new \DateTime();
        
        // Obtener grupos del alumno
        $gruposAlumno = $alumno->getGrupos();
        $gruposIds = array_map(fn($g) => $g->getId(), $gruposAlumno->toArray());

        // Obtener todos los exámenes semanales activos que aún no han cerrado
        // Filtrar: mostrar exámenes sin grupo (para todos) o exámenes del grupo del alumno
        $qb = $this->examenSemanalRepository->createQueryBuilder('e')
            ->where('e.activo = :activo')
            ->andWhere('e.fechaCierre >= :ahora')
            ->andWhere('(e.grupo IS NULL OR e.grupo IN (:gruposIds))')
            ->setParameter('activo', true)
            ->setParameter('ahora', $ahora)
            ->setParameter('gruposIds', !empty($gruposIds) ? $gruposIds : [-1]) // Si no tiene grupos, usar ID inexistente
            ->orderBy('e.fechaApertura', 'DESC');
        
        $todosExamenes = $qb->getQuery()->getResult();

        // Obtener exámenes semanales ya realizados por el alumno
        $examenesCompletados = $this->examenRepository->createQueryBuilder('e')
            ->where('e.usuario = :usuario')
            ->andWhere('e.examenSemanal IS NOT NULL')
            ->setParameter('usuario', $alumno)
            ->getQuery()
            ->getResult();
        
        $examenesRealizadosIds = [];
        $examenesPDFPorSemanal = []; // Mapa de examenSemanalId => Examen (si es PDF)
        foreach ($examenesCompletados as $examen) {
            if ($examen->getExamenSemanal()) {
                $examenSemanalId = $examen->getExamenSemanal()->getId();
                // Solo considerar como "realizado" si NO es PDF (los PDF se pueden editar)
                if (!$examen->isRealizadoEnPDF()) {
                    $examenesRealizadosIds[] = $examenSemanalId;
                } else {
                    // Guardar el examen PDF para poder editarlo
                    $examenesPDFPorSemanal[$examenSemanalId] = $examen;
                }
            }
        }

        // Filtrar solo exámenes pendientes (no realizados online)
        $examenesDisponibles = array_filter($todosExamenes, function($examen) use ($examenesRealizadosIds) {
            return !in_array($examen->getId(), $examenesRealizadosIds);
        });

        // Separar TODOS los exámenes por tipo (no solo uno de cada tipo)
        $examenesGenerales = [];
        $examenesMunicipales = [];
        $examenesConvocatorias = [];

        foreach ($examenesDisponibles as $examen) {
            if ($examen->getConvocatoria() !== null) {
                // Es examen de convocatoria
                $examenesConvocatorias[] = $examen;
            } elseif ($examen->getMunicipio() !== null) {
                // Es examen municipal
                $examenesMunicipales[] = $examen;
            } else {
                // Es examen general
                $examenesGenerales[] = $examen;
            }
        }

        // Ordenar por fecha de apertura descendente (más recientes primero)
        usort($examenesGenerales, fn($a, $b) => $b->getFechaApertura() <=> $a->getFechaApertura());
        usort($examenesMunicipales, fn($a, $b) => $b->getFechaApertura() <=> $a->getFechaApertura());
        usort($examenesConvocatorias, fn($a, $b) => $b->getFechaApertura() <=> $a->getFechaApertura());

        // Obtener todos los borradores del alumno para los exámenes disponibles
        $borradoresSemanales = [];
        $examenesIds = array_map(fn($e) => $e->getId(), $examenesDisponibles);
        if (!empty($examenesIds)) {
            $borradores = $this->examenBorradorRepository->createQueryBuilder('b')
                ->where('b.usuario = :usuario')
                ->andWhere('b.examenSemanal IN (:examenesIds)')
                ->setParameter('usuario', $alumno)
                ->setParameter('examenesIds', $examenesIds)
                ->getQuery()
                ->getResult();
            
            foreach ($borradores as $borrador) {
                if ($borrador->getExamenSemanal()) {
                    $borradoresSemanales[$borrador->getExamenSemanal()->getId()] = [
                        'preguntaActual' => $borrador->getPreguntaActual(),
                        'preguntasIds' => $borrador->getPreguntasIds(),
                        'respuestas' => $borrador->getRespuestas(),
                        'tiempoRestante' => $borrador->getTiempoRestante(),
                    ];
                }
            }
        }

        // Obtener histórico de exámenes semanales realizados
        $examenesRealizados = $this->examenRepository->createQueryBuilder('e')
            ->where('e.usuario = :usuario')
            ->andWhere('e.examenSemanal IS NOT NULL')
            ->setParameter('usuario', $alumno)
            ->orderBy('e.fecha', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('examen_semanal_alumno/index.html.twig', [
            'examenesGenerales' => $examenesGenerales,
            'examenesMunicipales' => $examenesMunicipales,
            'examenesConvocatorias' => $examenesConvocatorias,
            'examenesRealizados' => $examenesRealizados,
            'examenesPDFPorSemanal' => $examenesPDFPorSemanal,
            'borradoresSemanales' => $borradoresSemanales,
        ]);
    }

    #[Route('/{id}/realizar', name: 'app_examen_semanal_alumno_realizar', methods: ['GET', 'POST'])]
    public function realizar(Request $request, ExamenSemanal $examenSemanal, SessionInterface $session): Response
    {
        // Verificar que no sea profesor ni admin
        if ($this->isGranted('ROLE_PROFESOR') || $this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Esta ruta es solo para alumnos.');
        }

        $alumno = $this->getUser();

        // Verificar que el examen esté disponible
        if (!$examenSemanal->estaDisponible()) {
            $this->addFlash('error', 'Este examen no está disponible en este momento.');
            return $this->redirectToRoute('app_examen_semanal_alumno_index');
        }

        // Verificar que el alumno no haya realizado ya este examen
        $examenRealizado = $this->examenRepository->createQueryBuilder('e')
            ->where('e.usuario = :usuario')
            ->andWhere('e.examenSemanal = :examenSemanal')
            ->setParameter('usuario', $alumno)
            ->setParameter('examenSemanal', $examenSemanal)
            ->getQuery()
            ->getOneOrNullResult();
        
        if ($examenRealizado) {
            // Si ya tiene un examen realizado en PDF, no puede hacer el online
            if ($examenRealizado->isRealizadoEnPDF()) {
                $this->addFlash('error', 'Ya has realizado este examen en formato PDF. Puedes editar los resultados desde la lista de exámenes realizados.');
                return $this->redirectToRoute('app_examen_semanal_alumno_index');
            }
            // Si ya tiene un examen online, no puede hacerlo de nuevo
            $this->addFlash('error', 'Ya has realizado este examen semanal.');
            return $this->redirectToRoute('app_examen_semanal_alumno_index');
        }

        // Verificar si hay un borrador para este examen semanal
        $borrador = $this->examenBorradorRepository->findOneByUsuarioAndExamenSemanal($alumno, $examenSemanal);
        
        // Si hay borrador, restaurar desde él
        if ($borrador) {
            $session->set('examen_preguntas', $borrador->getPreguntasIds());
            $session->set('examen_respuestas', $borrador->getRespuestas());
            $session->set('examen_config', $borrador->getConfig());
            $session->set('examen_pregunta_actual', $borrador->getPreguntaActual());
            $session->set('examen_tiempo_restante', $borrador->getTiempoRestante());
            
            return $this->redirectToRoute('app_examen_pregunta', ['numero' => $borrador->getPreguntaActual()]);
        }
        
        // Limpiar sesión de examen anterior si existe
        $session->remove('examen_preguntas');
        $session->remove('examen_respuestas');
        $session->remove('examen_config');
        $session->remove('examen_pregunta_actual');

        // Obtener preguntas según el modo de creación del examen
        $preguntas = [];
        $esMunicipal = $examenSemanal->getMunicipio() !== null;
        $esConvocatoria = $examenSemanal->getConvocatoria() !== null;

        if ($examenSemanal->getModoCreacion() === 'preguntas_especificas') {
            // Examen con preguntas específicas (creadas al vuelo)
            if ($esMunicipal || $esConvocatoria) {
                $preguntas = $examenSemanal->getPreguntasMunicipales()->toArray();
                // Filtrar solo preguntas activas
                $preguntas = array_filter($preguntas, function($p) {
                    return $p->isActivo();
                });
            } else {
                $preguntas = $examenSemanal->getPreguntas()->toArray();
                // Filtrar solo preguntas activas
                $preguntas = array_filter($preguntas, function($p) {
                    return $p->isActivo();
                });
            }
            
            // Reindexar el array después de filtrar
            $preguntas = array_values($preguntas);
        } else {
            // Examen con preguntas por temas (método tradicional)
            if ($esMunicipal || $esConvocatoria) {
                $temasMunicipales = $examenSemanal->getTemasMunicipales();
                if ($temasMunicipales->isEmpty()) {
                    $this->addFlash('error', 'Este examen semanal no tiene temas municipales asignados. Contacta con tu profesor.');
                    return $this->redirectToRoute('app_examen_semanal_alumno_index');
                }
                foreach ($temasMunicipales as $temaMunicipal) {
                    $preguntasTema = $this->preguntaMunicipalRepository->findBy([
                        'temaMunicipal' => $temaMunicipal,
                        'dificultad' => $examenSemanal->getDificultad(),
                        'activo' => true,
                    ]);
                    $preguntas = array_merge($preguntas, $preguntasTema);
                }
            } else {
                $temas = $examenSemanal->getTemas();
                if ($temas->isEmpty()) {
                    $this->addFlash('error', 'Este examen semanal no tiene temas asignados. Contacta con tu profesor.');
                    return $this->redirectToRoute('app_examen_semanal_alumno_index');
                }
                foreach ($temas as $tema) {
                    $preguntasTema = $this->preguntaRepository->findBy([
                        'tema' => $tema,
                        'dificultad' => $examenSemanal->getDificultad(),
                        'activo' => true,
                    ]);
                    $preguntas = array_merge($preguntas, $preguntasTema);
                }
            }
        }

        if (empty($preguntas)) {
            $mensajeError = 'No hay preguntas disponibles para este examen.';
            if ($examenSemanal->getModoCreacion() === 'preguntas_especificas') {
                $mensajeError .= ' El examen no tiene preguntas específicas asignadas o todas están desactivadas.';
            } else {
                $mensajeError .= ' No hay preguntas activas para los temas y dificultad configurados en este examen.';
            }
            $this->addFlash('error', $mensajeError);
            return $this->redirectToRoute('app_examen_semanal_alumno_index');
        }

        // Limitar el número de preguntas si está especificado
        $numeroPreguntas = $examenSemanal->getNumeroPreguntas();
        if ($numeroPreguntas !== null && $numeroPreguntas > 0) {
            // Para exámenes generales (no municipales) con temas, usar distribución por porcentajes
            if (!$esMunicipal && !$esConvocatoria && $examenSemanal->getModoCreacion() !== 'preguntas_especificas') {
                $temas = $examenSemanal->getTemas()->toArray();
                if (!empty($temas)) {
                    // Usar distribución por porcentajes para exámenes generales
                    $preguntas = $this->distribuirPreguntasPorPorcentajes($preguntas, $temas, $numeroPreguntas);
                } else {
                    // Si no hay temas, usar selección aleatoria sin repetir artículos
                    shuffle($preguntas);
                    $preguntas = $this->seleccionarPreguntasSinRepetirArticulos($preguntas, $numeroPreguntas);
                }
            } else {
                // Para exámenes municipales, convocatorias o con preguntas específicas, usar selección aleatoria sin repetir artículos
                shuffle($preguntas);
                $preguntas = $this->seleccionarPreguntasSinRepetirArticulos($preguntas, $numeroPreguntas);
            }
            
            // Si hay menos preguntas disponibles que el límite solicitado, informar al usuario
            if (count($preguntas) < $numeroPreguntas) {
                $this->addFlash('info', 'Solo hay ' . count($preguntas) . ' preguntas disponibles sin repetir artículos. El examen se realizará con todas las preguntas disponibles.');
            }
        } else {
            // Si no hay límite, aplicar solo la restricción de no repetir artículos
            // pero usar todas las preguntas disponibles
            shuffle($preguntas);
            $preguntas = $this->seleccionarPreguntasSinRepetirArticulos($preguntas, count($preguntas));
        }
        
        // Verificar nuevamente que haya preguntas después de la selección
        if (empty($preguntas)) {
            $this->addFlash('error', 'No se pudieron seleccionar preguntas para este examen. Puede que todas las preguntas disponibles compartan el mismo artículo y no se puedan usar juntas.');
            return $this->redirectToRoute('app_examen_semanal_alumno_index');
        }

        $preguntasIds = array_map(fn($p) => $p->getId(), $preguntas);

        // Guardar en sesión
        $config = [
            'dificultad' => $examenSemanal->getDificultad(),
            'numero_preguntas' => count($preguntasIds),
            'es_municipal' => $esMunicipal || $esConvocatoria,
            'examen_semanal_id' => $examenSemanal->getId(),
        ];
        
        if ($esConvocatoria) {
            // Examen de convocatoria
            $config['convocatoria_id'] = $examenSemanal->getConvocatoria()->getId();
            $config['municipio_id'] = null; // No hay un municipio específico
            
            // Si el examen está en modo preguntas específicas, extraer temas municipales de las preguntas
            if ($examenSemanal->getModoCreacion() === 'preguntas_especificas') {
                $temasMunicipalesIds = [];
                foreach ($preguntas as $pregunta) {
                    if (method_exists($pregunta, 'getTemaMunicipal') && $pregunta->getTemaMunicipal()) {
                        $temaId = $pregunta->getTemaMunicipal()->getId();
                        if (!in_array($temaId, $temasMunicipalesIds)) {
                            $temasMunicipalesIds[] = $temaId;
                        }
                    }
                }
                $config['temas_municipales'] = $temasMunicipalesIds;
            } else {
                // Modo tradicional: usar los temas municipales del examen semanal
                $config['temas_municipales'] = array_map(fn($t) => $t->getId(), $examenSemanal->getTemasMunicipales()->toArray());
            }
        } elseif ($esMunicipal) {
            // Examen municipal
            $config['municipio_id'] = $examenSemanal->getMunicipio()->getId();
            
            // Si el examen está en modo preguntas específicas, extraer temas municipales de las preguntas
            if ($examenSemanal->getModoCreacion() === 'preguntas_especificas') {
                $temasMunicipalesIds = [];
                foreach ($preguntas as $pregunta) {
                    if (method_exists($pregunta, 'getTemaMunicipal') && $pregunta->getTemaMunicipal()) {
                        $temaId = $pregunta->getTemaMunicipal()->getId();
                        if (!in_array($temaId, $temasMunicipalesIds)) {
                            $temasMunicipalesIds[] = $temaId;
                        }
                    }
                }
                $config['temas_municipales'] = $temasMunicipalesIds;
            } else {
                // Modo tradicional: usar los temas municipales del examen semanal
                $config['temas_municipales'] = array_map(fn($t) => $t->getId(), $examenSemanal->getTemasMunicipales()->toArray());
            }
        } else {
            // Examen general
            $config['temas'] = array_map(fn($t) => $t->getId(), $examenSemanal->getTemas()->toArray());
        }
        
        $session->set('examen_preguntas', $preguntasIds);
        $session->set('examen_respuestas', []);
        $session->set('examen_config', $config);
        $session->set('examen_pregunta_actual', 0);

        // Guardar automáticamente en borrador al iniciar el examen semanal
        // Permite múltiples borradores - no se eliminan los anteriores
        $borrador = new ExamenBorrador();
        $borrador->setUsuario($alumno);
        $borrador->setTipoExamen('semanal');
        $borrador->setExamenSemanal($examenSemanal);
        $borrador->setConfig($config);
        $borrador->setPreguntasIds($preguntasIds);
        $borrador->setRespuestas([]);
        $borrador->setPreguntaActual(0);
        $borrador->setTiempoRestante(null);
        $borrador->setFechaActualizacion(new \DateTime());
        
        $this->entityManager->persist($borrador);
        $this->entityManager->flush();

        return $this->redirectToRoute('app_examen_pregunta', ['numero' => 1]);
    }

    #[Route('/{id}/ranking', name: 'app_examen_semanal_alumno_ranking', methods: ['GET'])]
    public function ranking(ExamenSemanal $examenSemanal): Response
    {
        // Verificar que no sea profesor ni admin
        if ($this->isGranted('ROLE_PROFESOR') || $this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Esta ruta es solo para alumnos.');
        }

        // Obtener todos los exámenes realizados de este examen semanal
        $examenes = $this->examenRepository->createQueryBuilder('e')
            ->where('e.examenSemanal = :examenSemanal')
            ->setParameter('examenSemanal', $examenSemanal)
            ->orderBy('e.nota', 'DESC')
            ->addOrderBy('e.fecha', 'ASC')
            ->getQuery()
            ->getResult();

        // Calcular posición del alumno actual
        $alumno = $this->getUser();
        $posicion = null;
        $miExamen = null;
        
        foreach ($examenes as $index => $examen) {
            if ($examen->getUsuario()->getId() === $alumno->getId()) {
                $posicion = $index + 1;
                $miExamen = $examen;
                break;
            }
        }

        return $this->render('examen_semanal_alumno/ranking.html.twig', [
            'examenSemanal' => $examenSemanal,
            'examenes' => $examenes,
            'posicion' => $posicion,
            'miExamen' => $miExamen,
        ]);
    }

    /**
     * Distribuir preguntas según porcentajes configurados por tema
     */
    private function distribuirPreguntasPorPorcentajes(array $preguntas, array $temas, int $cantidadTotal): array
    {
        // Obtener configuraciones de porcentajes
        $configuraciones = $this->configuracionExamenRepository->findByTemas($temas);
        
        // Agrupar preguntas por tema
        $preguntasPorTema = [];
        foreach ($preguntas as $pregunta) {
            $temaId = $pregunta->getTema()->getId();
            if (!isset($preguntasPorTema[$temaId])) {
                $preguntasPorTema[$temaId] = [];
            }
            $preguntasPorTema[$temaId][] = $pregunta;
        }
        
        // Calcular cuántas preguntas por tema según porcentajes
        $distribucionPorTema = [];
        $totalTemas = count($temas);
        $temasConPorcentaje = [];
        $porcentajesTemas = [];
        
        // Primero, calcular porcentajes y cantidades esperadas
        foreach ($temas as $tema) {
            $temaId = $tema->getId();
            $porcentaje = $this->configuracionExamenService->obtenerPorcentajeParaTema($configuraciones, $temaId, $totalTemas);
            $porcentajesTemas[$temaId] = $porcentaje;
            
            // Si el tema tiene porcentaje > 0 y hay preguntas disponibles, debe tener al menos 1 pregunta
            if ($porcentaje > 0 && isset($preguntasPorTema[$temaId]) && count($preguntasPorTema[$temaId]) > 0) {
                $temasConPorcentaje[] = $temaId;
                // Calcular cantidad ideal basada en porcentaje
                $cantidadIdeal = ($porcentaje / 100) * $cantidadTotal;
                // Asegurar mínimo de 1 pregunta
                $cantidadParaTema = max(1, (int) round($cantidadIdeal));
            } else {
                $cantidadParaTema = 0;
            }
            
            $distribucionPorTema[$temaId] = $cantidadParaTema;
        }
        
        // Ajustar distribución para que se acerque lo más posible a los porcentajes
        $sumaDistribucion = array_sum($distribucionPorTema);
        $diferencia = $cantidadTotal - $sumaDistribucion;
        
        if ($diferencia != 0) {
            // Crear lista de temas con su diferencia respecto al porcentaje ideal
            $temasAjustables = [];
            foreach ($temasConPorcentaje as $temaId) {
                $porcentaje = $porcentajesTemas[$temaId];
                $cantidadIdeal = ($porcentaje / 100) * $cantidadTotal;
                $cantidadActual = $distribucionPorTema[$temaId];
                $preguntasDisponibles = count($preguntasPorTema[$temaId] ?? []);
                
                $temasAjustables[] = [
                    'temaId' => $temaId,
                    'porcentaje' => $porcentaje,
                    'cantidadIdeal' => $cantidadIdeal,
                    'cantidadActual' => $cantidadActual,
                    'diferencia' => $cantidadIdeal - $cantidadActual,
                    'preguntasDisponibles' => $preguntasDisponibles,
                ];
            }
            
            if ($diferencia > 0) {
                // Faltan preguntas: asignar a temas que están por debajo de su porcentaje ideal
                // Ordenar por diferencia (mayor diferencia primero = más lejos del ideal)
                usort($temasAjustables, function($a, $b) {
                    return $b['diferencia'] <=> $a['diferencia'];
                });
                
                foreach ($temasAjustables as $temaAjustable) {
                    if ($diferencia <= 0) break;
                    $temaId = $temaAjustable['temaId'];
                    if ($temaAjustable['preguntasDisponibles'] > $distribucionPorTema[$temaId]) {
                        $distribucionPorTema[$temaId]++;
                        $diferencia--;
                    }
                }
                
                // Si aún faltan, distribuir equitativamente
                while ($diferencia > 0) {
                    $asignado = false;
                    foreach ($temasAjustables as $temaAjustable) {
                        if ($diferencia <= 0) break;
                        $temaId = $temaAjustable['temaId'];
                        if ($temaAjustable['preguntasDisponibles'] > $distribucionPorTema[$temaId]) {
                            $distribucionPorTema[$temaId]++;
                            $diferencia--;
                            $asignado = true;
                        }
                    }
                    if (!$asignado) break;
                }
            } else {
                // Sobran preguntas: quitar de temas que están por encima de su porcentaje ideal
                // Ordenar por diferencia negativa (menos diferencia = más por encima del ideal)
                usort($temasAjustables, function($a, $b) {
                    return $a['diferencia'] <=> $b['diferencia'];
                });
                
                while ($diferencia < 0) {
                    $quitado = false;
                    foreach ($temasAjustables as $temaAjustable) {
                        if ($diferencia >= 0) break;
                        $temaId = $temaAjustable['temaId'];
                        // No quitar si solo tiene 1 pregunta (mínimo garantizado)
                        if ($distribucionPorTema[$temaId] > 1) {
                            $distribucionPorTema[$temaId]--;
                            $diferencia++;
                            $quitado = true;
                        }
                    }
                    if (!$quitado) break;
                }
            }
        }
        
        // Seleccionar preguntas de cada tema según la distribución
        $preguntasSeleccionadas = [];
        $articulosUsados = [];
        $preguntasIdsUsadas = []; // Rastrear IDs de preguntas usadas para evitar duplicados exactos
        
        foreach ($distribucionPorTema as $temaId => $cantidad) {
            if ($cantidad <= 0 || !isset($preguntasPorTema[$temaId])) {
                continue;
            }
            
            // Mezclar preguntas del tema
            $preguntasTema = $preguntasPorTema[$temaId];
            shuffle($preguntasTema);
            
            // Seleccionar preguntas sin repetir artículos
            $preguntasSeleccionadasTema = [];
            foreach ($preguntasTema as $pregunta) {
                if (count($preguntasSeleccionadasTema) >= $cantidad) {
                    break;
                }
                
                $articuloId = null;
                if (method_exists($pregunta, 'getArticulo')) {
                    $articulo = $pregunta->getArticulo();
                    $articuloId = $articulo ? $articulo->getId() : null;
                }
                
                // Si el artículo ya fue usado, saltar esta pregunta
                if ($articuloId !== null && in_array($articuloId, $articulosUsados)) {
                    continue;
                }
                
                // Evitar repetir la misma pregunta
                if (in_array($pregunta->getId(), $preguntasIdsUsadas)) {
                    continue;
                }
                
                $preguntasSeleccionadasTema[] = $pregunta;
                $preguntasIdsUsadas[] = $pregunta->getId();
                
                // Marcar el artículo como usado
                if ($articuloId !== null) {
                    $articulosUsados[] = $articuloId;
                }
            }
            
            $preguntasSeleccionadas = array_merge($preguntasSeleccionadas, $preguntasSeleccionadasTema);
        }
        
        // Si no se alcanzó la cantidad total, completar con preguntas aleatorias
        // FASE 1: Intentar sin repetir artículos
        if (count($preguntasSeleccionadas) < $cantidadTotal) {
            $preguntasRestantes = array_filter($preguntas, function($p) use ($preguntasIdsUsadas) {
                return !in_array($p->getId(), $preguntasIdsUsadas);
            });
            shuffle($preguntasRestantes);
            
            $faltantes = $cantidadTotal - count($preguntasSeleccionadas);
            foreach ($preguntasRestantes as $pregunta) {
                if (count($preguntasSeleccionadas) >= $cantidadTotal) {
                    break;
                }
                
                $articuloId = null;
                if (method_exists($pregunta, 'getArticulo')) {
                    $articulo = $pregunta->getArticulo();
                    $articuloId = $articulo ? $articulo->getId() : null;
                }
                
                // Intentar sin repetir artículos
                if ($articuloId === null || !in_array($articuloId, $articulosUsados)) {
                    $preguntasSeleccionadas[] = $pregunta;
                    $preguntasIdsUsadas[] = $pregunta->getId();
                    if ($articuloId !== null) {
                        $articulosUsados[] = $articuloId;
                    }
                }
            }
        }
        
        // FASE 2: Si aún faltan preguntas, permitir repetir artículos (pero no la misma pregunta)
        if (count($preguntasSeleccionadas) < $cantidadTotal) {
            $preguntasRestantes = array_filter($preguntas, function($p) use ($preguntasIdsUsadas) {
                return !in_array($p->getId(), $preguntasIdsUsadas);
            });
            shuffle($preguntasRestantes);
            
            foreach ($preguntasRestantes as $pregunta) {
                if (count($preguntasSeleccionadas) >= $cantidadTotal) {
                    break;
                }
                
                // Solo verificar que no sea la misma pregunta (ya permitimos repetir artículo)
                if (!in_array($pregunta->getId(), $preguntasIdsUsadas)) {
                    $preguntasSeleccionadas[] = $pregunta;
                    $preguntasIdsUsadas[] = $pregunta->getId();
                }
            }
        }
        
        // Asegurar que no se exceda el límite (por si acaso hay más preguntas de las solicitadas)
        if (count($preguntasSeleccionadas) > $cantidadTotal) {
            $preguntasSeleccionadas = array_slice($preguntasSeleccionadas, 0, $cantidadTotal);
        }
        
        // Mezclar todas las preguntas seleccionadas para que no estén agrupadas por tema
        shuffle($preguntasSeleccionadas);
        
        return $preguntasSeleccionadas;
    }

    /**
     * Selecciona preguntas asegurándose de que no haya dos preguntas del mismo artículo
     * Si no hay suficientes, permite repetir artículos pero con preguntas diferentes
     * 
     * @param array $preguntas Array de preguntas disponibles
     * @param int $cantidad Cantidad de preguntas a seleccionar
     * @return array Array de preguntas seleccionadas
     */
    private function seleccionarPreguntasSinRepetirArticulos(array $preguntas, int $cantidad): array
    {
        // FASE 1: Intentar seleccionar sin repetir artículos
        $preguntasSeleccionadas = [];
        $articulosUsados = [];
        $preguntasIdsUsadas = []; // Rastrear IDs de preguntas usadas para evitar duplicados exactos
        
        foreach ($preguntas as $pregunta) {
            // Si ya tenemos suficientes preguntas, parar
            if (count($preguntasSeleccionadas) >= $cantidad) {
                break;
            }
            
            // Solo verificar artículos si la pregunta tiene el método getArticulo()
            // (las preguntas municipales no tienen artículo)
            $articuloId = null;
            if (method_exists($pregunta, 'getArticulo')) {
                $articulo = $pregunta->getArticulo();
                $articuloId = $articulo ? $articulo->getId() : null;
            }
            
            // Evitar repetir artículo Y evitar repetir la misma pregunta
            if ($articuloId !== null && in_array($articuloId, $articulosUsados)) {
                continue;
            }
            
            // Evitar repetir la misma pregunta
            if (in_array($pregunta->getId(), $preguntasIdsUsadas)) {
                continue;
            }
            
            // Agregar la pregunta a las seleccionadas
            $preguntasSeleccionadas[] = $pregunta;
            $preguntasIdsUsadas[] = $pregunta->getId();
            
            // Marcar el artículo como usado
            if ($articuloId !== null) {
                $articulosUsados[] = $articuloId;
            }
        }
        
        // FASE 2: Si faltan preguntas, permitir repetir artículos (pero no la misma pregunta)
        if (count($preguntasSeleccionadas) < $cantidad) {
            $preguntasRestantes = array_filter($preguntas, function($p) use ($preguntasIdsUsadas) {
                return !in_array($p->getId(), $preguntasIdsUsadas);
            });
            shuffle($preguntasRestantes);
            
            foreach ($preguntasRestantes as $pregunta) {
                if (count($preguntasSeleccionadas) >= $cantidad) {
                    break;
                }
                
                // Solo verificar que no sea la misma pregunta (ya permitimos repetir artículo)
                if (!in_array($pregunta->getId(), $preguntasIdsUsadas)) {
                    $preguntasSeleccionadas[] = $pregunta;
                    $preguntasIdsUsadas[] = $pregunta->getId();
                }
            }
        }
        
        // IMPORTANTE: Si después de la Fase 2 aún no se alcanza la cantidad solicitada,
        // se devuelven todas las preguntas disponibles (aunque no llegue al número solicitado)
        
        return $preguntasSeleccionadas;
    }

    #[Route('/{id}/introducir-pdf', name: 'app_examen_semanal_alumno_introducir_pdf', methods: ['GET', 'POST'])]
    public function introducirPDF(Request $request, ExamenSemanal $examenSemanal): Response
    {
        // Verificar que no sea profesor ni admin
        if ($this->isGranted('ROLE_PROFESOR') || $this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Esta ruta es solo para alumnos.');
        }

        $alumno = $this->getUser();

        // Verificar que el examen esté disponible
        if (!$examenSemanal->estaDisponible()) {
            $this->addFlash('error', 'Este examen no está disponible en este momento. Solo puedes introducir resultados cuando el período de realización está abierto.');
            return $this->redirectToRoute('app_examen_semanal_alumno_index');
        }

        // Verificar si ya existe un examen para este alumno y examen semanal
        $examenExistente = $this->examenRepository->createQueryBuilder('e')
            ->where('e.usuario = :usuario')
            ->andWhere('e.examenSemanal = :examenSemanal')
            ->setParameter('usuario', $alumno)
            ->setParameter('examenSemanal', $examenSemanal)
            ->getQuery()
            ->getOneOrNullResult();

        // Si ya existe un examen online, no puede introducir datos PDF
        if ($examenExistente && !$examenExistente->isRealizadoEnPDF()) {
            $this->addFlash('error', 'Ya has realizado este examen online. No puedes introducir datos de PDF.');
            return $this->redirectToRoute('app_examen_semanal_alumno_index');
        }

        // Si no existe, crear uno nuevo; si existe y es PDF, editar
        $examen = $examenExistente ?? new Examen();
        
        if (!$examenExistente) {
            // Configurar el examen nuevo
            $examen->setUsuario($alumno);
            $examen->setExamenSemanal($examenSemanal);
            $examen->setDificultad($examenSemanal->getDificultad());
            $examen->setNumeroPreguntas($examenSemanal->getNumeroPreguntas() ?? 0);
            $examen->setFecha(new \DateTime());
            $examen->setRealizadoEnPDF(true);
            $examen->setRespuestas([]);
            $examen->setPreguntasIds([]);
            
            // Agregar temas o temas municipales según corresponda
            if ($examenSemanal->getMunicipio() || $examenSemanal->getConvocatoria()) {
                foreach ($examenSemanal->getTemasMunicipales() as $temaMunicipal) {
                    $examen->addTemasMunicipale($temaMunicipal);
                }
                if ($examenSemanal->getMunicipio()) {
                    $examen->setMunicipio($examenSemanal->getMunicipio());
                }
                if ($examenSemanal->getConvocatoria()) {
                    $examen->setConvocatoria($examenSemanal->getConvocatoria());
                }
            } else {
                foreach ($examenSemanal->getTemas() as $tema) {
                    $examen->addTema($tema);
                }
            }
        }

        $form = $this->createForm(ExamenSemanalPDFType::class, $examen, [
            'examen_semanal' => $examenSemanal
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Calcular la nota sobre 20: (aciertos × (20/total)) - (errores × ((20/total)/4))
            // Cada 4 errores resta el equivalente a un acierto
            $numeroPreguntas = $examenSemanal->getNumeroPreguntas() ?? 1;
            if ($numeroPreguntas > 0) {
                $puntosPorAcierto = 20 / $numeroPreguntas;
                $puntosPorError = $puntosPorAcierto / 4; // Cada error resta 1/4 del valor de un acierto
                $nota = ($examen->getAciertos() * $puntosPorAcierto) - ($examen->getErrores() * $puntosPorError);
                $nota = max(0, min(20, round($nota, 2))); // Asegurar que esté entre 0 y 20
            } else {
                $nota = 0;
            }
            $examen->setNota(number_format($nota, 2, '.', ''));
            
            $examen->setRealizadoEnPDF(true);

            if (!$examenExistente) {
                $this->entityManager->persist($examen);
            }
            $this->entityManager->flush();

            $this->addFlash('success', 'Resultados del examen PDF guardados correctamente.');
            return $this->redirectToRoute('app_examen_semanal_alumno_index');
        }

        return $this->render('examen_semanal_alumno/introducir_pdf.html.twig', [
            'examenSemanal' => $examenSemanal,
            'form' => $form,
            'esEdicion' => $examenExistente !== null,
        ]);
    }
}

