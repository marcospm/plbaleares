<?php

namespace App\Controller;

use App\Entity\Examen;
use App\Entity\Pregunta;
use App\Form\ExamenIniciarType;
use App\Repository\ExamenRepository;
use App\Repository\ExamenBorradorRepository;
use App\Repository\PreguntaRepository;
use App\Repository\PreguntaMunicipalRepository;
use App\Repository\TemaRepository;
use App\Repository\TemaMunicipalRepository;
use App\Repository\MunicipioRepository;
use App\Repository\UserRepository;
use App\Repository\ConvocatoriaRepository;
use App\Repository\ConfiguracionExamenRepository;
use App\Entity\ExamenBorrador;
use App\Service\NotificacionService;
use App\Service\ConfiguracionExamenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/examen')]
#[IsGranted('ROLE_USER')]
class ExamenController extends AbstractController
{
    public function __construct(
        private PreguntaRepository $preguntaRepository,
        private PreguntaMunicipalRepository $preguntaMunicipalRepository,
        private TemaRepository $temaRepository,
        private TemaMunicipalRepository $temaMunicipalRepository,
        private MunicipioRepository $municipioRepository,
        private ConvocatoriaRepository $convocatoriaRepository,
        private ExamenRepository $examenRepository,
        private ExamenBorradorRepository $examenBorradorRepository,
        private ConfiguracionExamenRepository $configuracionExamenRepository,
        private ConfiguracionExamenService $configuracionExamenService,
        private EntityManagerInterface $entityManager,
        private NotificacionService $notificacionService
    ) {
    }

    #[Route('/iniciar', name: 'app_examen_iniciar', methods: ['GET', 'POST'])]
    public function iniciar(Request $request, SessionInterface $session): Response
    {
        // Limpiar sesión de examen anterior si existe
        $session->remove('examen_preguntas');
        $session->remove('examen_respuestas');
        $session->remove('examen_config');
        $session->remove('examen_pregunta_actual');
        $session->remove('examen_preguntas_bloqueadas');

        $municipioId = $request->query->getInt('municipio');
        $convocatoriaId = $request->query->getInt('convocatoria');
        $user = $this->getUser();
        
        // Verificar si el usuario tiene municipios activos
        // Los municipios accesibles se obtienen a través de las convocatorias
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
        
        // Si hay un municipio en la URL, establecer datos iniciales para tipo municipal
        $formData = null;
        if ($municipioId > 0 && $tieneMunicipiosActivos) {
            $municipio = $this->municipioRepository->find($municipioId);
            if ($municipio && $user->tieneAccesoAMunicipio($municipio) && $municipio->isActivo()) {
                $formData = [
                    'tipoExamen' => 'municipal',
                    'municipio' => $municipio,
                ];
            }
        } elseif ($convocatoriaId > 0 && $tieneConvocatoriasActivas) {
            // Si hay una convocatoria en la URL, establecer datos iniciales para tipo convocatoria
            $convocatoria = $this->convocatoriaRepository->find($convocatoriaId);
            if ($convocatoria && $user->getConvocatorias()->contains($convocatoria) && $convocatoria->isActivo()) {
                $formData = [
                    'tipoExamen' => 'convocatoria',
                    'convocatoria' => $convocatoria,
                ];
            }
        }
        // Si no hay municipio ni convocatoria en la URL, no establecer tipoExamen para que muestre el placeholder
        
        $form = $this->createForm(ExamenIniciarType::class, $formData, [
            'user' => $user,
            'municipio_id' => $municipioId > 0 ? $municipioId : null,
            'convocatoria_id' => $convocatoriaId > 0 ? $convocatoriaId : null,
            'tiene_municipios_activos' => $tieneMunicipiosActivos,
            'tiene_convocatorias_activas' => $tieneConvocatoriasActivas,
        ]);
        $form->handleRequest($request);
        
        // Pasar variable a la vista para mostrar/ocultar opción municipal
        $mostrarOpcionMunicipal = $tieneMunicipiosActivos;

        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                // Si el formulario no es válido, mostrar errores
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                    $this->addFlash('error', $error->getMessage());
                }
                // Si no hay errores específicos pero el formulario no es válido, puede ser un problema con los datos
                if (empty($errors)) {
                    $this->addFlash('error', 'El formulario no es válido. Por favor, verifica que hayas seleccionado al menos un tema, una dificultad y un número de preguntas.');
                }
                // Continuar para mostrar el formulario con errores y calcular preguntas disponibles
            } else {
                // Formulario válido, procesar
                $data = $form->getData();
                $tipoExamen = $data['tipoExamen'] ?? 'general';
                $municipio = $data['municipio'] ?? null;
                $temas = $data['temas'] ?? null;
                $temasMunicipales = $data['temasMunicipales'] ?? null;
                $dificultad = $data['dificultad'] ?? null;
                $numeroPreguntas = $data['numeroPreguntas'] ?? 20;

                if (empty($dificultad)) {
                    $this->addFlash('error', 'Debes seleccionar una dificultad.');
                    return $this->redirectToRoute('app_examen_iniciar');
                }

                $preguntas = [];
                $temasArray = [];
                $temasMunicipalesArray = [];
                $esMunicipal = false;
                $convocatoria = $data['convocatoria'] ?? null;

                if ($tipoExamen === 'convocatoria' && $convocatoria) {
                    // Verificar que el usuario tenga convocatorias activas
                    if (!$tieneConvocatoriasActivas) {
                        $this->addFlash('error', 'No tienes convocatorias activas asignadas. No puedes crear exámenes de convocatoria.');
                        return $this->redirectToRoute('app_examen_iniciar');
                    }
                    
                    // Verificar que la convocatoria esté activa
                    if (!$convocatoria->isActivo()) {
                        $this->addFlash('error', 'La convocatoria seleccionada no está activa.');
                        return $this->redirectToRoute('app_examen_iniciar');
                    }
                    
                    // Verificar que el usuario tenga acceso a la convocatoria
                    $user = $this->getUser();
                    if (!$user->getConvocatorias()->contains($convocatoria)) {
                        $this->addFlash('error', 'No tienes acceso a esta convocatoria.');
                        return $this->redirectToRoute('app_examen_iniciar');
                    }
                    
                    // Examen de convocatoria (municipal pero con múltiples municipios)
                    $esMunicipal = true;
                    
                    if ($temasMunicipales === null) {
                        $this->addFlash('error', 'No se recibieron temas municipales. Por favor, selecciona al menos un tema municipal.');
                        return $this->redirectToRoute('app_examen_iniciar');
                    }

                    if ($temasMunicipales instanceof \Doctrine\Common\Collections\Collection) {
                        $temasMunicipalesArray = $temasMunicipales->toArray();
                    } elseif (is_array($temasMunicipales)) {
                        $temasMunicipalesArray = $temasMunicipales;
                    } else {
                        $temasMunicipalesArray = [$temasMunicipales];
                    }

                    if (empty($temasMunicipalesArray)) {
                        $this->addFlash('error', 'Debes seleccionar al menos un tema municipal.');
                        return $this->redirectToRoute('app_examen_iniciar');
                    }

                    // Verificar que los temas seleccionados pertenezcan a algún municipio de la convocatoria
                    $municipiosConvocatoria = $convocatoria->getMunicipios();
                    $municipiosIds = array_map(fn($m) => $m->getId(), $municipiosConvocatoria->toArray());
                    
                    foreach ($temasMunicipalesArray as $tema) {
                        if (!in_array($tema->getMunicipio()->getId(), $municipiosIds)) {
                            $this->addFlash('error', 'Los temas seleccionados deben pertenecer a algún municipio de la convocatoria seleccionada.');
                            return $this->redirectToRoute('app_examen_iniciar');
                        }
                    }

                    // Obtener preguntas municipales de todos los temas seleccionados
                    $preguntas = $this->preguntaMunicipalRepository->findByTemasMunicipales(
                        $temasMunicipalesArray,
                        $dificultad
                    );
                    
                    // Para exámenes de convocatoria, filtrar temas sin preguntas sin avisar
                    $temasConPreguntas = [];
                    foreach ($temasMunicipalesArray as $tema) {
                        $preguntasTema = array_filter($preguntas, function($p) use ($tema) {
                            return $p->getTemaMunicipal() && $p->getTemaMunicipal()->getId() === $tema->getId();
                        });
                        if (!empty($preguntasTema)) {
                            $temasConPreguntas[] = $tema;
                        }
                    }
                    // Actualizar temasMunicipalesArray para solo incluir temas con preguntas
                    $temasMunicipalesArray = $temasConPreguntas;
                } elseif ($tipoExamen === 'municipal' && $municipio) {
                    // Verificar que el usuario tenga municipios activos
                    if (!$tieneMunicipiosActivos) {
                        $this->addFlash('error', 'No tienes municipios activos asignados. No puedes crear exámenes municipales.');
                        return $this->redirectToRoute('app_examen_iniciar');
                    }
                    
                    // Verificar que el municipio esté activo
                    if (!$municipio->isActivo()) {
                        $this->addFlash('error', 'El municipio seleccionado no está activo.');
                        return $this->redirectToRoute('app_examen_iniciar');
                    }
                    
                    // Examen municipal
                    $esMunicipal = true;
                    
                    if ($temasMunicipales === null) {
                        $this->addFlash('error', 'No se recibieron temas municipales. Por favor, selecciona al menos un tema municipal.');
                        return $this->redirectToRoute('app_examen_iniciar');
                    }

                    if ($temasMunicipales instanceof \Doctrine\Common\Collections\Collection) {
                        $temasMunicipalesArray = $temasMunicipales->toArray();
                    } elseif (is_array($temasMunicipales)) {
                        $temasMunicipalesArray = $temasMunicipales;
                    } else {
                        $temasMunicipalesArray = [$temasMunicipales];
                    }

                    if (empty($temasMunicipalesArray)) {
                        $this->addFlash('error', 'Debes seleccionar al menos un tema municipal.');
                        return $this->redirectToRoute('app_examen_iniciar');
                    }

                    // Verificar que el usuario tenga acceso al municipio a través de sus convocatorias
                    $user = $this->getUser();
                    if (!$user->tieneAccesoAMunicipio($municipio)) {
                        $this->addFlash('error', 'No tienes acceso a este municipio. Debes estar asignado a la convocatoria que contiene este municipio.');
                        return $this->redirectToRoute('app_examen_iniciar');
                    }

                    // Obtener preguntas municipales
                    $preguntas = $this->preguntaMunicipalRepository->findByTemasMunicipales(
                        $temasMunicipalesArray,
                        $dificultad
                    );
                    
                    // Verificar qué temas tienen preguntas y cuáles no
                    $temasSinPreguntas = [];
                    $temasConPreguntas = [];
                    foreach ($temasMunicipalesArray as $tema) {
                        $preguntasTema = array_filter($preguntas, function($p) use ($tema) {
                            return $p->getTemaMunicipal() && $p->getTemaMunicipal()->getId() === $tema->getId();
                        });
                        if (empty($preguntasTema)) {
                            $temasSinPreguntas[] = $tema->getNombre() . ' (' . $tema->getMunicipio()->getNombre() . ')';
                        } else {
                            $temasConPreguntas[] = $tema;
                        }
                    }
                    
                    // Para exámenes municipales, avisar sobre temas sin preguntas
                    if ($tipoExamen === 'municipal' && !empty($temasSinPreguntas)) {
                        $this->addFlash('warning', 'Los siguientes temas no tienen preguntas disponibles y no se incluirán en el examen: ' . implode(', ', $temasSinPreguntas));
                    }
                    // Para exámenes de convocatoria, simplemente ignorar temas sin preguntas sin avisar
                    
                    // Actualizar temasMunicipalesArray para solo incluir temas con preguntas
                    $temasMunicipalesArray = $temasConPreguntas;
                    
                    // Validar que después de filtrar, quede al menos un tema con preguntas
                    if (empty($temasMunicipalesArray)) {
                        if ($tipoExamen === 'convocatoria') {
                            $this->addFlash('error', 'Ninguno de los temas seleccionados tiene preguntas disponibles para la dificultad seleccionada. Por favor, selecciona otros temas o dificultad.');
                        } else {
                            $this->addFlash('error', 'Ninguno de los temas seleccionados tiene preguntas disponibles para la dificultad seleccionada. Por favor, selecciona otros temas o dificultad.');
                        }
                        return $this->redirectToRoute('app_examen_iniciar');
                    }
                } else {
                    // Examen general
                    if ($temas === null) {
                        $this->addFlash('error', 'No se recibieron temas. Por favor, selecciona al menos un tema.');
                        return $this->redirectToRoute('app_examen_iniciar');
                    }

                    if ($temas instanceof \Doctrine\Common\Collections\Collection) {
                        $temasArray = $temas->toArray();
                    } elseif (is_array($temas)) {
                        $temasArray = $temas;
                    } else {
                        $temasArray = [$temas];
                    }

                    if (empty($temasArray)) {
                        $this->addFlash('error', 'Debes seleccionar al menos un tema.');
                        return $this->redirectToRoute('app_examen_iniciar');
                    }
                    
                    // Obtener preguntas activas de los temas seleccionados con la dificultad
                    $preguntas = $this->preguntaRepository->createQueryBuilder('p')
                        ->where('p.dificultad = :dificultad')
                        ->andWhere('p.tema IN (:temas)')
                        ->andWhere('p.activo = :activo')
                        ->setParameter('dificultad', $dificultad)
                        ->setParameter('temas', $temasArray)
                        ->setParameter('activo', true)
                        ->getQuery()
                        ->getResult();
                }

                // Validar que haya al menos una pregunta
                if (count($preguntas) === 0) {
                    if ($tipoExamen === 'convocatoria') {
                        $this->addFlash('error', 'Ninguno de los temas seleccionados tiene preguntas disponibles para la dificultad seleccionada. Por favor, selecciona otros temas o dificultad.');
                    } else {
                        $this->addFlash('error', 'No hay preguntas disponibles para los temas y dificultad seleccionados. Por favor, selecciona otros temas o dificultad.');
                    }
                    return $this->redirectToRoute('app_examen_iniciar');
                }

                // Si hay menos preguntas de las solicitadas, usar todas las disponibles
                $preguntasDisponibles = count($preguntas);
                $preguntasAUsar = min($numeroPreguntas, $preguntasDisponibles);

                if ($preguntasDisponibles < $numeroPreguntas) {
                    $this->addFlash('info', 'Solo hay ' . $preguntasDisponibles . ' preguntas disponibles. El examen se realizará con todas las preguntas disponibles.');
                }

                // Para exámenes generales, usar distribución por porcentajes si está configurada
                if (!$esMunicipal) {
                    $preguntasSeleccionadas = $this->distribuirPreguntasPorPorcentajes($preguntas, $temasArray, $preguntasAUsar);
                } else {
                    // Para exámenes municipales, usar la lógica original
                    shuffle($preguntas);
                    $preguntasSeleccionadas = $this->seleccionarPreguntasSinRepetirArticulos($preguntas, $preguntasAUsar);
                }
                
                $preguntasIds = array_map(fn($p) => $p->getId(), $preguntasSeleccionadas);

                // Obtener tiempo límite del formulario (por defecto 60 minutos)
                $tiempoLimite = $data['tiempoLimite'] ?? 60;
                
                // Obtener modo estudio (solo para exámenes no semanales)
                // Los exámenes semanales no tienen modo estudio
                $modoEstudio = false; // Por defecto desactivado
                if (!isset($config['examen_semanal_id'])) {
                    $modoEstudio = $data['modoEstudio'] ?? false;
                }
                
                // Guardar en sesión
                $config = [
                    'dificultad' => $dificultad,
                    'numero_preguntas' => $preguntasAUsar,
                    'es_municipal' => $esMunicipal,
                    'tiempo_limite' => $tiempoLimite, // Tiempo en minutos
                    'modo_estudio' => $modoEstudio, // Modo estudio activado
                ];
                
                if ($esMunicipal) {
                    if ($tipoExamen === 'convocatoria' && $convocatoria) {
                        $config['convocatoria_id'] = $convocatoria->getId();
                        $config['municipio_id'] = null; // No hay un municipio específico, es de múltiples municipios
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
                $session->set('examen_preguntas_bloqueadas', []); // Preguntas bloqueadas en modo estudio

                return $this->redirectToRoute('app_examen_pregunta', ['numero' => 1]);
            }
        }

        // Calcular preguntas disponibles para mostrar (solo si hay datos seleccionados)
        $preguntasDisponibles = null; // null = no calcular todavía
        
        // Intentar obtener datos del formulario para mostrar preguntas disponibles
        $data = $form->getData();
        
        // Si el formulario fue enviado pero no es válido, intentar obtener datos del request
        if ($form->isSubmitted() && !$form->isValid()) {
            $submittedData = $request->request->all()['examen_iniciar'] ?? [];
            
            $tipoExamen = $submittedData['tipoExamen'] ?? 'general';
            $dificultad = $submittedData['dificultad'] ?? null;
            
            if ($tipoExamen === 'municipal' && isset($submittedData['municipio']) && !empty($submittedData['municipio'])
                && isset($submittedData['temasMunicipales']) && is_array($submittedData['temasMunicipales']) && !empty($submittedData['temasMunicipales'])
                && !empty($dificultad)) {
                
                $temaMunicipalIds = array_filter(array_map('intval', $submittedData['temasMunicipales']));
                if (!empty($temaMunicipalIds)) {
                    $temasMunicipalesEntidades = $this->temaMunicipalRepository->findBy(['id' => $temaMunicipalIds]);
                    if (!empty($temasMunicipalesEntidades)) {
                        $preguntas = $this->preguntaMunicipalRepository->findByTemasMunicipales(
                            $temasMunicipalesEntidades,
                            $dificultad
                        );
                        $preguntasDisponibles = count($preguntas);
                    }
                }
            } elseif (isset($submittedData['temas']) && is_array($submittedData['temas']) && !empty($submittedData['temas']) 
                && !empty($dificultad)) {
                
                $temaIds = array_filter(array_map('intval', $submittedData['temas']));
                if (!empty($temaIds)) {
                    // Cargar las entidades Tema desde los IDs
                    $temasEntidades = $this->temaRepository->findBy(['id' => $temaIds]);
                    if (!empty($temasEntidades)) {
                        $preguntas = $this->preguntaRepository->createQueryBuilder('p')
                            ->where('p.dificultad = :dificultad')
                            ->andWhere('p.tema IN (:temas)')
                            ->andWhere('p.activo = :activo')
                            ->setParameter('dificultad', $dificultad)
                            ->setParameter('temas', $temasEntidades)
                            ->setParameter('activo', true)
                            ->getQuery()
                            ->getResult();
                        $preguntasDisponibles = count($preguntas);
                    }
                }
            }
        } elseif ($data && isset($data['temas']) && !empty($data['temas']) && isset($data['dificultad']) && !empty($data['dificultad'])) {
            // Si hay datos del formulario válidos (aunque no se haya enviado)
            $temas = $data['temas'];
            $dificultad = $data['dificultad'];
            
            // Convertir temas a array
            if ($temas instanceof \Doctrine\Common\Collections\Collection) {
                $temasArray = $temas->toArray();
            } elseif (is_array($temas)) {
                $temasArray = $temas;
            } elseif ($temas !== null) {
                $temasArray = [$temas];
            } else {
                $temasArray = [];
            }
            
            // Solo calcular si realmente hay temas y dificultad seleccionados
            if (!empty($temasArray) && !empty($dificultad)) {
                $preguntas = $this->preguntaRepository->createQueryBuilder('p')
                    ->where('p.dificultad = :dificultad')
                    ->andWhere('p.tema IN (:temas)')
                    ->andWhere('p.activo = :activo')
                    ->setParameter('dificultad', $dificultad)
                    ->setParameter('temas', $temasArray)
                    ->setParameter('activo', true)
                    ->getQuery()
                    ->getResult();
                $preguntasDisponibles = count($preguntas);
            }
        }

        // Obtener borradores del usuario (excluyendo exámenes semanales que tienen su propia lista)
        $borradores = $this->examenBorradorRepository->createQueryBuilder('b')
            ->where('b.usuario = :usuario')
            ->andWhere('b.examenSemanal IS NULL')
            ->setParameter('usuario', $user)
            ->orderBy('b.fechaActualizacion', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('examen/iniciar.html.twig', [
            'form' => $form,
            'preguntasDisponibles' => $preguntasDisponibles,
            'mostrarOpcionMunicipal' => $mostrarOpcionMunicipal,
            'borradores' => $borradores,
        ]);
    }

    #[Route('/pregunta/{numero}', name: 'app_examen_pregunta', methods: ['GET', 'POST'])]
    public function pregunta(int $numero, Request $request, SessionInterface $session): Response
    {
        $user = $this->getUser();
        $preguntasIds = $session->get('examen_preguntas', []);
        $respuestas = $session->get('examen_respuestas', []);
        $config = $session->get('examen_config', []);
        $preguntaActual = $session->get('examen_pregunta_actual', 0);

        if (empty($preguntasIds)) {
            $this->addFlash('error', 'No hay un examen activo. Por favor, inicia un nuevo examen.');
            return $this->redirectToRoute('app_examen_iniciar');
        }

        $numero = max(1, min($numero, count($preguntasIds)));
        $indice = $numero - 1;

        if ($indice < 0 || $indice >= count($preguntasIds)) {
            return $this->redirectToRoute('app_examen_resultado');
        }

        $esMunicipal = $config['es_municipal'] ?? false;
        
        if ($esMunicipal) {
            $pregunta = $this->preguntaMunicipalRepository->find($preguntasIds[$indice]);
        } else {
            $pregunta = $this->preguntaRepository->find($preguntasIds[$indice]);
        }
        
        if (!$pregunta) {
            $this->addFlash('error', 'Pregunta no encontrada.');
            return $this->redirectToRoute('app_examen_iniciar');
        }

        // Guardar respuesta si se envía
        // El modo estudio solo aplica a exámenes no semanales
        $modoEstudio = false;
        if (!isset($config['examen_semanal_id'])) {
            $modoEstudio = $config['modo_estudio'] ?? false;
        }
        $preguntasBloqueadas = $session->get('examen_preguntas_bloqueadas', []);
        $preguntaBloqueada = in_array($pregunta->getId(), $preguntasBloqueadas);
        
        if ($request->isMethod('POST')) {
            $respuesta = $request->request->get('respuesta');
            if (in_array($respuesta, ['A', 'B', 'C', 'D'])) {
                $respuestas[$pregunta->getId()] = $respuesta;
                $session->set('examen_respuestas', $respuestas);
                
                // En modo estudio, bloquear la pregunta después de responder
                if ($modoEstudio && !$preguntaBloqueada) {
                    $preguntasBloqueadas[] = $pregunta->getId();
                    $session->set('examen_preguntas_bloqueadas', $preguntasBloqueadas);
                    $preguntaBloqueada = true;
                }
            }

            // Determinar siguiente acción
            $accion = $request->request->get('accion');
            $numeroDestino = $request->request->getInt('numero_destino');
            
            // Si la acción es guardar borrador
            if ($accion === 'guardar_borrador') {
                $tiempoRestante = $request->request->getInt('tiempo_restante');
                $examenSemanalId = $config['examen_semanal_id'] ?? null;
                $this->guardarBorrador($session, $user, $tiempoRestante, $examenSemanalId);
                
                if ($examenSemanalId) {
                    $this->addFlash('success', 'Examen guardado en borrador. Puedes continuarlo más tarde desde la lista de exámenes semanales.');
                    return $this->redirectToRoute('app_examen_semanal_alumno_index');
                } else {
                    $this->addFlash('success', 'Examen guardado en borrador. Puedes continuarlo más tarde desde la página de inicio.');
                    return $this->redirectToRoute('app_examen_iniciar');
                }
            }
            
            // En modo estudio, si se respondió sin acción específica, redirigir a la misma pregunta para mostrar feedback
            if ($modoEstudio && $preguntaBloqueada && empty($accion) && $numeroDestino <= 0) {
                return $this->redirectToRoute('app_examen_pregunta', ['numero' => $numero]);
            }
            
            // Si hay un número destino específico, redirigir allí
            if ($numeroDestino > 0 && $numeroDestino >= 1 && $numeroDestino <= count($preguntasIds)) {
                return $this->redirectToRoute('app_examen_pregunta', ['numero' => $numeroDestino]);
            }
            
            if ($accion === 'anterior' && $indice > 0) {
                return $this->redirectToRoute('app_examen_pregunta', ['numero' => $numero - 1]);
            } elseif ($accion === 'siguiente' && $indice < count($preguntasIds) - 1) {
                return $this->redirectToRoute('app_examen_pregunta', ['numero' => $numero + 1]);
            } elseif ($accion === 'finalizar') {
                return $this->redirectToRoute('app_examen_resultado');
            }
        }

        $respuestaActual = $respuestas[$pregunta->getId()] ?? null;
        $esUltima = ($indice === count($preguntasIds) - 1);
        $esPrimera = ($indice === 0);
        
        // Obtener respuesta correcta y retroalimentación para modo estudio
        // Mostrar solo cuando está en modo estudio y la pregunta está bloqueada (después de responder)
        $respuestaCorrecta = null;
        $retroalimentacion = null;
        if ($modoEstudio && $preguntaBloqueada) {
            $respuestaCorrecta = $pregunta->getRespuestaCorrecta();
            $retroalimentacion = $pregunta->getRetroalimentacion();
        }

        // Calcular porcentajes por tema
        $porcentajesPorTema = [];
        if (!$esMunicipal) {
            // Obtener todos los temas seleccionados en la configuración del examen
            $temas = $this->temaRepository->findBy(['id' => $config['temas'] ?? []]);
            $preguntasPorTema = [];
            
            // Contar preguntas por tema en el examen actual
            foreach ($preguntasIds as $preguntaId) {
                $preguntaTemp = $this->preguntaRepository->find($preguntaId);
                if ($preguntaTemp && $preguntaTemp->getTema()) {
                    $temaId = $preguntaTemp->getTema()->getId();
                    if (!isset($preguntasPorTema[$temaId])) {
                        $preguntasPorTema[$temaId] = 0;
                    }
                    $preguntasPorTema[$temaId]++;
                }
            }
            
            // Calcular porcentajes para todos los temas seleccionados
            $totalPreguntas = count($preguntasIds);
            foreach ($temas as $tema) {
                $temaId = $tema->getId();
                $cantidad = $preguntasPorTema[$temaId] ?? 0;
                $porcentaje = $totalPreguntas > 0 ? round(($cantidad / $totalPreguntas) * 100, 1) : 0;
                $porcentajesPorTema[$temaId] = [
                    'nombre' => $tema->getNombre(),
                    'cantidad' => $cantidad,
                    'porcentaje' => $porcentaje,
                ];
            }
        }

        // Preparar información de respuestas para el listado de preguntas
        $estadoPreguntas = [];
        for ($i = 0; $i < count($preguntasIds); $i++) {
            $preguntaId = $preguntasIds[$i];
            $estadoPreguntas[$i + 1] = [
                'numero' => $i + 1,
                'tieneRespuesta' => isset($respuestas[$preguntaId]),
                'esActual' => ($i + 1) === $numero,
            ];
        }
        
        // Obtener tiempo restante de la sesión si existe (viene de un borrador)
        $tiempoRestante = $session->get('examen_tiempo_restante');
        if ($tiempoRestante) {
            $config['tiempo_restante'] = $tiempoRestante;
            $session->remove('examen_tiempo_restante'); // Limpiar después de usarlo
        }

        return $this->render('examen/pregunta.html.twig', [
            'pregunta' => $pregunta,
            'numero' => $numero,
            'total' => count($preguntasIds),
            'respuestaActual' => $respuestaActual,
            'esUltima' => $esUltima,
            'esPrimera' => $esPrimera,
            'temas' => $esMunicipal ? [] : $this->temaRepository->findBy(['id' => $config['temas'] ?? []]),
            'esMunicipal' => $esMunicipal,
            'porcentajesPorTema' => $porcentajesPorTema,
            'estadoPreguntas' => $estadoPreguntas,
            'config' => $config,
            'modoEstudio' => $modoEstudio,
            'preguntaBloqueada' => $preguntaBloqueada,
            'respuestaCorrecta' => $respuestaCorrecta,
            'retroalimentacion' => $retroalimentacion,
        ]);
    }

    #[Route('/resultado', name: 'app_examen_resultado', methods: ['GET'])]
    public function resultado(SessionInterface $session): Response
    {
        $preguntasIds = $session->get('examen_preguntas', []);
        $respuestas = $session->get('examen_respuestas', []);
        $config = $session->get('examen_config', []);

        if (empty($preguntasIds)) {
            $this->addFlash('error', 'No hay un examen para corregir.');
            return $this->redirectToRoute('app_examen_iniciar');
        }

        // Obtener todas las preguntas
        $preguntas = [];
        $aciertos = 0;
        $errores = 0;
        $enBlanco = 0;
        $esMunicipal = $config['es_municipal'] ?? false;

        foreach ($preguntasIds as $preguntaId) {
            if ($esMunicipal) {
                $pregunta = $this->preguntaMunicipalRepository->createQueryBuilder('p')
                    ->leftJoin('p.temaMunicipal', 'tm')
                    ->addSelect('tm')
                    ->where('p.id = :id')
                    ->setParameter('id', $preguntaId)
                    ->getQuery()
                    ->getOneOrNullResult();
            } else {
                $pregunta = $this->preguntaRepository->createQueryBuilder('p')
                    ->leftJoin('p.tema', 't')
                    ->addSelect('t')
                    ->leftJoin('p.ley', 'l')
                    ->addSelect('l')
                    ->where('p.id = :id')
                    ->setParameter('id', $preguntaId)
                    ->getQuery()
                    ->getOneOrNullResult();
            }
            if (!$pregunta) {
                continue;
            }

            $respuestaAlumno = $respuestas[$preguntaId] ?? null;
            
            // Si la pregunta está en blanco (null o vacío), no cuenta ni suma ni resta
            if ($respuestaAlumno === null || $respuestaAlumno === '') {
                $enBlanco++;
                $preguntas[] = [
                    'pregunta' => $pregunta,
                    'respuestaAlumno' => null,
                    'esCorrecta' => null, // null indica que está en blanco
                ];
                continue;
            }

            // Solo evaluar si hay respuesta
            $esCorrecta = ($respuestaAlumno === $pregunta->getRespuestaCorrecta());

            if ($esCorrecta) {
                $aciertos++;
            } else {
                $errores++;
            }

            $preguntas[] = [
                'pregunta' => $pregunta,
                'respuestaAlumno' => $respuestaAlumno,
                'esCorrecta' => $esCorrecta,
            ];
        }

        // Calcular nota solo con las preguntas respondidas
        // (aciertos × (20/total)) - (errores × ((20/total)/4))
        // Cada 4 errores resta el equivalente a un acierto
        $total = count($preguntasIds);
        $preguntasRespondidas = $aciertos + $errores; // Solo las que tienen respuesta
        
        if ($preguntasRespondidas > 0 && $total > 0) {
            $puntosPorAcierto = 20 / $total;
            $puntosPorError = $puntosPorAcierto / 4; // Cada error resta 1/4 del valor de un acierto
            $nota = ($aciertos * $puntosPorAcierto) - ($errores * $puntosPorError);
        } else {
            // Si no hay preguntas respondidas, la nota es 0
            $nota = 0;
        }
        
        $nota = max(0, min(20, round($nota, 2)));

        // Guardar examen en BD
        $examen = new Examen();
        $examen->setUsuario($this->getUser());
        $examen->setDificultad($config['dificultad']);
        $examen->setNumeroPreguntas($config['numero_preguntas']);
        $examen->setFecha(new \DateTime());
        $examen->setNota((string) $nota);
        $examen->setAciertos($aciertos);
        $examen->setErrores($errores);
        $examen->setEnBlanco($enBlanco);
        $examen->setRespuestas($respuestas);
        $examen->setPreguntasIds($preguntasIds);

        // Asociar con examen semanal si viene de uno
        $examenSemanal = null;
        if (isset($config['examen_semanal_id'])) {
            $examenSemanalRepository = $this->entityManager->getRepository(\App\Entity\ExamenSemanal::class);
            $examenSemanal = $examenSemanalRepository->createQueryBuilder('es')
                ->leftJoin('es.temasMunicipales', 'tm')
                ->addSelect('tm')
                ->leftJoin('es.temas', 't')
                ->addSelect('t')
                ->leftJoin('es.convocatoria', 'c')
                ->addSelect('c')
                ->where('es.id = :id')
                ->setParameter('id', $config['examen_semanal_id'])
                ->getQuery()
                ->getOneOrNullResult();
            
            if ($examenSemanal) {
                $examen->setExamenSemanal($examenSemanal);
            }
        }

        // Agregar temas o temas municipales
        $esMunicipal = $config['es_municipal'] ?? false;
        if ($esMunicipal) {
            // Verificar si es un examen de convocatoria
            $convocatoriaId = $config['convocatoria_id'] ?? null;
            if ($convocatoriaId !== null && $convocatoriaId > 0) {
                $convocatoria = $this->convocatoriaRepository->find($convocatoriaId);
                if ($convocatoria) {
                    $examen->setConvocatoria($convocatoria);
                }
            } else {
                // Solo establecer municipio si hay un municipio_id válido (no para exámenes de convocatoria)
                $municipioId = $config['municipio_id'] ?? null;
                if ($municipioId !== null && $municipioId > 0) {
                    $municipio = $this->municipioRepository->find($municipioId);
                    if ($municipio) {
                        $examen->setMunicipio($municipio);
                    }
                }
            }
            
            // Obtener temas municipales de la sesión o del examen semanal
            $temasMunicipalesIds = $config['temas_municipales'] ?? [];
            
            // Si viene de un examen semanal y no hay temas en la sesión, obtenerlos del examen semanal
            // Tanto para exámenes municipales como de convocatoria
            if ($examenSemanal && empty($temasMunicipalesIds) && ($examenSemanal->getMunicipio() || $examenSemanal->getConvocatoria())) {
                $temasMunicipalesIds = array_map(fn($t) => $t->getId(), $examenSemanal->getTemasMunicipales()->toArray());
            }
            
            // Si aún no hay temas, intentar extraerlos de las preguntas
            if (empty($temasMunicipalesIds)) {
                $temasMunicipalesIds = [];
                foreach ($preguntasIds as $preguntaId) {
                    $pregunta = $this->preguntaMunicipalRepository->find($preguntaId);
                    if ($pregunta && $pregunta->getTemaMunicipal()) {
                        $temaId = $pregunta->getTemaMunicipal()->getId();
                        if (!in_array($temaId, $temasMunicipalesIds)) {
                            $temasMunicipalesIds[] = $temaId;
                        }
                    }
                }
            }
            
            if (!empty($temasMunicipalesIds)) {
                $temasMunicipales = $this->temaMunicipalRepository->findBy(['id' => $temasMunicipalesIds]);
                foreach ($temasMunicipales as $temaMunicipal) {
                    $examen->addTemasMunicipale($temaMunicipal);
                }
            }
        } else {
            // Para exámenes generales, obtener temas de la sesión o del examen semanal
            $temasIds = $config['temas'] ?? [];
            
            // Si viene de un examen semanal y no hay temas en la sesión, obtenerlos del examen semanal
            // Solo para exámenes generales (sin municipio ni convocatoria)
            if ($examenSemanal && empty($temasIds) && !$examenSemanal->getMunicipio() && !$examenSemanal->getConvocatoria()) {
                $temasIds = array_map(fn($t) => $t->getId(), $examenSemanal->getTemas()->toArray());
            }
            
            if (!empty($temasIds)) {
                $temas = $this->temaRepository->findBy(['id' => $temasIds]);
                foreach ($temas as $tema) {
                    $examen->addTema($tema);
                }
            }
        }

        $this->entityManager->persist($examen);
        $this->entityManager->flush();

        // Eliminar borrador si existe
        $user = $this->getUser();
        $examenSemanalId = $config['examen_semanal_id'] ?? null;
        
        if ($examenSemanalId) {
            // Si es examen semanal, buscar por examen semanal
            $examenSemanal = $this->entityManager->getRepository(\App\Entity\ExamenSemanal::class)->find($examenSemanalId);
            if ($examenSemanal) {
                $borrador = $this->examenBorradorRepository->findOneByUsuarioAndExamenSemanal($user, $examenSemanal);
                if ($borrador) {
                    $this->entityManager->remove($borrador);
                    $this->entityManager->flush();
                }
            }
        } else {
            // Determinar tipo de examen
            $tipoExamen = 'general';
            if (isset($config['convocatoria_id'])) {
                $tipoExamen = 'convocatoria';
            } elseif (isset($config['municipio_id'])) {
                $tipoExamen = 'municipal';
            }
            
            // Buscar y eliminar borrador
            $borrador = $this->examenBorradorRepository->findOneByUsuarioAndTipo($user, $tipoExamen);
            if ($borrador) {
                $this->entityManager->remove($borrador);
                $this->entityManager->flush();
            }
        }

        // Recargar el examen con todas sus relaciones para asegurar que estén disponibles en el template
        $examen = $this->examenRepository->createQueryBuilder('e')
            ->leftJoin('e.convocatoria', 'c')
            ->addSelect('c')
            ->leftJoin('c.municipios', 'cm')
            ->addSelect('cm')
            ->leftJoin('e.municipio', 'm')
            ->addSelect('m')
            ->leftJoin('e.temasMunicipales', 'tm')
            ->addSelect('tm')
            ->leftJoin('e.temas', 't')
            ->addSelect('t')
            ->where('e.id = :id')
            ->setParameter('id', $examen->getId())
            ->getQuery()
            ->getOneOrNullResult();

        // Crear notificación para el profesor asignado
        try {
            $this->notificacionService->crearNotificacionExamen($examen);
        } catch (\Exception $e) {
            // Si hay error al crear la notificación, no fallar la operación principal
            // Solo loguear el error (en producción usar un logger)
            error_log('Error al crear notificación de examen: ' . $e->getMessage());
        }

        // Limpiar sesión
        $session->remove('examen_preguntas');
        $session->remove('examen_respuestas');
        $session->remove('examen_config');
        $session->remove('examen_pregunta_actual');

        return $this->render('examen/resultado.html.twig', [
            'examen' => $examen,
            'preguntas' => $preguntas,
            'nota' => $nota,
            'aciertos' => $aciertos,
            'errores' => $errores,
            'enBlanco' => $enBlanco,
            'total' => $total,
            'esMunicipal' => $esMunicipal,
        ]);
    }

    #[Route('/historial', name: 'app_examen_historial', methods: ['GET'])]
    public function historial(Request $request, TemaRepository $temaRepository, TemaMunicipalRepository $temaMunicipalRepository): Response
    {
        $user = $this->getUser();
        $todosExamenes = $this->examenRepository->findBy(['usuario' => $user], ['fecha' => 'DESC']);

        // Separar exámenes: temario general (sin convocatoria) y por convocatoria
        $examenesTemarioGeneral = [];
        $examenesPorConvocatoria = [];
        $examenesAgrupadosPorConvocatoria = [];
        
        foreach ($todosExamenes as $examen) {
            if ($examen->getConvocatoria()) {
                $examenesPorConvocatoria[] = $examen;
                $convocatoriaId = $examen->getConvocatoria()->getId();
                if (!isset($examenesAgrupadosPorConvocatoria[$convocatoriaId])) {
                    $examenesAgrupadosPorConvocatoria[$convocatoriaId] = [];
                }
                $examenesAgrupadosPorConvocatoria[$convocatoriaId][] = $examen;
            } else {
                $examenesTemarioGeneral[] = $examen;
            }
        }

        // Paginación para exámenes del temario general
        $itemsPerPageGeneral = 20;
        $pageGeneral = max(1, $request->query->getInt('page_general', 1));
        $totalItemsGeneral = count($examenesTemarioGeneral);
        $totalPagesGeneral = max(1, ceil($totalItemsGeneral / $itemsPerPageGeneral));
        $pageGeneral = min($pageGeneral, $totalPagesGeneral);
        
        // Obtener los items de la página actual
        $offsetGeneral = ($pageGeneral - 1) * $itemsPerPageGeneral;
        $examenesTemarioGeneralPaginated = array_slice($examenesTemarioGeneral, $offsetGeneral, $itemsPerPageGeneral);

        // Paginación para exámenes por convocatoria
        $itemsPerPageConvocatoria = 20;
        $examenesPaginatedPorConvocatoria = [];
        $paginacionPorConvocatoria = [];
        
        foreach ($examenesAgrupadosPorConvocatoria as $convocatoriaId => $examenesConvocatoria) {
            $pageKey = 'page_convocatoria_' . $convocatoriaId;
            $pageConvocatoria = max(1, $request->query->getInt($pageKey, 1));
            $totalItemsConvocatoria = count($examenesConvocatoria);
            $totalPagesConvocatoria = max(1, ceil($totalItemsConvocatoria / $itemsPerPageConvocatoria));
            $pageConvocatoria = min($pageConvocatoria, $totalPagesConvocatoria);
            
            $offsetConvocatoria = ($pageConvocatoria - 1) * $itemsPerPageConvocatoria;
            $examenesPaginatedPorConvocatoria[$convocatoriaId] = array_slice($examenesConvocatoria, $offsetConvocatoria, $itemsPerPageConvocatoria);
            
            $paginacionPorConvocatoria[$convocatoriaId] = [
                'currentPage' => $pageConvocatoria,
                'totalPages' => $totalPagesConvocatoria,
                'totalItems' => $totalItemsConvocatoria,
                'itemsPerPage' => $itemsPerPageConvocatoria,
            ];
        }

        // Obtener cantidad de exámenes para el ranking (por defecto 3)
        $cantidad = $request->query->getInt('cantidad', 3);
        if ($cantidad < 2) {
            $cantidad = 2;
        }

        // Obtener tema seleccionado para temario general (opcional)
        $temaId = $request->query->getInt('tema', 0);
        $tema = null;
        if ($temaId > 0) {
            $tema = $temaRepository->find($temaId);
        }

        // Obtener convocatoria y municipio seleccionados para filtrar
        $convocatoriaId = $request->query->getInt('convocatoria', 0);
        $convocatoriaSeleccionada = null;
        if ($convocatoriaId > 0) {
            $convocatoriaSeleccionada = $this->convocatoriaRepository->find($convocatoriaId);
        }

        $municipioId = $request->query->getInt('municipio', 0);
        $municipioSeleccionado = null;
        if ($municipioId > 0) {
            $municipioSeleccionado = $this->municipioRepository->find($municipioId);
        }

        // Obtener todos los temas activos para el filtro
        $temas = $temaRepository->findBy(['activo' => true], ['id' => 'ASC']);

        // Obtener convocatorias activas del usuario
        $convocatorias = $this->convocatoriaRepository->findByUsuario($user);

        // Calcular rankings por dificultad (temario general - solo exámenes sin convocatoria)
        $rankings = [];
        $posicionesUsuario = [];
        $dificultades = ['facil', 'moderada', 'dificil'];
        
        foreach ($dificultades as $dificultad) {
            $ranking = $this->examenRepository->getRankingPorDificultad($dificultad, $cantidad, $tema);
            $rankings[$dificultad] = $ranking;
            $posicion = $this->examenRepository->getPosicionUsuario($user, $dificultad, $cantidad, $tema);
            $notaMedia = $this->examenRepository->getNotaMediaUsuario($user, $dificultad, $cantidad, $tema);
            $posicionesUsuario[$dificultad] = [
                'posicion' => $posicion,
                'notaMedia' => $notaMedia,
                'totalUsuarios' => count($ranking),
            ];
        }

        // Calcular rankings por convocatoria
        $rankingsPorConvocatoria = [];
        
        foreach ($convocatorias as $convocatoria) {
            // Si hay una convocatoria seleccionada y no es esta, saltarla
            if ($convocatoriaSeleccionada && $convocatoriaSeleccionada->getId() !== $convocatoria->getId()) {
                continue;
            }

            $municipiosConvocatoria = $convocatoria->getMunicipios()->toArray();
            
            $rankingsConvocatoria = [];
            $posicionesConvocatoria = [];
            
            foreach ($dificultades as $dificultad) {
                $ranking = $this->examenRepository->getRankingPorConvocatoriaYDificultad($convocatoria, $dificultad, $cantidad, $municipioSeleccionado);
                $rankingsConvocatoria[$dificultad] = $ranking;
                $posicion = $this->examenRepository->getPosicionUsuarioPorConvocatoria($user, $convocatoria, $dificultad, $cantidad, $municipioSeleccionado);
                $notaMedia = $this->examenRepository->getNotaMediaUsuarioPorConvocatoria($user, $convocatoria, $dificultad, $cantidad, $municipioSeleccionado);
                $posicionesConvocatoria[$dificultad] = [
                    'posicion' => $posicion,
                    'notaMedia' => $notaMedia,
                    'totalUsuarios' => count($ranking),
                ];
            }
            
            $rankingsPorConvocatoria[$convocatoria->getId()] = [
                'convocatoria' => $convocatoria,
                'rankings' => $rankingsConvocatoria,
                'posiciones' => $posicionesConvocatoria,
                'municipios' => $municipiosConvocatoria,
            ];
        }

        return $this->render('examen/historial.html.twig', [
            'examenesTemarioGeneral' => $examenesTemarioGeneralPaginated,
            'examenesPorConvocatoria' => $examenesPorConvocatoria,
            'examenesPaginatedPorConvocatoria' => $examenesPaginatedPorConvocatoria,
            'paginacionPorConvocatoria' => $paginacionPorConvocatoria,
            'rankings' => $rankings,
            'posicionesUsuario' => $posicionesUsuario,
            'rankingsPorConvocatoria' => $rankingsPorConvocatoria,
            'cantidad' => $cantidad,
            'usuarioActual' => $user,
            'temas' => $temas,
            'temaSeleccionado' => $tema,
            'convocatorias' => $convocatorias,
            'convocatoriaSeleccionada' => $convocatoriaSeleccionada,
            'municipioSeleccionado' => $municipioSeleccionado,
            // Datos de paginación para temario general
            'currentPageGeneral' => $pageGeneral,
            'totalPagesGeneral' => $totalPagesGeneral,
            'totalItemsGeneral' => $totalItemsGeneral,
            'itemsPerPageGeneral' => $itemsPerPageGeneral,
            // Información del grupo (siempre null para historial normal)
            'grupo' => null,
            'esHistorialGrupo' => false,
            'alumnosGrupo' => [],
        ]);
    }

    #[Route('/historial-grupo', name: 'app_examen_historial_grupo', methods: ['GET'])]
    public function historialGrupo(Request $request, TemaRepository $temaRepository, TemaMunicipalRepository $temaMunicipalRepository): Response
    {
        $user = $this->getUser();
        
        // Verificar que el usuario pertenece a algún grupo
        $gruposUsuario = $user->getGrupos();
        if ($gruposUsuario->isEmpty()) {
            $this->addFlash('error', 'No perteneces a ningún grupo.');
            return $this->redirectToRoute('app_examen_historial', [], Response::HTTP_SEE_OTHER);
        }
        
        // Usar el primer grupo (en el futuro se podría permitir seleccionar)
        $grupo = $gruposUsuario->first();
        $alumnosGrupo = $grupo->getAlumnos()->toArray();
        
        // Obtener todos los exámenes de los alumnos del grupo
        $alumnosIds = array_map(fn($alumno) => $alumno->getId(), $alumnosGrupo);
        $todosExamenes = $this->examenRepository->createQueryBuilder('e')
            ->where('e.usuario IN (:alumnosIds)')
            ->setParameter('alumnosIds', $alumnosIds)
            ->orderBy('e.fecha', 'DESC')
            ->getQuery()
            ->getResult();

        // Separar exámenes: temario general (sin convocatoria) y por convocatoria
        $examenesTemarioGeneral = [];
        $examenesPorConvocatoria = [];
        $examenesAgrupadosPorConvocatoria = [];
        
        foreach ($todosExamenes as $examen) {
            if ($examen->getConvocatoria()) {
                $examenesPorConvocatoria[] = $examen;
                $convocatoriaId = $examen->getConvocatoria()->getId();
                if (!isset($examenesAgrupadosPorConvocatoria[$convocatoriaId])) {
                    $examenesAgrupadosPorConvocatoria[$convocatoriaId] = [];
                }
                $examenesAgrupadosPorConvocatoria[$convocatoriaId][] = $examen;
            } else {
                $examenesTemarioGeneral[] = $examen;
            }
        }

        // Paginación para exámenes del temario general
        $itemsPerPageGeneral = 20;
        $pageGeneral = max(1, $request->query->getInt('page_general', 1));
        $totalItemsGeneral = count($examenesTemarioGeneral);
        $totalPagesGeneral = max(1, ceil($totalItemsGeneral / $itemsPerPageGeneral));
        $pageGeneral = min($pageGeneral, $totalPagesGeneral);
        
        // Obtener los items de la página actual
        $offsetGeneral = ($pageGeneral - 1) * $itemsPerPageGeneral;
        $examenesTemarioGeneralPaginated = array_slice($examenesTemarioGeneral, $offsetGeneral, $itemsPerPageGeneral);

        // Paginación para exámenes por convocatoria
        $itemsPerPageConvocatoria = 20;
        $examenesPaginatedPorConvocatoria = [];
        $paginacionPorConvocatoria = [];
        
        foreach ($examenesAgrupadosPorConvocatoria as $convocatoriaId => $examenesConvocatoria) {
            $pageKey = 'page_convocatoria_' . $convocatoriaId;
            $pageConvocatoria = max(1, $request->query->getInt($pageKey, 1));
            $totalItemsConvocatoria = count($examenesConvocatoria);
            $totalPagesConvocatoria = max(1, ceil($totalItemsConvocatoria / $itemsPerPageConvocatoria));
            $pageConvocatoria = min($pageConvocatoria, $totalPagesConvocatoria);
            
            $offsetConvocatoria = ($pageConvocatoria - 1) * $itemsPerPageConvocatoria;
            $examenesPaginatedPorConvocatoria[$convocatoriaId] = array_slice($examenesConvocatoria, $offsetConvocatoria, $itemsPerPageConvocatoria);
            
            $paginacionPorConvocatoria[$convocatoriaId] = [
                'currentPage' => $pageConvocatoria,
                'totalPages' => $totalPagesConvocatoria,
                'totalItems' => $totalItemsConvocatoria,
                'itemsPerPage' => $itemsPerPageConvocatoria,
            ];
        }

        // Obtener cantidad de exámenes para el ranking (por defecto 3)
        $cantidad = $request->query->getInt('cantidad', 3);
        if ($cantidad < 2) {
            $cantidad = 2;
        }

        // Obtener tema seleccionado para temario general (opcional)
        $temaId = $request->query->getInt('tema', 0);
        $tema = null;
        if ($temaId > 0) {
            $tema = $temaRepository->find($temaId);
        }

        // Obtener convocatoria y municipio seleccionados para filtrar
        $convocatoriaId = $request->query->getInt('convocatoria', 0);
        $convocatoriaSeleccionada = null;
        if ($convocatoriaId > 0) {
            $convocatoriaSeleccionada = $this->convocatoriaRepository->find($convocatoriaId);
        }

        $municipioId = $request->query->getInt('municipio', 0);
        $municipioSeleccionado = null;
        if ($municipioId > 0) {
            $municipioSeleccionado = $this->municipioRepository->find($municipioId);
        }

        // Obtener todos los temas activos para el filtro
        $temas = $temaRepository->findBy(['activo' => true], ['id' => 'ASC']);

        // Obtener convocatorias activas de los alumnos del grupo
        $convocatorias = [];
        foreach ($alumnosGrupo as $alumno) {
            $convocatoriasAlumno = $this->convocatoriaRepository->findByUsuario($alumno);
            foreach ($convocatoriasAlumno as $conv) {
                if (!in_array($conv, $convocatorias, true)) {
                    $convocatorias[] = $conv;
                }
            }
        }

        // Calcular rankings por dificultad solo para alumnos del grupo
        $rankings = [];
        $posicionesUsuario = [];
        $dificultades = ['facil', 'moderada', 'dificil'];
        
        // Para el ranking del grupo, calcular solo con los alumnos del grupo
        foreach ($dificultades as $dificultad) {
            $rankingGrupo = [];
            foreach ($alumnosGrupo as $alumno) {
                $notaMedia = $this->examenRepository->getNotaMediaUsuario($alumno, $dificultad, $cantidad, $tema);
                if ($notaMedia !== null) {
                    // Contar cuántos exámenes tiene realmente
                    $qb = $this->examenRepository->createQueryBuilder('e')
                        ->where('e.usuario = :usuario')
                        ->andWhere('e.dificultad = :dificultad')
                        ->andWhere('e.municipio IS NULL')
                        ->setParameter('usuario', $alumno)
                        ->setParameter('dificultad', $dificultad);
                    
                    if ($tema !== null) {
                        $qb->innerJoin('e.temas', 't')
                           ->andWhere('t.id = :temaId')
                           ->setParameter('temaId', $tema->getId())
                           ->groupBy('e.id')
                           ->having('COUNT(t.id) = 1');
                    }
                    
                    $examenesReales = $qb->orderBy('e.fecha', 'DESC')
                        ->setMaxResults($cantidad)
                        ->getQuery()
                        ->getResult();
                    
                    // Filtrar en PHP para asegurar que solo sean exámenes íntegramente del tema
                    if ($tema !== null) {
                        $examenesReales = array_filter($examenesReales, function($examen) use ($tema) {
                            return $examen->getTemas()->count() === 1 && $examen->getTemas()->contains($tema);
                        });
                    }
                    
                    $rankingGrupo[] = [
                        'usuario' => $alumno,
                        'notaMedia' => $notaMedia,
                        'cantidadExamenes' => count($examenesReales),
                    ];
                }
            }
            
            // Ordenar por nota media descendente
            usort($rankingGrupo, function($a, $b) {
                return $b['notaMedia'] <=> $a['notaMedia'];
            });
            
            $rankings[$dificultad] = $rankingGrupo;
            
            // Encontrar posición del usuario actual
            $posicion = null;
            foreach ($rankingGrupo as $index => $entry) {
                if ($entry['usuario']->getId() === $user->getId()) {
                    $posicion = $index + 1;
                    break;
                }
            }
            
            $notaMedia = $this->examenRepository->getNotaMediaUsuario($user, $dificultad, $cantidad, $tema);
            $posicionesUsuario[$dificultad] = [
                'posicion' => $posicion,
                'notaMedia' => $notaMedia,
                'totalUsuarios' => count($rankingGrupo),
            ];
        }

        // Calcular rankings por convocatoria solo para alumnos del grupo
        $rankingsPorConvocatoria = [];
        
        foreach ($convocatorias as $convocatoria) {
            // Si hay una convocatoria seleccionada y no es esta, saltarla
            if ($convocatoriaSeleccionada && $convocatoriaSeleccionada->getId() !== $convocatoria->getId()) {
                continue;
            }

            $municipiosConvocatoria = $convocatoria->getMunicipios()->toArray();
            
            $rankingsConvocatoria = [];
            $posicionesConvocatoria = [];
            
            foreach ($dificultades as $dificultad) {
                // Obtener ranking completo y filtrar solo alumnos del grupo
                $rankingCompleto = $this->examenRepository->getRankingPorConvocatoriaYDificultad($convocatoria, $dificultad, $cantidad, $municipioSeleccionado);
                $rankingGrupo = array_filter($rankingCompleto, function($entry) use ($alumnosIds) {
                    return in_array($entry['usuario']->getId(), $alumnosIds);
                });
                $rankingGrupo = array_values($rankingGrupo);
                
                $rankingsConvocatoria[$dificultad] = $rankingGrupo;
                
                // Encontrar posición del usuario actual
                $posicion = null;
                foreach ($rankingGrupo as $index => $entry) {
                    if ($entry['usuario']->getId() === $user->getId()) {
                        $posicion = $index + 1;
                        break;
                    }
                }
                
                $notaMedia = $this->examenRepository->getNotaMediaUsuarioPorConvocatoria($user, $convocatoria, $dificultad, $cantidad, $municipioSeleccionado);
                $posicionesConvocatoria[$dificultad] = [
                    'posicion' => $posicion,
                    'notaMedia' => $notaMedia,
                    'totalUsuarios' => count($rankingGrupo),
                ];
            }
            
            $rankingsPorConvocatoria[$convocatoria->getId()] = [
                'convocatoria' => $convocatoria,
                'rankings' => $rankingsConvocatoria,
                'posiciones' => $posicionesConvocatoria,
                'municipios' => $municipiosConvocatoria,
            ];
        }

        return $this->render('examen/historial.html.twig', [
            'examenesTemarioGeneral' => $examenesTemarioGeneralPaginated,
            'examenesPorConvocatoria' => $examenesPorConvocatoria,
            'examenesPaginatedPorConvocatoria' => $examenesPaginatedPorConvocatoria,
            'paginacionPorConvocatoria' => $paginacionPorConvocatoria,
            'rankings' => $rankings,
            'posicionesUsuario' => $posicionesUsuario,
            'rankingsPorConvocatoria' => $rankingsPorConvocatoria,
            'cantidad' => $cantidad,
            'usuarioActual' => $user,
            'temas' => $temas,
            'temaSeleccionado' => $tema,
            'convocatorias' => $convocatorias,
            'convocatoriaSeleccionada' => $convocatoriaSeleccionada,
            'municipioSeleccionado' => $municipioSeleccionado,
            // Datos de paginación para temario general
            'currentPageGeneral' => $pageGeneral,
            'totalPagesGeneral' => $totalPagesGeneral,
            'totalItemsGeneral' => $totalItemsGeneral,
            'itemsPerPageGeneral' => $itemsPerPageGeneral,
            // Información del grupo
            'grupo' => $grupo,
            'esHistorialGrupo' => true,
            'alumnosGrupo' => $alumnosGrupo,
        ]);
    }

    #[Route('/profesor', name: 'app_examen_profesor', methods: ['GET'])]
    #[IsGranted('ROLE_PROFESOR')]
    public function profesor(Request $request, UserRepository $userRepository, TemaRepository $temaRepository, \App\Repository\GrupoRepository $grupoRepository): Response
    {
        $usuarioActual = $this->getUser();
        $esAdmin = $this->isGranted('ROLE_ADMIN');
        $alumnosIds = [];
        
        // Si no es admin, obtener solo los alumnos asignados al profesor
        if (!$esAdmin) {
            $alumnosIds = array_map(function($alumno) {
                return $alumno->getId();
            }, $usuarioActual->getAlumnos()->toArray());
            
            if (empty($alumnosIds)) {
                // Si no tiene alumnos asignados, usar un ID que no existe para que no muestre nada
                $alumnosIds = [-1];
            }
        }
        
        // Obtener parámetros del request
        $usuarioIdParam = $request->query->get('usuario');
        $usuarioId = ($usuarioIdParam !== null && $usuarioIdParam !== '') ? (int)$usuarioIdParam : null;
        
        $dificultad = $request->query->get('dificultad');
        $dificultad = ($dificultad !== null && $dificultad !== '') ? $dificultad : null;
        
        $temaIdParam = $request->query->get('tema');
        $temaId = ($temaIdParam !== null && $temaIdParam !== '') ? (int)$temaIdParam : null;
        
        $tema = null;
        if ($temaId !== null && $temaId > 0) {
            $tema = $temaRepository->find($temaId);
        }
        
        // Obtener parámetro de grupo
        $grupoIdParam = $request->query->get('grupo');
        $grupoId = ($grupoIdParam !== null && $grupoIdParam !== '') ? (int)$grupoIdParam : null;
        
        $grupo = null;
        $alumnosGrupoIds = [];
        if ($grupoId !== null && $grupoId > 0) {
            $grupo = $grupoRepository->find($grupoId);
            if ($grupo) {
                // Obtener IDs de alumnos del grupo
                $alumnosGrupoIds = array_map(function($alumno) {
                    return $alumno->getId();
                }, $grupo->getAlumnos()->toArray());
                
                // Si no es admin, verificar que el grupo contiene alumnos asignados al profesor
                if (!$esAdmin && !empty($alumnosIds)) {
                    $alumnosGrupoIds = array_intersect($alumnosGrupoIds, $alumnosIds);
                }
            }
        }
        
        // Obtener parámetros para el ranking
        $tipoRanking = $request->query->get('tipo_ranking', 'general'); // 'general' o 'convocatoria'
        $convocatoriaIdParam = $request->query->get('convocatoria_ranking');
        $convocatoriaRankingId = ($convocatoriaIdParam !== null && $convocatoriaIdParam !== '') ? (int)$convocatoriaIdParam : null;
        $convocatoriaRanking = null;
        if ($convocatoriaRankingId !== null && $convocatoriaRankingId > 0) {
            $convocatoriaRanking = $this->convocatoriaRepository->find($convocatoriaRankingId);
        }
        
        // Obtener convocatorias disponibles para el filtro de ranking
        $convocatoriasDisponibles = [];
        if ($esAdmin) {
            $convocatoriasDisponibles = $this->convocatoriaRepository->findBy(['activo' => true], ['nombre' => 'ASC']);
        } else {
            // Para profesores, obtener convocatorias de sus alumnos
            $convocatoriasAlumnos = [];
            foreach ($usuarioActual->getAlumnos() as $alumno) {
                foreach ($alumno->getConvocatorias() as $conv) {
                    if ($conv->isActivo() && !in_array($conv, $convocatoriasAlumnos)) {
                        $convocatoriasAlumnos[] = $conv;
                    }
                }
            }
            $convocatoriasDisponibles = $convocatoriasAlumnos;
        }
        
        // Obtener todos los temas activos para el filtro
        $temas = $temaRepository->findBy(['activo' => true], ['id' => 'ASC']);
        
        // Obtener grupos disponibles para el filtro
        $todosGrupos = $grupoRepository->findAll();
        $gruposDisponibles = [];
        foreach ($todosGrupos as $g) {
            $alumnosDelGrupo = $g->getAlumnos()->toArray();
            // Si no es admin, solo mostrar grupos que tengan alumnos asignados al profesor
            if (!$esAdmin && !empty($alumnosIds)) {
                $alumnosComunes = array_intersect(
                    array_map(fn($a) => $a->getId(), $alumnosDelGrupo),
                    $alumnosIds
                );
                if (!empty($alumnosComunes)) {
                    $gruposDisponibles[] = $g;
                }
            } else {
                // Si es admin o no hay restricciones, mostrar todos los grupos
                $gruposDisponibles[] = $g;
            }
        }
        
        // Query para exámenes generales (sin municipio)
        $qbGeneral = $this->examenRepository->createQueryBuilder('e')
            ->join('e.usuario', 'u')
            ->leftJoin('e.examenSemanal', 'es')
            ->addSelect('es')
            ->where('e.municipio IS NULL')
            ->orderBy('e.fecha', 'DESC');
        
        // Query para exámenes municipales
        $qbMunicipal = $this->examenRepository->createQueryBuilder('e')
            ->join('e.usuario', 'u')
            ->leftJoin('e.examenSemanal', 'es')
            ->addSelect('es')
            ->where('e.municipio IS NOT NULL')
            ->orderBy('e.fecha', 'DESC');
        
        // Filtrar por alumnos asignados si no es admin
        if (!$esAdmin && !empty($alumnosIds)) {
            $qbGeneral->andWhere('u.id IN (:alumnosIds)')
                      ->setParameter('alumnosIds', $alumnosIds);
            $qbMunicipal->andWhere('u.id IN (:alumnosIds)')
                        ->setParameter('alumnosIds', $alumnosIds);
        }
        
        // Filtrar por grupo si está seleccionado
        if (!empty($alumnosGrupoIds)) {
            $qbGeneral->andWhere('u.id IN (:alumnosGrupoIds)')
                      ->setParameter('alumnosGrupoIds', $alumnosGrupoIds);
            $qbMunicipal->andWhere('u.id IN (:alumnosGrupoIds)')
                        ->setParameter('alumnosGrupoIds', $alumnosGrupoIds);
        } elseif ($grupoId !== null && $grupoId > 0 && empty($alumnosGrupoIds)) {
            // Si se selecciona un grupo pero no tiene alumnos (o no tiene alumnos asignados al profesor), no mostrar nada
            $qbGeneral->andWhere('1 = 0');
            $qbMunicipal->andWhere('1 = 0');
        }
        
        if ($usuarioId !== null && $usuarioId > 0) {
            // Verificar que el usuario seleccionado está en los alumnos asignados (si no es admin)
            if (!$esAdmin && !in_array($usuarioId, $alumnosIds)) {
                $this->addFlash('error', 'No tienes acceso a los exámenes de ese alumno.');
                return $this->redirectToRoute('app_examen_profesor', [], Response::HTTP_SEE_OTHER);
            }
            $usuarioEntity = $userRepository->find($usuarioId);
            if ($usuarioEntity) {
                $qbGeneral->andWhere('e.usuario = :usuario')
                           ->setParameter('usuario', $usuarioEntity);
                $qbMunicipal->andWhere('e.usuario = :usuario')
                            ->setParameter('usuario', $usuarioEntity);
            }
        }
        
        if ($dificultad && in_array($dificultad, ['facil', 'moderada', 'dificil'])) {
            $qbGeneral->andWhere('e.dificultad = :dificultad')
                       ->setParameter('dificultad', $dificultad);
            $qbMunicipal->andWhere('e.dificultad = :dificultad')
                        ->setParameter('dificultad', $dificultad);
        }
        
        // Filtrar por tema si está seleccionado (solo para exámenes generales)
        if ($tema !== null) {
            $qbGeneral->innerJoin('e.temas', 't')
                      ->andWhere('t.id = :temaId')
                      ->setParameter('temaId', $tema->getId())
                      ->groupBy('e.id')
                      ->having('COUNT(t.id) = 1');
        }
        
        $examenesGeneral = $qbGeneral->getQuery()->getResult();
        $examenesMunicipal = $qbMunicipal->getQuery()->getResult();
        
        // Filtrar en PHP para asegurar que solo sean exámenes íntegramente del tema
        if ($tema !== null) {
            $examenesGeneral = array_filter($examenesGeneral, function($examen) use ($tema) {
                return $examen->getTemas()->count() === 1 && $examen->getTemas()->contains($tema);
            });
        }
        
        // Convertir a arrays indexados numéricamente
        $examenesGeneral = array_values($examenesGeneral);
        $examenesMunicipal = array_values($examenesMunicipal);
        
        // Parámetros de paginación
        $itemsPerPage = 20; // Número de exámenes por página
        $pageGeneral = max(1, $request->query->getInt('page_general', 1));
        $pageMunicipal = max(1, $request->query->getInt('page_municipal', 1));
        
        // Calcular paginación para exámenes generales
        $totalItemsGeneral = count($examenesGeneral);
        $totalPagesGeneral = max(1, ceil($totalItemsGeneral / $itemsPerPage));
        $pageGeneral = min($pageGeneral, $totalPagesGeneral);
        $offsetGeneral = ($pageGeneral - 1) * $itemsPerPage;
        $examenesGeneralPaginated = array_slice($examenesGeneral, $offsetGeneral, $itemsPerPage);
        
        // Calcular paginación para exámenes municipales
        $totalItemsMunicipal = count($examenesMunicipal);
        $totalPagesMunicipal = max(1, ceil($totalItemsMunicipal / $itemsPerPage));
        $pageMunicipal = min($pageMunicipal, $totalPagesMunicipal);
        $offsetMunicipal = ($pageMunicipal - 1) * $itemsPerPage;
        $examenesMunicipalPaginated = array_slice($examenesMunicipal, $offsetMunicipal, $itemsPerPage);
        
        // Obtener usuarios para el filtro (solo alumnos asignados si no es admin)
        // Si hay un grupo seleccionado, solo mostrar alumnos de ese grupo
        $idsParaFiltro = $alumnosIds;
        if (!empty($alumnosGrupoIds)) {
            // Si hay grupo seleccionado, usar solo los alumnos del grupo
            $idsParaFiltro = $alumnosGrupoIds;
        }
        
        if ($esAdmin) {
            if (!empty($alumnosGrupoIds)) {
                // Si es admin pero hay grupo seleccionado, filtrar por grupo
                $todosUsuarios = $userRepository->createQueryBuilder('u')
                    ->where('u.activo = :activo')
                    ->andWhere('u.id IN (:alumnosGrupoIds)')
                    ->setParameter('activo', true)
                    ->setParameter('alumnosGrupoIds', $alumnosGrupoIds)
                    ->orderBy('u.username', 'ASC')
                    ->getQuery()
                    ->getResult();
            } else {
                $todosUsuarios = $userRepository->createQueryBuilder('u')
                    ->where('u.activo = :activo')
                    ->setParameter('activo', true)
                    ->orderBy('u.username', 'ASC')
                    ->getQuery()
                    ->getResult();
            }
        } else {
            if (!empty($idsParaFiltro)) {
                $todosUsuarios = $userRepository->createQueryBuilder('u')
                    ->where('u.activo = :activo')
                    ->andWhere('u.id IN (:idsParaFiltro)')
                    ->setParameter('activo', true)
                    ->setParameter('idsParaFiltro', $idsParaFiltro)
                    ->orderBy('u.username', 'ASC')
                    ->getQuery()
                    ->getResult();
            } else {
                $todosUsuarios = [];
            }
        }
        
        // Filtrar usuarios que no sean profesores o administradores
        $usuarios = array_filter($todosUsuarios, function($usuario) {
            $roles = $usuario->getRoles();
            return !in_array('ROLE_PROFESOR', $roles) && !in_array('ROLE_ADMIN', $roles);
        });
        
        // Obtener parámetros para el ranking
        $tipoRanking = $request->query->get('tipo_ranking', 'general'); // 'general' o 'convocatoria'
        $convocatoriaIdParam = $request->query->get('convocatoria_ranking');
        $convocatoriaRankingId = ($convocatoriaIdParam !== null && $convocatoriaIdParam !== '') ? (int)$convocatoriaIdParam : null;
        $convocatoriaRanking = null;
        if ($convocatoriaRankingId !== null && $convocatoriaRankingId > 0) {
            $convocatoriaRanking = $this->convocatoriaRepository->find($convocatoriaRankingId);
        }
        
        // Obtener convocatorias disponibles para el filtro de ranking
        $convocatoriasDisponibles = [];
        if ($esAdmin) {
            $convocatoriasDisponibles = $this->convocatoriaRepository->findBy(['activo' => true], ['nombre' => 'ASC']);
        } else {
            // Para profesores, obtener convocatorias de sus alumnos
            $convocatoriasAlumnos = [];
            foreach ($usuarioActual->getAlumnos() as $alumno) {
                foreach ($alumno->getConvocatorias() as $conv) {
                    if ($conv->isActivo() && !in_array($conv, $convocatoriasAlumnos)) {
                        $convocatoriasAlumnos[] = $conv;
                    }
                }
            }
            $convocatoriasDisponibles = $convocatoriasAlumnos;
        }
        
        // Calcular ranking según los filtros
        $ranking = [];
        $cantidadExamenesRanking = 10; // Últimos 10 exámenes para el ranking
        
        // Determinar qué alumnos incluir en el ranking
        // Si hay filtros específicos (grupo o alumno), usarlos; si no, usar todos los alumnos del profesor
        $alumnosParaRanking = null; // null significa "aplicar filtro por alumnos del profesor después"
        $hayFiltrosEspecificos = false;
        
        if (!empty($alumnosGrupoIds)) {
            // Si hay grupo seleccionado, usar solo alumnos del grupo
            $alumnosParaRanking = $alumnosGrupoIds;
            $hayFiltrosEspecificos = true;
        } elseif ($usuarioId !== null && $usuarioId > 0) {
            // Si hay alumno específico seleccionado, usar solo ese alumno
            $alumnosParaRanking = [$usuarioId];
            $hayFiltrosEspecificos = true;
        }
        // Si no hay filtros específicos, $alumnosParaRanking será null
        // y se aplicará el filtro por alumnos del profesor después (si no es admin)
        
        // Si hay dificultad seleccionada, usar esa; si no, usar null para indicar "todas las dificultades"
        $dificultadRanking = $dificultad ?: null;
        
        // Verificar y corregir $alumnosIds si es necesario
        // Asegurar que tenemos los alumnos correctos del profesor
        if (!$esAdmin) {
            // Obtener alumnos directamente de la relación
            $alumnosActuales = $usuarioActual->getAlumnos()->toArray();
            $alumnosIdsVerificados = array_map(function($alumno) {
                return $alumno->getId();
            }, $alumnosActuales);
            
            // Siempre usar los alumnos verificados para asegurar que tenemos los correctos
            if (!empty($alumnosIdsVerificados)) {
                $alumnosIds = $alumnosIdsVerificados;
            } elseif (empty($alumnosIds) || (count($alumnosIds) === 1 && $alumnosIds[0] === -1)) {
                // Si no hay alumnos, mantener el array vacío o con [-1]
                if (empty($alumnosIdsVerificados)) {
                    $alumnosIds = [-1];
                }
            }
        }
        
        if ($tipoRanking === 'convocatoria' && $convocatoriaRanking !== null) {
            // Ranking por convocatoria
            if ($dificultadRanking !== null) {
                // Hay dificultad seleccionada: usar el método normal
                $rankingCompleto = $this->examenRepository->getRankingPorConvocatoriaYDificultad(
                    $convocatoriaRanking,
                    $dificultadRanking,
                    $cantidadExamenesRanking
                );
                
                // Filtrar por alumnos del profesor/grupo
                if ($hayFiltrosEspecificos && $alumnosParaRanking !== null && !empty($alumnosParaRanking)) {
                    // Filtrar por filtros específicos (grupo o alumno)
                    $ranking = array_filter($rankingCompleto, function($entry) use ($alumnosParaRanking) {
                        return in_array($entry['usuario']->getId(), $alumnosParaRanking);
                    });
                    $ranking = array_values($ranking); // Reindexar
                } elseif (!$esAdmin && !empty($alumnosIds)) {
                    // Si no es admin y no hay filtros específicos, filtrar por todos los alumnos del profesor
                    $ranking = array_filter($rankingCompleto, function($entry) use ($alumnosIds) {
                        return in_array($entry['usuario']->getId(), $alumnosIds);
                    });
                    $ranking = array_values($ranking); // Reindexar
                } else {
                    // Admin sin filtros: mostrar todos
                    $ranking = $rankingCompleto;
                }
            } else {
                // Sin filtro de dificultad: calcular ranking usando todos los exámenes de la convocatoria
                $ranking = [];
                $alumnosParaCalcular = [];
                
                if ($hayFiltrosEspecificos && $alumnosParaRanking !== null && !empty($alumnosParaRanking)) {
                    $alumnosParaCalcular = $alumnosParaRanking;
                } elseif (!$esAdmin && !empty($alumnosIds)) {
                    $alumnosParaCalcular = $alumnosIds;
                } else {
                    // Admin: obtener todos los alumnos de la convocatoria
                    $alumnosParaCalcular = array_map(function($u) {
                        return $u->getId();
                    }, $convocatoriaRanking->getUsuarios()->toArray());
                }
                
                foreach ($alumnosParaCalcular as $alumnoId) {
                    $alumno = $userRepository->find($alumnoId);
                    if (!$alumno || !$alumno->isActivo()) {
                        continue;
                    }
                    
                    $roles = $alumno->getRoles();
                    if (in_array('ROLE_PROFESOR', $roles) || in_array('ROLE_ADMIN', $roles)) {
                        continue;
                    }
                    
                    // Obtener todos los exámenes de la convocatoria (sin filtrar por dificultad)
                    $qb = $this->examenRepository->createQueryBuilder('e')
                        ->where('e.usuario = :usuario')
                        ->andWhere('e.convocatoria = :convocatoria')
                        ->setParameter('usuario', $alumno)
                        ->setParameter('convocatoria', $convocatoriaRanking);
                    
                    $examenesReales = $qb->orderBy('e.fecha', 'DESC')
                        ->setMaxResults($cantidadExamenesRanking)
                        ->getQuery()
                        ->getResult();
                    
                    if (!empty($examenesReales)) {
                        $suma = 0;
                        foreach ($examenesReales as $examen) {
                            $suma += (float) $examen->getNota();
                        }
                        $notaMedia = round($suma / count($examenesReales), 2);
                        
                        $ranking[] = [
                            'usuario' => $alumno,
                            'notaMedia' => $notaMedia,
                            'cantidadExamenes' => count($examenesReales),
                        ];
                    }
                }
                
                // Ordenar por nota media descendente
                usort($ranking, function($a, $b) {
                    if ($a['notaMedia'] == $b['notaMedia']) {
                        return 0;
                    }
                    return ($a['notaMedia'] > $b['notaMedia']) ? -1 : 1;
                });
            }
        } else {
            // Ranking por temario general
            // Determinar qué alumnos incluir en el cálculo del ranking
            $alumnosParaCalcularRanking = null;
            if ($hayFiltrosEspecificos && $alumnosParaRanking !== null && !empty($alumnosParaRanking)) {
                // Si hay filtros específicos, usar esos alumnos
                $alumnosParaCalcularRanking = $alumnosParaRanking;
            } elseif (!$esAdmin && !empty($alumnosIds)) {
                // Si no es admin, usar todos los alumnos del profesor
                $alumnosParaCalcularRanking = $alumnosIds;
            }
            // Si es admin y no hay filtros, $alumnosParaCalcularRanking será null (todos los alumnos)
            
            // Calcular ranking solo para los alumnos especificados
            $ranking = [];
            if ($alumnosParaCalcularRanking !== null && !empty($alumnosParaCalcularRanking)) {
                // Calcular ranking solo para los alumnos del profesor/filtros
                foreach ($alumnosParaCalcularRanking as $alumnoId) {
                    $alumno = $userRepository->find($alumnoId);
                    if (!$alumno) {
                        continue; // Saltar si el alumno no existe
                    }
                    
                    if (!$alumno->isActivo()) {
                        continue; // Saltar si el alumno no está activo
                    }
                    
                    // Verificar que no sea profesor o admin
                    $roles = $alumno->getRoles();
                    if (in_array('ROLE_PROFESOR', $roles) || in_array('ROLE_ADMIN', $roles)) {
                        continue;
                    }
                    
                    // Calcular nota media
                    // Si hay dificultad seleccionada, usar esa; si no, usar todos los exámenes de temario general
                    $notaMedia = null;
                    $examenesReales = [];
                    
                    if ($dificultadRanking !== null) {
                        // Hay dificultad seleccionada: usar el método normal
                        $notaMedia = $this->examenRepository->getNotaMediaUsuario(
                            $alumno,
                            $dificultadRanking,
                            $cantidadExamenesRanking,
                            $tema
                        );
                        
                        if ($notaMedia !== null) {
                            // Contar cuántos exámenes tiene realmente
                            $qb = $this->examenRepository->createQueryBuilder('e')
                                ->where('e.usuario = :usuario')
                                ->andWhere('e.dificultad = :dificultad')
                                ->andWhere('e.municipio IS NULL')
                                ->setParameter('usuario', $alumno)
                                ->setParameter('dificultad', $dificultadRanking);
                            
                            if ($tema !== null) {
                                $qb->innerJoin('e.temas', 't')
                                   ->andWhere('t.id = :temaId')
                                   ->setParameter('temaId', $tema->getId())
                                   ->groupBy('e.id')
                                   ->having('COUNT(t.id) = 1');
                            }
                            
                            $examenesReales = $qb->orderBy('e.fecha', 'DESC')
                                ->setMaxResults($cantidadExamenesRanking)
                                ->getQuery()
                                ->getResult();
                            
                            // Filtrar en PHP para asegurar que solo sean exámenes íntegramente del tema
                            if ($tema !== null) {
                                $examenesReales = array_filter($examenesReales, function($examen) use ($tema) {
                                    return $examen->getTemas()->count() === 1 && $examen->getTemas()->contains($tema);
                                });
                            }
                        }
                    } else {
                        // No hay dificultad seleccionada: usar todos los exámenes de temario general
                        $qb = $this->examenRepository->createQueryBuilder('e')
                            ->where('e.usuario = :usuario')
                            ->andWhere('e.municipio IS NULL')
                            ->setParameter('usuario', $alumno);
                        
                        if ($tema !== null) {
                            $qb->innerJoin('e.temas', 't')
                               ->andWhere('t.id = :temaId')
                               ->setParameter('temaId', $tema->getId())
                               ->groupBy('e.id')
                               ->having('COUNT(t.id) = 1');
                        }
                        
                        $examenesReales = $qb->orderBy('e.fecha', 'DESC')
                            ->setMaxResults($cantidadExamenesRanking)
                            ->getQuery()
                            ->getResult();
                        
                        // Filtrar en PHP para asegurar que solo sean exámenes íntegramente del tema
                        if ($tema !== null) {
                            $examenesReales = array_filter($examenesReales, function($examen) use ($tema) {
                                return $examen->getTemas()->count() === 1 && $examen->getTemas()->contains($tema);
                            });
                        }
                        
                        // Calcular nota media de todos los exámenes
                        if (!empty($examenesReales)) {
                            $suma = 0;
                            foreach ($examenesReales as $examen) {
                                $suma += (float) $examen->getNota();
                            }
                            $notaMedia = round($suma / count($examenesReales), 2);
                        }
                    }
                    
                    if ($notaMedia !== null) {
                        $ranking[] = [
                            'usuario' => $alumno,
                            'notaMedia' => $notaMedia,
                            'cantidadExamenes' => count($examenesReales),
                        ];
                    }
                }
                
                // Ordenar por nota media descendente
                usort($ranking, function($a, $b) {
                    if ($a['notaMedia'] == $b['notaMedia']) {
                        return 0;
                    }
                    return ($a['notaMedia'] > $b['notaMedia']) ? -1 : 1;
                });
            } else {
                // Admin sin filtros: usar el método completo que obtiene todos los alumnos
                if ($dificultadRanking !== null) {
                    $ranking = $this->examenRepository->getRankingPorDificultad(
                        $dificultadRanking,
                        $cantidadExamenesRanking,
                        $tema
                    );
                } else {
                    // Sin filtro de dificultad: calcular para todos los alumnos activos
                    $todosUsuarios = $userRepository->createQueryBuilder('u')
                        ->where('u.activo = :activo')
                        ->setParameter('activo', true)
                        ->getQuery()
                        ->getResult();
                    
                    $ranking = [];
                    foreach ($todosUsuarios as $usuario) {
                        $roles = $usuario->getRoles();
                        if (in_array('ROLE_PROFESOR', $roles) || in_array('ROLE_ADMIN', $roles)) {
                            continue;
                        }
                        
                        $qb = $this->examenRepository->createQueryBuilder('e')
                            ->where('e.usuario = :usuario')
                            ->andWhere('e.municipio IS NULL')
                            ->setParameter('usuario', $usuario);
                        
                        if ($tema !== null) {
                            $qb->innerJoin('e.temas', 't')
                               ->andWhere('t.id = :temaId')
                               ->setParameter('temaId', $tema->getId())
                               ->groupBy('e.id')
                               ->having('COUNT(t.id) = 1');
                        }
                        
                        $examenesReales = $qb->orderBy('e.fecha', 'DESC')
                            ->setMaxResults($cantidadExamenesRanking)
                            ->getQuery()
                            ->getResult();
                        
                        if ($tema !== null) {
                            $examenesReales = array_filter($examenesReales, function($examen) use ($tema) {
                                return $examen->getTemas()->count() === 1 && $examen->getTemas()->contains($tema);
                            });
                        }
                        
                        if (!empty($examenesReales)) {
                            $suma = 0;
                            foreach ($examenesReales as $examen) {
                                $suma += (float) $examen->getNota();
                            }
                            $notaMedia = round($suma / count($examenesReales), 2);
                            
                            $ranking[] = [
                                'usuario' => $usuario,
                                'notaMedia' => $notaMedia,
                                'cantidadExamenes' => count($examenesReales),
                            ];
                        }
                    }
                    
                    // Ordenar por nota media descendente
                    usort($ranking, function($a, $b) {
                        if ($a['notaMedia'] == $b['notaMedia']) {
                            return 0;
                        }
                        return ($a['notaMedia'] > $b['notaMedia']) ? -1 : 1;
                    });
                }
            }
            
            // Debug: Verificar que tenemos alumnos
            // Si no hay ranking pero hay alumnos asignados, puede ser que no tengan exámenes con esa dificultad
        }
        
        // Paginación del ranking
        $itemsPerPageRanking = 20;
        $pageRanking = max(1, $request->query->getInt('page_ranking', 1));
        $totalItemsRanking = count($ranking);
        $totalPagesRanking = max(1, ceil($totalItemsRanking / $itemsPerPageRanking));
        $pageRanking = min($pageRanking, $totalPagesRanking);
        $offsetRanking = ($pageRanking - 1) * $itemsPerPageRanking;
        $rankingPaginated = array_slice($ranking, $offsetRanking, $itemsPerPageRanking);
        
        return $this->render('examen/profesor.html.twig', [
            'examenesGeneral' => $examenesGeneralPaginated,
            'examenesMunicipal' => $examenesMunicipalPaginated,
            'usuarios' => $usuarios,
            'temas' => $temas,
            'grupos' => $gruposDisponibles,
            'convocatorias' => $convocatoriasDisponibles,
            'usuarioSeleccionado' => $usuarioId !== null && $usuarioId > 0 ? $usuarioId : null,
            'dificultadSeleccionada' => $dificultad,
            'temaSeleccionado' => $tema,
            'grupoSeleccionado' => $grupoId !== null && $grupoId > 0 ? $grupoId : null,
            'tipoRanking' => $tipoRanking,
            'convocatoriaRankingSeleccionada' => $convocatoriaRankingId !== null && $convocatoriaRankingId > 0 ? $convocatoriaRankingId : null,
            'ranking' => $rankingPaginated,
            // Paginación general
            'currentPageGeneral' => $pageGeneral,
            'totalPagesGeneral' => $totalPagesGeneral,
            'totalItemsGeneral' => $totalItemsGeneral,
            'itemsPerPage' => $itemsPerPage,
            // Paginación municipal
            'currentPageMunicipal' => $pageMunicipal,
            'totalPagesMunicipal' => $totalPagesMunicipal,
            'totalItemsMunicipal' => $totalItemsMunicipal,
            // Paginación ranking
            'currentPageRanking' => $pageRanking,
            'totalPagesRanking' => $totalPagesRanking,
            'totalItemsRanking' => $totalItemsRanking,
        ]);
    }

    #[Route('/detalle/{id}', name: 'app_examen_detalle', methods: ['GET'])]
    public function detalle(Examen $examen): Response
    {
        // Cargar explícitamente los temas municipales y temas generales
        $examen = $this->examenRepository->createQueryBuilder('e')
            ->leftJoin('e.temasMunicipales', 'tm')
            ->addSelect('tm')
            ->leftJoin('tm.municipio', 'tmm')
            ->addSelect('tmm')
            ->leftJoin('e.temas', 't')
            ->addSelect('t')
            ->leftJoin('e.municipio', 'm')
            ->addSelect('m')
            ->leftJoin('e.convocatoria', 'c')
            ->addSelect('c')
            ->leftJoin('c.municipios', 'cm')
            ->addSelect('cm')
            ->where('e.id = :id')
            ->setParameter('id', $examen->getId())
            ->getQuery()
            ->getOneOrNullResult();
        
        if (!$examen) {
            throw $this->createNotFoundException('Examen no encontrado');
        }
        
        $usuarioActual = $this->getUser();
        $esAdmin = $this->isGranted('ROLE_ADMIN');
        $esProfesor = $this->isGranted('ROLE_PROFESOR') || $esAdmin;
        
        // Verificar que el examen pertenece al usuario actual o que es profesor
        if ($examen->getUsuario() !== $usuarioActual && !$esProfesor) {
            throw $this->createAccessDeniedException();
        }
        
        // Si es profesor (pero no admin), verificar que el alumno está asignado
        if ($esProfesor && !$esAdmin && $examen->getUsuario() !== $usuarioActual) {
            $alumnosIds = array_map(function($alumno) {
                return $alumno->getId();
            }, $usuarioActual->getAlumnos()->toArray());
            
            if (!in_array($examen->getUsuario()->getId(), $alumnosIds)) {
                throw $this->createAccessDeniedException('No tienes acceso a los exámenes de ese alumno.');
            }
        }

        $preguntas = [];
        $respuestas = $examen->getRespuestas();
        $preguntasIds = $examen->getPreguntasIds() ?? [];
        $esMunicipal = $examen->getMunicipio() !== null;
        $esConvocatoria = $examen->getConvocatoria() !== null;

        // Obtener las preguntas del examen usando los IDs guardados
        foreach ($preguntasIds as $preguntaId) {
            if ($esMunicipal || $esConvocatoria) {
                $pregunta = $this->preguntaMunicipalRepository->createQueryBuilder('p')
                    ->leftJoin('p.temaMunicipal', 'tm')
                    ->addSelect('tm')
                    ->where('p.id = :id')
                    ->setParameter('id', $preguntaId)
                    ->getQuery()
                    ->getOneOrNullResult();
            } else {
                $pregunta = $this->preguntaRepository->createQueryBuilder('p')
                    ->leftJoin('p.tema', 't')
                    ->addSelect('t')
                    ->leftJoin('p.ley', 'l')
                    ->addSelect('l')
                    ->where('p.id = :id')
                    ->setParameter('id', $preguntaId)
                    ->getQuery()
                    ->getOneOrNullResult();
            }
            
            if ($pregunta) {
                $respuestaAlumno = $respuestas[$preguntaId] ?? null;
                
                // Si la pregunta está en blanco (null o vacío), esCorrecta será null
                if ($respuestaAlumno === null || $respuestaAlumno === '') {
                    $esCorrecta = null;
                } else {
                    $esCorrecta = ($respuestaAlumno === $pregunta->getRespuestaCorrecta());
                }
                
                $preguntas[] = [
                    'pregunta' => $pregunta,
                    'respuestaAlumno' => $respuestaAlumno,
                    'esCorrecta' => $esCorrecta,
                ];
            }
        }

        return $this->render('examen/detalle.html.twig', [
            'examen' => $examen,
            'preguntas' => $preguntas,
            'esMunicipal' => $esMunicipal,
            'esConvocatoria' => $esConvocatoria,
        ]);
    }

    /**
     * Selecciona preguntas asegurándose de que no haya dos preguntas del mismo artículo
     * 
     * @param array $preguntas Array de preguntas disponibles
     * @param int $cantidad Cantidad de preguntas a seleccionar
     * @return array Array de preguntas seleccionadas sin repetir artículos
     */
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
            foreach ($preguntasRestantes as $pregunta) {
                if (count($preguntasSeleccionadas) >= $cantidadTotal) {
                    break;
                }
                
                $articuloId = null;
                if (method_exists($pregunta, 'getArticulo')) {
                    $articulo = $pregunta->getArticulo();
                    $articuloId = $articulo ? $articulo->getId() : null;
                }
                
                if ($articuloId === null || !in_array($articuloId, $articulosUsados)) {
                    $preguntasSeleccionadas[] = $pregunta;
                    if ($articuloId !== null) {
                        $articulosUsados[] = $articuloId;
                    }
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

    private function seleccionarPreguntasSinRepetirArticulos(array $preguntas, int $cantidad): array
    {
        $preguntasSeleccionadas = [];
        $articulosUsados = [];
        
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
            
            // Si el artículo ya fue usado, saltar esta pregunta
            if ($articuloId !== null && in_array($articuloId, $articulosUsados)) {
                continue;
            }
            
            // Agregar la pregunta a las seleccionadas
            $preguntasSeleccionadas[] = $pregunta;
            
            // Marcar el artículo como usado
            if ($articuloId !== null) {
                $articulosUsados[] = $articuloId;
            }
        }
        
        return $preguntasSeleccionadas;
    }

    /**
     * Guarda el examen actual en borrador
     */
    private function guardarBorrador(SessionInterface $session, $user, ?int $tiempoRestante = null, ?int $examenSemanalId = null): void
    {
        $preguntasIds = $session->get('examen_preguntas', []);
        $respuestas = $session->get('examen_respuestas', []);
        $config = $session->get('examen_config', []);
        $preguntaActual = $session->get('examen_pregunta_actual', 1);
        
        if (empty($preguntasIds)) {
            return;
        }
        
        // Si es examen semanal, buscar por examen semanal
        if ($examenSemanalId) {
            $examenSemanal = $this->entityManager->getRepository(\App\Entity\ExamenSemanal::class)->find($examenSemanalId);
            if ($examenSemanal) {
                $borrador = $this->examenBorradorRepository->findOneByUsuarioAndExamenSemanal($user, $examenSemanal);
                if (!$borrador) {
                    $borrador = new ExamenBorrador();
                    $borrador->setUsuario($user);
                    $borrador->setTipoExamen('semanal');
                    $borrador->setExamenSemanal($examenSemanal);
                }
            } else {
                return; // No se encontró el examen semanal
            }
        } else {
            // Determinar tipo de examen
            $tipoExamen = 'general';
            if (isset($config['convocatoria_id'])) {
                $tipoExamen = 'convocatoria';
            } elseif (isset($config['municipio_id'])) {
                $tipoExamen = 'municipal';
            }
            
            // Buscar borrador existente o crear uno nuevo
            $borrador = $this->examenBorradorRepository->findOneByUsuarioAndTipo($user, $tipoExamen);
            
            if (!$borrador) {
                $borrador = new ExamenBorrador();
                $borrador->setUsuario($user);
                $borrador->setTipoExamen($tipoExamen);
            }
        }
        
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

    /**
     * Continúa un examen desde un borrador
     */
    #[Route('/continuar/{id}', name: 'app_examen_continuar', methods: ['GET'])]
    public function continuar(int $id, SessionInterface $session): Response
    {
        $user = $this->getUser();
        $borrador = $this->examenBorradorRepository->find($id);
        
        if (!$borrador || $borrador->getUsuario() !== $user) {
            $this->addFlash('error', 'Borrador no encontrado.');
            return $this->redirectToRoute('app_examen_iniciar');
        }
        
        // Restaurar sesión desde el borrador
        $session->set('examen_preguntas', $borrador->getPreguntasIds());
        $session->set('examen_respuestas', $borrador->getRespuestas());
        $session->set('examen_config', $borrador->getConfig());
        $session->set('examen_pregunta_actual', $borrador->getPreguntaActual());
        $session->set('examen_tiempo_restante', $borrador->getTiempoRestante());
        
        // Redirigir a la pregunta actual
        return $this->redirectToRoute('app_examen_pregunta', ['numero' => $borrador->getPreguntaActual()]);
    }
}

