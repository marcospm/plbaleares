<?php

namespace App\Controller;

use App\Entity\Articulo;
use App\Form\ArticuloType;
use App\Repository\ArticuloRepository;
use App\Repository\LeyRepository;
use App\Repository\MensajeArticuloRepository;
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
    public function index(
        ArticuloRepository $articuloRepository, 
        LeyRepository $leyRepository, 
        MensajeArticuloRepository $mensajeArticuloRepository,
        Request $request
    ): Response {
        $search = trim($request->query->get('search', ''));
        $leyId = $request->query->getInt('ley', 0);
        $numero = trim($request->query->get('numero', ''));

        // Parámetros de paginación
        $itemsPerPage = 20; // Número de artículos por página
        $page = max(1, $request->query->getInt('page', 1));

        // Obtener todas las leyes ordenadas por nombre
        $leyes = $leyRepository->findAllOrderedByNombre();

        // Obtener total de artículos con filtros (sin cargar entidades)
        $totalItems = $articuloRepository->countConFiltros(
            $leyId > 0 ? $leyId : null,
            !empty($search) ? $search : null,
            !empty($numero) ? $numero : null,
            null // null = todos los estados (activos e inactivos)
        );

        // Calcular paginación
        $totalPages = max(1, ceil($totalItems / $itemsPerPage));
        $page = min($page, $totalPages); // Asegurar que la página no exceda el total
        
        // Obtener los items de la página actual usando paginación SQL
        $offset = ($page - 1) * $itemsPerPage;
        $articulosPaginated = $articuloRepository->buscarConFiltrosPaginated(
            $leyId > 0 ? $leyId : null,
            !empty($search) ? $search : null,
            !empty($numero) ? $numero : null,
            null, // null = todos los estados (activos e inactivos)
            $offset,
            $itemsPerPage
        );

        // Obtener contadores de mensajes para todos los artículos de la página en una sola consulta
        $articulosIds = array_map(function($articulo) {
            return $articulo->getId();
        }, $articulosPaginated);
        $contadoresMensajes = $mensajeArticuloRepository->countMensajesPrincipalesPorArticulos($articulosIds);

        return $this->render('articulo/index.html.twig', [
            'articulos' => $articulosPaginated,
            'leyes' => $leyes,
            'search' => $search,
            'leySeleccionada' => $leyId,
            'numero' => $numero,
            'contadoresMensajes' => $contadoresMensajes,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'itemsPerPage' => $itemsPerPage,
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
    public function show(
        Articulo $articulo,
        MensajeArticuloRepository $mensajeArticuloRepository,
        Request $request
    ): Response {
        // Obtener parámetros de filtro de la query string
        $filtros = [];
        if ($request->query->get('search')) {
            $filtros['search'] = $request->query->get('search');
        }
        if ($request->query->getInt('ley') > 0) {
            $filtros['ley'] = $request->query->getInt('ley');
        }
        if ($request->query->get('numero')) {
            $filtros['numero'] = $request->query->get('numero');
        }
        // Mantener la página actual
        $page = $request->query->getInt('page', 1);
        if ($page > 1) {
            $filtros['page'] = $page;
        }

        $mensajes = $mensajeArticuloRepository->findMensajesPrincipales($articulo);
        $totalMensajes = count($mensajes);
        
        return $this->render('articulo/show.html.twig', [
            'articulo' => $articulo,
            'mensajes' => $mensajes,
            'totalMensajes' => $totalMensajes,
            'filtros' => $filtros,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_articulo_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Articulo $articulo, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ArticuloType::class, $articulo);
        $form->handleRequest($request);

        // Obtener parámetros de filtro de la query string o del request anterior
        $filtros = [];
        $search = $request->query->get('search') ?? $request->request->get('filtro_search');
        $ley = $request->query->getInt('ley') ?: $request->request->getInt('filtro_ley', 0);
        $numero = $request->query->get('numero') ?? $request->request->get('filtro_numero');
        
        if ($search) {
            $filtros['search'] = $search;
        }
        if ($ley > 0) {
            $filtros['ley'] = $ley;
        }
        if ($numero) {
            $filtros['numero'] = $numero;
        }
        // Mantener la página actual
        $page = $request->query->getInt('page') ?: $request->request->getInt('filtro_page', 1);
        if ($page > 1) {
            $filtros['page'] = $page;
        }

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
            return $this->redirectToRoute('app_articulo_index', $filtros, Response::HTTP_SEE_OTHER);
        }

        return $this->render('articulo/edit.html.twig', [
            'articulo' => $articulo,
            'form' => $form,
            'filtros' => $filtros,
        ]);
    }

    #[Route('/{id}/toggle-activo', name: 'app_articulo_toggle_activo', methods: ['POST'])]
    public function toggleActivo(Articulo $articulo, EntityManagerInterface $entityManager, Request $request): Response
    {
        // Obtener parámetros de filtro de la query string
        $filtros = [];
        if ($request->query->get('search')) {
            $filtros['search'] = $request->query->get('search');
        }
        if ($request->query->getInt('ley') > 0) {
            $filtros['ley'] = $request->query->getInt('ley');
        }
        if ($request->query->get('numero')) {
            $filtros['numero'] = $request->query->get('numero');
        }
        // Mantener la página actual
        $page = $request->query->getInt('page', 1);
        if ($page > 1) {
            $filtros['page'] = $page;
        }

        if ($this->isCsrfTokenValid('toggle'.$articulo->getId(), $request->getPayload()->getString('_token'))) {
            $articulo->setActivo(!$articulo->isActivo());
            $entityManager->flush();

            $estado = $articulo->isActivo() ? 'activado' : 'desactivado';
            $this->addFlash('success', "El artículo '{$articulo->getNumero()}' ha sido {$estado} correctamente.");
        }

        return $this->redirectToRoute('app_articulo_index', $filtros, Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_articulo_delete', methods: ['POST'])]
    public function delete(Request $request, Articulo $articulo, EntityManagerInterface $entityManager): Response
    {
        // Obtener parámetros de filtro de la query string
        $filtros = [];
        if ($request->query->get('search')) {
            $filtros['search'] = $request->query->get('search');
        }
        if ($request->query->getInt('ley') > 0) {
            $filtros['ley'] = $request->query->getInt('ley');
        }
        if ($request->query->get('numero')) {
            $filtros['numero'] = $request->query->get('numero');
        }
        // Mantener la página actual
        $page = $request->query->getInt('page', 1);
        if ($page > 1) {
            $filtros['page'] = $page;
        }

        if ($this->isCsrfTokenValid('delete'.$articulo->getId(), $request->getPayload()->get('_token'))) {
            $entityManager->remove($articulo);
            $entityManager->flush();
            $this->addFlash('success', 'Artículo eliminado correctamente.');
        }

        return $this->redirectToRoute('app_articulo_index', $filtros, Response::HTTP_SEE_OTHER);
    }
}

