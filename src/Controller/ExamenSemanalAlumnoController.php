<?php

namespace App\Controller;

use App\Entity\Examen;
use App\Entity\ExamenSemanal;
use App\Entity\User;
use App\Repository\ExamenSemanalRepository;
use App\Repository\ExamenRepository;
use App\Repository\ExamenBorradorRepository;
use App\Repository\TemaRepository;
use App\Repository\TemaMunicipalRepository;
use App\Repository\MunicipioRepository;
use App\Entity\ExamenBorrador;
use App\Form\ExamenSemanalPDFType;
use App\Service\NotificacionService;
use App\Service\ExamenSemanalPreguntasService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
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
        private TemaRepository $temaRepository,
        private TemaMunicipalRepository $temaMunicipalRepository,
        private MunicipioRepository $municipioRepository,
        private ExamenSemanalPreguntasService $examenSemanalPreguntas,
        private EntityManagerInterface $entityManager,
        private NotificacionService $notificacionService,
        private ?CacheItemPoolInterface $cache = null
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

        // Obtener versión del caché de exámenes semanales
        $version = 0;
        if ($this->cache) {
            $versionItem = $this->cache->getItem('examenes_semanales_version');
            if ($versionItem->isHit()) {
                $version = $versionItem->get();
            }
        }

        // Generar clave de caché única para este alumno y sus grupos (incluyendo versión)
        $gruposIdsSorted = $gruposIds;
        sort($gruposIdsSorted);
        $cacheKey = 'examenes_semanales_alumno_' . $alumno->getId() . '_grupos_' . md5(implode(',', $gruposIdsSorted)) . '_v' . $version;
        
        // Intentar obtener del caché
        $todosExamenes = null;
        if ($this->cache) {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                $todosExamenes = $item->get();
            }
        }
        
        // Si no está en caché, obtener de la base de datos
        if ($todosExamenes === null) {
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
            
            // Guardar en caché (5 minutos)
            if ($this->cache) {
                $item->set($todosExamenes);
                $item->expiresAfter(300); // 5 minutos
                $this->cache->save($item);
            }
        }

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

        $esMunicipal = $examenSemanal->getMunicipio() !== null;
        $esConvocatoria = $examenSemanal->getConvocatoria() !== null;

        if ($examenSemanal->getModoCreacion() !== 'preguntas_especificas') {
            if (($esMunicipal || $esConvocatoria) && $examenSemanal->getTemasMunicipales()->isEmpty()) {
                $this->addFlash('error', 'Este examen semanal no tiene temas municipales asignados. Contacta con tu profesor.');
                return $this->redirectToRoute('app_examen_semanal_alumno_index');
            }
            if (!$esMunicipal && !$esConvocatoria && $examenSemanal->getTemas()->isEmpty()) {
                $this->addFlash('error', 'Este examen semanal no tiene temas asignados. Contacta con tu profesor.');
                return $this->redirectToRoute('app_examen_semanal_alumno_index');
            }
        }

        $preguntas = $this->examenSemanalPreguntas->cargar($examenSemanal);

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

        $numeroPreguntas = $examenSemanal->getNumeroPreguntas();
        $preguntas = $this->examenSemanalPreguntas->ordenar($examenSemanal, $preguntas);

        if ($numeroPreguntas !== null && $numeroPreguntas > 0 && count($preguntas) < $numeroPreguntas) {
            $this->addFlash('info', 'Solo hay ' . count($preguntas) . ' preguntas disponibles sin repetir artículos. El examen se realizará con todas las preguntas disponibles.');
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
        // Asegurar que el alumno esté gestionado por el EntityManager
        if (!$this->entityManager->contains($alumno)) {
            $alumno = $this->entityManager->getReference(User::class, $alumno->getId());
        }
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

