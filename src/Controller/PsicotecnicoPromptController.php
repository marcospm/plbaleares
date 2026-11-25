<?php

namespace App\Controller;

use App\Service\PromptExamenPsicotecnico;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class PsicotecnicoPromptController extends AbstractController
{
    #[Route('/psicotecnico/prompt-examen', name: 'app_psicotecnico_prompt_examen', methods: ['GET'])]
    public function index(Request $request, PromptExamenPsicotecnico $prompts): Response
    {
        $nivel = $request->query->getString('nivel', PromptExamenPsicotecnico::NIVEL_NORMAL);
        if (!$prompts->esNivel($nivel)) {
            $nivel = PromptExamenPsicotecnico::NIVEL_NORMAL;
        }

        $seleccion = $request->query->get('formulario') === '1'
            ? $prompts->normalizar($request->query->all('areas'))
            : $prompts->normalizar(null);
        $prompt = null;
        if ($seleccion === []) {
            $this->addFlash('error', 'Selecciona al menos un área.');
        } else {
            $prompt = $prompts->generar($nivel, $seleccion);
        }

        return $this->render('psicotecnico/prompt_examen.html.twig', [
            'nivel' => $nivel,
            'niveles' => $prompts->niveles(),
            'areas' => $prompts->areas(),
            'seleccion' => $seleccion,
            'prompt' => $prompt,
            'total' => count($seleccion) * 14,
            'minutos' => count($seleccion) * 10,
        ]);
    }
}
