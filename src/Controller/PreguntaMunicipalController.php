<?php

namespace App\Controller;

use App\Entity\PreguntaMunicipal;
use App\Form\PreguntaMunicipalType;
use App\Repository\PreguntaMunicipalRepository;
use App\Repository\MunicipioRepository;
use App\Repository\TemaMunicipalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pregunta-municipal')]
#[IsGranted('ROLE_PROFESOR')]
class PreguntaMunicipalController extends AbstractController
{
    #[Route('/', name: 'app_pregunta_municipal_index', methods: ['GET'])]
    public function index(
        PreguntaMunicipalRepository $preguntaMunicipalRepository,
        MunicipioRepository $municipioRepository,
        TemaMunicipalRepository $temaMunicipalRepository,
        Request $request
    ): Response {
        $municipioId = $request->query->getInt('municipio');
        $temaId = $request->query->getInt('tema');
        $dificultad = $request->query->get('dificultad', '');

        $preguntas = $preguntaMunicipalRepository->findAll();

        if ($municipioId > 0) {
            $municipio = $municipioRepository->find($municipioId);
            if ($municipio) {
                $preguntas = array_filter($preguntas, function($p) use ($municipio) {
                    return $p->getMunicipio()->getId() === $municipio->getId();
                });
            }
        }

        if ($temaId > 0) {
            $preguntas = array_filter($preguntas, function($p) use ($temaId) {
                return $p->getTemaMunicipal() && $p->getTemaMunicipal()->getId() === $temaId;
            });
        }

        if (!empty($dificultad)) {
            $preguntas = array_filter($preguntas, function($p) use ($dificultad) {
                return $p->getDificultad() === $dificultad;
            });
        }

        return $this->render('pregunta_municipal/index.html.twig', [
            'preguntas' => $preguntas,
            'municipios' => $municipioRepository->findAll(),
            'municipioSeleccionado' => $municipioId,
            'temaSeleccionado' => $temaId,
            'dificultadSeleccionada' => $dificultad,
        ]);
    }

    #[Route('/new', name: 'app_pregunta_municipal_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, MunicipioRepository $municipioRepository): Response
    {
        $preguntaMunicipal = new PreguntaMunicipal();
        $municipioId = $request->query->getInt('municipio');
        $municipio = $municipioId > 0 ? $municipioRepository->find($municipioId) : null;
        
        $form = $this->createForm(PreguntaMunicipalType::class, $preguntaMunicipal, ['municipio' => $municipio]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($preguntaMunicipal);
            $entityManager->flush();

            $this->addFlash('success', 'Pregunta municipal creada correctamente.');
            return $this->redirectToRoute('app_pregunta_municipal_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('pregunta_municipal/new.html.twig', [
            'pregunta_municipal' => $preguntaMunicipal,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_pregunta_municipal_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PreguntaMunicipal $preguntaMunicipal, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PreguntaMunicipalType::class, $preguntaMunicipal, ['municipio' => $preguntaMunicipal->getMunicipio()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Pregunta municipal actualizada correctamente.');
            return $this->redirectToRoute('app_pregunta_municipal_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('pregunta_municipal/edit.html.twig', [
            'pregunta_municipal' => $preguntaMunicipal,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle-activo', name: 'app_pregunta_municipal_toggle_activo', methods: ['POST'])]
    public function toggleActivo(PreguntaMunicipal $preguntaMunicipal, EntityManagerInterface $entityManager, Request $request): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$preguntaMunicipal->getId(), $request->getPayload()->getString('_token'))) {
            $preguntaMunicipal->setActivo(!$preguntaMunicipal->isActivo());
            $entityManager->flush();

            $estado = $preguntaMunicipal->isActivo() ? 'activada' : 'desactivada';
            $this->addFlash('success', "La pregunta municipal ha sido {$estado} correctamente.");
        }

        return $this->redirectToRoute('app_pregunta_municipal_index', [], Response::HTTP_SEE_OTHER);
    }
}


