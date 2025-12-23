<?php

namespace App\Controller;

use App\Entity\ExamenSemanal;
use App\Form\ExamenSemanalType;
use App\Repository\ExamenSemanalRepository;
use App\Repository\UserRepository;
use App\Service\NotificacionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        private UserRepository $userRepository
    ) {
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
            // Validar que fechaCierre sea posterior a fechaApertura
            if ($examenSemanal->getFechaCierre() <= $examenSemanal->getFechaApertura()) {
                $this->addFlash('error', 'La fecha de cierre debe ser posterior a la fecha de apertura.');
                return $this->render('examen_semanal/new.html.twig', [
                    'examenSemanal' => $examenSemanal,
                    'form' => $form,
                ]);
            }

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

            // Crear examen del temario general si hay temas seleccionados
            if ($tieneTemasGenerales) {
                $examenGeneral = new ExamenSemanal();
                $examenGeneral->setNombre($examenSemanal->getNombre() . ' - Temario General');
                $examenGeneral->setDescripcion($examenSemanal->getDescripcion());
                $examenGeneral->setFechaApertura($examenSemanal->getFechaApertura());
                $examenGeneral->setFechaCierre($examenSemanal->getFechaCierre());
                $examenGeneral->setDificultad($examenSemanal->getDificultad());
                $examenGeneral->setNumeroPreguntas($examenSemanal->getNumeroPreguntas());
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
                $examenMunicipal->setNombre($examenSemanal->getNombre() . ' - ' . $examenSemanal->getMunicipio()->getNombre());
                $examenMunicipal->setDescripcion($examenSemanal->getDescripcion());
                $examenMunicipal->setFechaApertura($examenSemanal->getFechaApertura());
                $examenMunicipal->setFechaCierre($examenSemanal->getFechaCierre());
                $examenMunicipal->setDificultad($examenSemanal->getDificultad());
                $examenMunicipal->setNumeroPreguntas($examenSemanal->getNumeroPreguntas());
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
}

