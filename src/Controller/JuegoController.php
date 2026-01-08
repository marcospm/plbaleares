<?php

namespace App\Controller;

use App\Repository\PreguntaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class JuegoController extends AbstractController
{
    #[Route('/juegos', name: 'app_juego_index')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_juego_preguntas_sin_opciones');
    }

    #[Route('/juegos/preguntas-sin-opciones', name: 'app_juego_preguntas_sin_opciones')]
    public function preguntasSinOpciones(): Response
    {
        return $this->render('juego/preguntas_sin_opciones.html.twig');
    }

    #[Route('/api/juegos/pregunta-aleatoria', name: 'app_juego_api_pregunta_aleatoria')]
    public function getPreguntaAleatoria(PreguntaRepository $preguntaRepository): JsonResponse
    {
        $pregunta = $preguntaRepository->findAleatoriaActiva();
        
        if (!$pregunta) {
            return new JsonResponse(['error' => 'No hay preguntas disponibles'], 404);
        }

        // Verificar que la pregunta tenga texto
        if (!$pregunta->getTexto() || trim($pregunta->getTexto()) === '') {
            // Si esta pregunta no tiene texto, intentar obtener otra
            $pregunta = $preguntaRepository->findAleatoriaActiva();
            if (!$pregunta || !$pregunta->getTexto() || trim($pregunta->getTexto()) === '') {
                return new JsonResponse(['error' => 'No hay preguntas con texto disponible'], 404);
            }
        }

        // Obtener la respuesta correcta completa
        $respuestaCorrecta = '';
        switch ($pregunta->getRespuestaCorrecta()) {
            case 'A':
                $respuestaCorrecta = $pregunta->getOpcionA() ?? '';
                break;
            case 'B':
                $respuestaCorrecta = $pregunta->getOpcionB() ?? '';
                break;
            case 'C':
                $respuestaCorrecta = $pregunta->getOpcionC() ?? '';
                break;
            case 'D':
                $respuestaCorrecta = $pregunta->getOpcionD() ?? '';
                break;
        }

        // Verificar que la respuesta correcta no esté vacía
        if (empty($respuestaCorrecta) || trim($respuestaCorrecta) === '') {
            return new JsonResponse(['error' => 'La pregunta no tiene respuesta correcta válida'], 404);
        }

        return new JsonResponse([
            'id' => $pregunta->getId(),
            'texto' => $pregunta->getTexto() ?? '',
            'opcionA' => $pregunta->getOpcionA() ?? '',
            'opcionB' => $pregunta->getOpcionB() ?? '',
            'opcionC' => $pregunta->getOpcionC() ?? '',
            'opcionD' => $pregunta->getOpcionD() ?? '',
            'respuestaCorrecta' => $respuestaCorrecta,
            'letraCorrecta' => $pregunta->getRespuestaCorrecta(),
            'ley' => [
                'id' => $pregunta->getLey()->getId(),
                'nombre' => $pregunta->getLey()->getNombre(),
            ],
        ]);
    }
}

