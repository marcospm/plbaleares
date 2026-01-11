<?php

namespace App\Controller;

use App\Entity\Ley;
use App\Form\LeyType;
use App\Repository\LeyRepository;
use App\Service\BoeLeyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/ley')]
#[IsGranted('ROLE_PROFESOR')]
class LeyController extends AbstractController
{
    #[Route('/', name: 'app_ley_index', methods: ['GET'])]
    public function index(LeyRepository $leyRepository, BoeLeyService $boeLeyService, Request $request): Response
    {
        $search = $request->query->get('search', '');
        $leyes = $leyRepository->findAllOrderedByNombre();

        if (!empty($search)) {
            $leyes = array_filter($leyes, function($ley) use ($search) {
                return stripos($ley->getNombre(), $search) !== false ||
                       stripos($ley->getDescripcion() ?? '', $search) !== false;
            });
        }

        // Obtener información del BOE para cada ley
        $leyesConBoe = [];
        foreach ($leyes as $ley) {
            $infoBoe = $boeLeyService->getInfoLey($ley->getId());
            $leyesConBoe[] = [
                'ley' => $ley,
                'ultima_actualizacion' => $infoBoe['ultima_actualizacion'],
                'tiene_link' => $infoBoe['tiene_link'],
                'articulos_afectados' => $infoBoe['articulos_afectados'],
                'otros_afectados' => $infoBoe['otros_afectados'],
            ];
        }

        return $this->render('ley/index.html.twig', [
            'leyesConBoe' => $leyesConBoe,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_ley_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $ley = new Ley();
        $form = $this->createForm(LeyType::class, $ley);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($ley);
            $entityManager->flush();

            $this->addFlash('success', 'Ley creada correctamente.');
            return $this->redirectToRoute('app_ley_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ley/new.html.twig', [
            'ley' => $ley,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_ley_show', methods: ['GET'])]
    public function show(Ley $ley, BoeLeyService $boeLeyService): Response
    {
        $infoBoe = $boeLeyService->getInfoLey($ley->getId());
        
        return $this->render('ley/show.html.twig', [
            'ley' => $ley,
            'articulos_afectados' => $infoBoe['articulos_afectados'],
            'otros_afectados' => $infoBoe['otros_afectados'],
            'ultima_actualizacion' => $infoBoe['ultima_actualizacion'],
        ]);
    }

    #[Route('/{id}/edit', name: 'app_ley_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Ley $ley, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(LeyType::class, $ley);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Ley actualizada correctamente.');
            return $this->redirectToRoute('app_ley_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ley/edit.html.twig', [
            'ley' => $ley,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle-activo', name: 'app_ley_toggle_activo', methods: ['POST'])]
    public function toggleActivo(Ley $ley, EntityManagerInterface $entityManager, Request $request): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$ley->getId(), $request->getPayload()->getString('_token'))) {
            $ley->setActivo(!$ley->isActivo());
            $entityManager->flush();

            $estado = $ley->isActivo() ? 'activada' : 'desactivada';
            $this->addFlash('success', "La ley '{$ley->getNombre()}' ha sido {$estado} correctamente.");
        }

        return $this->redirectToRoute('app_ley_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_ley_delete', methods: ['POST'])]
    public function delete(Request $request, Ley $ley, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$ley->getId(), $request->getPayload()->get('_token'))) {
            $entityManager->remove($ley);
            $entityManager->flush();
            $this->addFlash('success', 'Ley eliminada correctamente.');
        }

        return $this->redirectToRoute('app_ley_index', [], Response::HTTP_SEE_OTHER);
    }
}

