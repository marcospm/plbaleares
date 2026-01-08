<?php

namespace App\Controller;

use App\Repository\ArticuloRepository;
use App\Repository\LeyRepository;
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

    #[Route('/juegos/adivina-numero-articulo', name: 'app_juego_adivina_numero_articulo')]
    public function adivinaNumeroArticulo(): Response
    {
        return $this->render('juego/adivina_numero_articulo.html.twig');
    }

    #[Route('/juegos/completa-fecha-ley', name: 'app_juego_completa_fecha_ley')]
    public function completaFechaLey(): Response
    {
        return $this->render('juego/completa_fecha_ley.html.twig');
    }

    #[Route('/juegos/completa-texto-legal', name: 'app_juego_completa_texto_legal')]
    public function completaTextoLegal(): Response
    {
        return $this->render('juego/completa_texto_legal.html.twig');
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

    #[Route('/api/juegos/preguntas-lote', name: 'app_juego_api_preguntas_lote')]
    public function getPreguntasLote(PreguntaRepository $preguntaRepository): JsonResponse
    {
        $preguntas = $preguntaRepository->findAleatoriasActivas(20);
        
        if (empty($preguntas)) {
            return new JsonResponse(['error' => 'No hay preguntas disponibles'], 404);
        }

        $resultado = [];
        foreach ($preguntas as $pregunta) {
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

            if (empty($respuestaCorrecta) || trim($respuestaCorrecta) === '') {
                continue; // Saltar preguntas sin respuesta válida
            }

            $resultado[] = [
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
            ];
        }

        if (empty($resultado)) {
            return new JsonResponse(['error' => 'No hay preguntas con respuestas válidas'], 404);
        }

        return new JsonResponse($resultado);
    }

    #[Route('/api/juegos/articulos-lote', name: 'app_juego_api_articulos_lote')]
    public function getArticulosLote(ArticuloRepository $articuloRepository): JsonResponse
    {
        $articulos = $articuloRepository->findAleatoriosConNombre(20);
        
        if (empty($articulos)) {
            return new JsonResponse(['error' => 'No hay artículos disponibles'], 404);
        }

        $resultado = [];
        foreach ($articulos as $articulo) {
            $resultado[] = [
                'id' => $articulo->getId(),
                'numero' => $articulo->getNumero(),
                'sufijo' => $articulo->getSufijo(),
                'numeroCompleto' => $articulo->getNumeroCompleto(),
                'nombre' => $articulo->getNombre(),
                'ley' => [
                    'id' => $articulo->getLey()->getId(),
                    'nombre' => $articulo->getLey()->getNombre(),
                ],
            ];
        }

        return new JsonResponse($resultado);
    }

    #[Route('/api/juegos/leyes-con-fecha', name: 'app_juego_api_leyes_con_fecha')]
    public function getLeyesConFecha(LeyRepository $leyRepository): JsonResponse
    {
        $leyes = $leyRepository->findLeyesConFormatoFecha();
        
        if (empty($leyes)) {
            return new JsonResponse(['error' => 'No hay leyes con formato de fecha disponible'], 404);
        }

        $resultado = [];
        foreach ($leyes as $ley) {
            $nombre = $ley->getNombre() ?? '';
            
            // Extraer los componentes: número/número, de día de mes
            // Patrón más flexible: puede empezar con "Ley" o no, y permite espacios variables
            // Ejemplos: "20/2006, de 15 de diciembre", "Ley 20/2006, de 15 de diciembre"
            if (preg_match('/(\d+)\/(\d+),\s*de\s+(\d+)\s+de\s+(\w+)/i', $nombre, $matches)) {
                $resultado[] = [
                    'id' => $ley->getId(),
                    'nombre' => $nombre,
                    'numero1' => $matches[1],      // Primer número
                    'numero2' => $matches[2],       // Año
                    'dia' => $matches[3],          // Día
                    'mes' => $matches[4],          // Mes
                ];
            }
        }

        if (empty($resultado)) {
            return new JsonResponse(['error' => 'No se pudieron procesar las leyes'], 404);
        }

        // Mezclar aleatoriamente
        shuffle($resultado);

        return new JsonResponse($resultado);
    }

    #[Route('/api/juegos/articulos-texto-legal-lote', name: 'app_juego_api_articulos_texto_legal_lote')]
    public function getArticulosTextoLegalLote(ArticuloRepository $articuloRepository): JsonResponse
    {
        $articulos = $articuloRepository->findAleatoriosConTextoLegal(20);
        
        if (empty($articulos)) {
            return new JsonResponse(['error' => 'No hay artículos con texto legal disponibles'], 404);
        }

        $resultado = [];
        foreach ($articulos as $articulo) {
            $resultado[] = [
                'id' => $articulo->getId(),
                'numero' => $articulo->getNumero(),
                'sufijo' => $articulo->getSufijo(),
                'numeroCompleto' => $articulo->getNumeroCompleto(),
                'nombre' => $articulo->getNombre(),
                'textoLegal' => $articulo->getTextoLegal(),
                'ley' => [
                    'id' => $articulo->getLey()->getId(),
                    'nombre' => $articulo->getLey()->getNombre(),
                ],
            ];
        }

        return new JsonResponse($resultado);
    }
}

