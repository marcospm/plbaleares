<?php

namespace App\Controller;

use App\Entity\CategoriaPsicotecnico;
use App\Form\CategoriaPsicotecnicoType;
use App\Repository\CategoriaPsicotecnicoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/psicotecnico/categoria')]
#[IsGranted('ROLE_ADMIN')]
class CategoriaPsicotecnicoController extends AbstractController
{
    #[Route('/', name: 'app_categoria_psicotecnico_index', methods: ['GET'])]
    public function index(CategoriaPsicotecnicoRepository $categoriaRepository): Response
    {
        return $this->render('psicotecnico/categoria_index.html.twig', [
            'categorias' => $categoriaRepository->findOrdenadas(),
        ]);
    }

    #[Route('/new', name: 'app_categoria_psicotecnico_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $categoria = new CategoriaPsicotecnico();
        $form = $this->createForm(CategoriaPsicotecnicoType::class, $categoria);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($categoria);
            $entityManager->flush();

            $this->addFlash('success', 'Categoría creada. Ya puedes usarla al colgar archivos.');

            return $this->redirectToRoute('app_categoria_psicotecnico_index');
        }

        return $this->render('psicotecnico/categoria_form.html.twig', [
            'categoria' => $categoria,
            'form' => $form,
            'titulo' => 'Nueva categoría',
        ]);
    }

    #[Route('/{id}/edit', name: 'app_categoria_psicotecnico_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, CategoriaPsicotecnico $categoria, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CategoriaPsicotecnicoType::class, $categoria);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Categoría actualizada.');

            return $this->redirectToRoute('app_categoria_psicotecnico_index');
        }

        return $this->render('psicotecnico/categoria_form.html.twig', [
            'categoria' => $categoria,
            'form' => $form,
            'titulo' => 'Editar categoría',
        ]);
    }

    #[Route('/{id}', name: 'app_categoria_psicotecnico_delete', methods: ['POST'])]
    public function delete(Request $request, CategoriaPsicotecnico $categoria, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $categoria->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_categoria_psicotecnico_index');
        }

        if ($categoria->getArchivos()->count() > 0) {
            $this->addFlash('error', 'No se puede eliminar porque hay archivos con esta categoría. Desactívala si no quieres que la vean los alumnos.');

            return $this->redirectToRoute('app_categoria_psicotecnico_index');
        }

        $entityManager->remove($categoria);
        $entityManager->flush();
        $this->addFlash('success', 'Categoría eliminada.');

        return $this->redirectToRoute('app_categoria_psicotecnico_index');
    }
}
