<?php

namespace App\Controller;

use App\Entity\TemaMunicipal;
use App\Form\TemaMunicipalType;
use App\Repository\TemaMunicipalRepository;
use App\Repository\MunicipioRepository;
use App\Repository\ConvocatoriaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/tema-municipal')]
#[IsGranted('ROLE_PROFESOR')]
class TemaMunicipalController extends AbstractController
{
    public function __construct(
        private KernelInterface $kernel
    ) {
    }

    #[Route('/', name: 'app_tema_municipal_index', methods: ['GET'])]
    public function index(TemaMunicipalRepository $temaMunicipalRepository, MunicipioRepository $municipioRepository, ConvocatoriaRepository $convocatoriaRepository, Request $request): Response
    {
        $municipioId = $request->query->getInt('municipio');
        $convocatoriaId = $request->query->getInt('convocatoria');
        
        $qb = $temaMunicipalRepository->createQueryBuilder('t');
        
        // Filtrar por municipio si está seleccionado
        if ($municipioId > 0) {
            $qb->andWhere('t.municipio = :municipioId')
               ->setParameter('municipioId', $municipioId);
        }
        
        // Filtrar por convocatoria si está seleccionada
        if ($convocatoriaId > 0) {
            $convocatoria = $convocatoriaRepository->find($convocatoriaId);
            if ($convocatoria && $convocatoria->getMunicipios()->count() > 0) {
                $municipiosIds = $convocatoria->getMunicipios()->map(fn($m) => $m->getId())->toArray();
                $qb->andWhere('t.municipio IN (:municipiosIds)')
                   ->setParameter('municipiosIds', $municipiosIds);
            } else {
                // Si la convocatoria no tiene municipios, no mostrar nada
                $qb->andWhere('1 = 0');
            }
        }
        
        $temas = $qb->orderBy('t.nombre', 'ASC')
                    ->getQuery()
                    ->getResult();

        return $this->render('tema_municipal/index.html.twig', [
            'temas' => $temas,
            'municipios' => $municipioRepository->findBy([], ['nombre' => 'ASC']),
            'convocatorias' => $convocatoriaRepository->findBy([], ['nombre' => 'ASC']),
            'municipioSeleccionado' => $municipioId,
            'convocatoriaSeleccionada' => $convocatoriaId,
        ]);
    }

    #[Route('/new', name: 'app_tema_municipal_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $temaMunicipal = new TemaMunicipal();
        $form = $this->createForm(TemaMunicipalType::class, $temaMunicipal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $pdfFile */
            $pdfFile = $form->get('pdfFile')->getData();
            
            if ($pdfFile) {
                // Validar extensión del archivo manualmente
                $extension = strtolower($pdfFile->getClientOriginalExtension());
                if ($extension !== 'pdf') {
                    $this->addFlash('error', 'El archivo debe ser un PDF (extensión .pdf).');
                    return $this->render('tema_municipal/new.html.twig', [
                        'temaMunicipal' => $temaMunicipal,
                        'form' => $form,
                    ]);
                }
                
                // Validar tamaño máximo (10MB)
                if ($pdfFile->getSize() > 10 * 1024 * 1024) {
                    $this->addFlash('error', 'El archivo es demasiado grande. Tamaño máximo: 10MB.');
                    return $this->render('tema_municipal/new.html.twig', [
                        'temaMunicipal' => $temaMunicipal,
                        'form' => $form,
                    ]);
                }
                
                $originalFilename = pathinfo($pdfFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.pdf';
                
                try {
                    $pdfFile->move(
                        $this->kernel->getProjectDir() . '/public/pdf_municipales',
                        $newFilename
                    );
                    $temaMunicipal->setRutaPdf('/pdf_municipales/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error al subir el PDF: ' . $e->getMessage());
                }
            }
            
            $entityManager->persist($temaMunicipal);
            $entityManager->flush();

            $this->addFlash('success', 'Tema municipal creado correctamente.');
            return $this->redirectToRoute('app_tema_municipal_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tema_municipal/new.html.twig', [
            'tema_municipal' => $temaMunicipal,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_tema_municipal_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TemaMunicipal $temaMunicipal, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(TemaMunicipalType::class, $temaMunicipal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $pdfFile */
            $pdfFile = $form->get('pdfFile')->getData();
            
            if ($pdfFile) {
                // Validar extensión del archivo manualmente
                $extension = strtolower($pdfFile->getClientOriginalExtension());
                if ($extension !== 'pdf') {
                    $this->addFlash('error', 'El archivo debe ser un PDF (extensión .pdf).');
                    return $this->render('tema_municipal/edit.html.twig', [
                        'temaMunicipal' => $temaMunicipal,
                        'form' => $form,
                    ]);
                }
                
                // Validar tamaño máximo (10MB)
                if ($pdfFile->getSize() > 10 * 1024 * 1024) {
                    $this->addFlash('error', 'El archivo es demasiado grande. Tamaño máximo: 10MB.');
                    return $this->render('tema_municipal/edit.html.twig', [
                        'temaMunicipal' => $temaMunicipal,
                        'form' => $form,
                    ]);
                }
                
                // Eliminar PDF anterior si existe
                if ($temaMunicipal->getRutaPdf() && file_exists($this->kernel->getProjectDir() . '/public' . $temaMunicipal->getRutaPdf())) {
                    unlink($this->kernel->getProjectDir() . '/public' . $temaMunicipal->getRutaPdf());
                }
                
                $originalFilename = pathinfo($pdfFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.pdf';
                
                try {
                    $pdfFile->move(
                        $this->kernel->getProjectDir() . '/public/pdf_municipales',
                        $newFilename
                    );
                    $temaMunicipal->setRutaPdf('/pdf_municipales/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error al subir el PDF: ' . $e->getMessage());
                }
            }
            
            $entityManager->flush();

            $this->addFlash('success', 'Tema municipal actualizado correctamente.');
            return $this->redirectToRoute('app_tema_municipal_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tema_municipal/edit.html.twig', [
            'tema_municipal' => $temaMunicipal,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle-activo', name: 'app_tema_municipal_toggle_activo', methods: ['POST'])]
    public function toggleActivo(TemaMunicipal $temaMunicipal, EntityManagerInterface $entityManager, Request $request): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$temaMunicipal->getId(), $request->getPayload()->getString('_token'))) {
            $temaMunicipal->setActivo(!$temaMunicipal->isActivo());
            $entityManager->flush();

            $estado = $temaMunicipal->isActivo() ? 'activado' : 'desactivado';
            $this->addFlash('success', "El tema municipal '{$temaMunicipal->getNombre()}' ha sido {$estado} correctamente.");
        }

        return $this->redirectToRoute('app_tema_municipal_index', [], Response::HTTP_SEE_OTHER);
    }
}

