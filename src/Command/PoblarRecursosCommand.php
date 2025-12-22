<?php

namespace App\Command;

use App\Entity\Recurso;
use App\Repository\TemaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:poblar-recursos',
    description: 'Poblar la base de datos con los 30 primeros recursos (temas)',
)]
class PoblarRecursosCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TemaRepository $temaRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Poblando Recursos con los 30 Temas');

        $temas = $this->temaRepository->findAll();
        
        if (count($temas) < 30) {
            $io->warning(sprintf('Solo se encontraron %d temas. Se crearán recursos para los temas disponibles.', count($temas)));
        }

        $creados = 0;
        foreach ($temas as $index => $tema) {
            // Verificar si ya existe un recurso con este nombre
            $recursoExistente = $this->entityManager->getRepository(Recurso::class)
                ->findOneBy(['nombre' => $tema->getNombre()]);

            if (!$recursoExistente) {
                $recurso = new Recurso();
                $recurso->setNombre($tema->getNombre());
                // Usar la ruta PDF del tema como enlace, o un placeholder
                $recurso->setEnlace($tema->getRutaPdf() ?? '#');
                
                $this->entityManager->persist($recurso);
                $creados++;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Se crearon %d recursos correctamente.', $creados));
        
        return Command::SUCCESS;
    }
}

