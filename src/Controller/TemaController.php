<?php

namespace App\Controller;

use App\Entity\Tema;
use App\Form\TemaType;
use App\Repository\TemaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tema')]
#[IsGranted('ROLE_PROFESOR')]
class TemaController extends AbstractController
{
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
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $tema = new Tema();
        $form = $this->createForm(TemaType::class, $tema);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
    public function edit(Request $request, Tema $tema, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TemaType::class, $tema);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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

