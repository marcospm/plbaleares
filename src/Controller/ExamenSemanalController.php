<?php

namespace App\Controller;

use App\Entity\ExamenSemanal;
use App\Form\ExamenSemanalType;
use App\Repository\ExamenSemanalRepository;
use App\Repository\UserRepository;
use App\Repository\MunicipioRepository;
use App\Repository\TemaMunicipalRepository;
use App\Repository\PreguntaRepository;
use App\Repository\PreguntaMunicipalRepository;
use App\Service\NotificacionService;
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
        private TemaMunicipalRepository $temaMunicipalRepository,
        private PreguntaRepository $preguntaRepository,
        private PreguntaMunicipalRepository $preguntaMunicipalRepository
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

    #[Route('/', name: 'app_examen_semanal_index', methods: ['GET'])]
    public function index(): Response
    {
        $examenes = $this->examenSemanalRepository->findAll();

        return $this->render('examen_semanal/index.html.twig', [
            'examenes' => $examenes,
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

            if (!$tieneTemasGenerales && !$tieneTemasMunicipales) {
                $this->addFlash('error', 'Debes seleccionar temas del temario general o crear un examen municipal (municipio + temas municipales).');
                return $this->render('examen_semanal/new.html.twig', [
                    'examenSemanal' => $examenSemanal,
                    'form' => $form,
                ]);
            }

            // Validar que si se selecciona municipio, tenga temas municipales
            if ($examenSemanal->getMunicipio() && $examenSemanal->getTemasMunicipales()->isEmpty()) {
                $this->addFlash('error', 'Si seleccionas un municipio, debes seleccionar al menos un tema municipal.');
                return $this->render('examen_semanal/new.html.twig', [
                    'examenSemanal' => $examenSemanal,
                    'form' => $form,
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
                    // Verificar que no sea profesor ni admin
                    if (!in_array('ROLE_PROFESOR', $alumno->getRoles()) && !in_array('ROLE_ADMIN', $alumno->getRoles())) {
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

        return $this->render('examen_semanal/new.html.twig', [
            'examenSemanal' => $examenSemanal,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_examen_semanal_show', methods: ['GET'])]
    public function show(ExamenSemanal $examenSemanal): Response
    {
        return $this->render('examen_semanal/show.html.twig', [
            'examenSemanal' => $examenSemanal,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_examen_semanal_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ExamenSemanal $examenSemanal): Response
    {
        $form = $this->createForm(ExamenSemanalType::class, $examenSemanal);
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

    /**
     * Obtiene las preguntas de un examen semanal
     */
    private function obtenerPreguntasExamen(ExamenSemanal $examenSemanal): array
    {
        $preguntas = [];
        $esMunicipal = $examenSemanal->getMunicipio() !== null;

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

        // Si hay un número limitado de preguntas, seleccionar aleatoriamente
        if ($examenSemanal->getNumeroPreguntas() && count($preguntas) > $examenSemanal->getNumeroPreguntas()) {
            shuffle($preguntas);
            $preguntas = array_slice($preguntas, 0, $examenSemanal->getNumeroPreguntas());
        }

        return $preguntas;
    }
}

