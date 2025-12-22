<?php

namespace App\Controller;

use App\Entity\FechasPruebas;
use App\Form\FechasPruebasType;
use App\Repository\FechasPruebasRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/fechas-pruebas')]
#[IsGranted('ROLE_PROFESOR')]
class FechasPruebasController extends AbstractController
{
    #[Route('/', name: 'app_fechas_pruebas_index', methods: ['GET', 'POST'])]
    public function index(Request $request, FechasPruebasRepository $fechasPruebasRepository, EntityManagerInterface $entityManager): Response
    {
        // Obtener o crear la entrada de fechas
        $fechasPruebas = $fechasPruebasRepository->findActivas();
        if (!$fechasPruebas) {
            $fechasPruebas = new FechasPruebas();
        }

        $form = $this->createForm(FechasPruebasType::class, $fechasPruebas);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fechasPruebas->setFechaActualizacion(new \DateTime());
            $entityManager->persist($fechasPruebas);
            $entityManager->flush();

            $this->addFlash('success', 'Fechas de pruebas actualizadas correctamente.');
            return $this->redirectToRoute('app_fechas_pruebas_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('fechas_pruebas/index.html.twig', [
            'fechasPruebas' => $fechasPruebas,
            'form' => $form,
        ]);
    }
}



