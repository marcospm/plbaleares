<?php

namespace App\Controller;

use App\Entity\Recurso;
use App\Form\RecursoType;
use App\Repository\RecursoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/recurso')]
#[IsGranted('ROLE_PROFESOR')]
final class RecursoController extends AbstractController
{
    #[Route(name: 'app_recurso_index', methods: ['GET'])]
    public function index(RecursoRepository $recursoRepository): Response
    {
        return $this->render('recurso/index.html.twig', [
            'recursos' => $recursoRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_recurso_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $recurso = new Recurso();
        $form = $this->createForm(RecursoType::class, $recurso);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($recurso);
            $entityManager->flush();

            return $this->redirectToRoute('app_recurso_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recurso/new.html.twig', [
            'recurso' => $recurso,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_recurso_show', methods: ['GET'])]
    public function show(Recurso $recurso): Response
    {
        return $this->render('recurso/show.html.twig', [
            'recurso' => $recurso,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_recurso_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Recurso $recurso, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(RecursoType::class, $recurso);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_recurso_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recurso/edit.html.twig', [
            'recurso' => $recurso,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_recurso_delete', methods: ['POST'])]
    public function delete(Request $request, Recurso $recurso, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$recurso->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($recurso);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_recurso_index', [], Response::HTTP_SEE_OTHER);
    }
}
