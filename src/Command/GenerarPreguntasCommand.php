<?php

namespace App\Command;

use App\Entity\Pregunta;
use App\Repository\TemaRepository;
use App\Repository\LeyRepository;
use App\Repository\ArticuloRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generar-preguntas',
    description: 'Generar preguntas de ejemplo para los artículos existentes',
)]
class GenerarPreguntasCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TemaRepository $temaRepository,
        private LeyRepository $leyRepository,
        private ArticuloRepository $articuloRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $tema = $this->temaRepository->find(1);
        $ley = $this->leyRepository->find(1);

        if (!$tema || !$ley) {
            $io->error('No se encontró el tema o la ley necesarios.');
            return Command::FAILURE;
        }

        $articulos = $this->articuloRepository->findBy(['ley' => $ley]);

        if (empty($articulos)) {
            $io->error('No se encontraron artículos.');
            return Command::FAILURE;
        }

        $preguntasGeneradas = 0;

        foreach ($articulos as $articulo) {
            $numeroArticulo = $articulo->getNumero();

            // Preguntas según el artículo
            if (str_contains($numeroArticulo, '1')) {
                // Artículo 1 - Estado y valores
                $preguntas = $this->getPreguntasArticulo1($tema, $ley, $articulo);
            } elseif (str_contains($numeroArticulo, '2')) {
                // Artículo 2 - Unidad y autonomía
                $preguntas = $this->getPreguntasArticulo2($tema, $ley, $articulo);
            } elseif (str_contains($numeroArticulo, '3')) {
                // Artículo 3 - Lengua
                $preguntas = $this->getPreguntasArticulo3($tema, $ley, $articulo);
            } else {
                continue;
            }

            foreach ($preguntas as $preguntaData) {
                $pregunta = new Pregunta();
                $pregunta->setTexto($preguntaData['texto']);
                $pregunta->setOpcionA($preguntaData['opcionA']);
                $pregunta->setOpcionB($preguntaData['opcionB']);
                $pregunta->setOpcionC($preguntaData['opcionC']);
                $pregunta->setOpcionD($preguntaData['opcionD']);
                $pregunta->setRespuestaCorrecta($preguntaData['respuestaCorrecta']);
                $pregunta->setDificultad($preguntaData['dificultad']);
                $pregunta->setRetroalimentacion($preguntaData['retroalimentacion']);
                $pregunta->setTema($tema);
                $pregunta->setLey($ley);
                $pregunta->setArticulo($articulo);

                $this->entityManager->persist($pregunta);
                $preguntasGeneradas++;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Se generaron %d preguntas correctamente.', $preguntasGeneradas));

        return Command::SUCCESS;
    }

    private function getPreguntasArticulo1($tema, $ley, $articulo): array
    {
        return [
            [
                'texto' => 'Según el Artículo 1 de la Constitución, España se constituye en:',
                'opcionA' => 'Un Estado democrático de Derecho',
                'opcionB' => 'Una monarquía absoluta',
                'opcionC' => 'Una república federal',
                'opcionD' => 'Un Estado confederal',
                'respuestaCorrecta' => 'A',
                'dificultad' => 'facil',
                'retroalimentacion' => 'El Artículo 1 establece que España se constituye en un Estado social y democrático de Derecho. Es importante recordar que la forma política es monarquía parlamentaria, pero el Estado es democrático de Derecho.',
            ],
            [
                'texto' => '¿Cuáles son los valores superiores del ordenamiento jurídico según el Artículo 1?',
                'opcionA' => 'La libertad, la justicia y la igualdad',
                'opcionB' => 'La libertad, la justicia, la igualdad y el pluralismo político',
                'opcionC' => 'La soberanía nacional y la unidad del Estado',
                'opcionD' => 'La democracia y el Estado de Derecho',
                'respuestaCorrecta' => 'B',
                'dificultad' => 'moderada',
                'retroalimentacion' => 'El Artículo 1.1 establece que los valores superiores del ordenamiento jurídico son: la libertad, la justicia, la igualdad y el pluralismo político. Estos cuatro valores son fundamentales y deben estar presentes en toda interpretación del ordenamiento jurídico.',
            ],
            [
                'texto' => 'Según el Artículo 1.3, la forma política del Estado español es:',
                'opcionA' => 'República democrática',
                'opcionB' => 'Monarquía parlamentaria',
                'opcionC' => 'Monarquía constitucional',
                'opcionD' => 'Estado federal',
                'respuestaCorrecta' => 'B',
                'dificultad' => 'dificil',
                'retroalimentacion' => 'El Artículo 1.3 establece que "La forma política del Estado español es la Monarquía parlamentaria". Es importante distinguir entre la forma de Estado (democrático de Derecho) y la forma política (monarquía parlamentaria). La monarquía parlamentaria implica que el Rey reina pero no gobierna, siendo el Parlamento quien ejerce la soberanía.',
            ],
        ];
    }

    private function getPreguntasArticulo2($tema, $ley, $articulo): array
    {
        return [
            [
                'texto' => 'El Artículo 2 de la Constitución establece que la Constitución se fundamenta en:',
                'opcionA' => 'La unidad de la Nación española',
                'opcionB' => 'La indisoluble unidad de la Nación española',
                'opcionC' => 'La autonomía de las nacionalidades y regiones',
                'opcionD' => 'La soberanía popular',
                'respuestaCorrecta' => 'B',
                'dificultad' => 'facil',
                'retroalimentacion' => 'El Artículo 2 establece que "La Constitución se fundamenta en la indisoluble unidad de la Nación española". La palabra "indisoluble" es clave, ya que significa que esta unidad no puede romperse.',
            ],
            [
                'texto' => 'Según el Artículo 2, la Constitución reconoce y garantiza el derecho a la autonomía de:',
                'opcionA' => 'Las provincias',
                'opcionB' => 'Las nacionalidades y regiones',
                'opcionC' => 'Los municipios',
                'opcionD' => 'Las comarcas',
                'respuestaCorrecta' => 'B',
                'dificultad' => 'moderada',
                'retroalimentacion' => 'El Artículo 2 reconoce y garantiza el derecho a la autonomía de las nacionalidades y regiones que integran la Nación española. Este artículo establece el principio de autonomía dentro de la unidad, permitiendo que las diferentes regiones tengan sus propios gobiernos y competencias.',
            ],
            [
                'texto' => 'El Artículo 2 establece que la autonomía de las nacionalidades y regiones se ejercerá dentro del marco de:',
                'opcionA' => 'La soberanía nacional',
                'opcionB' => 'La solidaridad entre todas ellas',
                'opcionC' => 'La unidad de la Nación española y la solidaridad entre todas ellas',
                'opcionD' => 'El Estado de Derecho',
                'respuestaCorrecta' => 'C',
                'dificultad' => 'dificil',
                'retroalimentacion' => 'El Artículo 2 establece que la autonomía se ejercerá "dentro del marco de la unidad de la Nación española y la solidaridad entre todas ellas". Esto significa que la autonomía tiene límites: no puede romper la unidad nacional y debe ejercerse con solidaridad entre todas las regiones, evitando desigualdades.',
            ],
        ];
    }

    private function getPreguntasArticulo3($tema, $ley, $articulo): array
    {
        return [
            [
                'texto' => 'Según el Artículo 3.1, el castellano es:',
                'opcionA' => 'La lengua oficial del Estado',
                'opcionB' => 'La lengua cooficial en todo el territorio',
                'opcionC' => 'Una de las lenguas oficiales',
                'opcionD' => 'La lengua preferente',
                'respuestaCorrecta' => 'A',
                'dificultad' => 'facil',
                'retroalimentacion' => 'El Artículo 3.1 establece que "El castellano es la lengua española oficial del Estado". Todos los españoles tienen el deber de conocerla y el derecho a usarla. Es la única lengua oficial en todo el territorio nacional.',
            ],
            [
                'texto' => 'Según el Artículo 3.2, las demás lenguas españolas serán también oficiales:',
                'opcionA' => 'En todo el territorio nacional',
                'opcionB' => 'En las respectivas Comunidades Autónomas de acuerdo con sus Estatutos',
                'opcionC' => 'En las regiones que así lo decidan',
                'opcionD' => 'En las provincias que lo soliciten',
                'respuestaCorrecta' => 'B',
                'dificultad' => 'moderada',
                'retroalimentacion' => 'El Artículo 3.2 establece que "Las demás lenguas españolas serán también oficiales en las respectivas Comunidades Autónomas de acuerdo con sus Estatutos". Esto significa que el catalán, gallego, euskera y valenciano son cooficiales en sus respectivas comunidades, pero solo si así lo establecen sus Estatutos de Autonomía.',
            ],
            [
                'texto' => 'El Artículo 3.3 establece que la riqueza de las distintas modalidades lingüísticas de España es:',
                'opcionA' => 'Un derecho de las Comunidades Autónomas',
                'opcionB' => 'Un patrimonio cultural que será objeto de especial respeto y protección',
                'opcionC' => 'Una competencia exclusiva del Estado',
                'opcionD' => 'Un deber de todos los españoles',
                'respuestaCorrecta' => 'B',
                'dificultad' => 'dificil',
                'retroalimentacion' => 'El Artículo 3.3 establece que "La riqueza de las distintas modalidades lingüísticas de España es un patrimonio cultural que será objeto de especial respeto y protección". Esto significa que todas las lenguas y modalidades lingüísticas de España, aunque no sean oficiales, forman parte del patrimonio cultural y deben ser protegidas y respetadas.',
            ],
        ];
    }
}

