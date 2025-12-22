<?php

namespace App\Controller;

use App\Entity\Examen;
use App\Entity\Pregunta;
use App\Form\ExamenIniciarType;
use App\Repository\ExamenRepository;
use App\Repository\PreguntaRepository;
use App\Repository\PreguntaMunicipalRepository;
use App\Repository\TemaRepository;
use App\Repository\TemaMunicipalRepository;
use App\Repository\MunicipioRepository;
use App\Repository\UserRepository;
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
        private ExamenRepository $examenRepository,
        private EntityManagerInterface $entityManager
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

        $municipioId = $request->query->getInt('municipio');
        $form = $this->createForm(ExamenIniciarType::class, null, [
            'user' => $this->getUser(),
            'municipio_id' => $municipioId > 0 ? $municipioId : null,
        ]);
        $form->handleRequest($request);

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

                if ($tipoExamen === 'municipal' && $municipio) {
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

                    // Verificar que el usuario tenga acceso al municipio
                    $user = $this->getUser();
                    if (!$user->getMunicipios()->contains($municipio)) {
                        $this->addFlash('error', 'No tienes acceso a este municipio.');
                        return $this->redirectToRoute('app_examen_iniciar');
                    }

                    // Obtener preguntas municipales
                    $preguntas = $this->preguntaMunicipalRepository->findByTemasMunicipales(
                        $temasMunicipalesArray,
                        $dificultad
                    );
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
                    $this->addFlash('error', 'No hay preguntas disponibles para los temas y dificultad seleccionados. Por favor, selecciona otros temas o dificultad.');
                    return $this->redirectToRoute('app_examen_iniciar');
                }

                // Si hay menos preguntas de las solicitadas, usar todas las disponibles
                $preguntasDisponibles = count($preguntas);
                $preguntasAUsar = min($numeroPreguntas, $preguntasDisponibles);

                if ($preguntasDisponibles < $numeroPreguntas) {
                    $this->addFlash('info', 'Solo hay ' . $preguntasDisponibles . ' preguntas disponibles. El examen se realizará con todas las preguntas disponibles.');
                }

                // Seleccionar aleatoriamente las preguntas
                shuffle($preguntas);
                $preguntasSeleccionadas = array_slice($preguntas, 0, $preguntasAUsar);
                $preguntasIds = array_map(fn($p) => $p->getId(), $preguntasSeleccionadas);

                // Guardar en sesión
                $config = [
                    'dificultad' => $dificultad,
                    'numero_preguntas' => $preguntasAUsar,
                    'es_municipal' => $esMunicipal,
                ];
                
                if ($esMunicipal) {
                    $config['municipio_id'] = $municipio->getId();
                    $config['temas_municipales'] = array_map(fn($t) => $t->getId(), $temasMunicipalesArray);
                } else {
                    $config['temas'] = array_map(fn($t) => $t->getId(), $temasArray);
                }
                
                $session->set('examen_preguntas', $preguntasIds);
                $session->set('examen_respuestas', []);
                $session->set('examen_config', $config);
                $session->set('examen_pregunta_actual', 0);

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

        return $this->render('examen/iniciar.html.twig', [
            'form' => $form,
            'preguntasDisponibles' => $preguntasDisponibles,
        ]);
    }

    #[Route('/pregunta/{numero}', name: 'app_examen_pregunta', methods: ['GET', 'POST'])]
    public function pregunta(int $numero, Request $request, SessionInterface $session): Response
    {
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
        if ($request->isMethod('POST')) {
            $respuesta = $request->request->get('respuesta');
            if (in_array($respuesta, ['A', 'B', 'C', 'D'])) {
                $respuestas[$pregunta->getId()] = $respuesta;
                $session->set('examen_respuestas', $respuestas);
            }

            // Determinar siguiente acción
            $accion = $request->request->get('accion');
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

        return $this->render('examen/pregunta.html.twig', [
            'pregunta' => $pregunta,
            'numero' => $numero,
            'total' => count($preguntasIds),
            'respuestaActual' => $respuestaActual,
            'esUltima' => $esUltima,
            'esPrimera' => $esPrimera,
            'temas' => $esMunicipal ? [] : $this->temaRepository->findBy(['id' => $config['temas'] ?? []]),
            'esMunicipal' => $esMunicipal,
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
        $esMunicipal = $config['es_municipal'] ?? false;

        foreach ($preguntasIds as $preguntaId) {
            if ($esMunicipal) {
                $pregunta = $this->preguntaMunicipalRepository->find($preguntaId);
            } else {
                $pregunta = $this->preguntaRepository->find($preguntaId);
            }
            if (!$pregunta) {
                continue;
            }

            $respuestaAlumno = $respuestas[$preguntaId] ?? null;
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

        // Calcular nota: (aciertos × (20/total)) - (errores/4)
        $total = count($preguntasIds);
        $puntosPorAcierto = 20 / $total;
        $nota = ($aciertos * $puntosPorAcierto) - ($errores / 4);
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
        $examen->setRespuestas($respuestas);
        $examen->setPreguntasIds($preguntasIds);

        // Agregar temas o temas municipales
        $esMunicipal = $config['es_municipal'] ?? false;
        if ($esMunicipal) {
            $municipio = $this->municipioRepository->find($config['municipio_id'] ?? null);
            if ($municipio) {
                $examen->setMunicipio($municipio);
            }
            $temasMunicipales = $this->temaMunicipalRepository->findBy(['id' => $config['temas_municipales'] ?? []]);
            foreach ($temasMunicipales as $temaMunicipal) {
                $examen->addTemasMunicipale($temaMunicipal);
            }
        } else {
            $temas = $this->temaRepository->findBy(['id' => $config['temas'] ?? []]);
            foreach ($temas as $tema) {
                $examen->addTema($tema);
            }
        }

        $this->entityManager->persist($examen);
        $this->entityManager->flush();

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
            'total' => $total,
            'esMunicipal' => $esMunicipal,
        ]);
    }

    #[Route('/historial', name: 'app_examen_historial', methods: ['GET'])]
    public function historial(Request $request): Response
    {
        $user = $this->getUser();
        $examenes = $this->examenRepository->findBy(['usuario' => $user], ['fecha' => 'DESC']);

        // Obtener cantidad de exámenes para el ranking (por defecto 3)
        $cantidad = $request->query->getInt('cantidad', 3);
        if ($cantidad < 2) {
            $cantidad = 2;
        }

        // Calcular rankings por dificultad
        $rankings = [];
        $posicionesUsuario = [];
        $dificultades = ['facil', 'moderada', 'dificil'];
        
        foreach ($dificultades as $dificultad) {
            $ranking = $this->examenRepository->getRankingPorDificultad($dificultad, $cantidad);
            $rankings[$dificultad] = $ranking;
            $posicion = $this->examenRepository->getPosicionUsuario($user, $dificultad, $cantidad);
            $notaMedia = $this->examenRepository->getNotaMediaUsuario($user, $dificultad, $cantidad);
            $posicionesUsuario[$dificultad] = [
                'posicion' => $posicion,
                'notaMedia' => $notaMedia,
                'totalUsuarios' => count($ranking),
            ];
        }

        return $this->render('examen/historial.html.twig', [
            'examenes' => $examenes,
            'rankings' => $rankings,
            'posicionesUsuario' => $posicionesUsuario,
            'cantidad' => $cantidad,
            'usuarioActual' => $user,
        ]);
    }

    #[Route('/profesor', name: 'app_examen_profesor', methods: ['GET'])]
    #[IsGranted('ROLE_PROFESOR')]
    public function profesor(Request $request, UserRepository $userRepository): Response
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
        
        $usuarioId = $request->query->getInt('usuario');
        $dificultad = $request->query->get('dificultad');
        
        $qb = $this->examenRepository->createQueryBuilder('e')
            ->join('e.usuario', 'u')
            ->orderBy('e.fecha', 'DESC');
        
        // Filtrar por alumnos asignados si no es admin
        if (!$esAdmin && !empty($alumnosIds)) {
            $qb->andWhere('u.id IN (:alumnosIds)')
               ->setParameter('alumnosIds', $alumnosIds);
        }
        
        if ($usuarioId) {
            // Verificar que el usuario seleccionado está en los alumnos asignados (si no es admin)
            if (!$esAdmin && !in_array($usuarioId, $alumnosIds)) {
                $this->addFlash('error', 'No tienes acceso a los exámenes de ese alumno.');
                return $this->redirectToRoute('app_examen_profesor', [], Response::HTTP_SEE_OTHER);
            }
            $qb->andWhere('e.usuario = :usuario')
               ->setParameter('usuario', $usuarioId);
        }
        
        if ($dificultad && in_array($dificultad, ['facil', 'moderada', 'dificil'])) {
            $qb->andWhere('e.dificultad = :dificultad')
               ->setParameter('dificultad', $dificultad);
        }
        
        $examenes = $qb->getQuery()->getResult();
        
        // Obtener usuarios para el filtro (solo alumnos asignados si no es admin)
        if ($esAdmin) {
            $todosUsuarios = $userRepository->createQueryBuilder('u')
                ->where('u.activo = :activo')
                ->setParameter('activo', true)
                ->orderBy('u.username', 'ASC')
                ->getQuery()
                ->getResult();
        } else {
            if (!empty($alumnosIds)) {
                $todosUsuarios = $userRepository->createQueryBuilder('u')
                    ->where('u.activo = :activo')
                    ->andWhere('u.id IN (:alumnosIds)')
                    ->setParameter('activo', true)
                    ->setParameter('alumnosIds', $alumnosIds)
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
        
        return $this->render('examen/profesor.html.twig', [
            'examenes' => $examenes,
            'usuarios' => $usuarios,
            'usuarioSeleccionado' => $usuarioId,
            'dificultadSeleccionada' => $dificultad,
        ]);
    }

    #[Route('/detalle/{id}', name: 'app_examen_detalle', methods: ['GET'])]
    public function detalle(Examen $examen): Response
    {
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

        // Obtener las preguntas del examen usando los IDs guardados
        foreach ($preguntasIds as $preguntaId) {
            if ($esMunicipal) {
                $pregunta = $this->preguntaMunicipalRepository->find($preguntaId);
            } else {
                $pregunta = $this->preguntaRepository->find($preguntaId);
            }
            
            if ($pregunta) {
                $respuestaAlumno = $respuestas[$preguntaId] ?? null;
                $esCorrecta = ($respuestaAlumno === $pregunta->getRespuestaCorrecta());
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
        ]);
    }
}

