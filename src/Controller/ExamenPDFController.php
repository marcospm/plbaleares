<?php

namespace App\Controller;

use App\Entity\ExamenPDF;
use App\Form\ExamenPDFType;
use App\Repository\ExamenPDFRepository;
use App\Repository\TemaRepository;
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

#[Route('/examen-pdf')]
#[IsGranted('ROLE_PROFESOR')]
class ExamenPDFController extends AbstractController
{
    public function __construct(
        private KernelInterface $kernel
    ) {
    }

    #[Route('/', name: 'app_examen_pdf_index', methods: ['GET'])]
    public function index(ExamenPDFRepository $examenPDFRepository, TemaRepository $temaRepository, Request $request): Response
    {
        $temaId = $request->query->getInt('tema');
        $examenes = $examenPDFRepository->findAll();
        
        if ($temaId > 0) {
            $examenes = array_filter($examenes, function($examen) use ($temaId) {
                foreach ($examen->getTemas() as $tema) {
                    if ($tema->getId() === $temaId) {
                        return true;
                    }
                }
                return false;
            });
        }
        
        // Ordenar por fecha de subida descendente
        usort($examenes, function($a, $b) {
            return $b->getFechaSubida() <=> $a->getFechaSubida();
        });
        
        $temas = $temaRepository->findAll();
        
        return $this->render('examen_pdf/index.html.twig', [
            'examenes' => $examenes,
            'temas' => $temas,
            'temaSeleccionado' => $temaId,
        ]);
    }

    #[Route('/new', name: 'app_examen_pdf_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $examenPDF = new ExamenPDF();
        $form = $this->createForm(ExamenPDFType::class, $examenPDF, ['require_file' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $archivoPDF */
            $archivoPDF = $form->get('archivoPDF')->getData();
            
            if ($archivoPDF) {
                // Validar extensión del archivo
                $extension = strtolower($archivoPDF->getClientOriginalExtension());
                if ($extension !== 'pdf') {
                    $this->addFlash('error', 'El archivo debe ser un PDF (extensión .pdf).');
                    return $this->render('examen_pdf/new.html.twig', [
                        'examenPDF' => $examenPDF,
                        'form' => $form,
                    ]);
                }
                
                $originalFilename = pathinfo($archivoPDF->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.pdf';
                
                try {
                    $directorio = $this->kernel->getProjectDir() . '/public/examenes';
                    if (!is_dir($directorio)) {
                        mkdir($directorio, 0755, true);
                    }
                    
                    $archivoPDF->move($directorio, $newFilename);
                    $examenPDF->setRutaArchivo('/examenes/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error al subir el archivo PDF: ' . $e->getMessage());
                    return $this->render('examen_pdf/new.html.twig', [
                        'examenPDF' => $examenPDF,
                        'form' => $form,
                    ]);
                }
            } else {
                // Si estamos creando y no hay archivo, mostrar error
                $this->addFlash('error', 'Debes subir un archivo PDF.');
                return $this->render('examen_pdf/new.html.twig', [
                    'examenPDF' => $examenPDF,
                    'form' => $form,
                ]);
            }
            
            // Manejar archivo de respuestas (opcional)
            /** @var UploadedFile|null $archivoRespuestas */
            $archivoRespuestas = $form->get('archivoRespuestas')->getData();
            
            if ($archivoRespuestas) {
                $extension = strtolower($archivoRespuestas->getClientOriginalExtension());
                if ($extension !== 'pdf') {
                    $this->addFlash('error', 'El archivo de respuestas debe ser un PDF (extensión .pdf).');
                    return $this->render('examen_pdf/new.html.twig', [
                        'examenPDF' => $examenPDF,
                        'form' => $form,
                    ]);
                }
                
                $originalFilename = pathinfo($archivoRespuestas->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = 'respuestas-' . $safeFilename . '-' . uniqid() . '.pdf';
                
                try {
                    $directorio = $this->kernel->getProjectDir() . '/public/examenes';
                    if (!is_dir($directorio)) {
                        mkdir($directorio, 0755, true);
                    }
                    
                    $archivoRespuestas->move($directorio, $newFilename);
                    $examenPDF->setRutaArchivoRespuestas('/examenes/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error al subir el archivo de respuestas: ' . $e->getMessage());
                    return $this->render('examen_pdf/new.html.twig', [
                        'examenPDF' => $examenPDF,
                        'form' => $form,
                    ]);
                }
            }
            
            $entityManager->persist($examenPDF);
            $entityManager->flush();

            $this->addFlash('success', 'Examen PDF creado correctamente.');
            return $this->redirectToRoute('app_examen_pdf_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('examen_pdf/new.html.twig', [
            'examenPDF' => $examenPDF,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_examen_pdf_show', methods: ['GET'])]
    public function show(ExamenPDF $examenPDF): Response
    {
        return $this->render('examen_pdf/show.html.twig', [
            'examenPDF' => $examenPDF,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_examen_pdf_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ExamenPDF $examenPDF, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ExamenPDFType::class, $examenPDF, ['require_file' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $archivoPDF */
            $archivoPDF = $form->get('archivoPDF')->getData();
            
            if ($archivoPDF) {
                // Validar extensión del archivo
                $extension = strtolower($archivoPDF->getClientOriginalExtension());
                if ($extension !== 'pdf') {
                    $this->addFlash('error', 'El archivo debe ser un PDF.');
                    return $this->render('examen_pdf/edit.html.twig', [
                        'examenPDF' => $examenPDF,
                        'form' => $form,
                    ]);
                }
                
                // Eliminar archivo anterior si existe
                if ($examenPDF->getRutaArchivo() && file_exists($this->kernel->getProjectDir() . '/public' . $examenPDF->getRutaArchivo())) {
                    unlink($this->kernel->getProjectDir() . '/public' . $examenPDF->getRutaArchivo());
                }
                
                $originalFilename = pathinfo($archivoPDF->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.pdf';
                
                try {
                    $directorio = $this->kernel->getProjectDir() . '/public/examenes';
                    if (!is_dir($directorio)) {
                        mkdir($directorio, 0755, true);
                    }
                    
                    $archivoPDF->move($directorio, $newFilename);
                    $examenPDF->setRutaArchivo('/examenes/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error al subir el archivo PDF: ' . $e->getMessage());
                    return $this->render('examen_pdf/edit.html.twig', [
                        'examenPDF' => $examenPDF,
                        'form' => $form,
                    ]);
                }
            }
            
            // Manejar archivo de respuestas (opcional)
            /** @var UploadedFile|null $archivoRespuestas */
            $archivoRespuestas = $form->get('archivoRespuestas')->getData();
            
            if ($archivoRespuestas) {
                $extension = strtolower($archivoRespuestas->getClientOriginalExtension());
                if ($extension !== 'pdf') {
                    $this->addFlash('error', 'El archivo de respuestas debe ser un PDF.');
                    return $this->render('examen_pdf/edit.html.twig', [
                        'examenPDF' => $examenPDF,
                        'form' => $form,
                    ]);
                }
                
                // Eliminar archivo anterior si existe
                if ($examenPDF->getRutaArchivoRespuestas() && file_exists($this->kernel->getProjectDir() . '/public' . $examenPDF->getRutaArchivoRespuestas())) {
                    unlink($this->kernel->getProjectDir() . '/public' . $examenPDF->getRutaArchivoRespuestas());
                }
                
                $originalFilename = pathinfo($archivoRespuestas->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = 'respuestas-' . $safeFilename . '-' . uniqid() . '.pdf';
                
                try {
                    $directorio = $this->kernel->getProjectDir() . '/public/examenes';
                    if (!is_dir($directorio)) {
                        mkdir($directorio, 0755, true);
                    }
                    
                    $archivoRespuestas->move($directorio, $newFilename);
                    $examenPDF->setRutaArchivoRespuestas('/examenes/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error al subir el archivo de respuestas: ' . $e->getMessage());
                    return $this->render('examen_pdf/edit.html.twig', [
                        'examenPDF' => $examenPDF,
                        'form' => $form,
                    ]);
                }
            }
            
            $entityManager->flush();

            $this->addFlash('success', 'Examen PDF actualizado correctamente.');
            return $this->redirectToRoute('app_examen_pdf_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('examen_pdf/edit.html.twig', [
            'examenPDF' => $examenPDF,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_examen_pdf_delete', methods: ['POST'])]
    public function delete(Request $request, ExamenPDF $examenPDF, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$examenPDF->getId(), $request->getPayload()->getString('_token'))) {
            // Eliminar archivo físico
            if ($examenPDF->getRutaArchivo() && file_exists($this->kernel->getProjectDir() . '/public' . $examenPDF->getRutaArchivo())) {
                unlink($this->kernel->getProjectDir() . '/public' . $examenPDF->getRutaArchivo());
            }
            
            $entityManager->remove($examenPDF);
            $entityManager->flush();
            
            $this->addFlash('success', 'Examen PDF eliminado correctamente.');
        }

        return $this->redirectToRoute('app_examen_pdf_index', [], Response::HTTP_SEE_OTHER);
    }
}
