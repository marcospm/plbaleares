<?php

namespace App\Command;

use App\Repository\ArticuloRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:verificar-videos',
    description: 'Verificar qué artículos tienen videos y mostrar sus URLs',
)]
class VerificarVideosCommand extends Command
{
    public function __construct(
        private ArticuloRepository $articuloRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Verificando Videos en Artículos');

        $articulos = $this->articuloRepository->findAll();
        $conVideo = 0;
        $sinVideo = 0;

        foreach ($articulos as $articulo) {
            if ($articulo->getVideo()) {
                $conVideo++;
                $io->writeln(sprintf('✓ Artículo %s (ID: %d) - Ley: %s', 
                    $articulo->getNumero(), 
                    $articulo->getId(),
                    $articulo->getLey() ? $articulo->getLey()->getNombre() : 'Sin ley'
                ));
                $io->writeln(sprintf('  URL: %s', $articulo->getVideo()));
                
                // Extraer ID del video
                $videoUrl = $articulo->getVideo();
                $videoId = '';
                if (strpos($videoUrl, 'youtube.com/watch?v=') !== false) {
                    $videoId = explode('v=', $videoUrl)[1];
                    $videoId = explode('&', $videoId)[0];
                    $videoId = explode('#', $videoId)[0];
                } elseif (strpos($videoUrl, 'youtu.be/') !== false) {
                    $videoId = explode('youtu.be/', $videoUrl)[1];
                    $videoId = explode('?', $videoId)[0];
                    $videoId = explode('&', $videoId)[0];
                    $videoId = explode('#', $videoId)[0];
                } elseif (strpos($videoUrl, 'youtube.com/embed/') !== false) {
                    $videoId = explode('embed/', $videoUrl)[1];
                    $videoId = explode('?', $videoId)[0];
                    $videoId = explode('&', $videoId)[0];
                    $videoId = explode('#', $videoId)[0];
                } else {
                    $videoId = $videoUrl;
                }
                
                $io->writeln(sprintf('  ID extraído: %s', $videoId));
                $io->writeln(sprintf('  URL embed: https://www.youtube.com/embed/%s', $videoId));
                $io->writeln('');
            } else {
                $sinVideo++;
            }
        }

        $io->success(sprintf('Total: %d artículos con video, %d sin video', $conVideo, $sinVideo));
        
        return Command::SUCCESS;
    }
}

