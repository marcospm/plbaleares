<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:activar-usuarios',
    description: 'Activar todos los usuarios existentes (especialmente admins)',
)]
class ActivarUsuariosCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Activando Usuarios Existentes');

        $usuarios = $this->userRepository->findAll();
        $activados = 0;
        $adminsActivados = 0;

        foreach ($usuarios as $usuario) {
            if (!$usuario->isActivo()) {
                $usuario->setActivo(true);
                $this->entityManager->persist($usuario);
                $activados++;
                
                if (in_array('ROLE_PROFESOR', $usuario->getRoles())) {
                    $adminsActivados++;
                    $io->writeln(sprintf('✓ Admin activado: %s', $usuario->getUsername()));
                } else {
                    $io->writeln(sprintf('✓ Usuario activado: %s', $usuario->getUsername()));
                }
            } else {
                if (in_array('ROLE_PROFESOR', $usuario->getRoles())) {
                    $io->writeln(sprintf('→ Admin ya activo: %s', $usuario->getUsername()));
                }
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Se activaron %d usuarios (%d admins).', $activados, $adminsActivados));
        
        return Command::SUCCESS;
    }
}

