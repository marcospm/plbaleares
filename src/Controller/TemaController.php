<?php

namespace App\Controller;

use App\Entity\Tema;
use App\Form\TemaType;
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

#[Route('/tema')]
#[IsGranted('ROLE_PROFESOR')]
class TemaController extends AbstractController
{
    public function __construct(
        private KernelInterface $kernel
    ) {
    }
    #[Route('/', name: 'app_tema_index', methods: ['GET'])]
    public function index(TemaRepository $temaRepository, Request $request): Response
    {
        $search = $request->query->get('search', '');
        $temas = $temaRepository->findAll();

        if (!empty($search)) {
            $temas = array_filter($temas, function($tema) use ($search) {
                return stripos($tema->getNombre(), $search) !== false ||
                       stripos($tema->getDescripcion() ?? '', $search) !== false;
            });
        }

        return $this->render('tema/index.html.twig', [
            'temas' => $temas,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_tema_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $tema = new Tema();
        $form = $this->createForm(TemaType::class, $tema);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $pdfFile */
            $pdfFile = $form->get('pdfFile')->getData();
            
            if ($pdfFile) {
                // Validar extensión del archivo
                $extension = strtolower($pdfFile->getClientOriginalExtension());
                if ($extension !== 'pdf') {
                    $this->addFlash('error', 'El archivo debe ser un PDF (extensión .pdf).');
                    return $this->render('tema/new.html.twig', [
                        'tema' => $tema,
                        'form' => $form,
                    ]);
                }
                
                $originalFilename = pathinfo($pdfFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.pdf';
                
                try {
                    $directorio = $this->kernel->getProjectDir() . '/public/pdfs';
                    if (!is_dir($directorio)) {
                        mkdir($directorio, 0755, true);
                    }
                    
                    $pdfFile->move($directorio, $newFilename);
                    $tema->setRutaPdf('/pdfs/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error al subir el PDF: ' . $e->getMessage());
                    return $this->render('tema/new.html.twig', [
                        'tema' => $tema,
                        'form' => $form,
                    ]);
                }
            }
            
            $entityManager->persist($tema);
            $entityManager->flush();

            $this->addFlash('success', 'Tema creado correctamente.');
            return $this->redirectToRoute('app_tema_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tema/new.html.twig', [
            'tema' => $tema,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_tema_show', methods: ['GET'])]
    public function show(Tema $tema): Response
    {
        return $this->render('tema/show.html.twig', [
            'tema' => $tema,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_tema_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Tema $tema, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(TemaType::class, $tema);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $pdfFile */
            $pdfFile = $form->get('pdfFile')->getData();
            
            if ($pdfFile) {
                // Validar extensión del archivo
                $extension = strtolower($pdfFile->getClientOriginalExtension());
                if ($extension !== 'pdf') {
                    $this->addFlash('error', 'El archivo debe ser un PDF (extensión .pdf).');
                    return $this->render('tema/edit.html.twig', [
                        'tema' => $tema,
                        'form' => $form,
                    ]);
                }
                
                // Eliminar PDF anterior si existe (intentar, pero no fallar si no es posible)
                if ($tema->getRutaPdf()) {
                    $rutaArchivoAnterior = $this->kernel->getProjectDir() . '/public' . $tema->getRutaPdf();
                    if (file_exists($rutaArchivoAnterior)) {
                        try {
                            // Verificar si el archivo es escribible antes de intentar eliminarlo
                            if (is_writable($rutaArchivoAnterior) || is_writable(dirname($rutaArchivoAnterior))) {
                                @unlink($rutaArchivoAnterior);
                            }
                            // Si no se puede eliminar (sistema de solo lectura), simplemente continuar
                            // El nuevo archivo se guardará con un nombre único de todos modos
                        } catch (\Exception $e) {
                            // Ignorar el error si no se puede eliminar (puede ser sistema de solo lectura)
                            // El nuevo archivo se guardará con un nombre único de todos modos
                        }
                    }
                }
                
                $originalFilename = pathinfo($pdfFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.pdf';
                
                try {
                    $directorio = $this->kernel->getProjectDir() . '/public/pdfs';
                    if (!is_dir($directorio)) {
                        mkdir($directorio, 0755, true);
                    }
                    
                    $pdfFile->move($directorio, $newFilename);
                    $tema->setRutaPdf('/pdfs/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error al subir el PDF: ' . $e->getMessage());
                    return $this->render('tema/edit.html.twig', [
                        'tema' => $tema,
                        'form' => $form,
                    ]);
                }
            }
            
            $entityManager->flush();

            $this->addFlash('success', 'Tema actualizado correctamente.');
            return $this->redirectToRoute('app_tema_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tema/edit.html.twig', [
            'tema' => $tema,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle-activo', name: 'app_tema_toggle_activo', methods: ['POST'])]
    public function toggleActivo(Tema $tema, EntityManagerInterface $entityManager, Request $request): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$tema->getId(), $request->getPayload()->getString('_token'))) {
            $tema->setActivo(!$tema->isActivo());
            $entityManager->flush();

            $estado = $tema->isActivo() ? 'activado' : 'desactivado';
            $this->addFlash('success', "El tema '{$tema->getNombre()}' ha sido {$estado} correctamente.");
        }

        return $this->redirectToRoute('app_tema_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_tema_delete', methods: ['POST'])]
    public function delete(Request $request, Tema $tema, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$tema->getId(), $request->getPayload()->get('_token'))) {
            $entityManager->remove($tema);
            $entityManager->flush();
            $this->addFlash('success', 'Tema eliminado correctamente.');
        }

        return $this->redirectToRoute('app_tema_index', [], Response::HTTP_SEE_OTHER);
    }
}

