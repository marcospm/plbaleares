<?php

namespace App\Command;

use App\Repository\TemaMunicipalRepository;
use App\Repository\TemaRepository;
use App\Service\PdfTitleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:fix-pdf-titles',
    description: 'Corrige el metadato /Title de los PDFs de temas para que coincida con el nombre del tema',
)]
class FixPdfTitlesCommand extends Command
{
    public function __construct(
        private KernelInterface $kernel,
        private TemaRepository $temaRepository,
        private TemaMunicipalRepository $temaMunicipalRepository,
        private PdfTitleService $pdfTitleService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Corrigiendo títulos de PDFs de temas');

        $projectDir = $this->kernel->getProjectDir();
        $ok = 0;
        $omitidos = 0;
        $errores = 0;

        foreach ($this->temaRepository->findAll() as $tema) {
            $resultado = $this->corregir($projectDir, $tema->getRutaPdf(), $tema->getNombre(), $io, 'Tema #' . $tema->getId());
            $ok += $resultado === true ? 1 : 0;
            $omitidos += $resultado === null ? 1 : 0;
            $errores += $resultado === false ? 1 : 0;
        }

        foreach ($this->temaMunicipalRepository->findAll() as $tema) {
            $resultado = $this->corregir($projectDir, $tema->getRutaPdf(), $tema->getNombre(), $io, 'Municipal #' . $tema->getId());
            $ok += $resultado === true ? 1 : 0;
            $omitidos += $resultado === null ? 1 : 0;
            $errores += $resultado === false ? 1 : 0;
        }

        $io->success(sprintf('Listo. Corregidos: %d | Omitidos: %d | Errores: %d', $ok, $omitidos, $errores));

        return $errores > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return bool|null true=ok, null=omitido, false=error
     */
    private function corregir(string $projectDir, ?string $rutaPdf, ?string $titulo, SymfonyStyle $io, string $etiqueta): ?bool
    {
        if (!$rutaPdf || !$titulo) {
            return null;
        }

        $rutaAbsoluta = $projectDir . '/public' . $rutaPdf;
        if (!is_file($rutaAbsoluta)) {
            $io->writeln(sprintf('<comment>Omitido %s:</comment> no existe %s', $etiqueta, $rutaPdf));
            return null;
        }

        try {
            $this->pdfTitleService->applyTitleToFile($rutaAbsoluta, $titulo);
            $io->writeln(sprintf('<info>✓</info> %s → %s', $etiqueta, $titulo));
            return true;
        } catch (\Throwable $e) {
            $io->writeln(sprintf('<error>✗ %s:</error> %s', $etiqueta, $e->getMessage()));
            return false;
        }
    }
}
