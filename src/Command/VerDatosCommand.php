<?php

namespace App\Command;

use App\Repository\TemaRepository;
use App\Repository\LeyRepository;
use App\Repository\ArticuloRepository;
use App\Repository\PreguntaRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ver-datos',
    description: 'Ver datos existentes en la base de datos',
)]
class VerDatosCommand extends Command
{
    public function __construct(
        private TemaRepository $temaRepository,
        private LeyRepository $leyRepository,
        private ArticuloRepository $articuloRepository,
        private PreguntaRepository $preguntaRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Datos en la Base de Datos');

        // Temas
        $temas = $this->temaRepository->findAll();
        $io->section('Temas (' . count($temas) . ')');
        if (count($temas) > 0) {
            foreach ($temas as $tema) {
                $io->writeln(sprintf('  - ID: %d | Nombre: %s', $tema->getId(), $tema->getNombre()));
            }
        } else {
            $io->writeln('  No hay temas registrados');
        }

        // Leyes
        $leyes = $this->leyRepository->findAll();
        $io->section('Leyes (' . count($leyes) . ')');
        if (count($leyes) > 0) {
            foreach ($leyes as $ley) {
                $io->writeln(sprintf('  - ID: %d | Nombre: %s', $ley->getId(), $ley->getNombre()));
            }
        } else {
            $io->writeln('  No hay leyes registradas');
        }

        // Artículos
        $articulos = $this->articuloRepository->findAll();
        $io->section('Artículos (' . count($articulos) . ')');
        if (count($articulos) > 0) {
            foreach ($articulos as $articulo) {
                $io->writeln(sprintf('  - ID: %d | Art. %s | Ley: %s', 
                    $articulo->getId(), 
                    $articulo->getNumero(), 
                    $articulo->getLey()->getNombre()
                ));
            }
        } else {
            $io->writeln('  No hay artículos registrados');
        }

        // Preguntas
        $preguntas = $this->preguntaRepository->findAll();
        $io->section('Preguntas (' . count($preguntas) . ')');
        if (count($preguntas) > 0) {
            $porDificultad = ['facil' => 0, 'moderada' => 0, 'dificil' => 0];
            foreach ($preguntas as $pregunta) {
                $porDificultad[$pregunta->getDificultad()]++;
            }
            $io->writeln(sprintf('  - Fácil: %d', $porDificultad['facil']));
            $io->writeln(sprintf('  - Moderada: %d', $porDificultad['moderada']));
            $io->writeln(sprintf('  - Difícil: %d', $porDificultad['dificil']));
        } else {
            $io->writeln('  No hay preguntas registradas');
        }

        return Command::SUCCESS;
    }
}

