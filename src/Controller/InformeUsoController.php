<?php

namespace App\Controller;

use App\Service\InformeUsoPlataformaService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

#[Route('/admin/informe-uso')]
#[IsGranted('ROLE_ADMIN')]
class InformeUsoController extends AbstractController
{
    public function __construct(
        private InformeUsoPlataformaService $informeUsoPlataformaService,
        private Environment $twig,
    ) {
    }

    #[Route('', name: 'app_informe_uso_index', methods: ['GET'])]
    public function index(): Response
    {
        $ahora = new \DateTimeImmutable();

        return $this->render('informe_uso/index.html.twig', [
            'mesActual' => (int) $ahora->format('n'),
            'anioActual' => (int) $ahora->format('Y'),
            'anioMinimo' => (int) $ahora->format('Y') - 5,
        ]);
    }

    #[Route('/pdf', name: 'app_informe_uso_pdf', methods: ['GET'])]
    public function pdf(Request $request): Response
    {
        $ahora = new \DateTimeImmutable();
        $mes = $request->query->getInt('mes', (int) $ahora->format('n'));
        $anio = $request->query->getInt('anio', (int) $ahora->format('Y'));

        if ($mes < 1 || $mes > 12 || $anio < 2000 || $anio > 2100) {
            $this->addFlash('error', 'El mes o el año no son válidos.');

            return $this->redirectToRoute('app_informe_uso_index');
        }

        $inicio = new \DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mes));
        if ($inicio > $ahora) {
            $this->addFlash('error', 'Ese mes todavía no ha empezado.');

            return $this->redirectToRoute('app_informe_uso_index');
        }

        $datos = $this->informeUsoPlataformaService->generar($mes, $anio);
        $html = $this->twig->render('informe_uso/pdf.html.twig', $datos);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $nombre = sprintf('Informe_uso_%04d_%02d.pdf', $anio, $mes);

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
        ]);
    }
}
