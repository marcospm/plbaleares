<?php

namespace App\Controller;

use App\Entity\Plantilla;
use App\Entity\PlantillaMunicipal;
use App\Entity\User;
use App\Repository\PlantillaRepository;
use App\Repository\PlantillaMunicipalRepository;
use App\Repository\PreguntaRepository;
use App\Repository\PreguntaMunicipalRepository;
use App\Repository\ConvocatoriaRepository;
use App\Repository\MunicipioRepository;
use App\Repository\ExamenBorradorRepository;
use App\Repository\TemaRepository;
use App\Repository\TemaMunicipalRepository;
use App\Entity\ExamenBorrador;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/examen-plantilla')]
#[IsGranted('ROLE_USER')]
class ExamenPlantillaController extends AbstractController
{
    public function __construct(
        private PlantillaRepository $plantillaRepository,
        private PlantillaMunicipalRepository $plantillaMunicipalRepository,
        private PreguntaRepository $preguntaRepository,
        private PreguntaMunicipalRepository $preguntaMunicipalRepository,
        private ConvocatoriaRepository $convocatoriaRepository,
        private MunicipioRepository $municipioRepository,
        private ExamenBorradorRepository $examenBorradorRepository,
        private TemaRepository $temaRepository,
        private TemaMunicipalRepository $temaMunicipalRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/iniciar', name: 'app_examen_plantilla_iniciar', methods: ['GET'])]
    public function iniciar(Request $request): Response
    {
        $user = $this->getUser();
        
        // Obtener borradores de tipo "plantilla" del usuario
        $borradores = [];
        if ($user) {
            $borradores = $this->examenBorradorRepository->findBy([
                'usuario' => $user,
                'tipoExamen' => 'plantilla'
            ], ['fechaActualizacion' => 'DESC']);
        }
        
        // Verificar si el usuario tiene municipios activos
        $tieneMunicipiosActivos = false;
        if ($user) {
            $municipiosAccesibles = $user->getMunicipiosAccesibles();
            $tieneMunicipiosActivos = $municipiosAccesibles->count() > 0;
        }
        
        // Verificar si el usuario tiene convocatorias activas
        $tieneConvocatoriasActivas = false;
        if ($user) {
            $convocatoriasActivas = $this->convocatoriaRepository->findByUsuario($user);
            $tieneConvocatoriasActivas = count($convocatoriasActivas) > 0;
        }
        
        // Preparar datos adicionales para los borradores (municipios y convocatorias)
        $municipios = [];
        $convocatorias = [];
        if ($user) {
            $municipiosAccesibles = $user->getMunicipiosAccesibles();
            foreach ($municipiosAccesibles as $municipio) {
                $municipios[$municipio->getId()] = $municipio;
            }
            
            $convocatoriasActivas = $this->convocatoriaRepository->findByUsuario($user);
            foreach ($convocatoriasActivas as $convocatoria) {
                $convocatorias[$convocatoria->getId()] = $convocatoria;
            }
        }

        // Preparar información sobre temas disponibles por municipio/convocatoria
        $temasPorMunicipio = [];
        $temasPorConvocatoria = [];
        $municipiosData = [];
        $convocatoriasData = [];
        
        if ($user) {
            // Verificar temas disponibles por municipio y preparar datos
            // Obtener todos los municipios accesibles para verificar sus temas
            $municipiosAccesibles = $user->getMunicipiosAccesibles();
            foreach ($municipiosAccesibles as $municipio) {
                $municipioId = $municipio->getId();
                $temasMunicipales = $this->temaMunicipalRepository->findBy([
                    'municipio' => $municipio,
                    'activo' => true
                ]);
                // Usar string como clave para asegurar consistencia con JSON
                $temasPorMunicipio[(string)$municipioId] = count($temasMunicipales) > 0;
                $municipiosData[(string)$municipioId] = [
                    'id' => $municipioId,
                    'nombre' => $municipio->getNombre(),
                ];
            }
            
            // Verificar temas disponibles por convocatoria y preparar datos
            foreach ($convocatorias as $convocatoriaId => $convocatoria) {
                $municipiosConvocatoria = $convocatoria->getMunicipios();
                $municipiosIds = array_map(fn($m) => $m->getId(), $municipiosConvocatoria->toArray());
                
                if (!empty($municipiosIds)) {
                    $temasMunicipales = $this->temaMunicipalRepository->createQueryBuilder('t')
                        ->innerJoin('t.municipio', 'm')
                        ->where('m.id IN (:municipiosIds)')
                        ->andWhere('t.activo = :activo')
                        ->setParameter('municipiosIds', $municipiosIds)
                        ->setParameter('activo', true)
                        ->getQuery()
                        ->getResult();
                    $temasPorConvocatoria[$convocatoriaId] = count($temasMunicipales) > 0;
                } else {
                    $temasPorConvocatoria[$convocatoriaId] = false;
                }
                
                $convocatoriasData[$convocatoriaId] = [
                    'id' => $convocatoria->getId(),
                    'nombre' => $convocatoria->getNombre(),
                ];
            }
        }

        return $this->render('examen_plantilla/iniciar.html.twig', [
            'borradores' => $borradores,
            'municipios' => $municipios,
            'convocatorias' => $convocatorias,
            'tieneMunicipiosActivos' => $tieneMunicipiosActivos,
            'tieneConvocatoriasActivas' => $tieneConvocatoriasActivas,
            'temasPorMunicipio' => $temasPorMunicipio,
            'temasPorConvocatoria' => $temasPorConvocatoria,
            'municipiosData' => $municipiosData,
            'convocatoriasData' => $convocatoriasData,
        ]);
    }

    #[Route('/api/plantillas', name: 'app_examen_plantilla_api_plantillas', methods: ['GET'])]
    public function getPlantillas(Request $request): JsonResponse
    {
        try {
            $tipoExamen = $request->query->get('tipo', 'general');
            $temaId = $request->query->getInt('tema');
            $temaMunicipalId = $request->query->getInt('tema_municipal');
            $municipioId = $request->query->getInt('municipio');
            $convocatoriaId = $request->query->getInt('convocatoria');

            $plantillas = [];

        if ($tipoExamen === 'general') {
            if ($temaId > 0) {
                // Buscar el tema primero para asegurar que existe
                $tema = $this->temaRepository->find($temaId);
                if (!$tema) {
                    // Si el tema no existe, devolver array vacío
                    return new JsonResponse(['plantillas' => []]);
                }
                // Usar el método del repositorio que ya existe
                $plantillasResult = $this->plantillaRepository->findByTema($tema);
            } else {
                // Si no hay filtro de tema, obtener todas las plantillas
                $qb = $this->plantillaRepository->createQueryBuilder('p')
                    ->leftJoin('p.tema', 't')
                    ->addSelect('t')
                    ->orderBy('t.nombre', 'ASC')
                    ->addOrderBy('p.dificultad', 'ASC')
                    ->addOrderBy('p.nombre', 'ASC');
                $plantillasResult = $qb->getQuery()->getResult();
            }
            
            // Debug: Log del número de plantillas encontradas
            error_log('Plantillas encontradas para tema ' . $temaId . ': ' . count($plantillasResult));
            
            // Obtener contadores de preguntas activas usando el método del repositorio
            $preguntasCounts = [];
            foreach ($plantillasResult as $plantilla) {
                $plantillaId = $plantilla->getId();
                if ($plantillaId) {
                    try {
                        $count = $this->plantillaRepository->countPreguntasActivas($plantilla);
                        $preguntasCounts[$plantillaId] = $count;
                    } catch (\Exception $e) {
                        error_log('Error al contar preguntas para plantilla ' . $plantillaId . ': ' . $e->getMessage());
                        $preguntasCounts[$plantillaId] = 0;
                    }
                }
            }
            
            // Mostrar todas las plantillas encontradas, incluso si tienen 0 preguntas
            foreach ($plantillasResult as $plantilla) {
                $numeroPreguntas = $preguntasCounts[$plantilla->getId()] ?? 0;
                $tema = $plantilla->getTema();
                // Incluir todas las plantillas, incluso si no tienen tema asignado
                $plantillas[] = [
                    'id' => $plantilla->getId(),
                    'nombre' => $plantilla->getNombre(),
                    'dificultad' => $plantilla->getDificultad(),
                    'dificultadLabel' => $plantilla->getDificultadLabel(),
                    'numeroPreguntas' => $numeroPreguntas,
                    'tema' => $tema ? $tema->getNombre() : 'Sin tema',
                ];
            }
            
            // Debug: Log del número de plantillas en el array final
            error_log('Plantillas en array final: ' . count($plantillas));
        } elseif ($tipoExamen === 'municipal') {
            if ($municipioId > 0) {
                $qb = $this->plantillaMunicipalRepository->createQueryBuilder('p')
                    ->innerJoin('p.temaMunicipal', 't')
                    ->addSelect('t')
                    ->leftJoin('t.municipio', 'm')
                    ->addSelect('m')
                    ->where('t.municipio = :municipioId')
                    ->setParameter('municipioId', $municipioId)
                    ->orderBy('t.nombre', 'ASC')
                    ->addOrderBy('p.nombre', 'ASC');

                if ($temaMunicipalId > 0) {
                    $qb->andWhere('p.temaMunicipal = :temaMunicipalId')
                       ->setParameter('temaMunicipalId', $temaMunicipalId);
                }

                $plantillasResult = $qb->getQuery()->getResult();
                
                // Obtener contadores de preguntas activas usando el método del repositorio
                $preguntasCounts = [];
                foreach ($plantillasResult as $plantilla) {
                    $plantillaId = $plantilla->getId();
                    if ($plantillaId) {
                        try {
                            $count = $this->plantillaMunicipalRepository->countPreguntasActivas($plantilla);
                            $preguntasCounts[$plantillaId] = $count;
                        } catch (\Exception $e) {
                            error_log('Error al contar preguntas municipales para plantilla ' . $plantillaId . ': ' . $e->getMessage());
                            $preguntasCounts[$plantillaId] = 0;
                        }
                    }
                }
                
                foreach ($plantillasResult as $plantilla) {
                    $numeroPreguntas = $preguntasCounts[$plantilla->getId()] ?? 0;
                    // Mostrar todas las plantillas, incluso si tienen 0 preguntas
                    $temaMunicipal = $plantilla->getTemaMunicipal();
                    if ($temaMunicipal) {
                        $municipio = $temaMunicipal->getMunicipio();
                        $plantillas[] = [
                            'id' => $plantilla->getId(),
                            'nombre' => $plantilla->getNombre(),
                            'dificultad' => $plantilla->getDificultad(),
                            'dificultadLabel' => $plantilla->getDificultadLabel(),
                            'numeroPreguntas' => $numeroPreguntas,
                            'tema' => $temaMunicipal->getNombre(),
                            'municipio' => $municipio ? $municipio->getNombre() : '',
                        ];
                    }
                }
            }
        } elseif ($tipoExamen === 'convocatoria') {
            if ($convocatoriaId > 0) {
                $convocatoria = $this->convocatoriaRepository->find($convocatoriaId);
                if ($convocatoria) {
                    $municipios = $convocatoria->getMunicipios();
                    $municipiosIds = array_map(fn($m) => $m->getId(), $municipios->toArray());
                    
                    $qb = $this->plantillaMunicipalRepository->createQueryBuilder('p')
                        ->innerJoin('p.temaMunicipal', 't')
                        ->addSelect('t')
                        ->leftJoin('t.municipio', 'm')
                        ->addSelect('m')
                        ->where('t.municipio IN (:municipiosIds)')
                        ->setParameter('municipiosIds', $municipiosIds)
                        ->orderBy('t.municipio', 'ASC')
                        ->addOrderBy('t.nombre', 'ASC')
                        ->addOrderBy('p.nombre', 'ASC');

                    $plantillasResult = $qb->getQuery()->getResult();
                    
                    // Obtener contadores de preguntas activas usando el método del repositorio
                    $preguntasCounts = [];
                    foreach ($plantillasResult as $plantilla) {
                        $plantillaId = $plantilla->getId();
                        if ($plantillaId) {
                            try {
                                $count = $this->plantillaMunicipalRepository->countPreguntasActivas($plantilla);
                                $preguntasCounts[$plantillaId] = $count;
                            } catch (\Exception $e) {
                                error_log('Error al contar preguntas municipales (convocatoria) para plantilla ' . $plantillaId . ': ' . $e->getMessage());
                                $preguntasCounts[$plantillaId] = 0;
                            }
                        }
                    }
                    
                    foreach ($plantillasResult as $plantilla) {
                        $numeroPreguntas = $preguntasCounts[$plantilla->getId()] ?? 0;
                        // Mostrar todas las plantillas, incluso si tienen 0 preguntas
                        $temaMunicipal = $plantilla->getTemaMunicipal();
                        if ($temaMunicipal) {
                            $municipio = $temaMunicipal->getMunicipio();
                            $plantillas[] = [
                                'id' => $plantilla->getId(),
                                'nombre' => $plantilla->getNombre(),
                                'dificultad' => $plantilla->getDificultad(),
                                'dificultadLabel' => $plantilla->getDificultadLabel(),
                                'numeroPreguntas' => $numeroPreguntas,
                                'tema' => $temaMunicipal->getNombre(),
                                'municipio' => $municipio ? $municipio->getNombre() : '',
                            ];
                        }
                    }
                }
            }
        }

        return new JsonResponse(['plantillas' => $plantillas]);
        } catch (\Exception $e) {
            // Log del error para debugging
            error_log('Error al cargar plantillas: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            error_log('File: ' . $e->getFile() . ' Line: ' . $e->getLine());
            
            // Devolver respuesta vacía en caso de error
            return new JsonResponse([
                'plantillas' => [],
                'error' => 'Error al cargar las plantillas: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/crear', name: 'app_examen_plantilla_crear', methods: ['POST'])]
    public function crear(Request $request, SessionInterface $session): Response
    {
        $tipoExamen = $request->request->get('tipo_examen');
        $plantillaId = $request->request->getInt('plantilla_id');
        $esMunicipal = $request->request->getBoolean('es_municipal', false);
        
        // Leer tiempo límite - puede venir como string o int
        $tiempoLimiteRaw = $request->request->get('tiempo_limite', 60);
        $tiempoLimite = is_numeric($tiempoLimiteRaw) ? (int)$tiempoLimiteRaw : 60;
        
        // Leer modo estudio - puede venir como checkbox (1 o null) o boolean
        $modoEstudioRaw = $request->request->get('modo_estudio', false);
        $modoEstudio = $modoEstudioRaw === '1' || $modoEstudioRaw === true || $modoEstudioRaw === 'on';

        if (!$plantillaId) {
            $this->addFlash('error', 'Debes seleccionar una plantilla.');
            return $this->redirectToRoute('app_examen_plantilla_iniciar');
        }

        // Validar tiempo límite - asegurar que sea un número válido y positivo
        if (!is_numeric($tiempoLimite) || $tiempoLimite < 1) {
            $tiempoLimite = 60; // Valor por defecto si es inválido
        }
        
        // Asegurar que tiempo límite sea un entero
        $tiempoLimite = (int)$tiempoLimite;

        // Obtener preguntas de la plantilla
        $preguntas = [];
        $dificultad = null;
        $temasArray = [];
        $temasMunicipalesArray = [];
        $municipio = null;
        $convocatoria = null;

        if ($esMunicipal) {
            $plantilla = $this->plantillaMunicipalRepository->createQueryBuilder('p')
                ->leftJoin('p.temaMunicipal', 't')
                ->addSelect('t')
                ->leftJoin('t.municipio', 'm')
                ->addSelect('m')
                ->where('p.id = :id')
                ->setParameter('id', $plantillaId)
                ->getQuery()
                ->getOneOrNullResult();
            
            if (!$plantilla) {
                $this->addFlash('error', 'Plantilla no encontrada.');
                return $this->redirectToRoute('app_examen_plantilla_iniciar');
            }

            // Cargar preguntas activas de forma optimizada usando consulta SQL directa
            $preguntas = $this->preguntaMunicipalRepository->createQueryBuilder('p')
                ->where('p.plantilla = :plantilla')
                ->andWhere('p.activo = :activo')
                ->setParameter('plantilla', $plantilla)
                ->setParameter('activo', true)
                ->getQuery()
                ->getResult();
            
            $dificultad = $plantilla->getDificultad();
            $temasMunicipalesArray = [$plantilla->getTemaMunicipal()];
            $municipio = $plantilla->getTemaMunicipal()->getMunicipio();
        } else {
            $plantilla = $this->plantillaRepository->createQueryBuilder('p')
                ->leftJoin('p.tema', 't')
                ->addSelect('t')
                ->where('p.id = :id')
                ->setParameter('id', $plantillaId)
                ->getQuery()
                ->getOneOrNullResult();
            
            if (!$plantilla) {
                $this->addFlash('error', 'Plantilla no encontrada.');
                return $this->redirectToRoute('app_examen_plantilla_iniciar');
            }

            // Cargar preguntas activas de forma optimizada usando consulta SQL directa
            $preguntas = $this->preguntaRepository->createQueryBuilder('p')
                ->where('p.plantilla = :plantilla')
                ->andWhere('p.activo = :activo')
                ->setParameter('plantilla', $plantilla)
                ->setParameter('activo', true)
                ->getQuery()
                ->getResult();
            
            $dificultad = $plantilla->getDificultad();
            $temasArray = [$plantilla->getTema()];
        }

        if (empty($preguntas)) {
            $this->addFlash('error', 'La plantilla seleccionada no tiene preguntas activas.');
            return $this->redirectToRoute('app_examen_plantilla_iniciar');
        }

        // Mezclar preguntas
        shuffle($preguntas);

        // Guardar en sesión (similar al ExamenController)
        $preguntasIds = array_map(fn($p) => $p->getId(), $preguntas);
        
        // Asegurar que tiempo_limite sea un entero positivo
        $tiempoLimite = max(1, (int)$tiempoLimite);
        
        $config = [
            'tipo_examen' => $tipoExamen,
            'dificultad' => $dificultad,
            'numero_preguntas' => count($preguntasIds),
            'es_municipal' => $esMunicipal,
            'tiempo_limite' => $tiempoLimite, // Asegurar que es un entero
            'modo_estudio' => $modoEstudio,
            'por_plantilla' => true,
            'plantilla_id' => $plantillaId,
        ];
        
        if ($esMunicipal) {
            if ($tipoExamen === 'convocatoria') {
                $convocatoriaId = $request->request->getInt('convocatoria_id');
                if ($convocatoriaId > 0) {
                    $convocatoria = $this->convocatoriaRepository->find($convocatoriaId);
                    if ($convocatoria) {
                        $config['convocatoria_id'] = $convocatoriaId;
                        $config['municipio_id'] = null;
                    }
                }
            } else {
                $config['municipio_id'] = $municipio->getId();
            }
            $config['temas_municipales'] = array_map(fn($t) => $t->getId(), $temasMunicipalesArray);
        } else {
            $config['temas'] = array_map(fn($t) => $t->getId(), $temasArray);
        }
        
        $session->set('examen_preguntas', $preguntasIds);
        $session->set('examen_respuestas', []);
        $session->set('examen_config', $config);
        $session->set('examen_pregunta_actual', 0);
        $session->set('examen_preguntas_bloqueadas', []);
        $session->set('examen_preguntas_riesgo', []);

        // Guardar automáticamente en borrador al iniciar el examen
        $user = $this->getUser();
        $this->guardarBorrador($session, $user);

        return $this->redirectToRoute('app_examen_pregunta', ['numero' => 1]);
    }

    private function guardarBorrador(SessionInterface $session, $user, ?int $tiempoRestante = null): void
    {
        $preguntasIds = $session->get('examen_preguntas', []);
        $respuestas = $session->get('examen_respuestas', []);
        $config = $session->get('examen_config', []);
        $preguntaActual = $session->get('examen_pregunta_actual', 0);
        
        if (empty($preguntasIds)) {
            return;
        }
        
        // Siempre crear un nuevo borrador para permitir múltiples borradores
        $borrador = new ExamenBorrador();
        // Asegurar que el usuario esté gestionado por el EntityManager
        if (!$this->entityManager->contains($user)) {
            $user = $this->entityManager->getReference(User::class, $user->getId());
        }
        $borrador->setUsuario($user);
        $borrador->setTipoExamen('plantilla');
        
        // Actualizar datos del borrador
        $borrador->setConfig($config);
        $borrador->setPreguntasIds($preguntasIds);
        $borrador->setRespuestas($respuestas);
        $borrador->setPreguntaActual($preguntaActual);
        $borrador->setTiempoRestante($tiempoRestante);
        $borrador->setFechaActualizacion(new \DateTime());
        
        $this->entityManager->persist($borrador);
        $this->entityManager->flush();
    }

    #[Route('/api/temas', name: 'app_examen_plantilla_api_temas', methods: ['GET'])]
    public function getTemas(): JsonResponse
    {
        $temas = $this->entityManager->getRepository(\App\Entity\Tema::class)
            ->findBy(['activo' => true], ['nombre' => 'ASC']);
        $temasData = array_map(function($tema) {
            return [
                'id' => $tema->getId(),
                'nombre' => $tema->getNombre(),
            ];
        }, $temas);

        return new JsonResponse(['temas' => $temasData]);
    }

    #[Route('/api/municipios', name: 'app_examen_plantilla_api_municipios', methods: ['GET'])]
    public function getMunicipios(): JsonResponse
    {
        $user = $this->getUser();
        $municipios = [];
        
        if ($user) {
            $municipiosAccesibles = $user->getMunicipiosAccesibles();
            $municipios = array_map(function($municipio) {
                return [
                    'id' => $municipio->getId(),
                    'nombre' => $municipio->getNombre(),
                ];
            }, $municipiosAccesibles->toArray());
        }

        return new JsonResponse(['municipios' => $municipios]);
    }

    #[Route('/api/convocatorias', name: 'app_examen_plantilla_api_convocatorias', methods: ['GET'])]
    public function getConvocatorias(): JsonResponse
    {
        $user = $this->getUser();
        $convocatorias = [];
        
        if ($user) {
            $convocatoriasActivas = $this->convocatoriaRepository->findByUsuario($user);
            $convocatorias = array_map(function($convocatoria) {
                return [
                    'id' => $convocatoria->getId(),
                    'nombre' => $convocatoria->getNombre(),
                ];
            }, $convocatoriasActivas);
        }

        return new JsonResponse(['convocatorias' => $convocatorias]);
    }

    #[Route('/api/temas-por-municipio/{municipioId}', name: 'app_examen_plantilla_api_temas_municipales', methods: ['GET'])]
    public function getTemasPorMunicipio(int $municipioId): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse(['error' => 'No autorizado'], 401);
        }

        $municipio = $this->municipioRepository->find($municipioId);
        
        if (!$municipio) {
            return new JsonResponse(['error' => 'Municipio no encontrado'], 404);
        }

        // Verificar que el usuario tenga acceso al municipio
        if (!$user->tieneAccesoAMunicipio($municipio)) {
            return new JsonResponse(['error' => 'No tienes acceso a este municipio'], 403);
        }

        $temas = $this->temaMunicipalRepository->findBy([
            'municipio' => $municipio,
            'activo' => true
        ]);
        
        $temasArray = array_map(function($tema) {
            return [
                'id' => $tema->getId(),
                'nombre' => $tema->getNombre(),
            ];
        }, $temas);

        return new JsonResponse(['temas' => $temasArray]);
    }
}
