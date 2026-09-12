<?php

namespace App\Controller;

use App\Entity\Tema;
use App\Entity\TemaMunicipal;
use App\Service\PdfTitleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Sirve PDFs de temas con el metadato /Title alineado al nombre del tema
 * (p. ej. "Tema 18 - ...") para que la pestaña del navegador no muestre un número erróneo.
 */
#[Route('/tema-pdf')]
class TemaPdfController extends AbstractController
{
    public function __construct(
        private KernelInterface $kernel,
        private PdfTitleService $pdfTitleService,
        private SluggerInterface $slugger,
    ) {
    }

    #[Route('/{id}', name: 'app_tema_pdf_ver', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function verTema(Tema $tema): Response
    {
        return $this->servirPdf(
            $tema->getRutaPdf(),
            $tema->getNombre() ?? 'Tema',
            inline: true
        );
    }

    #[Route('/{id}/descargar', name: 'app_tema_pdf_descargar', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function descargarTema(Tema $tema): Response
    {
        return $this->servirPdf(
            $tema->getRutaPdf(),
            $tema->getNombre() ?? 'Tema',
            inline: false
        );
    }

    #[Route('/municipal/{id}', name: 'app_tema_municipal_pdf_ver', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function verTemaMunicipal(TemaMunicipal $temaMunicipal): Response
    {
        return $this->servirPdf(
            $temaMunicipal->getRutaPdf(),
            $temaMunicipal->getNombre() ?? 'Tema municipal',
            inline: true
        );
    }

    #[Route('/municipal/{id}/descargar', name: 'app_tema_municipal_pdf_descargar', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function descargarTemaMunicipal(TemaMunicipal $temaMunicipal): Response
    {
        return $this->servirPdf(
            $temaMunicipal->getRutaPdf(),
            $temaMunicipal->getNombre() ?? 'Tema municipal',
            inline: false
        );
    }

    private function servirPdf(?string $rutaPdf, string $titulo, bool $inline): Response
    {
        if (!$rutaPdf) {
            throw $this->createNotFoundException('Este tema no tiene PDF asociado.');
        }

        $rutaAbsoluta = $this->kernel->getProjectDir() . '/public' . $rutaPdf;
        if (!is_file($rutaAbsoluta) || !is_readable($rutaAbsoluta)) {
            throw $this->createNotFoundException('No se encontró el archivo PDF.');
        }

        $contenido = file_get_contents($rutaAbsoluta);
        if ($contenido === false) {
            throw $this->createNotFoundException('No se pudo leer el archivo PDF.');
        }

        $contenido = $this->pdfTitleService->withTitle($contenido, $titulo);
        $nombreArchivo = $this->slugger->slug($titulo)->toString() . '.pdf';
        $disposicion = $inline ? 'inline' : 'attachment';

        return new Response($contenido, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposicion, $nombreArchivo),
            'Content-Length' => (string) strlen($contenido),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
