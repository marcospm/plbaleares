<?php

namespace App\Controller;

use App\Repository\RecursoRepository;
use App\Repository\ExamenPDFRepository;
use App\Repository\TemaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/recursos')]
class RecursoPublicoController extends AbstractController
{
    #[Route('/', name: 'app_recurso_publico_index', methods: ['GET'])]
    public function index(ExamenPDFRepository $examenPDFRepository, TemaRepository $temaRepository): Response
    {
        $examenes = $examenPDFRepository->findAll();
        // Ordenar por fecha de subida descendente
        usort($examenes, function($a, $b) {
            return $b->getFechaSubida() <=> $a->getFechaSubida();
        });
        
        // Obtener todos los temas activos, ordenados por ID
        $temas = $temaRepository->findBy(['activo' => true], ['id' => 'ASC']);
        
        return $this->render('recurso/publico_index.html.twig', [
            'examenes' => $examenes,
            'temas' => $temas,
        ]);
    }
}

