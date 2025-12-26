<?php

namespace App\Controller;

use App\Entity\Pregunta;
use App\Repository\MensajePreguntaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pregunta-publica')]
class PreguntaPublicoController extends AbstractController
{
    #[Route('/{id}', name: 'app_pregunta_publica_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(
        Pregunta $pregunta,
        MensajePreguntaRepository $mensajePreguntaRepository
    ): Response {
        // Verificar que el usuario esté autenticado
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Obtener mensajes de la pregunta
        $mensajes = $mensajePreguntaRepository->findMensajesPrincipales($pregunta);
        $totalMensajes = $mensajePreguntaRepository->countMensajesPrincipales($pregunta);

        return $this->render('pregunta/publico_show.html.twig', [
            'pregunta' => $pregunta,
            'mensajes' => $mensajes,
            'totalMensajes' => $totalMensajes,
        ]);
    }
}




