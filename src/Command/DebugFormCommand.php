<?php

namespace App\Command;

use App\Form\ExamenIniciarType;
use App\Repository\TemaRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Form\FormFactoryInterface;

#[AsCommand(
    name: 'app:debug-form',
    description: 'Debug del formulario de examen',
)]
class DebugFormCommand extends Command
{
    public function __construct(
        private FormFactoryInterface $formFactory,
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

        // Simular datos del formulario
        $data = [
            'temas' => [$tema],
            'dificultad' => 'facil',
            'numeroPreguntas' => 20,
        ];

        $form = $this->formFactory->create(ExamenIniciarType::class);
        $form->submit($data);

        $io->writeln('Formulario válido: ' . ($form->isValid() ? 'Sí' : 'No'));
        
        if (!$form->isValid()) {
            $io->writeln('Errores:');
            foreach ($form->getErrors(true) as $error) {
                $io->writeln('  - ' . $error->getMessage());
            }
        }

        $formData = $form->getData();
        $io->writeln('Datos del formulario:');
        $io->writeln('  Temas: ' . (isset($formData['temas']) ? gettype($formData['temas']) : 'no definido'));
        if (isset($formData['temas'])) {
            if ($formData['temas'] instanceof \Doctrine\Common\Collections\Collection) {
                $io->writeln('  Es una Collection con ' . $formData['temas']->count() . ' elementos');
            } elseif (is_array($formData['temas'])) {
                $io->writeln('  Es un array con ' . count($formData['temas']) . ' elementos');
            }
        }

        return Command::SUCCESS;
    }
}

