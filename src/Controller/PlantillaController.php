<?php

namespace App\Controller;

use App\Entity\Plantilla;
use App\Entity\PlantillaMunicipal;
use App\Form\PlantillaType;
use App\Form\PlantillaMunicipalType;
use App\Repository\PlantillaRepository;
use App\Repository\PlantillaMunicipalRepository;
use App\Repository\TemaRepository;
use App\Repository\TemaMunicipalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/plantilla')]
#[IsGranted('ROLE_PROFESOR')]
class PlantillaController extends AbstractController
{
    public function __construct(
        private PlantillaRepository $plantillaRepository,
        private PlantillaMunicipalRepository $plantillaMunicipalRepository,
        private TemaRepository $temaRepository,
        private TemaMunicipalRepository $temaMunicipalRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/', name: 'app_plantilla_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $tipo = $request->query->get('tipo', 'general'); // 'general' o 'municipal'
        $temaId = $request->query->getInt('tema');
        $dificultad = $request->query->get('dificultad', '');

        if ($tipo === 'municipal') {
            $qb = $this->plantillaMunicipalRepository->createQueryBuilder('p')
                ->leftJoin('p.temaMunicipal', 't')
                ->addSelect('t')
                ->leftJoin('t.municipio', 'm')
                ->addSelect('m')
                ->orderBy('p.temaMunicipal', 'ASC')
                ->addOrderBy('p.dificultad', 'ASC')
                ->addOrderBy('p.nombre', 'ASC');

            if ($temaId > 0) {
                $qb->andWhere('p.temaMunicipal = :temaId')
                   ->setParameter('temaId', $temaId);
            }

            if (!empty($dificultad)) {
                $qb->andWhere('p.dificultad = :dificultad')
                   ->setParameter('dificultad', $dificultad);
            }

            $plantillas = $qb->getQuery()->getResult();
            $temas = $this->temaMunicipalRepository->findBy(['activo' => true], ['nombre' => 'ASC']);
            
            // Calcular número de preguntas activas en batch para todas las plantillas
            $plantillasIds = array_map(fn($p) => $p->getId(), $plantillas);
            $preguntasCounts = [];
            if (!empty($plantillasIds)) {
                $counts = $this->entityManager->createQueryBuilder()
                    ->select('IDENTITY(p.plantilla) as plantilla_id, COUNT(p.id) as count')
                    ->from('App\Entity\PreguntaMunicipal', 'p')
                    ->where('p.plantilla IN (:plantillasIds)')
                    ->andWhere('p.activo = :activo')
                    ->setParameter('plantillasIds', $plantillasIds)
                    ->setParameter('activo', true)
                    ->groupBy('p.plantilla')
                    ->getQuery()
                    ->getResult();
                
                foreach ($counts as $count) {
                    $preguntasCounts[$count['plantilla_id']] = (int)$count['count'];
                }
            }
            
        } else {
            $qb = $this->plantillaRepository->createQueryBuilder('p')
                ->leftJoin('p.tema', 't')
                ->addSelect('t')
                ->orderBy('p.tema', 'ASC')
                ->addOrderBy('p.dificultad', 'ASC')
                ->addOrderBy('p.nombre', 'ASC');

            if ($temaId > 0) {
                $qb->andWhere('p.tema = :temaId')
                   ->setParameter('temaId', $temaId);
            }

            if (!empty($dificultad)) {
                $qb->andWhere('p.dificultad = :dificultad')
                   ->setParameter('dificultad', $dificultad);
            }

            $plantillas = $qb->getQuery()->getResult();
            $temas = $this->temaRepository->findBy(['activo' => true], ['nombre' => 'ASC']);
            
            // Calcular número de preguntas activas en batch para todas las plantillas
            $plantillasIds = array_map(fn($p) => $p->getId(), $plantillas);
            $preguntasCounts = [];
            if (!empty($plantillasIds)) {
                $counts = $this->entityManager->createQueryBuilder()
                    ->select('IDENTITY(p.plantilla) as plantilla_id, COUNT(p.id) as count')
                    ->from('App\Entity\Pregunta', 'p')
                    ->where('p.plantilla IN (:plantillasIds)')
                    ->andWhere('p.activo = :activo')
                    ->setParameter('plantillasIds', $plantillasIds)
                    ->setParameter('activo', true)
                    ->groupBy('p.plantilla')
                    ->getQuery()
                    ->getResult();
                
                foreach ($counts as $count) {
                    $preguntasCounts[$count['plantilla_id']] = (int)$count['count'];
                }
            }
            
        }

        return $this->render('plantilla/index.html.twig', [
            'plantillas' => $plantillas,
            'preguntasCounts' => $preguntasCounts ?? [],
            'temas' => $temas,
            'tipo' => $tipo,
            'temaSeleccionado' => $temaId,
            'dificultadSeleccionada' => $dificultad,
        ]);
    }

    #[Route('/new', name: 'app_plantilla_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $tipo = $request->query->get('tipo', 'general');
        
        if ($tipo === 'municipal') {
            $plantilla = new PlantillaMunicipal();
            $form = $this->createForm(PlantillaMunicipalType::class, $plantilla);
        } else {
            $plantilla = new Plantilla();
            $form = $this->createForm(PlantillaType::class, $plantilla);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($plantilla);
            $this->entityManager->flush();

            $this->addFlash('success', 'Plantilla creada correctamente.');
            return $this->redirectToRoute('app_plantilla_index', ['tipo' => $tipo], Response::HTTP_SEE_OTHER);
        }

        return $this->render('plantilla/new.html.twig', [
            'plantilla' => $plantilla,
            'form' => $form,
            'tipo' => $tipo,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_plantilla_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $tipo = $request->query->get('tipo', 'general');
        
        if ($tipo === 'municipal') {
            $plantilla = $this->plantillaMunicipalRepository->find($id);
            if (!$plantilla) {
                throw $this->createNotFoundException('Plantilla no encontrada');
            }
            $form = $this->createForm(PlantillaMunicipalType::class, $plantilla);
        } else {
            $plantilla = $this->plantillaRepository->find($id);
            if (!$plantilla) {
                throw $this->createNotFoundException('Plantilla no encontrada');
            }
            $form = $this->createForm(PlantillaType::class, $plantilla);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Plantilla actualizada correctamente.');
            return $this->redirectToRoute('app_plantilla_index', ['tipo' => $tipo], Response::HTTP_SEE_OTHER);
        }

        return $this->render('plantilla/edit.html.twig', [
            'plantilla' => $plantilla,
            'form' => $form,
            'tipo' => $tipo,
        ]);
    }

    #[Route('/{id}', name: 'app_plantilla_delete', methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $tipo = $request->query->get('tipo', 'general');
        
        if ($tipo === 'municipal') {
            $plantilla = $this->plantillaMunicipalRepository->find($id);
        } else {
            $plantilla = $this->plantillaRepository->find($id);
        }

        if (!$plantilla) {
            throw $this->createNotFoundException('Plantilla no encontrada');
        }

        // Verificar que no tenga preguntas asignadas usando consulta optimizada
        $preguntasCount = 0;
        if ($tipo === 'municipal') {
            $preguntasCount = $this->plantillaMunicipalRepository->countPreguntasActivas($plantilla);
        } else {
            $preguntasCount = $this->plantillaRepository->countPreguntasActivas($plantilla);
        }

        if ($preguntasCount > 0) {
            $this->addFlash('error', "No se puede eliminar la plantilla porque tiene {$preguntasCount} pregunta(s) asignada(s).");
            return $this->redirectToRoute('app_plantilla_index', ['tipo' => $tipo], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete'.$id, $request->getPayload()->getString('_token'))) {
            $this->entityManager->remove($plantilla);
            $this->entityManager->flush();
            $this->addFlash('success', 'Plantilla eliminada correctamente.');
        }

        return $this->redirectToRoute('app_plantilla_index', ['tipo' => $tipo], Response::HTTP_SEE_OTHER);
    }

    #[Route('/api/por-tema-dificultad', name: 'app_plantilla_api_por_tema_dificultad', methods: ['GET'])]
    public function getPlantillasPorTemaYDificultad(Request $request): JsonResponse
    {
        $temaId = $request->query->getInt('tema');
        $dificultad = $request->query->get('dificultad');

        if (!$temaId || !$dificultad) {
            return new JsonResponse(['plantillas' => []]);
        }

        $tema = $this->temaRepository->find($temaId);
        if (!$tema) {
            return new JsonResponse(['plantillas' => []]);
        }

        $plantillas = $this->plantillaRepository->createQueryBuilder('p')
            ->leftJoin('p.tema', 't')
            ->addSelect('t')
            ->where('p.tema = :tema')
            ->andWhere('p.dificultad = :dificultad')
            ->setParameter('tema', $tema)
            ->setParameter('dificultad', $dificultad)
            ->orderBy('p.nombre', 'ASC')
            ->getQuery()
            ->getResult();

        // Obtener contadores de preguntas activas en batch
        $plantillasIds = array_map(fn($p) => $p->getId(), $plantillas);
        $preguntasCounts = [];
        if (!empty($plantillasIds)) {
            $counts = $this->entityManager->createQueryBuilder()
                ->select('IDENTITY(p.plantilla) as plantilla_id, COUNT(p.id) as count')
                ->from('App\Entity\Pregunta', 'p')
                ->where('p.plantilla IN (:plantillasIds)')
                ->andWhere('p.activo = :activo')
                ->setParameter('plantillasIds', $plantillasIds)
                ->setParameter('activo', true)
                ->groupBy('p.plantilla')
                ->getQuery()
                ->getResult();
            
            foreach ($counts as $count) {
                $preguntasCounts[$count['plantilla_id']] = (int)$count['count'];
            }
        }

        $plantillasData = array_map(function($plantilla) use ($preguntasCounts) {
            return [
                'id' => $plantilla->getId(),
                'nombre' => $plantilla->getNombre(),
                'numeroPreguntas' => $preguntasCounts[$plantilla->getId()] ?? 0,
            ];
        }, $plantillas);

        return new JsonResponse(['plantillas' => $plantillasData]);
    }

    #[Route('/api/por-tema-municipal-dificultad', name: 'app_plantilla_api_por_tema_municipal_dificultad', methods: ['GET'])]
    public function getPlantillasMunicipalesPorTemaYDificultad(Request $request): JsonResponse
    {
        $temaMunicipalId = $request->query->getInt('tema_municipal');
        $dificultad = $request->query->get('dificultad');
        $municipioId = $request->query->getInt('municipio');

        if (!$temaMunicipalId || !$dificultad || !$municipioId) {
            return new JsonResponse(['plantillas' => []]);
        }

        $temaMunicipal = $this->temaMunicipalRepository->find($temaMunicipalId);
        if (!$temaMunicipal) {
            return new JsonResponse(['plantillas' => []]);
        }

        // Verificar que el tema municipal pertenezca al municipio
        if ($temaMunicipal->getMunicipio()->getId() !== $municipioId) {
            return new JsonResponse(['plantillas' => []]);
        }

        $plantillas = $this->plantillaMunicipalRepository->createQueryBuilder('p')
            ->leftJoin('p.temaMunicipal', 't')
            ->addSelect('t')
            ->leftJoin('t.municipio', 'm')
            ->addSelect('m')
            ->where('p.temaMunicipal = :temaMunicipal')
            ->andWhere('p.dificultad = :dificultad')
            ->setParameter('temaMunicipal', $temaMunicipal)
            ->setParameter('dificultad', $dificultad)
            ->orderBy('p.nombre', 'ASC')
            ->getQuery()
            ->getResult();

        // Obtener contadores de preguntas activas en batch
        $plantillasIds = array_map(fn($p) => $p->getId(), $plantillas);
        $preguntasCounts = [];
        if (!empty($plantillasIds)) {
            $counts = $this->entityManager->createQueryBuilder()
                ->select('IDENTITY(p.plantilla) as plantilla_id, COUNT(p.id) as count')
                ->from('App\Entity\PreguntaMunicipal', 'p')
                ->where('p.plantilla IN (:plantillasIds)')
                ->andWhere('p.activo = :activo')
                ->setParameter('plantillasIds', $plantillasIds)
                ->setParameter('activo', true)
                ->groupBy('p.plantilla')
                ->getQuery()
                ->getResult();
            
            foreach ($counts as $count) {
                $preguntasCounts[$count['plantilla_id']] = (int)$count['count'];
            }
        }

        $plantillasData = array_map(function($plantilla) use ($preguntasCounts) {
            return [
                'id' => $plantilla->getId(),
                'nombre' => $plantilla->getNombre(),
                'numeroPreguntas' => $preguntasCounts[$plantilla->getId()] ?? 0,
            ];
        }, $plantillas);

        return new JsonResponse(['plantillas' => $plantillasData]);
    }
}
