<?php

namespace App\Controller;

use App\Entity\TemaMunicipal;
use App\Form\TemaMunicipalType;
use App\Repository\TemaMunicipalRepository;
use App\Repository\MunicipioRepository;
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
    public function index(TemaMunicipalRepository $temaMunicipalRepository, MunicipioRepository $municipioRepository, Request $request): Response
    {
        $municipioId = $request->query->getInt('municipio');
        $temas = $temaMunicipalRepository->findAll();
        
        if ($municipioId > 0) {
            $municipio = $municipioRepository->find($municipioId);
            if ($municipio) {
                $temas = $temaMunicipalRepository->findByMunicipio($municipio);
            }
        }

        return $this->render('tema_municipal/index.html.twig', [
            'temas' => $temas,
            'municipios' => $municipioRepository->findAll(),
            'municipioSeleccionado' => $municipioId,
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

