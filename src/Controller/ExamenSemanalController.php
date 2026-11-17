<?php

namespace App\Controller;

use App\Entity\Articulo;
use App\Entity\ExamenBorrador;
use App\Entity\ExamenSemanal;
use App\Entity\Ley;
use App\Entity\Pregunta;
use App\Entity\PreguntaMunicipal;
use App\Entity\Tema;
use App\Entity\TemaMunicipal;
use App\Entity\User;
use App\Form\ExamenSemanalType;
use App\Form\ExamenSemanalGeneralType;
use App\Form\ExamenSemanalMunicipalType;
use App\Form\ExamenSemanalConvocatoriaType;
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
use App\Service\DificultadExamenService;
use App\Service\ExamenSemanalPreguntasService;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
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
        private ExamenSemanalPreguntasService $examenSemanalPreguntas,
        private DificultadExamenService $dificultadExamenService,
        private ?CacheItemPoolInterface $cache = null
    ) {
    }

    #[Route('/temas-municipales/{municipioId}', name: 'app_examen_semanal_temas_municipales', methods: ['GET'])]
    public function getTemasMunicipales(int $municipioId): JsonResponse
    {
        try {
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
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error al obtener temas: ' . $e->getMessage()], 500);
        }
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

    #[Route('/municipios-convocatoria/{convocatoriaId}', name: 'app_examen_semanal_municipios_convocatoria', methods: ['GET'])]
    public function municipiosConvocatoria(int $convocatoriaId): JsonResponse
    {
        $convocatoria = $this->convocatoriaRepository->find($convocatoriaId);
        
        if (!$convocatoria) {
            return new JsonResponse(['error' => 'Convocatoria no encontrada'], 404);
        }
        
        $municipios = $convocatoria->getMunicipios();
        $municipiosArray = array_map(function($municipio) {
            return [
                'id' => $municipio->getId(),
                'nombre' => $municipio->getNombre(),
            ];
        }, $municipios->toArray());
        
        return new JsonResponse($municipiosArray);
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
                if (!$this->dificultadExamenService->acepta((string) $datos['dificultad'])) {
                    return new JsonResponse(['error' => 'Esa dificultad no está disponible.'], 400);
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
                
                // Asignar grupo si se ha proporcionado
                if (!empty($datos['grupoId'])) {
                    $grupoRepository = $this->entityManager->getRepository(\App\Entity\Grupo::class);
                    $grupo = $grupoRepository->find($datos['grupoId']);
                    if ($grupo) {
                        $examenSemanal->setGrupo($grupo);
                    }
                }

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
                
                $this->invalidarCacheExamenesSemanales();

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
        $temas = $this->temaRepository->findActivosOrderedByNombre();
        $leyes = $this->leyRepository->findActivasOrderedByNombre();
        $municipios = $this->municipioRepository->findBy(['activo' => true], ['nombre' => 'ASC']);

        return $this->render('examen_semanal/new_con_preguntas.html.twig', [
            'tipoExamen' => $tipoExamen,
            'temas' => $temas,
            'leyes' => $leyes,
            'municipios' => $municipios,
        ]);
    }

    #[Route('/new-con-preguntas-convocatoria', name: 'app_examen_semanal_new_con_preguntas_convocatoria', methods: ['GET', 'POST'], priority: 10)]
    public function newConPreguntasConvocatoria(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            try {
                $content = $request->getContent();
                $datos = json_decode($content, true);
                
                if (json_last_error() !== JSON_ERROR_NONE || !$datos) {
                    $datos = $request->request->all();
                }
                
                // Validar datos básicos del examen
                if (empty($datos['nombre']) || empty($datos['fechaApertura']) || empty($datos['fechaCierre']) || empty($datos['dificultad']) || empty($datos['convocatoriaId'])) {
                    return new JsonResponse(['error' => 'Faltan datos requeridos del examen'], 400);
                }
                if (!$this->dificultadExamenService->acepta((string) $datos['dificultad'])) {
                    return new JsonResponse(['error' => 'Esa dificultad no está disponible.'], 400);
                }

                $convocatoria = $this->convocatoriaRepository->find($datos['convocatoriaId']);
                if (!$convocatoria) {
                    return new JsonResponse(['error' => 'Convocatoria no encontrada'], 404);
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
                $examenSemanal->setConvocatoria($convocatoria);
                
                // Asignar grupo si se ha proporcionado
                if (!empty($datos['grupoId'])) {
                    $grupoRepository = $this->entityManager->getRepository(\App\Entity\Grupo::class);
                    $grupo = $grupoRepository->find($datos['grupoId']);
                    if ($grupo) {
                        $examenSemanal->setGrupo($grupo);
                    }
                }

                $this->entityManager->persist($examenSemanal);
                $this->entityManager->flush();
                
                $this->invalidarCacheExamenesSemanales();

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
        $convocatorias = $this->convocatoriaRepository->findActivasOrderedByNombre();

        return $this->render('examen_semanal/new_con_preguntas_convocatoria.html.twig', [
            'convocatorias' => $convocatorias,
        ]);
    }

    #[Route('/', name: 'app_examen_semanal_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Parámetros de paginación
        $itemsPerPage = 20;
        $page = max(1, $request->query->getInt('page', 1));
        $search = trim($request->query->get('search', ''));
        $dificultad = $request->query->get('dificultad', '');
        $estado = $request->query->get('estado', '');

        // Construir query builder
        $qb = $this->examenSemanalRepository->createQueryBuilder('e')
            ->orderBy('e.fechaCreacion', 'DESC');

        // Filtro de búsqueda por nombre
        if (!empty($search)) {
            $qb->andWhere('e.nombre LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // Filtro por dificultad
        if (!empty($dificultad) && in_array($dificultad, ['facil', 'moderada', 'dificil'])) {
            $qb->andWhere('e.dificultad = :dificultad')
               ->setParameter('dificultad', $dificultad);
        }

        // Filtro por estado
        if (!empty($estado)) {
            $now = new \DateTime();
            switch ($estado) {
                case 'disponible':
                    $qb->andWhere('e.activo = :activo')
                       ->andWhere('e.borrador = :borrador')
                       ->andWhere('e.fechaApertura <= :now')
                       ->andWhere('e.fechaCierre >= :now')
                       ->setParameter('activo', true)
                       ->setParameter('borrador', false)
                       ->setParameter('now', $now);
                    break;
                case 'pendiente':
                    $qb->andWhere('e.activo = :activo')
                       ->andWhere('e.borrador = :borrador')
                       ->andWhere('e.fechaApertura > :now')
                       ->setParameter('activo', true)
                       ->setParameter('borrador', false)
                       ->setParameter('now', $now);
                    break;
                case 'cerrado':
                    $qb->andWhere('e.borrador = :borrador')
                       ->andWhere('e.fechaCierre < :now')
                       ->setParameter('borrador', false)
                       ->setParameter('now', $now);
                    break;
                case 'inactivo':
                    $qb->andWhere('e.activo = :activo')
                       ->andWhere('e.borrador = :borrador')
                       ->setParameter('activo', false)
                       ->setParameter('borrador', false);
                    break;
                case 'borrador':
                    $qb->andWhere('e.borrador = :borrador')
                       ->setParameter('borrador', true);
                    break;
            }
        }

        // Contar total
        $totalQb = clone $qb;
        $totalItems = (int) $totalQb->select('COUNT(e.id)')
                                   ->getQuery()
                                   ->getSingleScalarResult();

        // Calcular paginación
        $totalPages = max(1, (int) ceil($totalItems / $itemsPerPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        // Obtener exámenes paginados
        $examenes = $qb->setFirstResult($offset)
                       ->setMaxResults($itemsPerPage)
                       ->getQuery()
                       ->getResult();

        $ids = array_map(static fn (ExamenSemanal $examen) => $examen->getId(), $examenes);
        $realizaciones = $this->examenSemanalRepository->contarRealizaciones($ids);

        // Solo marcamos qué filas pueden mostrar la distribución. El cálculo
        // (seleccionar preguntas de todo el temario) se hace al pulsar el botón.
        $examenesConDistribucion = [];
        foreach ($examenes as $examen) {
            if (!$examen->isBorrador() && $examen->getMunicipio() === null && $examen->getConvocatoria() === null && $examen->getModoCreacion() !== 'preguntas_especificas') {
                $examenesConDistribucion[$examen->getId()] = true;
            }
        }

        return $this->render('examen_semanal/index.html.twig', [
            'examenes' => $examenes,
            'realizaciones' => $realizaciones,
            'examenesConDistribucion' => $examenesConDistribucion,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'itemsPerPage' => $itemsPerPage,
            'search' => $search,
            'dificultad' => $dificultad,
            'estado' => $estado,
        ]);
    }

    #[Route('/{id}/porcentajes', name: 'app_examen_semanal_porcentajes', methods: ['GET'], requirements: ['id' => '\d+'], priority: 10)]
    public function porcentajes(ExamenSemanal $examenSemanal): JsonResponse
    {
        return new JsonResponse($this->calcularPorcentajesPorTema($examenSemanal));
    }

    #[Route('/{id}/continuar-borrador', name: 'app_examen_semanal_borrador_continuar', methods: ['GET'], requirements: ['id' => '\d+'], priority: 10)]
    public function continuarBorrador(ExamenSemanal $examenSemanal): Response
    {
        if (!$examenSemanal->isBorrador()) {
            $this->addFlash('error', 'Este examen ya no es un borrador.');

            return $this->redirectToRoute('app_examen_semanal_show', ['id' => $examenSemanal->getId()]);
        }

        $tipo = (string) (($examenSemanal->getBorradorMeta() ?? [])['tipo'] ?? '');
        if ($tipo === 'municipal') {
            return $this->redirectToRoute('app_pregunta_municipal_continuar_borrador_examen', ['id' => $examenSemanal->getId()]);
        }

        return $this->redirectToRoute('app_pregunta_continuar_borrador_examen', ['id' => $examenSemanal->getId()]);
    }

    #[Route('/new', name: 'app_examen_semanal_new', methods: ['GET'])]
    public function new(Request $request): Response
    {
        $request->getSession()->remove('examen_semanal_pegar');
        $request->getSession()->remove('examen_semanal_bloque');
        // Página de selección de tipo de examen
        $convocatorias = $this->convocatoriaRepository->findBy(['activo' => true], ['nombre' => 'ASC']);
        return $this->render('examen_semanal/new.html.twig', [
            'convocatorias' => $convocatorias,
        ]);
    }

    #[Route('/new-general', name: 'app_examen_semanal_new_general', methods: ['GET', 'POST'])]
    public function newGeneral(Request $request): Response
    {
        $examenSemanal = new ExamenSemanal();
        $examenSemanal->setCreadoPor($this->getUser());
        
        $form = $this->createForm(ExamenSemanalGeneralType::class, $examenSemanal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Validar que fechaCierre sea posterior a fechaApertura
            if ($examenSemanal->getFechaCierre() <= $examenSemanal->getFechaApertura()) {
                $this->addFlash('error', 'La fecha de cierre debe ser posterior a la fecha de apertura.');
                return $this->render('examen_semanal/new_general.html.twig', [
                    'examenSemanal' => $examenSemanal,
                    'form' => $form,
                ]);
            }

            $examenSemanal->setActivo(true);
            $this->entityManager->persist($examenSemanal);
            $this->entityManager->flush();
            
            $this->invalidarCacheExamenesSemanales();

            // Crear notificaciones
            $this->crearNotificaciones($examenSemanal);

            $this->addFlash('success', 'Examen semanal del temario general creado correctamente.');
            return $this->redirectToRoute('app_examen_semanal_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('examen_semanal/new_general.html.twig', [
            'examenSemanal' => $examenSemanal,
            'form' => $form,
        ]);
    }

    #[Route('/new-municipal', name: 'app_examen_semanal_new_municipal', methods: ['GET', 'POST'])]
    public function newMunicipal(Request $request): Response
    {
        $examenSemanal = new ExamenSemanal();
        $examenSemanal->setCreadoPor($this->getUser());
        
        $form = $this->createForm(ExamenSemanalMunicipalType::class, $examenSemanal);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                // Mostrar errores de validación
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                if (!empty($errors)) {
                    $this->addFlash('error', 'Por favor, corrige los siguientes errores: ' . implode(', ', $errors));
                }
                
                return $this->render('examen_semanal/new_municipal.html.twig', [
                    'examenSemanal' => $examenSemanal,
                    'form' => $form,
                ]);
            }

            // Validar que fechaCierre sea posterior a fechaApertura
            if ($examenSemanal->getFechaCierre() <= $examenSemanal->getFechaApertura()) {
                $this->addFlash('error', 'La fecha de cierre debe ser posterior a la fecha de apertura.');
                return $this->render('examen_semanal/new_municipal.html.twig', [
                    'examenSemanal' => $examenSemanal,
                    'form' => $form,
                ]);
            }

            // Obtener temas municipales del formulario (campo no mapeado)
            $temasMunicipales = $form->get('temasMunicipales')->getData();
            
            // Convertir ArrayCollection a array si es necesario
            if ($temasMunicipales instanceof \Doctrine\Common\Collections\Collection) {
                $temasMunicipales = $temasMunicipales->toArray();
            }
            
            // Validar que haya temas municipales seleccionados
            if (empty($temasMunicipales) || !is_array($temasMunicipales) || count($temasMunicipales) === 0) {
                $this->addFlash('error', 'Debes seleccionar al menos un tema municipal.');
                return $this->render('examen_semanal/new_municipal.html.twig', [
                    'examenSemanal' => $examenSemanal,
                    'form' => $form,
                ]);
            }

            // Asignar los temas municipales manualmente
            foreach ($temasMunicipales as $temaMunicipal) {
                if ($temaMunicipal instanceof \App\Entity\TemaMunicipal) {
                    $examenSemanal->addTemasMunicipale($temaMunicipal);
                }
            }

            $examenSemanal->setActivo(true);
            $this->entityManager->persist($examenSemanal);
            $this->entityManager->flush();
            
            $this->invalidarCacheExamenesSemanales();

            // Crear notificaciones
            $this->crearNotificaciones($examenSemanal);

            $this->addFlash('success', 'Examen semanal municipal creado correctamente.');
            return $this->redirectToRoute('app_examen_semanal_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('examen_semanal/new_municipal.html.twig', [
            'examenSemanal' => $examenSemanal,
            'form' => $form,
        ]);
    }

    #[Route('/new-convocatoria', name: 'app_examen_semanal_new_convocatoria', methods: ['GET', 'POST'])]
    public function newConvocatoria(Request $request): Response
    {
        $examenSemanal = new ExamenSemanal();
        $examenSemanal->setCreadoPor($this->getUser());
        
        $form = $this->createForm(ExamenSemanalConvocatoriaType::class, $examenSemanal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Validar que fechaCierre sea posterior a fechaApertura
            if ($examenSemanal->getFechaCierre() <= $examenSemanal->getFechaApertura()) {
                $this->addFlash('error', 'La fecha de cierre debe ser posterior a la fecha de apertura.');
                return $this->render('examen_semanal/new_convocatoria.html.twig', [
                    'examenSemanal' => $examenSemanal,
                    'form' => $form,
                ]);
            }

            // Validar que la convocatoria tenga municipios
            if ($examenSemanal->getConvocatoria()->getMunicipios()->isEmpty()) {
                $this->addFlash('error', 'La convocatoria seleccionada no tiene municipios asignados.');
                return $this->render('examen_semanal/new_convocatoria.html.twig', [
                    'examenSemanal' => $examenSemanal,
                    'form' => $form,
                ]);
            }

            // Añadir todos los temas municipales de todos los municipios de la convocatoria
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
                $examenSemanal->addTemasMunicipale($temaMunicipal);
            }

            $examenSemanal->setActivo(true);
            $this->entityManager->persist($examenSemanal);
            $this->entityManager->flush();
            
            $this->invalidarCacheExamenesSemanales();

            // Crear notificaciones
            $this->crearNotificaciones($examenSemanal);

            $this->addFlash('success', 'Examen semanal de convocatoria creado correctamente.');
            return $this->redirectToRoute('app_examen_semanal_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('examen_semanal/new_convocatoria.html.twig', [
            'examenSemanal' => $examenSemanal,
            'form' => $form,
        ]);
    }

    /**
     * Método auxiliar para crear notificaciones
     */
    private function crearNotificaciones(ExamenSemanal $examenSemanal): void
    {
        try {
            $grupoExamen = $examenSemanal->getGrupo();
            $profesor = $examenSemanal->getCreadoPor();
            
            if ($grupoExamen) {
                // Si el examen tiene grupo, solo notificar a alumnos de ese grupo
                $alumnos = $grupoExamen->getAlumnos();
            } else {
                // Si no tiene grupo, notificar a todos los alumnos
                $alumnos = $this->userRepository->createQueryBuilder('u')
                    ->where('u.roles LIKE :role')
                    ->andWhere('u.activo = :activo')
                    ->setParameter('role', '%ROLE_USER%')
                    ->setParameter('activo', true)
                    ->getQuery()
                    ->getResult();
            }

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
    }

    #[Route('/new-old', name: 'app_examen_semanal_new_old', methods: ['GET', 'POST'])]
    public function newOld(Request $request): Response
    {
        $examenSemanal = new ExamenSemanal();
        $examenSemanal->setCreadoPor($this->getUser());
        
        $form = $this->createForm(ExamenSemanalType::class, $examenSemanal);
        $form->handleRequest($request);

        // En modo creación, los campos básicos (nombre, fechaApertura, fechaCierre, dificultad)
        // no están mapeados porque se usan campos separados (nombreMunicipal, fechaAperturaMunicipal, etc.)
        // Por lo tanto, la validación del formulario puede fallar, pero aún podemos procesar los datos
        // Verificar si hay errores reales en campos mapeados (municipio, temas, temasMunicipales, convocatoria, grupo)
        $hasRealErrors = false;
        if ($form->isSubmitted() && !$form->isValid()) {
            $errors = $form->getErrors(true);
            foreach ($errors as $error) {
                $origin = $error->getOrigin();
                // Solo considerar errores de campos que están mapeados a la entidad
                if ($origin && $origin->getConfig() && $origin->getConfig()->getMapped()) {
                    $fieldName = $origin->getName();
                    // Los campos mapeados son: municipio, temas, temasMunicipales, convocatoria, grupo
                    $mappedFields = ['municipio', 'temas', 'temasMunicipales', 'convocatoria', 'grupo'];
                    if (in_array($fieldName, $mappedFields)) {
                        $hasRealErrors = true;
                        break;
                    }
                }
            }
            
            // Si hay errores reales en campos mapeados, mostrar el formulario con errores
            if ($hasRealErrors) {
                $convocatorias = $this->convocatoriaRepository->findBy(['activo' => true], ['nombre' => 'ASC']);
                return $this->render('examen_semanal/new.html.twig', [
                    'examenSemanal' => $examenSemanal,
                    'form' => $form,
                    'convocatorias' => $convocatorias,
                ]);
            }
        }

        // Si el formulario está enviado y es válido, o si no hay errores reales en campos mapeados,
        // procesar la creación del examen
        // Nota: En modo creación, ignoramos errores de validación de campos no mapeados
        if ($form->isSubmitted()) {
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
                    'convocatorias' => $this->convocatoriaRepository->findActivasOrderedByNombre(),
                ]);
            }

            // Validar examen de 30 temas: temas son obligatorios
            if ($tieneTemasGenerales && $examenSemanal->getTemas()->isEmpty()) {
                $this->addFlash('error', 'Para crear un examen de 30 temas, debes seleccionar al menos un tema del temario general.');
                return $this->render('examen_semanal/new.html.twig', [
                    'examenSemanal' => $examenSemanal,
                    'form' => $form,
                    'convocatorias' => $this->convocatoriaRepository->findActivasOrderedByNombre(),
                ]);
            }

            // Validar examen de convocatoria: convocatoria es obligatoria
            if ($tieneConvocatoria && $examenSemanal->getConvocatoria() === null) {
                $this->addFlash('error', 'Para crear un examen de convocatoria, debes seleccionar una convocatoria.');
                return $this->render('examen_semanal/new.html.twig', [
                    'examenSemanal' => $examenSemanal,
                    'form' => $form,
                    'convocatorias' => $this->convocatoriaRepository->findActivasOrderedByNombre(),
                ]);
            }

            // Validar examen municipal: municipio y temas municipales son obligatorios
            if ($examenSemanal->getMunicipio() !== null) {
                if ($examenSemanal->getTemasMunicipales()->isEmpty()) {
                    $this->addFlash('error', 'Para crear un examen municipal, debes seleccionar un municipio y al menos un tema municipal.');
                    return $this->render('examen_semanal/new.html.twig', [
                        'examenSemanal' => $examenSemanal,
                        'form' => $form,
                        'convocatorias' => $this->convocatoriaRepository->findActivasOrderedByNombre(),
                    ]);
                }
            } elseif (!$examenSemanal->getTemasMunicipales()->isEmpty()) {
                // Si hay temas municipales pero no municipio
                $this->addFlash('error', 'Si seleccionas temas municipales, debes seleccionar también un municipio.');
                return $this->render('examen_semanal/new.html.twig', [
                    'examenSemanal' => $examenSemanal,
                    'form' => $form,
                    'convocatorias' => $this->convocatoriaRepository->findActivasOrderedByNombre(),
                ]);
            }

            // Validar que si se selecciona convocatoria, tenga municipios
            if ($examenSemanal->getConvocatoria() && $examenSemanal->getConvocatoria()->getMunicipios()->isEmpty()) {
                $this->addFlash('error', 'La convocatoria seleccionada no tiene municipios asignados.');
                return $this->render('examen_semanal/new.html.twig', [
                    'examenSemanal' => $examenSemanal,
                    'form' => $form,
                    'convocatorias' => $this->convocatoriaRepository->findActivasOrderedByNombre(),
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
                
                // Asignar grupo si se ha seleccionado
                $grupo = $form->get('grupo')->getData();
                if ($grupo) {
                    $examenGeneral->setGrupo($grupo);
                }
                
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
                
                // Asignar grupo si se ha seleccionado
                $grupo = $form->get('grupo')->getData();
                if ($grupo) {
                    $examenMunicipal->setGrupo($grupo);
                }
                
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
                
                // Asignar grupo si se ha seleccionado
                $grupo = $form->get('grupo')->getData();
                if ($grupo) {
                    $examenConvocatoria->setGrupo($grupo);
                }
                
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
            
            $this->invalidarCacheExamenesSemanales();

            // Crear notificaciones para todos los alumnos (filtrando por grupo si el examen tiene uno)
            try {
                foreach ($examenesCreados as $examenCreado) {
                    $grupoExamen = $examenCreado->getGrupo();
                    
                    if ($grupoExamen) {
                        // Si el examen tiene grupo, solo notificar a alumnos de ese grupo
                        $alumnos = $grupoExamen->getAlumnos();
                    } else {
                        // Si no tiene grupo, notificar a todos los alumnos
                        $alumnos = $this->userRepository->createQueryBuilder('u')
                            ->where('u.roles LIKE :role')
                            ->andWhere('u.activo = :activo')
                            ->setParameter('role', '%ROLE_USER%')
                            ->setParameter('activo', true)
                            ->getQuery()
                            ->getResult();
                    }

                    foreach ($alumnos as $alumno) {
                        // Verificar que no sea profesor ni admin, y que no sea el mismo que el profesor que crea el examen
                        if (!in_array('ROLE_PROFESOR', $alumno->getRoles()) 
                            && !in_array('ROLE_ADMIN', $alumno->getRoles())
                            && $alumno->getId() !== $profesor->getId()) {
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

                return $this->renderEditar($examenSemanal, $form);
            }

            $this->entityManager->flush();

            $this->invalidarCacheExamenesSemanales();

            $this->addFlash('success', 'Examen semanal actualizado correctamente.');
            return $this->redirectToRoute('app_examen_semanal_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderEditar($examenSemanal, $form);
    }

    #[Route('/{id}/preguntas', name: 'app_examen_semanal_pregunta_anadir', methods: ['POST'], requirements: ['id' => '\d+'], priority: 10)]
    public function anadirPregunta(Request $request, ExamenSemanal $examenSemanal): Response
    {
        $bloqueo = $this->rechazarSiNoSePuedenEditarPreguntas($examenSemanal, $request);
        if ($bloqueo instanceof Response) {
            return $bloqueo;
        }

        $datos = $this->datosPreguntaDesdeRequest($request);
        $pregunta = $this->esPreguntaMunicipal($examenSemanal) ? new PreguntaMunicipal() : new Pregunta();
        $error = $this->validarDatosPregunta($datos) ?? $this->asignarUbicacion($examenSemanal, $pregunta, $request);
        if ($error !== null) {
            return $this->errorPreguntas($examenSemanal, $request, 'nueva', $error);
        }

        $this->aplicarDatosPregunta($pregunta, $datos);
        $pregunta->setActivo(true);
        $this->fijarPreguntas($examenSemanal);
        $this->entityManager->persist($pregunta);
        if ($pregunta instanceof PreguntaMunicipal) {
            $examenSemanal->addPreguntasMunicipale($pregunta);
        } else {
            $examenSemanal->addPregunta($pregunta);
        }
        $this->sincronizarTemasDesdePreguntas($examenSemanal);
        $this->entityManager->flush();
        $this->invalidarCacheExamenesSemanales();

        $this->addFlash('success', 'Pregunta añadida al examen.');

        return $this->volverAEditarPreguntas($examenSemanal);
    }

    #[Route('/{id}/preguntas/{preguntaId}', name: 'app_examen_semanal_pregunta_actualizar', methods: ['POST'], requirements: ['id' => '\d+', 'preguntaId' => '\d+'], priority: 10)]
    public function actualizarPregunta(Request $request, ExamenSemanal $examenSemanal, int $preguntaId): Response
    {
        $bloqueo = $this->rechazarSiNoSePuedenEditarPreguntas($examenSemanal, $request);
        if ($bloqueo instanceof Response) {
            return $bloqueo;
        }

        $pregunta = $this->buscarPreguntaEditable($examenSemanal, $preguntaId);
        if ($pregunta === null) {
            $this->addFlash('error', 'Esa pregunta no está en este examen.');

            return $this->volverAEditarPreguntas($examenSemanal);
        }

        $datos = $this->datosPreguntaDesdeRequest($request);
        $error = $this->validarDatosPregunta($datos) ?? $this->asignarUbicacion($examenSemanal, $pregunta, $request);
        if ($error !== null) {
            return $this->errorPreguntas($examenSemanal, $request, 'p'.$preguntaId, $error);
        }

        $this->aplicarDatosPregunta($pregunta, $datos);

        $this->fijarPreguntas($examenSemanal);
        $this->sincronizarTemasDesdePreguntas($examenSemanal);
        $this->entityManager->flush();
        $this->invalidarCacheExamenesSemanales();

        $this->addFlash('success', 'Pregunta actualizada.');

        return $this->volverAEditarPreguntas($examenSemanal);
    }

    #[Route('/{id}/preguntas/{preguntaId}/quitar', name: 'app_examen_semanal_pregunta_quitar', methods: ['POST'], requirements: ['id' => '\d+', 'preguntaId' => '\d+'], priority: 10)]
    public function quitarPregunta(Request $request, ExamenSemanal $examenSemanal, int $preguntaId): Response
    {
        $bloqueo = $this->rechazarSiNoSePuedenEditarPreguntas($examenSemanal, $request);
        if ($bloqueo instanceof Response) {
            return $bloqueo;
        }

        $pregunta = $this->buscarPreguntaEditable($examenSemanal, $preguntaId);
        if ($pregunta === null) {
            $this->addFlash('error', 'Esa pregunta no está en este examen.');

            return $this->volverAEditarPreguntas($examenSemanal);
        }

        if (count($this->preguntasEditables($examenSemanal)) <= 1) {
            $this->addFlash('error', 'El examen tiene que conservar al menos una pregunta.');

            return $this->volverAEditarPreguntas($examenSemanal);
        }

        $this->fijarPreguntas($examenSemanal);
        if ($pregunta instanceof PreguntaMunicipal) {
            $examenSemanal->removePreguntasMunicipale($pregunta);
        } else {
            $examenSemanal->removePregunta($pregunta);
        }
        $this->sincronizarTemasDesdePreguntas($examenSemanal);
        $this->entityManager->flush();
        $this->invalidarCacheExamenesSemanales();

        $this->addFlash('success', 'Pregunta eliminada del examen.');

        return $this->volverAEditarPreguntas($examenSemanal);
    }

    #[Route('/{id}', name: 'app_examen_semanal_delete', methods: ['POST'])]
    public function delete(Request $request, ExamenSemanal $examenSemanal): Response
    {
        if ($this->isCsrfTokenValid('delete'.$examenSemanal->getId(), $request->getPayload()->getString('_token'))) {
            // Verificar si hay exámenes asociados antes de intentar eliminar
            if (!$examenSemanal->getExamenes()->isEmpty()) {
                $this->addFlash('error', 'No se puede eliminar el examen semanal porque tiene exámenes realizados por alumnos asociados. Elimina primero los exámenes relacionados.');
                return $this->redirectToRoute('app_examen_semanal_index', [], Response::HTTP_SEE_OTHER);
            }

            try {
                $this->entityManager->remove($examenSemanal);
                $this->entityManager->flush();
                
                $this->invalidarCacheExamenesSemanales();
                
                $this->addFlash('success', 'Examen semanal eliminado correctamente.');
            } catch (ForeignKeyConstraintViolationException $e) {
                $this->addFlash('error', 'No se puede eliminar el examen semanal porque tiene datos relacionados. Por favor, elimina primero los exámenes asociados.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error al eliminar el examen semanal: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_examen_semanal_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/pdf', name: 'app_examen_semanal_pdf', methods: ['GET'])]
    public function generarPdf(ExamenSemanal $examenSemanal): Response
    {
        return $this->responderVistaImprimible($examenSemanal, false);
    }

    #[Route('/{id}/pdf-respuestas', name: 'app_examen_semanal_pdf_respuestas', methods: ['GET'])]
    public function generarPdfRespuestas(ExamenSemanal $examenSemanal): Response
    {
        return $this->responderVistaImprimible($examenSemanal, true);
    }

    private function responderVistaImprimible(ExamenSemanal $examenSemanal, bool $mostrarRespuestas): Response
    {
        $preguntas = $this->obtenerPreguntasExamen($examenSemanal);

        if ($preguntas === []) {
            $this->addFlash('error', 'No hay preguntas disponibles para este examen semanal.');

            return $this->redirectToRoute('app_examen_semanal_index', [], Response::HTTP_SEE_OTHER);
        }

        $dificultadNombre = match ($examenSemanal->getDificultad()) {
            'facil' => 'facil',
            'moderada' => 'moderada',
            'dificil' => 'dificil',
            default => 'sin_dificultad',
        };
        $prefijo = $mostrarRespuestas ? 'respuestas_' : 'examen_';
        $nombreArchivo = $prefijo . preg_replace('/[^a-z0-9]+/', '_', strtolower($examenSemanal->getNombre())) . '_' . $dificultadNombre;

        return $this->render('examen_semanal/pdf_examen.html.twig', [
            'examenSemanal' => $examenSemanal,
            'preguntas' => $preguntas,
            'mostrarRespuestas' => $mostrarRespuestas,
            'nombreArchivo' => $nombreArchivo,
        ]);
    }

    #[Route('/{id}/agregar-pregunta', name: 'app_examen_semanal_agregar_pregunta', methods: ['POST'])]
    public function agregarPregunta(ExamenSemanal $examenSemanal, Request $request): JsonResponse
    {
        if ($examenSemanal->getModoCreacion() !== 'preguntas_especificas') {
            return new JsonResponse(['error' => 'Este examen no permite agregar preguntas específicas'], 400);
        }

        $datos = json_decode($request->getContent(), true);
        
        // Si es examen de convocatoria, puede tener preguntas municipales con municipio seleccionado por pregunta
        if ($examenSemanal->getConvocatoria()) {
            // Pregunta municipal para convocatoria (municipio se selecciona al crear la pregunta)
            $errores = $this->preguntaService->validarDatosPreguntaMunicipal($datos);
            if (!empty($errores)) {
                return new JsonResponse(['error' => implode(', ', $errores)], 400);
            }

            if (empty($datos['municipioId'])) {
                return new JsonResponse(['error' => 'Debes seleccionar un municipio'], 400);
            }

            $municipio = $this->municipioRepository->find($datos['municipioId']);
            if (!$municipio) {
                return new JsonResponse(['error' => 'Municipio no encontrado'], 404);
            }

            // Verificar que el municipio pertenece a la convocatoria
            if (!$examenSemanal->getConvocatoria()->getMunicipios()->contains($municipio)) {
                return new JsonResponse(['error' => 'El municipio seleccionado no pertenece a esta convocatoria'], 400);
            }

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
        } elseif ($examenSemanal->getMunicipio()) {
            // Pregunta municipal (municipio fijo del examen)
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
        
        $this->invalidarCacheExamenesSemanales();

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

        // Si es examen de convocatoria o municipal, las preguntas son municipales
        if ($examenSemanal->getConvocatoria() || $examenSemanal->getMunicipio()) {
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
        
        $this->invalidarCacheExamenesSemanales();

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
        $totalPreguntas = ($examenSemanal->getConvocatoria() || $examenSemanal->getMunicipio())
            ? $examenSemanal->getPreguntasMunicipales()->count()
            : $examenSemanal->getPreguntas()->count();

        if ($totalPreguntas === 0) {
            return new JsonResponse(['error' => 'El examen debe tener al menos una pregunta'], 400);
        }

        // Actualizar número de preguntas
        $examenSemanal->setNumeroPreguntas($totalPreguntas);
        $this->entityManager->flush();
        
        $this->invalidarCacheExamenesSemanales();

        // Crear notificaciones para todos los alumnos (filtrando por grupo si el examen tiene uno)
        try {
            $grupoExamen = $examenSemanal->getGrupo();
            
            if ($grupoExamen) {
                // Si el examen tiene grupo, solo notificar a alumnos de ese grupo
                $alumnos = $grupoExamen->getAlumnos();
            } else {
                // Si no tiene grupo, notificar a todos los alumnos
                $alumnos = $this->userRepository->createQueryBuilder('u')
                    ->where('u.roles LIKE :role')
                    ->andWhere('u.activo = :activo')
                    ->setParameter('role', '%ROLE_USER%')
                    ->setParameter('activo', true)
                    ->getQuery()
                    ->getResult();
            }

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
     * Calcula los porcentajes por tema de un examen semanal
     */
    private function calcularPorcentajesPorTema(ExamenSemanal $examenSemanal): array
    {
        $porcentajesPorTema = [];
        
        if ($examenSemanal->getMunicipio() !== null || $examenSemanal->getConvocatoria() !== null || $examenSemanal->getModoCreacion() === 'preguntas_especificas') {
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
        return $this->examenSemanalPreguntas->paraExamen($examenSemanal);
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
        if ($examenSemanal->getMunicipio() === null && $examenSemanal->getConvocatoria() === null && $examenSemanal->getModoCreacion() !== 'preguntas_especificas') {
            $porcentajesPorTema = $this->calcularPorcentajesPorTema($examenSemanal);
        }
        
        // Ordenar exámenes por nota (de mayor a menor)
        $examenes = $examenSemanal->getExamenes()->toArray();
        usort($examenes, function($a, $b) {
            // Ordenar por nota descendente (mayor nota primero)
            return (float)$b->getNota() <=> (float)$a->getNota();
        });

        $usuarioActual = $this->getUser();
        $esAdmin = $this->isGranted('ROLE_ADMIN');
        $esCreador = $usuarioActual instanceof User
            && $examenSemanal->getCreadoPor()?->getId() === $usuarioActual->getId();
        $alumnosIds = [];
        if ($usuarioActual instanceof User) {
            foreach ($usuarioActual->getAlumnos() as $alumno) {
                $alumnosIds[$alumno->getId()] = true;
            }
        }
        $puedeVerDetalle = [];
        foreach ($examenes as $examenRealizado) {
            $alumnoId = $examenRealizado->getUsuario()?->getId();
            $puedeVerDetalle[$examenRealizado->getId()] = $esAdmin
                || $esCreador
                || ($alumnoId !== null && isset($alumnosIds[$alumnoId]));
        }

        return $this->render('examen_semanal/show.html.twig', [
            'examenSemanal' => $examenSemanal,
            'porcentajesPorTema' => $porcentajesPorTema,
            'examenesOrdenados' => $examenes,
            'puedeVerDetalle' => $puedeVerDetalle,
        ]);
    }

    private function renderEditar(ExamenSemanal $examenSemanal, $form, string $formularioPregunta = '', array $valoresPregunta = []): Response
    {
        $motivo = $this->motivoBloqueoPreguntas($examenSemanal);
        $puedeEditar = $motivo === null && !$examenSemanal->isBorrador();
        $municipal = $this->esPreguntaMunicipal($examenSemanal);
        $temasMunicipales = [];
        if ($puedeEditar && $municipal && $examenSemanal->getMunicipio() !== null) {
            $temasMunicipales = $this->temaMunicipalRepository->findByMunicipio($examenSemanal->getMunicipio());
        }

        return $this->render('examen_semanal/edit.html.twig', [
            'examenSemanal' => $examenSemanal,
            'form' => $form,
            'puedeEditarPreguntas' => $puedeEditar,
            'motivoPreguntas' => $motivo,
            'preguntasExamen' => $puedeEditar ? $this->preguntasEditables($examenSemanal) : [],
            'temas' => $puedeEditar && !$municipal ? $this->temaRepository->findActivosOrderedByNombre() : [],
            'temasMunicipales' => $temasMunicipales,
            'formularioPregunta' => $formularioPregunta,
            'valoresPregunta' => $valoresPregunta,
            'dificultades' => [
                'facil' => 'Fácil',
                'moderada' => 'Moderada',
                'dificil' => 'Difícil',
                'indeterminado' => 'Indeterminado',
            ],
        ]);
    }

    private function rechazarSiNoSePuedenEditarPreguntas(ExamenSemanal $examenSemanal, Request $request): ?Response
    {
        if (!$this->isCsrfTokenValid('examen_preguntas'.$examenSemanal->getId(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'No se ha podido guardar el cambio. Vuelve a intentarlo.');

            return $this->volverAEditarPreguntas($examenSemanal);
        }

        $motivo = $this->motivoBloqueoPreguntas($examenSemanal);
        if ($examenSemanal->isBorrador()) {
            $motivo = 'Las preguntas de un borrador se cambian al continuar el borrador.';
        }
        if ($motivo !== null) {
            $this->addFlash('error', $motivo);

            return $this->volverAEditarPreguntas($examenSemanal);
        }

        return null;
    }

    private function motivoBloqueoPreguntas(ExamenSemanal $examenSemanal): ?string
    {
        if ($examenSemanal->isBorrador()) {
            return null;
        }
        if (!$examenSemanal->getExamenes()->isEmpty()) {
            return 'Ya hay alumnos que han realizado este examen. Las preguntas no se pueden cambiar.';
        }

        $borradores = (int) $this->entityManager->getRepository(ExamenBorrador::class)->count(['examenSemanal' => $examenSemanal]);
        if ($borradores > 0) {
            return 'Hay un alumno que ya ha empezado este examen. Las preguntas no se pueden cambiar.';
        }

        return null;
    }

    private function esPreguntaMunicipal(ExamenSemanal $examenSemanal): bool
    {
        return $examenSemanal->getMunicipio() !== null || $examenSemanal->getConvocatoria() !== null;
    }

    /**
     * @return array<int, Pregunta|PreguntaMunicipal>
     */
    private function preguntasEditables(ExamenSemanal $examenSemanal): array
    {
        if ($examenSemanal->getModoCreacion() === 'preguntas_especificas') {
            $preguntas = $this->esPreguntaMunicipal($examenSemanal)
                ? $examenSemanal->getPreguntasMunicipales()->toArray()
                : $examenSemanal->getPreguntas()->toArray();
            usort($preguntas, static fn ($a, $b) => $a->getId() <=> $b->getId());

            return array_values($preguntas);
        }

        return $this->examenSemanalPreguntas->paraExamen($examenSemanal);
    }

    private function buscarPreguntaEditable(ExamenSemanal $examenSemanal, int $preguntaId): Pregunta|PreguntaMunicipal|null
    {
        foreach ($this->preguntasEditables($examenSemanal) as $pregunta) {
            if ($pregunta->getId() === $preguntaId) {
                return $pregunta;
            }
        }

        return null;
    }

    /**
     * Deja la lista que verán los alumnos guardada en el examen.
     * Así, añadir o quitar una pregunta no vuelve a mezclar el temario.
     */
    private function fijarPreguntas(ExamenSemanal $examenSemanal): void
    {
        if ($examenSemanal->getModoCreacion() === 'preguntas_especificas') {
            return;
        }

        $preguntas = $this->examenSemanalPreguntas->paraExamen($examenSemanal);
        if ($this->esPreguntaMunicipal($examenSemanal)) {
            foreach ($examenSemanal->getPreguntasMunicipales()->toArray() as $pregunta) {
                $examenSemanal->removePreguntasMunicipale($pregunta);
            }
            foreach ($preguntas as $pregunta) {
                if ($pregunta instanceof PreguntaMunicipal) {
                    $examenSemanal->addPreguntasMunicipale($pregunta);
                }
            }
        } else {
            foreach ($examenSemanal->getPreguntas()->toArray() as $pregunta) {
                $examenSemanal->removePregunta($pregunta);
            }
            foreach ($preguntas as $pregunta) {
                if ($pregunta instanceof Pregunta) {
                    $examenSemanal->addPregunta($pregunta);
                }
            }
        }

        $examenSemanal->setModoCreacion('preguntas_especificas');
    }

    private function sincronizarTemasDesdePreguntas(ExamenSemanal $examenSemanal): void
    {
        if ($examenSemanal->getModoCreacion() !== 'preguntas_especificas') {
            return;
        }

        if ($this->esPreguntaMunicipal($examenSemanal)) {
            $actuales = [];
            foreach ($examenSemanal->getPreguntasMunicipales() as $pregunta) {
                $tema = $pregunta->getTemaMunicipal();
                if ($tema !== null) {
                    $actuales[$tema->getId()] = $tema;
                }
            }
            foreach ($examenSemanal->getTemasMunicipales()->toArray() as $tema) {
                if (!isset($actuales[$tema->getId()])) {
                    $examenSemanal->removeTemasMunicipale($tema);
                }
            }
            foreach ($actuales as $tema) {
                $examenSemanal->addTemasMunicipale($tema);
            }
            $examenSemanal->setNumeroPreguntas($examenSemanal->getPreguntasMunicipales()->count());

            return;
        }

        $actuales = [];
        foreach ($examenSemanal->getPreguntas() as $pregunta) {
            $tema = $pregunta->getTema();
            if ($tema !== null) {
                $actuales[$tema->getId()] = $tema;
            }
        }
        foreach ($examenSemanal->getTemas()->toArray() as $tema) {
            if (!isset($actuales[$tema->getId()])) {
                $examenSemanal->removeTema($tema);
            }
        }
        foreach ($actuales as $tema) {
            $examenSemanal->addTema($tema);
        }
        $examenSemanal->setNumeroPreguntas($examenSemanal->getPreguntas()->count());
    }

    /**
     * @param array<string, string> $datos
     */
    private function aplicarDatosPregunta(Pregunta|PreguntaMunicipal $pregunta, array $datos): void
    {
        $pregunta->setTexto($datos['texto']);
        $pregunta->setOpcionA($datos['opcionA']);
        $pregunta->setOpcionB($datos['opcionB']);
        $pregunta->setOpcionC($datos['opcionC']);
        $pregunta->setOpcionD($datos['opcionD']);
        $pregunta->setRespuestaCorrecta($datos['respuestaCorrecta']);
        $pregunta->setDificultad($datos['dificultad']);
        $pregunta->setRetroalimentacion($datos['retroalimentacion'] !== '' ? $datos['retroalimentacion'] : null);
    }

    private function asignarUbicacion(ExamenSemanal $examenSemanal, Pregunta|PreguntaMunicipal $pregunta, Request $request): ?string
    {
        if ($pregunta instanceof PreguntaMunicipal) {
            return $this->asignarUbicacionMunicipal($examenSemanal, $pregunta, $request);
        }

        return $this->asignarUbicacionGeneral($pregunta, $request);
    }

    private function asignarUbicacionGeneral(Pregunta $pregunta, Request $request): ?string
    {
        $tema = $this->temaRepository->find((int) $request->request->get('temaId'));
        $ley = $this->leyRepository->find((int) $request->request->get('leyId'));
        $articulo = $this->articuloRepository->find((int) $request->request->get('articuloId'));
        if (!$tema instanceof Tema || !$ley instanceof Ley || !$articulo instanceof Articulo) {
            return 'Selecciona tema, ley y artículo.';
        }
        if ($articulo->getLey()?->getId() !== $ley->getId()) {
            return 'El artículo no pertenece a la ley seleccionada.';
        }

        $sinCambios = $pregunta->getId() !== null
            && $pregunta->getTema()?->getId() === $tema->getId()
            && $pregunta->getLey()?->getId() === $ley->getId()
            && $pregunta->getArticulo()?->getId() === $articulo->getId();
        if (!$sinCambios && !$tema->getLeyes()->contains($ley)) {
            return 'La ley no pertenece al tema seleccionado.';
        }

        $pregunta->setTema($tema);
        $pregunta->setLey($ley);
        $pregunta->setArticulo($articulo);

        return null;
    }

    private function asignarUbicacionMunicipal(ExamenSemanal $examenSemanal, PreguntaMunicipal $pregunta, Request $request): ?string
    {
        if ($examenSemanal->getConvocatoria() !== null) {
            $municipio = $this->municipioRepository->find((int) $request->request->get('municipioId'));
            if ($municipio === null || !$examenSemanal->getConvocatoria()->getMunicipios()->contains($municipio)) {
                return 'Selecciona un municipio de la convocatoria.';
            }
        } else {
            $municipio = $examenSemanal->getMunicipio();
            if ($municipio === null) {
                return 'Este examen no tiene municipio.';
            }
        }

        $tema = $this->temaMunicipalRepository->find((int) $request->request->get('temaMunicipalId'));
        if (!$tema instanceof TemaMunicipal || $tema->getMunicipio()?->getId() !== $municipio->getId()) {
            return 'Selecciona un tema municipal válido.';
        }

        $pregunta->setMunicipio($municipio);
        $pregunta->setTemaMunicipal($tema);

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function datosPreguntaDesdeRequest(Request $request): array
    {
        return [
            'texto' => trim((string) $request->request->get('texto', '')),
            'opcionA' => trim((string) $request->request->get('opcionA', '')),
            'opcionB' => trim((string) $request->request->get('opcionB', '')),
            'opcionC' => trim((string) $request->request->get('opcionC', '')),
            'opcionD' => trim((string) $request->request->get('opcionD', '')),
            'respuestaCorrecta' => strtoupper(trim((string) $request->request->get('respuestaCorrecta', ''))),
            'retroalimentacion' => trim((string) $request->request->get('retroalimentacion', '')),
            'dificultad' => trim((string) $request->request->get('dificultad', '')),
        ];
    }

    /**
     * @param array<string, string> $datos
     */
    private function validarDatosPregunta(array $datos): ?string
    {
        if ($datos['texto'] === '') {
            return 'Escribe el enunciado de la pregunta.';
        }
        foreach (['A', 'B', 'C', 'D'] as $letra) {
            if ($datos['opcion'.$letra] === '') {
                return 'Escribe la opción '.$letra.'.';
            }
        }
        if (!in_array($datos['respuestaCorrecta'], ['A', 'B', 'C', 'D'], true)) {
            return 'La respuesta correcta debe ser A, B, C o D.';
        }
        if (!in_array($datos['dificultad'], ['facil', 'moderada', 'dificil', 'indeterminado'], true)) {
            return 'Selecciona la dificultad de la pregunta.';
        }

        return null;
    }

    private function errorPreguntas(ExamenSemanal $examenSemanal, Request $request, string $formulario, string $mensaje): Response
    {
        $this->addFlash('error', $mensaje);
        $form = $this->createForm(ExamenSemanalType::class, $examenSemanal, [
            'is_edit_mode' => true,
            'examen_semanal' => $examenSemanal,
        ]);

        return $this->renderEditar($examenSemanal, $form, $formulario, [
            'texto' => trim((string) $request->request->get('texto', '')),
            'opcionA' => trim((string) $request->request->get('opcionA', '')),
            'opcionB' => trim((string) $request->request->get('opcionB', '')),
            'opcionC' => trim((string) $request->request->get('opcionC', '')),
            'opcionD' => trim((string) $request->request->get('opcionD', '')),
            'respuestaCorrecta' => strtoupper(trim((string) $request->request->get('respuestaCorrecta', ''))),
            'retroalimentacion' => trim((string) $request->request->get('retroalimentacion', '')),
            'dificultad' => trim((string) $request->request->get('dificultad', '')),
            'temaId' => (string) $request->request->get('temaId', ''),
            'leyId' => (string) $request->request->get('leyId', ''),
            'articuloId' => (string) $request->request->get('articuloId', ''),
            'temaMunicipalId' => (string) $request->request->get('temaMunicipalId', ''),
            'municipioId' => (string) $request->request->get('municipioId', ''),
        ]);
    }

    private function volverAEditarPreguntas(ExamenSemanal $examenSemanal): Response
    {
        return $this->redirect($this->generateUrl('app_examen_semanal_edit', ['id' => $examenSemanal->getId()]).'#preguntas-examen');
    }

    /**
     * Invalida el caché de exámenes semanales incrementando la versión
     */
    private function invalidarCacheExamenesSemanales(): void
    {
        if (!$this->cache) {
            return;
        }

        // Incrementar versión del caché de exámenes semanales
        $versionKey = 'examenes_semanales_version';
        $versionItem = $this->cache->getItem($versionKey);
        $version = $versionItem->get() ?? 0;
        $versionItem->set($version + 1);
        $versionItem->expiresAfter(86400); // 24 horas
        $this->cache->save($versionItem);
    }
}

