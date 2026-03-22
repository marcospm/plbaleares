<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\InformeMensualPdfService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/informe-mensual-alumno')]
#[IsGranted('ROLE_USER')]
class InformeMensualAlumnoController extends AbstractController
{
    public function __construct(
        private InformeMensualPdfService $informeMensualPdfService
    ) {
    }

    #[Route('/', name: 'app_informe_mensual_alumno_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyIfNotAlumno();

        $anioActual = (int) date('Y');
        $mesActual = (int) date('m');

        return $this->render('informe_mensual/alumno_index.html.twig', [
            'anioActual' => $anioActual,
            'mesActual' => $mesActual,
        ]);
    }

    #[Route('/generar', name: 'app_informe_mensual_alumno_generar', methods: ['GET'])]
    public function generar(Request $request): Response
    {
        $this->denyIfNotAlumno();

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $mes = $request->query->getInt('mes', (int) date('m'));
        $anio = $request->query->getInt('anio', (int) date('Y'));

        if ($mes < 1 || $mes > 12) {
            $this->addFlash('error', 'Mes inválido.');

            return $this->redirectToRoute('app_informe_mensual_alumno_index');
        }

        if ($anio < 2000 || $anio > 2100) {
            $this->addFlash('error', 'Año inválido.');

            return $this->redirectToRoute('app_informe_mensual_alumno_index');
        }

        return $this->informeMensualPdfService->generatePdf($user, $mes, $anio);
    }

    private function denyIfNotAlumno(): void
    {
        if ($this->isGranted('ROLE_PROFESOR')) {
            throw $this->createAccessDeniedException('Esta sección solo está disponible para alumnos.');
        }
    }
}
