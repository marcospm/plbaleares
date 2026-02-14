<?php

namespace App\Controller;

use App\Entity\Sesion;
use App\Form\SesionType;
use App\Repository\SesionRepository;
use App\Repository\TemaRepository;
use App\Repository\TemaMunicipalRepository;
use App\Repository\MunicipioRepository;
use App\Repository\ConvocatoriaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/sesion')]
#[IsGranted('ROLE_PROFESOR')]
final class SesionController extends AbstractController
{
    #[Route(name: 'app_sesion_index', methods: ['GET'])]
    public function index(
        SesionRepository $sesionRepository,
        TemaRepository $temaRepository,
        TemaMunicipalRepository $temaMunicipalRepository,
        MunicipioRepository $municipioRepository,
        ConvocatoriaRepository $convocatoriaRepository,
        Request $request
    ): Response {
        // Obtener filtros de la petición
        $search = $request->query->get('search', '');
        $temaId = $request->query->get('tema', '');
        $temaId = $temaId !== '' && $temaId !== null ? (int)$temaId : null;
        $temaMunicipalId = $request->query->get('tema_municipal', '');
        $temaMunicipalId = $temaMunicipalId !== '' && $temaMunicipalId !== null ? (int)$temaMunicipalId : null;
        $municipioId = $request->query->get('municipio', '');
        $municipioId = $municipioId !== '' && $municipioId !== null ? (int)$municipioId : null;
        $convocatoriaId = $request->query->get('convocatoria', '');
        $convocatoriaId = $convocatoriaId !== '' && $convocatoriaId !== null ? (int)$convocatoriaId : null;
        
        // Parámetros de paginación
        $itemsPerPage = 20;
        $page = max(1, $request->query->getInt('page', 1));

        // Obtener sesiones con paginación y filtros
        $result = $sesionRepository->findPaginatedForProfesor(
            $search ?: null,
            $temaId,
            $temaMunicipalId,
            $municipioId,
            $convocatoriaId,
            null,
            $page,
            $itemsPerPage
        );
        $sesiones = $result['sesiones'];
        $totalItems = $result['total'];
        
        // Calcular total de páginas
        $totalPages = max(1, ceil($totalItems / $itemsPerPage));
        $page = min($page, $totalPages);

        // Obtener datos para los filtros - todos los selectores deben estar rellenados de antemano
        $temas = $temaRepository->findBy(['activo' => true], ['id' => 'ASC']);
        $municipios = $municipioRepository->findBy(['activo' => true], ['nombre' => 'ASC']);
        $convocatorias = $convocatoriaRepository->findAll();
        $temasMunicipales = $temaMunicipalRepository->findBy(
            ['activo' => true],
            ['nombre' => 'ASC']
        );

        return $this->render('sesion/index.html.twig', [
            'sesiones' => $sesiones,
            'search' => $search,
            'temaId' => $temaId,
            'temaMunicipalId' => $temaMunicipalId,
            'municipioId' => $municipioId,
            'convocatoriaId' => $convocatoriaId,
            'temas' => $temas,
            'temasMunicipales' => $temasMunicipales,
            'municipios' => $municipios,
            'convocatorias' => $convocatorias,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'itemsPerPage' => $itemsPerPage,
        ]);
    }

    #[Route('/new', name: 'app_sesion_new', methods: ['GET'])]
    public function new(): Response
    {
        // Página de selección de tipo de sesión
        return $this->render('sesion/new.html.twig');
    }

    #[Route('/new/general', name: 'app_sesion_new_general', methods: ['GET', 'POST'])]
    public function newGeneral(Request $request, EntityManagerInterface $entityManager): Response
    {
        $sesion = new Sesion();
        $sesion->setCreadoPor($this->getUser());
        
        $form = $this->createForm(SesionType::class, $sesion, ['tipo' => 'general']);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                foreach ($form->getErrors(true) as $error) {
                    $fieldName = $error->getOrigin() ? $error->getOrigin()->getName() : 'formulario';
                    $this->addFlash('error', sprintf('Error en %s: %s', $fieldName, $error->getMessage()));
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Asegurar que solo hay temas generales
            $sesion->getTemasMunicipales()->clear();
            $sesion->setMunicipio(null);
            $sesion->setConvocatoria(null);
            
            if ($sesion->getTemas()->count() === 0) {
                $this->addFlash('error', 'Debes seleccionar al menos un tema general.');
                return $this->render('sesion/new_general.html.twig', [
                    'sesion' => $sesion,
                    'form' => $form,
                ]);
            }

            $entityManager->persist($sesion);
            $entityManager->flush();

            $this->addFlash('success', 'Sesión de temario general creada correctamente.');
            return $this->redirectToRoute('app_sesion_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('sesion/new_general.html.twig', [
            'sesion' => $sesion,
            'form' => $form,
        ]);
    }

    #[Route('/new/municipal', name: 'app_sesion_new_municipal', methods: ['GET', 'POST'])]
    public function newMunicipal(Request $request, EntityManagerInterface $entityManager): Response
    {
        $sesion = new Sesion();
        $sesion->setCreadoPor($this->getUser());
        
        // Obtener convocatoria y municipio del query string si existen
        $convocatoriaId = $request->query->getInt('convocatoria', 0);
        $municipioId = $request->query->getInt('municipio', 0);
        
        if ($convocatoriaId) {
            $convocatoria = $entityManager->getRepository(\App\Entity\Convocatoria::class)->find($convocatoriaId);
            if ($convocatoria) {
                $sesion->addConvocatoria($convocatoria);
            }
        }
        
        if ($municipioId) {
            $municipio = $entityManager->getRepository(\App\Entity\Municipio::class)->find($municipioId);
            if ($municipio) {
                $sesion->addMunicipio($municipio);
            }
        }
        
        $form = $this->createForm(SesionType::class, $sesion, ['tipo' => 'municipal']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Asegurar que solo hay temas municipales
            $sesion->getTemas()->clear();

            $entityManager->persist($sesion);
            $entityManager->flush();

            $this->addFlash('success', 'Sesión de temario municipal creada correctamente.');
            return $this->redirectToRoute('app_sesion_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('sesion/new_municipal.html.twig', [
            'sesion' => $sesion,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_sesion_show', methods: ['GET'])]
    public function show(Sesion $sesion): Response
    {
        return $this->render('sesion/show.html.twig', [
            'sesion' => $sesion,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_sesion_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Sesion $sesion, EntityManagerInterface $entityManager): Response
    {
        // Obtener convocatoria y municipio del query string si existen (para cargar temas municipales)
        $convocatoriaId = $request->query->getInt('convocatoria', 0);
        $municipioId = $request->query->getInt('municipio', 0);
        
        if ($convocatoriaId) {
            $convocatoria = $entityManager->getRepository(\App\Entity\Convocatoria::class)->find($convocatoriaId);
            if ($convocatoria && !$sesion->getConvocatorias()->contains($convocatoria)) {
                $sesion->addConvocatoria($convocatoria);
            }
        }
        
        if ($municipioId) {
            $municipio = $entityManager->getRepository(\App\Entity\Municipio::class)->find($municipioId);
            if ($municipio && !$sesion->getMunicipios()->contains($municipio)) {
                $sesion->addMunicipio($municipio);
            }
        }
        
        // Determinar el tipo de sesión según los datos existentes
        $tipo = null;
        if ($sesion->getTemas()->count() > 0) {
            $tipo = 'general';
        } elseif ($sesion->getTemasMunicipales()->count() > 0) {
            $tipo = 'municipal';
        }
        
        $form = $this->createForm(SesionType::class, $sesion, ['tipo' => $tipo]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                foreach ($form->getErrors(true) as $error) {
                    $fieldName = $error->getOrigin() ? $error->getOrigin()->getName() : 'formulario';
                    $this->addFlash('error', sprintf('Error en %s: %s', $fieldName, $error->getMessage()));
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Si estamos editando y hay campo tipoTema, usar ese valor
            if ($tipo === null && $form->has('tipoTema')) {
                $tipoSeleccionado = $form->get('tipoTema')->getData();
                if ($tipoSeleccionado) {
                    $tipo = $tipoSeleccionado;
                }
            }
            
            // Validar según el tipo de sesión
            if ($tipo === 'general') {
                // Asegurar que solo hay temas generales
                $sesion->getTemasMunicipales()->clear();
                $sesion->getMunicipios()->clear();
                $sesion->getConvocatorias()->clear();
                
                if ($sesion->getTemas()->count() === 0) {
                    $this->addFlash('error', 'Debes seleccionar al menos un tema general.');
                    return $this->render('sesion/edit.html.twig', [
                        'sesion' => $sesion,
                        'form' => $form,
                        'tipo' => $tipo,
                    ]);
                }
            } elseif ($tipo === 'municipal') {
                // Asegurar que solo hay temas municipales
                $sesion->getTemas()->clear();
            }

            $entityManager->flush();

            $this->addFlash('success', 'Sesión actualizada correctamente.');
            return $this->redirectToRoute('app_sesion_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('sesion/edit.html.twig', [
            'sesion' => $sesion,
            'form' => $form,
            'tipo' => $tipo,
        ]);
    }

    #[Route('/{id}', name: 'app_sesion_delete', methods: ['POST'])]
    public function delete(Request $request, Sesion $sesion, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$sesion->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($sesion);
            $entityManager->flush();
            $this->addFlash('success', 'Sesión eliminada correctamente.');
        }

        return $this->redirectToRoute('app_sesion_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/ajax/municipios/{convocatoriaId}', name: 'app_sesion_ajax_municipios', methods: ['GET'])]
    public function ajaxMunicipios(int $convocatoriaId, EntityManagerInterface $entityManager): JsonResponse
    {
        $convocatoria = $entityManager->getRepository(\App\Entity\Convocatoria::class)->find($convocatoriaId);
        
        if (!$convocatoria) {
            return $this->json(['error' => 'Convocatoria no encontrada'], 404);
        }

        $municipios = [];
        foreach ($convocatoria->getMunicipios() as $municipio) {
            if ($municipio->isActivo()) {
                $municipios[] = [
                    'id' => $municipio->getId(),
                    'nombre' => $municipio->getNombre(),
                ];
            }
        }

        return $this->json(['municipios' => $municipios]);
    }

    #[Route('/ajax/temas-municipales/{municipioId}', name: 'app_sesion_ajax_temas_municipales', methods: ['GET'])]
    public function ajaxTemasMunicipales(int $municipioId, EntityManagerInterface $entityManager): JsonResponse
    {
        $municipio = $entityManager->getRepository(\App\Entity\Municipio::class)->find($municipioId);
        
        if (!$municipio) {
            return $this->json(['error' => 'Municipio no encontrado'], 404);
        }

        $temasMunicipales = [];
        foreach ($municipio->getTemasMunicipales() as $temaMunicipal) {
            if ($temaMunicipal->isActivo()) {
                $temasMunicipales[] = [
                    'id' => $temaMunicipal->getId(),
                    'nombre' => $temaMunicipal->getNombre(),
                ];
            }
        }

        return $this->json(['temasMunicipales' => $temasMunicipales]);
    }
}
