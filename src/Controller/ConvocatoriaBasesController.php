<?php

namespace App\Controller;

use App\Entity\Convocatoria;
use App\Entity\ConvocatoriaBase;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ConvocatoriaBasesController extends AbstractController
{
    public function __construct(
        private KernelInterface $kernel,
    ) {
    }

    #[Route('/convocatoria/{id}/bases/{base}', name: 'app_convocatoria_bases', methods: ['GET'], requirements: ['id' => '\d+', 'base' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function ver(Convocatoria $convocatoria, ConvocatoriaBase $base): BinaryFileResponse
    {
        $this->comprobarAcceso($convocatoria);

        if ($base->getConvocatoria()?->getId() !== $convocatoria->getId()) {
            throw $this->createNotFoundException('No se encontró el documento.');
        }

        $ruta = $base->getRutaArchivo();
        if (!$ruta || !str_starts_with($ruta, '/pdfs/bases/') || str_contains($ruta, '..')) {
            throw $this->createNotFoundException('No se encontró el documento.');
        }

        $rutaAbsoluta = $this->kernel->getProjectDir() . '/public' . $ruta;
        if (!is_file($rutaAbsoluta) || !is_readable($rutaAbsoluta)) {
            throw $this->createNotFoundException('No se encontró el documento.');
        }

        $nombre = basename(str_replace('\\', '/', (string) $base->getNombreOriginal()));
        if ($nombre === '' || $nombre === '.' || $nombre === '..') {
            $nombre = 'bases.pdf';
        }
        if (!str_ends_with(strtolower($nombre), '.pdf')) {
            $nombre .= '.pdf';
        }

        $response = new BinaryFileResponse($rutaAbsoluta);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $nombre);

        return $response;
    }

    private function comprobarAcceso(Convocatoria $convocatoria): void
    {
        if ($this->isGranted('ROLE_PROFESOR')) {
            return;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        foreach ($convocatoria->getUsuarios() as $usuario) {
            if ($usuario->getId() === $user->getId()) {
                return;
            }
        }

        throw $this->createAccessDeniedException('No tienes acceso a los documentos de esta convocatoria.');
    }
}
