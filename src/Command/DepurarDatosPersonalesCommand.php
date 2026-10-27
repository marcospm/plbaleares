<?php

namespace App\Command;

use App\Service\SupresionDatosService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:depurar-datos-personales',
    description: 'Borra resultados ya anonimizados y mensajes de contacto que superan el plazo de conservación',
)]
class DepurarDatosPersonalesCommand extends Command
{
    public function __construct(
        private SupresionDatosService $supresionDatosService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $resultado = $this->supresionDatosService->depurarConservacion();

        $io->success(sprintf(
            'Depuración hecha. Exámenes: %d. Partidas: %d. Tareas: %d. Mensajes de contacto: %d.',
            $resultado['examenes'],
            $resultado['partidas'],
            $resultado['tareas'],
            $resultado['contactos']
        ));

        return Command::SUCCESS;
    }
}
