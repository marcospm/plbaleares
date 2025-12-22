<?php

namespace App\Command;

use App\Repository\PreguntaRepository;
use App\Repository\TemaRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-preguntas',
    description: 'Test de consulta de preguntas',
)]
class TestPreguntasCommand extends Command
{
    public function __construct(
        private PreguntaRepository $preguntaRepository,
        private TemaRepository $temaRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $tema = $this->temaRepository->find(1);
        if (!$tema) {
            $io->error('No se encontró el tema 1');
            return Command::FAILURE;
        }

        $io->writeln('Tema encontrado: ' . $tema->getNombre());
        $io->writeln('ID del tema: ' . $tema->getId());

        // Probar consulta con dificultad fácil
        $preguntas = $this->preguntaRepository->createQueryBuilder('p')
            ->where('p.dificultad = :dificultad')
            ->andWhere('p.tema IN (:temas)')
            ->setParameter('dificultad', 'facil')
            ->setParameter('temas', [$tema])
            ->getQuery()
            ->getResult();

        $io->writeln('Preguntas encontradas (fácil): ' . count($preguntas));

        // Probar con moderada
        $preguntas = $this->preguntaRepository->createQueryBuilder('p')
            ->where('p.dificultad = :dificultad')
            ->andWhere('p.tema IN (:temas)')
            ->setParameter('dificultad', 'moderada')
            ->setParameter('temas', [$tema])
            ->getQuery()
            ->getResult();

        $io->writeln('Preguntas encontradas (moderada): ' . count($preguntas));

        // Probar con difícil
        $preguntas = $this->preguntaRepository->createQueryBuilder('p')
            ->where('p.dificultad = :dificultad')
            ->andWhere('p.tema IN (:temas)')
            ->setParameter('dificultad', 'dificil')
            ->setParameter('temas', [$tema])
            ->getQuery()
            ->getResult();

        $io->writeln('Preguntas encontradas (difícil): ' . count($preguntas));

        return Command::SUCCESS;
    }
}

