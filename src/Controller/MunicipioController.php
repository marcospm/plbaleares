<?php

namespace App\Controller;

use App\Entity\Municipio;
use App\Form\MunicipioType;
use App\Repository\MunicipioRepository;
use App\Repository\UserRepository;
use App\Repository\ConvocatoriaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/municipio')]
#[IsGranted('ROLE_PROFESOR')]
class MunicipioController extends AbstractController
{
    #[Route('/', name: 'app_municipio_index', methods: ['GET'])]
    public function index(MunicipioRepository $municipioRepository, ConvocatoriaRepository $convocatoriaRepository, Request $request): Response
    {
        $search = trim($request->query->get('search', ''));
        
        // Filtrar municipios por búsqueda si existe
        if (!empty($search)) {
            $municipios = $municipioRepository->createQueryBuilder('m')
                ->where('m.nombre LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->orderBy('m.nombre', 'ASC')
                ->getQuery()
                ->getResult();
        } else {
            $municipios = $municipioRepository->findBy([], ['nombre' => 'ASC']);
        }
        
        // Obtener convocatorias para cada municipio
        $convocatoriasPorMunicipio = [];
        foreach ($municipios as $municipio) {
            $convocatoriasPorMunicipio[$municipio->getId()] = $convocatoriaRepository->createQueryBuilder('c')
                ->innerJoin('c.municipios', 'm')
                ->where('m.id = :municipioId')
                ->setParameter('municipioId', $municipio->getId())
                ->orderBy('c.nombre', 'ASC')
                ->getQuery()
                ->getResult();
        }
        
        return $this->render('municipio/index.html.twig', [
            'municipios' => $municipios,
            'convocatoriasPorMunicipio' => $convocatoriasPorMunicipio,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_municipio_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $municipio = new Municipio();
        $form = $this->createForm(MunicipioType::class, $municipio);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Debug: verificar el valor antes de guardar
            $numeroPlazas = $municipio->getNumeroPlazas();
            
            $entityManager->persist($municipio);
            $entityManager->flush();

            $this->addFlash('success', 'Municipio creado correctamente.' . ($numeroPlazas ? " Número de plazas: {$numeroPlazas}" : ''));
            return $this->redirectToRoute('app_municipio_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('municipio/new.html.twig', [
            'municipio' => $municipio,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_municipio_show', methods: ['GET'])]
    public function show(Municipio $municipio, ConvocatoriaRepository $convocatoriaRepository): Response
    {
        // Obtener convocatorias que contienen este municipio
        $convocatorias = $convocatoriaRepository->createQueryBuilder('c')
            ->innerJoin('c.municipios', 'm')
            ->where('m.id = :municipioId')
            ->setParameter('municipioId', $municipio->getId())
            ->orderBy('c.nombre', 'ASC')
            ->getQuery()
            ->getResult();
        
        return $this->render('municipio/show.html.twig', [
            'municipio' => $municipio,
            'convocatorias' => $convocatorias,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_municipio_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Municipio $municipio, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MunicipioType::class, $municipio);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Debug: verificar el valor antes de guardar
            $numeroPlazas = $municipio->getNumeroPlazas();
            
            $entityManager->flush();

            $this->addFlash('success', 'Municipio actualizado correctamente.' . ($numeroPlazas ? " Número de plazas: {$numeroPlazas}" : ''));
            return $this->redirectToRoute('app_municipio_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('municipio/edit.html.twig', [
            'municipio' => $municipio,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle-activo', name: 'app_municipio_toggle_activo', methods: ['POST'])]
    public function toggleActivo(Municipio $municipio, EntityManagerInterface $entityManager, Request $request): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$municipio->getId(), $request->getPayload()->getString('_token'))) {
            $municipio->setActivo(!$municipio->isActivo());
            $entityManager->flush();

            $estado = $municipio->isActivo() ? 'activado' : 'desactivado';
            $this->addFlash('success', "El municipio '{$municipio->getNombre()}' ha sido {$estado} correctamente.");
        }

        return $this->redirectToRoute('app_municipio_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/asignar-alumnos', name: 'app_municipio_asignar_alumnos', methods: ['GET', 'POST'])]
    public function asignarAlumnos(Request $request, Municipio $municipio, UserRepository $userRepository, EntityManagerInterface $entityManager, ConvocatoriaRepository $convocatoriaRepository): Response
    {
        // Esta funcionalidad está deshabilitada. Los alumnos ahora se asignan a través de convocatorias.
        // Si un alumno está asignado a una convocatoria, automáticamente tiene acceso a todos los municipios de esa convocatoria.
        
        $this->addFlash('info', 'La asignación directa de alumnos a municipios está deshabilitada. Los alumnos deben asignarse a convocatorias, y automáticamente tendrán acceso a todos los municipios de esas convocatorias.');
        
        // Obtener las convocatorias que contienen este municipio
        $convocatorias = $convocatoriaRepository->createQueryBuilder('c')
            ->innerJoin('c.municipios', 'm')
            ->where('m.id = :municipioId')
            ->andWhere('c.activo = :activo')
            ->setParameter('municipioId', $municipio->getId())
            ->setParameter('activo', true)
            ->orderBy('c.nombre', 'ASC')
            ->getQuery()
            ->getResult();
        
        return $this->render('municipio/asignar_alumnos.html.twig', [
            'municipio' => $municipio,
            'convocatorias' => $convocatorias,
            'deshabilitado' => true,
        ]);
    }
}












