<?php

namespace App\Controller;

use App\Entity\Articulo;
use App\Form\ArticuloType;
use App\Repository\ArticuloRepository;
use App\Repository\LeyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/articulo')]
#[IsGranted('ROLE_PROFESOR')]
class ArticuloController extends AbstractController
{
    public function __construct(
        private KernelInterface $kernel
    ) {
    }
    #[Route('/', name: 'app_articulo_index', methods: ['GET'])]
    public function index(ArticuloRepository $articuloRepository, LeyRepository $leyRepository, Request $request): Response
    {
        $search = $request->query->get('search', '');
        $leyId = $request->query->getInt('ley');
        $numero = $request->query->get('numero', '');

        $leyes = $leyRepository->findAll();
        $articulos = $articuloRepository->findAll();

        // Filtrar por ley
        if ($leyId > 0) {
            $articulos = array_filter($articulos, function($articulo) use ($leyId) {
                return $articulo->getLey() && $articulo->getLey()->getId() === $leyId;
            });
        }

        // Filtrar por número
        if (!empty($numero)) {
            $articulos = array_filter($articulos, function($articulo) use ($numero) {
                return stripos($articulo->getNumero(), $numero) !== false;
            });
        }

        // Filtrar por búsqueda
        if (!empty($search)) {
            $articulos = array_filter($articulos, function($articulo) use ($search) {
                return stripos($articulo->getNumero(), $search) !== false ||
                       stripos($articulo->getExplicacion() ?? '', $search) !== false ||
                       ($articulo->getLey() && stripos($articulo->getLey()->getNombre(), $search) !== false);
            });
        }

        return $this->render('articulo/index.html.twig', [
            'articulos' => $articulos,
            'leyes' => $leyes,
            'search' => $search,
            'leySeleccionada' => $leyId,
            'numero' => $numero,
        ]);
    }

    #[Route('/new', name: 'app_articulo_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $articulo = new Articulo();
        $form = $this->createForm(ArticuloType::class, $articulo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $videoFile */
            $videoFile = $form->get('videoFile')->getData();
            
            if ($videoFile) {
                $originalFilename = pathinfo($videoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $videoFile->guessExtension();
                
                try {
                    $videoFile->move(
                        $this->kernel->getProjectDir() . '/public/videos',
                        $newFilename
                    );
                    $articulo->setVideo('/videos/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error al subir el video: ' . $e->getMessage());
                }
            }
            
            $entityManager->persist($articulo);
            $entityManager->flush();

            $this->addFlash('success', 'Artículo creado correctamente.');
            return $this->redirectToRoute('app_articulo_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('articulo/new.html.twig', [
            'articulo' => $articulo,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_articulo_show', methods: ['GET'])]
    public function show(Articulo $articulo): Response
    {
        return $this->render('articulo/show.html.twig', [
            'articulo' => $articulo,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_articulo_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Articulo $articulo, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ArticuloType::class, $articulo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $videoFile */
            $videoFile = $form->get('videoFile')->getData();
            
            if ($videoFile) {
                // Eliminar video anterior si existe
                if ($articulo->getVideo() && file_exists($this->kernel->getProjectDir() . '/public' . $articulo->getVideo())) {
                    unlink($this->kernel->getProjectDir() . '/public' . $articulo->getVideo());
                }
                
                $originalFilename = pathinfo($videoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $videoFile->guessExtension();
                
                try {
                    $videoFile->move(
                        $this->kernel->getProjectDir() . '/public/videos',
                        $newFilename
                    );
                    $articulo->setVideo('/videos/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error al subir el video: ' . $e->getMessage());
                }
            }
            
            $entityManager->flush();

            $this->addFlash('success', 'Artículo actualizado correctamente.');
            return $this->redirectToRoute('app_articulo_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('articulo/edit.html.twig', [
            'articulo' => $articulo,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle-activo', name: 'app_articulo_toggle_activo', methods: ['POST'])]
    public function toggleActivo(Articulo $articulo, EntityManagerInterface $entityManager, Request $request): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$articulo->getId(), $request->getPayload()->getString('_token'))) {
            $articulo->setActivo(!$articulo->isActivo());
            $entityManager->flush();

            $estado = $articulo->isActivo() ? 'activado' : 'desactivado';
            $this->addFlash('success', "El artículo '{$articulo->getNumero()}' ha sido {$estado} correctamente.");
        }

        return $this->redirectToRoute('app_articulo_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_articulo_delete', methods: ['POST'])]
    public function delete(Request $request, Articulo $articulo, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$articulo->getId(), $request->getPayload()->get('_token'))) {
            $entityManager->remove($articulo);
            $entityManager->flush();
            $this->addFlash('success', 'Artículo eliminado correctamente.');
        }

        return $this->redirectToRoute('app_articulo_index', [], Response::HTTP_SEE_OTHER);
    }
}

