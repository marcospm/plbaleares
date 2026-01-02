<?php

namespace App\Controller;

use App\Entity\ExamenSemanal;
use App\Form\ExamenSemanalType;
use App\Repository\ExamenSemanalRepository;
use App\Repository\UserRepository;
use App\Repository\MunicipioRepository;
use App\Repository\ConvocatoriaRepository;
use App\Repository\TemaMunicipalRepository;
use App\Repository\PreguntaRepository;
use App\Repository\PreguntaMunicipalRepository;
use App\Service\NotificacionService;
use App\Service\PreguntaService;
use App\Repository\TemaRepository;
use App\Repository\LeyRepository;
use App\Repository\ArticuloRepository;
use App\Repository\ConfiguracionExamenRepository;
use App\Service\ConfiguracionExamenService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/examen-semanal')]
#[IsGranted('ROLE_PROFESOR')]
class ExamenSemanalController extends AbstractController
{
    public function __construct(
        private ExamenSemanalRepository $examenSemanalRepository,
        private EntityManagerInterface $entityManager,
        private NotificacionService $notificacionService,
        private UserRepository $userRepository,
        private MunicipioRepository $municipioRepository,
        private ConvocatoriaRepository $convocatoriaRepository,
        private TemaMunicipalRepository $temaMunicipalRepository,
        private PreguntaRepository $preguntaRepository,
        private PreguntaMunicipalRepository $preguntaMunicipalRepository,
        private PreguntaService $preguntaService,
        private TemaRepository $temaRepository,
        private LeyRepository $leyRepository,
        private ArticuloRepository $articuloRepository,
        private ConfiguracionExamenRepository $configuracionExamenRepository,
        private ConfiguracionExamenService $configuracionExamenService
    ) {
    }

    #[Route('/temas-municipales/{municipioId}', name: 'app_examen_semanal_temas_municipales', methods: ['GET'])]
    public function getTemasMunicipales(int $municipioId): JsonResponse
    {
        $municipio = $this->municipioRepository->find($municipioId);
        
        if (!$municipio) {
            return new JsonResponse(['error' => 'Municipio no encontrado'], 404);
        }

        $temas = $this->temaMunicipalRepository->findByMunicipio($municipio);
        
        $temasArray = [];
        foreach ($temas as $tema) {
            $temasArray[] = [
                'id' => $tema->getId(),
                'nombre' => $tema->getNombre(),
            ];
        }

        return new JsonResponse($temasArray);
    }

    #[Route('/temas-municipales-convocatoria/{convocatoriaId}', name: 'app_examen_semanal_temas_municipales_convocatoria', methods: ['GET'])]
    public function temasMunicipalesConvocatoria(int $convocatoriaId): JsonResponse
    {
        $convocatoria = $this->convocatoriaRepository->find($convocatoriaId);
        
        if (!$convocatoria) {
            return new JsonResponse(['error' => 'Convocatoria no encontrada'], 404);
        }
        
        // Obtener todos los municipios de la convocatoria
        $municipios = $convocatoria->getMunicipios();
        $municipiosIds = array_map(fn($m) => $m->getId(), $municipios->toArray());
        
        // Obtener todos los temas municipales de todos los municipios de la convocatoria
        $temas = $this->temaMunicipalRepository->createQueryBuilder('t')
            ->innerJoin('t.municipio', 'm')
            ->where('m.id IN (:municipiosIds)')
            ->andWhere('t.activo = :activo')
            ->setParameter('municipiosIds', $municipiosIds)
            ->setParameter('activo', true)
            ->orderBy('m.nombre', 'ASC')
            ->addOrderBy('t.nombre', 'ASC')
            ->getQuery()
            ->getResult();
        
        $temasArray = array_map(function($tema) {
            return [
                'id' => $tema->getId(),
                'nombre' => $tema->getNombre() . ' (' . $tema->getMunicipio()->getNombre() . ')',
            ];
        }, $temas);
        
        return new JsonResponse($temasArray);
    }

    #[Route('/articulos/{leyId}', name: 'app_examen_semanal_articulos', methods: ['GET'])]
    public function getArticulos(int $leyId): JsonResponse
    {
        $ley = $this->leyRepository->find($leyId);
        
        if (!$ley) {
            return new JsonResponse(['error' => 'Ley no encontrada'], 404);
        }

        $articulos = $this->articuloRepository->findBy(['ley' => $ley, 'activo' => true], ['numero' => 'ASC']);
        
        $articulosArray = [];
        foreach ($articulos as $articulo) {
            $articulosArray[] = [
                'id' => $articulo->getId(),
                'numero' => $articulo->getNumero(),
                'sufijo' => $articulo->getSufijo(),
                'numeroCompleto' => $articulo->getNumeroCompleto(),
                'nombre' => $articulo->getNombre(),
            ];
        }

        return new JsonResponse($articulosArray);
    }

    #[Route('/new-con-preguntas', name: 'app_examen_semanal_new_con_preguntas', methods: ['GET', 'POST'], priority: 10)]
    public function newConPreguntas(Request $request): Response
    {
        $tipoExamen = $request->query->get('tipo', 'general'); // 'general' o 'municipal'
        
        if ($request->isMethod('POST')) {
            try {
                $content = $request->getContent();
                $datos = json_decode($content, true);
                
                if (json_last_error() !== JSON_ERROR_NONE || !$datos) {
                    // Si falla el JSON, intentar con request->request
                    $datos = $request->request->all();
                }
                
                // Validar datos básicos del examen
                if (empty($datos['nombre']) || empty($datos['fechaApertura']) || empty($datos['fechaCierre']) || empty($datos['dificultad'])) {
                    return new JsonResponse(['error' => 'Faltan datos requeridos del examen'], 400);
                }

                // Crear examen semanal
                $examenSemanal = new ExamenSemanal();
                $examenSemanal->setNombre($datos['nombre']);
                $examenSemanal->setDescripcion($datos['descripcion'] ?? null);
                $examenSemanal->setFechaApertura(new \DateTime($datos['fechaApertura']));
                $examenSemanal->setFechaCierre(new \DateTime($datos['fechaCierre']));
                $examenSemanal->setDificultad($datos['dificultad']);
                $examenSemanal->setCreadoPor($this->getUser());
                $examenSemanal->setModoCreacion('preguntas_especificas');
                $examenSemanal->setActivo(true);

                if ($tipoExamen === 'municipal') {
                    if (empty($datos['municipioId'])) {
                        return new JsonResponse(['error' => 'Debes seleccionar un municipio'], 400);
                    }
                    $municipio = $this->municipioRepository->find($datos['municipioId']);
                    if (!$municipio) {
                        return new JsonResponse(['error' => 'Municipio no encontrado'], 404);
                    }
                    $examenSemanal->setMunicipio($municipio);
                }

                $this->entityManager->persist($examenSemanal);
                $this->entityManager->flush();

                return new JsonResponse([
                    'success' => true,
                    'examenId' => $examenSemanal->getId(),
                    'message' => 'Examen creado correctamente'
                ]);
            } catch (\Exception $e) {
                return new JsonResponse([
                    'error' => 'Error al crear el examen: ' . $e->getMessage()
                ], 500);
            }
        }

        // GET: mostrar formulario
        $temas = $this->temaRepository->findBy(['activo' => true], ['nombre' => 'ASC']);
        $leyes = $this->leyRepository->findBy(['activo' => true], ['nombre' => 'ASC']);
        $municipios = $this->municipioRepository->findBy(['activo' => true], ['nombre' => 'ASC']);

        return $this->render('examen_semanal/new_con_preguntas.html.twig', [
            'tipoExamen' => $tipoExamen,
            'temas' => $temas,
            'leyes' => $leyes,
            'municipios' => $municipios,
        ]);
    }

    #[Route('/', name: 'app_examen_semanal_index', methods: ['GET'])]
    public function index(): Response
    {
        // Ordenar por fecha de creación descendente (más reciente primero)
        $examenes = $this->examenSemanalRepository->findBy(
            [],
            ['fechaCreacion' => 'DESC']
        );

        // Calcular porcentajes por tema para cada examen
        $porcentajesPorExamen = [];
        foreach ($examenes as $examen) {
            if ($examen->getMunicipio() === null && $examen->getModoCreacion() !== 'preguntas_especificas') {
                $porcentajesPorExamen[$examen->getId()] = $this->calcularPorcentajesPorTema($examen);
            }
        }

        return $this->render('examen_semanal/index.html.twig', [
            'examenes' => $examenes,
            'porcentajesPorExamen' => $porcentajesPorExamen,
        ]);
    }

    #[Route('/new', name: 'app_examen_semanal_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $examenSemanal = new ExamenSemanal();
        $examenSemanal->setCreadoPor($this->getUser());
        
        $form = $this->createForm(ExamenSemanalType::class, $examenSemanal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Validar que haya al menos un tipo de examen seleccionado
            $tieneTemasGenerales = !$examenSemanal->getTemas()->isEmpty();
            $tieneTemasMunicipales = $examenSemanal->getMunicipio() !== null && !$examenSemanal->getTemasMunicipales()->isEmpty();
            
            // Para convocatoria, solo verificar que haya una convocatoria seleccionada
            // Los temas se cargarán automáticamente de todos los municipios de la convocatoria
            $tieneConvocatoria = $examenSemanal->getConvocatoria() !== null;

            if (!$tieneTemasGenerales && !$tieneTemasMunicipales && !$tieneConvocatoria) {
                $this->addFlash('error', 'Debes seleccionar temas del temario general, crear un examen municipal (municipio + temas municipales) o crear un examen de convocatoria.');
                return $this->render('examen_semanal/new.html.twig', [
                    'examenSemanal' => $examenSemanal,
                    'form' => $form,
                    'convocatorias' => $this->convocatoriaRepository->findBy(['activo' => true], ['nombre' => 'ASC']),
                ]);
            }

            // Validar examen de 30 temas: temas son obligatorios
            if ($tieneTemasGenerales && $examenSemanal->getTemas()->isEmpty()) {
                $this->addFlash('error', 'Para crear un examen de 30 temas, debes seleccionar al menos un tema del temario general.');
                return $this->render('examen_semanal/new.html.twig', [
                    'examenSemanal' => $examenSemanal,
                    'form' => $form,
                    'convocatorias' => $this->convocatoriaRepository->findBy(['activo' => true], ['nombre' => 'ASC']),
                ]);
            }

            // Validar examen de convocatoria: convocatoria es obligatoria
            if ($tieneConvocatoria && $examenSemanal->getConvocatoria() === null) {
                $this->addFlash('error', 'Para crear un examen de convocatoria, debes seleccionar una convocatoria.');
                return $this->render('examen_semanal/new.html.twig', [
                    'examenSemanal' => $examenSemanal,
                    'form' => $form,
                    'convocatorias' => $this->convocatoriaRepository->findBy(['activo' => true], ['nombre' => 'ASC']),
                ]);
            }

            // Validar examen municipal: municipio y temas municipales son obligatorios
            if ($examenSemanal->getMunicipio() !== null) {
                if ($examenSemanal->getTemasMunicipales()->isEmpty()) {
                    $this->addFlash('error', 'Para crear un examen municipal, debes seleccionar un municipio y al menos un tema municipal.');
                    return $this->render('examen_semanal/new.html.twig', [
                        'examenSemanal' => $examenSemanal,
                        'form' => $form,
                        'convocatorias' => $this->convocatoriaRepository->findBy(['activo' => true], ['nombre' => 'ASC']),
                    ]);
                }
            } elseif (!$examenSemanal->getTemasMunicipales()->isEmpty()) {
                // Si hay temas municipales pero no municipio
                $this->addFlash('error', 'Si seleccionas temas municipales, debes seleccionar también un municipio.');
                return $this->render('examen_semanal/new.html.twig', [
                    'examenSemanal' => $examenSemanal,
                    'form' => $form,
                    'convocatorias' => $this->convocatoriaRepository->findBy(['activo' => true], ['nombre' => 'ASC']),
                ]);
            }

            // Validar que si se selecciona convocatoria, tenga municipios
            if ($examenSemanal->getConvocatoria() && $examenSemanal->getConvocatoria()->getMunicipios()->isEmpty()) {
                $this->addFlash('error', 'La convocatoria seleccionada no tiene municipios asignados.');
                return $this->render('examen_semanal/new.html.twig', [
                    'examenSemanal' => $examenSemanal,
                    'form' => $form,
                    'convocatorias' => $this->convocatoriaRepository->findBy(['activo' => true], ['nombre' => 'ASC']),
                ]);
            }

            $examenesCreados = [];
            $profesor = $this->getUser();
            $formData = $form->getData();

            // Crear examen del temario general si hay temas seleccionados
            if ($tieneTemasGenerales) {
                $examenGeneral = new ExamenSemanal();
                
                // Validar que el nombre sea requerido
                $nombreGeneral = $form->get('nombreGeneral')->getData();
                if (empty($nombreGeneral)) {
                    $this->addFlash('error', 'Debes especificar el nombre para el examen general.');
                    return $this->render('examen_semanal/new.html.twig', [
                        'examenSemanal' => $examenSemanal,
                        'form' => $form,
                    ]);
                }
                $examenGeneral->setNombre($nombreGeneral);
                
                // Usar descripción específica
                $descripcionGeneral = $form->get('descripcionGeneral')->getData();
                $examenGeneral->setDescripcion($descripcionGeneral);
                
                // Usar fechas específicas o requerir que se completen
                $fechaAperturaGeneral = $form->get('fechaAperturaGeneral')->getData();
                $fechaCierreGeneral = $form->get('fechaCierreGeneral')->getData();
                
                if (!$fechaAperturaGeneral || !$fechaCierreGeneral) {
                    $this->addFlash('error', 'Debes especificar fecha de apertura y cierre para el examen general.');
                    return $this->render('examen_semanal/new.html.twig', [
                        'examenSemanal' => $examenSemanal,
                        'form' => $form,
                    ]);
                }
                
                if ($fechaCierreGeneral <= $fechaAperturaGeneral) {
                    $this->addFlash('error', 'La fecha de cierre del examen general debe ser posterior a la fecha de apertura.');
                    return $this->render('examen_semanal/new.html.twig', [
                        'examenSemanal' => $examenSemanal,
                        'form' => $form,
                    ]);
                }
                
                $examenGeneral->setFechaApertura($fechaAperturaGeneral);
                $examenGeneral->setFechaCierre($fechaCierreGeneral);
                
                // Usar dificultad específica o requerir que se complete
                $dificultadGeneral = $form->get('dificultadGeneral')->getData();
                if (!$dificultadGeneral) {
                    $this->addFlash('error', 'Debes especificar la dificultad para el examen general.');
                    return $this->render('examen_semanal/new.html.twig', [
                        'examenSemanal' => $examenSemanal,
                        'form' => $form,
                    ]);
                }
                $examenGeneral->setDificultad($dificultadGeneral);
                
                // Número de preguntas (opcional)
                $numeroPreguntasGeneral = $form->get('numeroPreguntasGeneral')->getData();
                $examenGeneral->setNumeroPreguntas($numeroPreguntasGeneral);
                
                $examenGeneral->setCreadoPor($profesor);
                $examenGeneral->setActivo(true);
                
                foreach ($examenSemanal->getTemas() as $tema) {
                    $examenGeneral->addTema($tema);
                }
                
                $this->entityManager->persist($examenGeneral);
                $examenesCreados[] = $examenGeneral;
            }

            // Crear examen municipal si hay municipio y temas municipales seleccionados
            if ($tieneTemasMunicipales) {
                $examenMunicipal = new ExamenSemanal();
                
                // Validar que el nombre sea requerido
                $nombreMunicipal = $form->get('nombreMunicipal')->getData();
                if (empty($nombreMunicipal)) {
                    $this->addFlash('error', 'Debes especificar el nombre para el examen municipal.');
                    return $this->render('examen_semanal/new.html.twig', [
                        'examenSemanal' => $examenSemanal,
                        'form' => $form,
                    ]);
                }
                $examenMunicipal->setNombre($nombreMunicipal);
                
                // Usar descripción específica
                $descripcionMunicipal = $form->get('descripcionMunicipal')->getData();
                $examenMunicipal->setDescripcion($descripcionMunicipal);
                
                // Usar fechas específicas o requerir que se completen
                $fechaAperturaMunicipal = $form->get('fechaAperturaMunicipal')->getData();
                $fechaCierreMunicipal = $form->get('fechaCierreMunicipal')->getData();
                
                if (!$fechaAperturaMunicipal || !$fechaCierreMunicipal) {
                    $this->addFlash('error', 'Debes especificar fecha de apertura y cierre para el examen municipal.');
                    return $this->render('examen_semanal/new.html.twig', [
                        'examenSemanal' => $examenSemanal,
                        'form' => $form,
                    ]);
                }
                
                if ($fechaCierreMunicipal <= $fechaAperturaMunicipal) {
                    $this->addFlash('error', 'La fecha de cierre del examen municipal debe ser posterior a la fecha de apertura.');
                    return $this->render('examen_semanal/new.html.twig', [
                        'examenSemanal' => $examenSemanal,
                        'form' => $form,
                    ]);
                }
                
                $examenMunicipal->setFechaApertura($fechaAperturaMunicipal);
                $examenMunicipal->setFechaCierre($fechaCierreMunicipal);
                
                // Usar dificultad específica o requerir que se complete
                $dificultadMunicipal = $form->get('dificultadMunicipal')->getData();
                if (!$dificultadMunicipal) {
                    $this->addFlash('error', 'Debes especificar la dificultad para el examen municipal.');
                    return $this->render('examen_semanal/new.html.twig', [
                        'examenSemanal' => $examenSemanal,
                        'form' => $form,
                    ]);
                }
                $examenMunicipal->setDificultad($dificultadMunicipal);
                
                // Número de preguntas (opcional)
                $numeroPreguntasMunicipal = $form->get('numeroPreguntasMunicipal')->getData();
                $examenMunicipal->setNumeroPreguntas($numeroPreguntasMunicipal);
                
                $examenMunicipal->setCreadoPor($profesor);
                $examenMunicipal->setActivo(true);
                $examenMunicipal->setMunicipio($examenSemanal->getMunicipio());
                
                foreach ($examenSemanal->getTemasMunicipales() as $temaMunicipal) {
                    $examenMunicipal->addTemasMunicipale($temaMunicipal);
                }
                
                $this->entityManager->persist($examenMunicipal);
                $examenesCreados[] = $examenMunicipal;
            }

            // Crear examen de convocatoria si hay convocatoria seleccionada
            if ($tieneConvocatoria) {
                $examenConvocatoria = new ExamenSemanal();
                
                // Validar que el nombre sea requerido
                $nombreConvocatoria = $form->get('nombreConvocatoria')->getData();
                if (empty($nombreConvocatoria)) {
                    $this->addFlash('error', 'Debes especificar el nombre para el examen de convocatoria.');
                    return $this->render('examen_semanal/new.html.twig', [
                        'examenSemanal' => $examenSemanal,
                        'form' => $form,
                    ]);
                }
                $examenConvocatoria->setNombre($nombreConvocatoria);
                
                // Usar descripción específica
                $descripcionConvocatoria = $form->get('descripcionConvocatoria')->getData();
                $examenConvocatoria->setDescripcion($descripcionConvocatoria);
                
                // Usar fechas específicas o requerir que se completen
                $fechaAperturaConvocatoria = $form->get('fechaAperturaConvocatoria')->getData();
                $fechaCierreConvocatoria = $form->get('fechaCierreConvocatoria')->getData();
                
                if (!$fechaAperturaConvocatoria || !$fechaCierreConvocatoria) {
                    $this->addFlash('error', 'Debes especificar fecha de apertura y cierre para el examen de convocatoria.');
                    return $this->render('examen_semanal/new.html.twig', [
                        'examenSemanal' => $examenSemanal,
                        'form' => $form,
                    ]);
                }
                
                if ($fechaCierreConvocatoria <= $fechaAperturaConvocatoria) {
                    $this->addFlash('error', 'La fecha de cierre del examen de convocatoria debe ser posterior a la fecha de apertura.');
                    return $this->render('examen_semanal/new.html.twig', [
                        'examenSemanal' => $examenSemanal,
                        'form' => $form,
                    ]);
                }
                
                $examenConvocatoria->setFechaApertura($fechaAperturaConvocatoria);
                $examenConvocatoria->setFechaCierre($fechaCierreConvocatoria);
                
                // Usar dificultad específica o requerir que se complete
                $dificultadConvocatoria = $form->get('dificultadConvocatoria')->getData();
                if (!$dificultadConvocatoria) {
                    $this->addFlash('error', 'Debes especificar la dificultad para el examen de convocatoria.');
                    return $this->render('examen_semanal/new.html.twig', [
                        'examenSemanal' => $examenSemanal,
                        'form' => $form,
                    ]);
                }
                $examenConvocatoria->setDificultad($dificultadConvocatoria);
                
                // Número de preguntas (opcional)
                $numeroPreguntasConvocatoria = $form->get('numeroPreguntasConvocatoria')->getData();
                $examenConvocatoria->setNumeroPreguntas($numeroPreguntasConvocatoria);
                
                $examenConvocatoria->setCreadoPor($profesor);
                $examenConvocatoria->setActivo(true);
                $examenConvocatoria->setConvocatoria($examenSemanal->getConvocatoria());
                
                // Añadir todos los temas municipales de todos los municipios de la convocatoria
                // Obtener todos los municipios de la convocatoria
                $convocatoria = $examenSemanal->getConvocatoria();
                $municipios = $convocatoria->getMunicipios();
                $municipiosIds = array_map(fn($m) => $m->getId(), $municipios->toArray());
                
                // Obtener todos los temas municipales activos de todos los municipios de la convocatoria
                $todosLosTemas = $this->temaMunicipalRepository->createQueryBuilder('t')
                    ->innerJoin('t.municipio', 'm')
                    ->where('m.id IN (:municipiosIds)')
                    ->andWhere('t.activo = :activo')
                    ->setParameter('municipiosIds', $municipiosIds)
                    ->setParameter('activo', true)
                    ->getQuery()
                    ->getResult();
                
                // Añadir todos los temas al examen
                foreach ($todosLosTemas as $temaMunicipal) {
                    $examenConvocatoria->addTemasMunicipale($temaMunicipal);
                }
                
                $this->entityManager->persist($examenConvocatoria);
                $examenesCreados[] = $examenConvocatoria;
            }

            $this->entityManager->flush();

            // Crear notificaciones para todos los alumnos
            try {
                $alumnos = $this->userRepository->createQueryBuilder('u')
                    ->where('u.roles LIKE :role')
                    ->andWhere('u.activo = :activo')
                    ->setParameter('role', '%ROLE_USER%')
                    ->setParameter('activo', true)
                    ->getQuery()
                    ->getResult();

                foreach ($alumnos as $alumno) {
                    // Verificar que no sea profesor ni admin, y que no sea el mismo que el profesor que crea el examen
                    if (!in_array('ROLE_PROFESOR', $alumno->getRoles()) 
                        && !in_array('ROLE_ADMIN', $alumno->getRoles())
                        && $alumno->getId() !== $profesor->getId()) {
                        foreach ($examenesCreados as $examenCreado) {
                            $this->notificacionService->crearNotificacionExamenSemanal($examenCreado, $alumno, $profesor);
                        }
                    }
                }
                $this->entityManager->flush();
            } catch (\Exception $e) {
                error_log('Error al crear notificaciones de examen semanal: ' . $e->getMessage());
            }

            $mensaje = count($examenesCreados) > 1 
                ? sprintf('Se han creado %d exámenes semanales correctamente.', count($examenesCreados))
                : 'Examen semanal creado correctamente.';
            $this->addFlash('success', $mensaje);
            return $this->redirectToRoute('app_examen_semanal_index', [], Response::HTTP_SEE_OTHER);
        }

        $convocatorias = $this->convocatoriaRepository->findBy(['activo' => true], ['nombre' => 'ASC']);

        return $this->render('examen_semanal/new.html.twig', [
            'examenSemanal' => $examenSemanal,
            'form' => $form,
            'convocatorias' => $convocatorias,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_examen_semanal_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ExamenSemanal $examenSemanal): Response
    {
        $form = $this->createForm(ExamenSemanalType::class, $examenSemanal, [
            'is_edit_mode' => true,
            'examen_semanal' => $examenSemanal,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Validar que fechaCierre sea posterior a fechaApertura
            if ($examenSemanal->getFechaCierre() <= $examenSemanal->getFechaApertura()) {
                $this->addFlash('error', 'La fecha de cierre debe ser posterior a la fecha de apertura.');
                return $this->render('examen_semanal/edit.html.twig', [
                    'examenSemanal' => $examenSemanal,
                    'form' => $form,
                ]);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Examen semanal actualizado correctamente.');
            return $this->redirectToRoute('app_examen_semanal_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('examen_semanal/edit.html.twig', [
            'examenSemanal' => $examenSemanal,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_examen_semanal_delete', methods: ['POST'])]
    public function delete(Request $request, ExamenSemanal $examenSemanal): Response
    {
        if ($this->isCsrfTokenValid('delete'.$examenSemanal->getId(), $request->getPayload()->getString('_token'))) {
            $this->entityManager->remove($examenSemanal);
            $this->entityManager->flush();
            $this->addFlash('success', 'Examen semanal eliminado correctamente.');
        }

        return $this->redirectToRoute('app_examen_semanal_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/pdf', name: 'app_examen_semanal_pdf', methods: ['GET'])]
    public function generarPdf(ExamenSemanal $examenSemanal): Response
    {
        // Obtener preguntas del examen semanal
        $preguntas = $this->obtenerPreguntasExamen($examenSemanal);
        
        if (empty($preguntas)) {
            $this->addFlash('error', 'No hay preguntas disponibles para este examen semanal.');
            return $this->redirectToRoute('app_examen_semanal_index', [], Response::HTTP_SEE_OTHER);
        }

        // Renderizar el template HTML
        $html = $this->renderView('examen_semanal/pdf_examen.html.twig', [
            'examenSemanal' => $examenSemanal,
            'preguntas' => $preguntas,
            'mostrarRespuestas' => false,
        ]);

        // Configurar opciones de dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        // Crear instancia de dompdf
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Generar nombre del archivo
        $nombreArchivo = 'examen_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($examenSemanal->getNombre())) . '.pdf';

        // Devolver el PDF
        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $nombreArchivo . '"',
            ]
        );
    }

    #[Route('/{id}/pdf-respuestas', name: 'app_examen_semanal_pdf_respuestas', methods: ['GET'])]
    public function generarPdfRespuestas(ExamenSemanal $examenSemanal): Response
    {
        // Obtener preguntas del examen semanal
        $preguntas = $this->obtenerPreguntasExamen($examenSemanal);
        
        if (empty($preguntas)) {
            $this->addFlash('error', 'No hay preguntas disponibles para este examen semanal.');
            return $this->redirectToRoute('app_examen_semanal_index', [], Response::HTTP_SEE_OTHER);
        }

        // Renderizar el template HTML con respuestas
        $html = $this->renderView('examen_semanal/pdf_examen.html.twig', [
            'examenSemanal' => $examenSemanal,
            'preguntas' => $preguntas,
            'mostrarRespuestas' => true,
        ]);

        // Configurar opciones de dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        // Crear instancia de dompdf
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Generar nombre del archivo
        $nombreArchivo = 'respuestas_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($examenSemanal->getNombre())) . '.pdf';

        // Devolver el PDF
        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $nombreArchivo . '"',
            ]
        );
    }

    #[Route('/{id}/agregar-pregunta', name: 'app_examen_semanal_agregar_pregunta', methods: ['POST'])]
    public function agregarPregunta(ExamenSemanal $examenSemanal, Request $request): JsonResponse
    {
        if ($examenSemanal->getModoCreacion() !== 'preguntas_especificas') {
            return new JsonResponse(['error' => 'Este examen no permite agregar preguntas específicas'], 400);
        }

        $datos = json_decode($request->getContent(), true);
        
        if ($examenSemanal->getMunicipio()) {
            // Pregunta municipal
            $errores = $this->preguntaService->validarDatosPreguntaMunicipal($datos);
            if (!empty($errores)) {
                return new JsonResponse(['error' => implode(', ', $errores)], 400);
            }

            $municipio = $examenSemanal->getMunicipio();
            $temaMunicipal = $this->temaMunicipalRepository->find($datos['temaMunicipalId']);
            if (!$temaMunicipal || $temaMunicipal->getMunicipio()->getId() !== $municipio->getId()) {
                return new JsonResponse(['error' => 'Tema municipal no válido'], 400);
            }

            $pregunta = $this->preguntaService->crearPreguntaMunicipal($datos, $temaMunicipal, $municipio);
            $examenSemanal->addPreguntasMunicipale($pregunta);
            
            // Agregar el tema municipal al examen semanal si no está ya agregado
            if (!$examenSemanal->getTemasMunicipales()->contains($temaMunicipal)) {
                $examenSemanal->addTemasMunicipale($temaMunicipal);
            }
        } else {
            // Pregunta general
            $errores = $this->preguntaService->validarDatosPreguntaGeneral($datos);
            if (!empty($errores)) {
                return new JsonResponse(['error' => implode(', ', $errores)], 400);
            }

            $tema = $this->temaRepository->find($datos['temaId']);
            $ley = $this->leyRepository->find($datos['leyId']);
            $articulo = $this->articuloRepository->find($datos['articuloId']);

            if (!$tema || !$ley || !$articulo) {
                return new JsonResponse(['error' => 'Tema, ley o artículo no encontrado'], 404);
            }

            $pregunta = $this->preguntaService->crearPreguntaGeneral($datos, $tema, $ley, $articulo);
            $examenSemanal->addPregunta($pregunta);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'preguntaId' => $pregunta->getId(),
            'message' => 'Pregunta agregada correctamente'
        ]);
    }

    #[Route('/{id}/eliminar-pregunta/{preguntaId}', name: 'app_examen_semanal_eliminar_pregunta', methods: ['DELETE'])]
    public function eliminarPregunta(ExamenSemanal $examenSemanal, int $preguntaId, Request $request): JsonResponse
    {
        if ($examenSemanal->getModoCreacion() !== 'preguntas_especificas') {
            return new JsonResponse(['error' => 'Este examen no permite eliminar preguntas específicas'], 400);
        }

        if ($examenSemanal->getMunicipio()) {
            $pregunta = $this->preguntaMunicipalRepository->find($preguntaId);
            if (!$pregunta || !$examenSemanal->getPreguntasMunicipales()->contains($pregunta)) {
                return new JsonResponse(['error' => 'Pregunta no encontrada en este examen'], 404);
            }
            $examenSemanal->removePreguntasMunicipale($pregunta);
        } else {
            $pregunta = $this->preguntaRepository->find($preguntaId);
            if (!$pregunta || !$examenSemanal->getPreguntas()->contains($pregunta)) {
                return new JsonResponse(['error' => 'Pregunta no encontrada en este examen'], 404);
            }
            $examenSemanal->removePregunta($pregunta);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Pregunta eliminada del examen correctamente'
        ]);
    }

    #[Route('/{id}/finalizar', name: 'app_examen_semanal_finalizar', methods: ['POST'])]
    public function finalizarCreacion(ExamenSemanal $examenSemanal, Request $request): JsonResponse
    {
        if ($examenSemanal->getModoCreacion() !== 'preguntas_especificas') {
            return new JsonResponse(['error' => 'Este examen no está en modo de preguntas específicas'], 400);
        }

        // Validar que tenga al menos una pregunta
        $totalPreguntas = $examenSemanal->getMunicipio() 
            ? $examenSemanal->getPreguntasMunicipales()->count()
            : $examenSemanal->getPreguntas()->count();

        if ($totalPreguntas === 0) {
            return new JsonResponse(['error' => 'El examen debe tener al menos una pregunta'], 400);
        }

        // Actualizar número de preguntas
        $examenSemanal->setNumeroPreguntas($totalPreguntas);
        $this->entityManager->flush();

        // Crear notificaciones para todos los alumnos
        try {
            $alumnos = $this->userRepository->createQueryBuilder('u')
                ->where('u.roles LIKE :role')
                ->andWhere('u.activo = :activo')
                ->setParameter('role', '%ROLE_USER%')
                ->setParameter('activo', true)
                ->getQuery()
                ->getResult();

            $profesor = $this->getUser();
            foreach ($alumnos as $alumno) {
                // Verificar que no sea profesor ni admin, y que no sea el mismo que el profesor que crea el examen
                if (!in_array('ROLE_PROFESOR', $alumno->getRoles()) 
                    && !in_array('ROLE_ADMIN', $alumno->getRoles())
                    && $alumno->getId() !== $profesor->getId()) {
                    $this->notificacionService->crearNotificacionExamenSemanal($examenSemanal, $alumno, $profesor);
                }
            }
            $this->entityManager->flush();
        } catch (\Exception $e) {
            error_log('Error al crear notificaciones de examen semanal: ' . $e->getMessage());
        }

        return new JsonResponse([
            'success' => true,
            'examenId' => $examenSemanal->getId(),
            'message' => 'Examen finalizado correctamente',
            'redirect' => $this->generateUrl('app_examen_semanal_index')
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
                
                $preguntasSeleccionadasTema[] = $pregunta;
                
                // Marcar el artículo como usado
                if ($articuloId !== null) {
                    $articulosUsados[] = $articuloId;
                }
            }
            
            $preguntasSeleccionadas = array_merge($preguntasSeleccionadas, $preguntasSeleccionadasTema);
        }
        
        // Si no se alcanzó la cantidad total, completar con preguntas aleatorias
        if (count($preguntasSeleccionadas) < $cantidadTotal) {
            $preguntasRestantes = array_filter($preguntas, function($p) use ($preguntasSeleccionadas) {
                return !in_array($p, $preguntasSeleccionadas, true);
            });
            shuffle($preguntasRestantes);
            
            $faltantes = $cantidadTotal - count($preguntasSeleccionadas);
            $preguntasSeleccionadas = array_merge($preguntasSeleccionadas, array_slice($preguntasRestantes, 0, $faltantes));
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
     * Calcula los porcentajes por tema de un examen semanal
     */
    private function calcularPorcentajesPorTema(ExamenSemanal $examenSemanal): array
    {
        $porcentajesPorTema = [];
        
        if ($examenSemanal->getMunicipio() !== null || $examenSemanal->getModoCreacion() === 'preguntas_especificas') {
            return $porcentajesPorTema;
        }
        
        // Obtener preguntas del examen
        $preguntas = $this->obtenerPreguntasExamen($examenSemanal);
        
        if (empty($preguntas)) {
            return $porcentajesPorTema;
        }
        
        // Contar preguntas por tema
        $preguntasPorTema = [];
        foreach ($preguntas as $pregunta) {
            if (method_exists($pregunta, 'getTema') && $pregunta->getTema()) {
                $temaId = $pregunta->getTema()->getId();
                if (!isset($preguntasPorTema[$temaId])) {
                    $preguntasPorTema[$temaId] = [
                        'tema' => $pregunta->getTema(),
                        'cantidad' => 0,
                    ];
                }
                $preguntasPorTema[$temaId]['cantidad']++;
            }
        }
        
        // Calcular porcentajes
        $totalPreguntas = count($preguntas);
        foreach ($preguntasPorTema as $temaId => $datos) {
            $porcentaje = $totalPreguntas > 0 ? round(($datos['cantidad'] / $totalPreguntas) * 100, 1) : 0;
            $porcentajesPorTema[$temaId] = [
                'nombre' => $datos['tema']->getNombre(),
                'cantidad' => $datos['cantidad'],
                'porcentaje' => $porcentaje,
            ];
        }
        
        return $porcentajesPorTema;
    }

    /**
     * Obtiene las preguntas de un examen semanal
     */
    private function obtenerPreguntasExamen(ExamenSemanal $examenSemanal): array
    {
        // Si el examen usa preguntas específicas, devolverlas directamente
        if ($examenSemanal->getModoCreacion() === 'preguntas_especificas') {
            if ($examenSemanal->getMunicipio()) {
                return $examenSemanal->getPreguntasMunicipales()->toArray();
            } else {
                return $examenSemanal->getPreguntas()->toArray();
            }
        }

        // Método original: obtener preguntas por temas
        $preguntas = [];
        $esMunicipal = $examenSemanal->getMunicipio() !== null;
        $temas = [];

        if ($esMunicipal) {
            // Obtener preguntas municipales
            $temasMunicipales = $examenSemanal->getTemasMunicipales()->toArray();
            if (!empty($temasMunicipales)) {
                $preguntas = $this->preguntaMunicipalRepository->findByTemasMunicipales(
                    $temasMunicipales,
                    $examenSemanal->getDificultad()
                );
            }
        } else {
            // Obtener preguntas generales
            $temas = $examenSemanal->getTemas()->toArray();
            if (!empty($temas)) {
                $preguntas = $this->preguntaRepository->createQueryBuilder('p')
                    ->where('p.dificultad = :dificultad')
                    ->andWhere('p.tema IN (:temas)')
                    ->andWhere('p.activo = :activo')
                    ->setParameter('dificultad', $examenSemanal->getDificultad())
                    ->setParameter('temas', $temas)
                    ->setParameter('activo', true)
                    ->getQuery()
                    ->getResult();
            }
        }

        // Si hay un número limitado de preguntas, usar distribución por porcentajes para exámenes generales
        if ($examenSemanal->getNumeroPreguntas() && count($preguntas) > $examenSemanal->getNumeroPreguntas()) {
            if (!$esMunicipal && !empty($temas)) {
                // Para exámenes generales, usar distribución por porcentajes si está configurada
                $preguntas = $this->distribuirPreguntasPorPorcentajes($preguntas, $temas, $examenSemanal->getNumeroPreguntas());
            } else {
                // Para exámenes municipales o si no hay temas, seleccionar aleatoriamente
                shuffle($preguntas);
                $preguntas = array_slice($preguntas, 0, $examenSemanal->getNumeroPreguntas());
            }
        }

        return $preguntas;
    }

    #[Route('/{id}', name: 'app_examen_semanal_show', methods: ['GET'], requirements: ['id' => '\d+'], priority: -1)]
    public function show(int $id): Response
    {
        $examenSemanal = $this->examenSemanalRepository->find($id);
        
        if (!$examenSemanal) {
            throw $this->createNotFoundException('Examen semanal no encontrado');
        }
        
        // Calcular porcentajes por tema si es examen general
        $porcentajesPorTema = [];
        if ($examenSemanal->getMunicipio() === null && $examenSemanal->getModoCreacion() !== 'preguntas_especificas') {
            $porcentajesPorTema = $this->calcularPorcentajesPorTema($examenSemanal);
        }
        
        return $this->render('examen_semanal/show.html.twig', [
            'examenSemanal' => $examenSemanal,
            'porcentajesPorTema' => $porcentajesPorTema,
        ]);
    }
}

