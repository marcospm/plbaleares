<?php

namespace App\Command;

use App\Repository\ExamenRepository;
use App\Repository\ExamenSemanalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:actualizar-temas-municipales-examen',
    description: 'Actualiza los temas municipales de exámenes que tienen examen_semanal_id pero no tienen temas municipales guardados',
)]
class ActualizarTemasMunicipalesExamenCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ExamenRepository $examenRepository,
        private ExamenSemanalRepository $examenSemanalRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Actualizando temas municipales de exámenes');

        // Obtener todos los exámenes que tienen examen_semanal_id y municipio_id pero no tienen temas municipales
        $examenes = $this->examenRepository->createQueryBuilder('e')
            ->leftJoin('e.temasMunicipales', 'tm')
            ->leftJoin('e.examenSemanal', 'es')
            ->leftJoin('es.temasMunicipales', 'estm')
            ->where('e.examenSemanal IS NOT NULL')
            ->andWhere('e.municipio IS NOT NULL')
            ->getQuery()
            ->getResult();

        $actualizados = 0;
        $sinCambios = 0;

        foreach ($examenes as $examen) {
            $examenSemanal = $examen->getExamenSemanal();
            
            if (!$examenSemanal || !$examenSemanal->getMunicipio()) {
                continue;
            }

            // Cargar los temas municipales del examen semanal
            $temasMunicipalesSemanal = $examenSemanal->getTemasMunicipales();
            
            if ($temasMunicipalesSemanal->isEmpty()) {
                $sinCambios++;
                continue;
            }

            // Verificar si el examen ya tiene todos los temas municipales
            $temasExamen = $examen->getTemasMunicipales();
            $todosTemasPresentes = true;
            
            foreach ($temasMunicipalesSemanal as $temaSemanal) {
                if (!$temasExamen->contains($temaSemanal)) {
                    $todosTemasPresentes = false;
                    $examen->addTemasMunicipale($temaSemanal);
                }
            }

            if (!$todosTemasPresentes) {
                $this->entityManager->flush();
                $actualizados++;
                $io->writeln(sprintf(
                    '  ✓ Examen ID %d actualizado con %d tema(s) municipal(es) del examen semanal ID %d',
                    $examen->getId(),
                    $temasMunicipalesSemanal->count(),
                    $examenSemanal->getId()
                ));
            } else {
                $sinCambios++;
            }
        }

        $io->success(sprintf(
            'Proceso completado. %d examen(es) actualizado(s), %d sin cambios necesarios.',
            $actualizados,
            $sinCambios
        ));

        return Command::SUCCESS;
    }
}

