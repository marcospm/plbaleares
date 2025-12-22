<?php

namespace App\Command;

use App\Entity\Tema;
use App\Entity\Ley;
use App\Entity\Articulo;
use App\Repository\TemaRepository;
use App\Repository\LeyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generar-temas-completos',
    description: 'Generar los 30 temas con sus leyes y artículos correspondientes',
)]
class GenerarTemasCompletosCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TemaRepository $temaRepository,
        private LeyRepository $leyRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Generando 30 Temas con Leyes y Artículos');

        // Array para almacenar las leyes creadas (evitar duplicados)
        $leyesCreadas = [];

        // Datos de los 30 temas
        $temasData = $this->getTemasData();

        foreach ($temasData as $index => $temaData) {
            $numeroTema = $index + 1;
            $io->writeln(sprintf('Procesando Tema %d: %s', $numeroTema, $temaData['nombre']));

            // Crear o obtener el tema
            $tema = $this->temaRepository->findOneBy(['nombre' => $temaData['nombre']]);
            if (!$tema) {
                $tema = new Tema();
                $tema->setNombre($temaData['nombre']);
            }
            $tema->setDescripcion($temaData['descripcion'] ?? null);
            $tema->setRutaPdf(sprintf('/pdfs/tema-%d.pdf', $numeroTema));
            $this->entityManager->persist($tema);

            // Procesar leyes del tema
            foreach ($temaData['leyes'] as $leyData) {
                $nombreLey = $leyData['nombre'];
                
                // Crear o obtener la ley (evitar duplicados)
                if (!isset($leyesCreadas[$nombreLey])) {
                    $ley = $this->leyRepository->findOneBy(['nombre' => $nombreLey]);
                    if (!$ley) {
                        $ley = new Ley();
                        $ley->setNombre($nombreLey);
                        $ley->setDescripcion($leyData['descripcion'] ?? null);
                        $this->entityManager->persist($ley);
                    }
                    $leyesCreadas[$nombreLey] = $ley;
                } else {
                    $ley = $leyesCreadas[$nombreLey];
                }

                // Relacionar tema con ley
                if (!$tema->getLeyes()->contains($ley)) {
                    $tema->addLey($ley);
                }

                // Crear artículos de la ley para este tema
                if (isset($leyData['articulos']) && is_array($leyData['articulos'])) {
                    // Asegurar que la ley esté persistida y tenga ID
                    $this->entityManager->flush();
                    
                    foreach ($leyData['articulos'] as $articuloData) {
                        $numeroArticulo = $articuloData['numero'];
                        
                        // Verificar si el artículo ya existe para esta ley
                        $articuloExistente = $this->entityManager->getRepository(Articulo::class)
                            ->createQueryBuilder('a')
                            ->where('a.ley = :ley')
                            ->andWhere('a.numero = :numero')
                            ->setParameter('ley', $ley)
                            ->setParameter('numero', $numeroArticulo)
                            ->getQuery()
                            ->getOneOrNullResult();

                        if (!$articuloExistente) {
                            $articulo = new Articulo();
                            $articulo->setNumero($numeroArticulo);
                            $articulo->setLey($ley);
                            $articulo->setExplicacion($articuloData['explicacion'] ?? null);
                            $this->entityManager->persist($articulo);
                        }
                    }
                }
            }

            $this->entityManager->flush();
        }

        $io->success(sprintf('Se generaron %d temas correctamente.', count($temasData)));
        
        // Mostrar resumen
        $totalLeyes = count($leyesCreadas);
        $totalArticulos = $this->entityManager->getRepository(Articulo::class)->count([]);
        
        $io->table(
            ['Concepto', 'Cantidad'],
            [
                ['Temas', count($temasData)],
                ['Leyes', $totalLeyes],
                ['Artículos', $totalArticulos],
            ]
        );

        return Command::SUCCESS;
    }

    private function getTemasData(): array
    {
        return [
            // Tema 1
            [
                'nombre' => 'Tema 1 - La Constitución española de 1978. La Constitución como norma suprema',
                'descripcion' => 'Características y estructura de la Constitución española. Principios constitucionales básicos.',
                'leyes' => [
                    [
                        'nombre' => 'Constitución Española de 1978',
                        'descripcion' => 'Constitución Española aprobada el 6 de diciembre de 1978',
                        'articulos' => [
                            ['numero' => '1', 'explicacion' => 'España se constituye en un Estado social y democrático de Derecho. Valores superiores: libertad, justicia, igualdad y pluralismo político. La forma política es la Monarquía parlamentaria.'],
                            ['numero' => '2', 'explicacion' => 'La Constitución se fundamenta en la indisoluble unidad de la Nación española y reconoce el derecho a la autonomía de las nacionalidades y regiones.'],
                            ['numero' => '3', 'explicacion' => 'El castellano es la lengua oficial del Estado. Las demás lenguas españolas serán también oficiales en las respectivas Comunidades Autónomas.'],
                            ['numero' => '9', 'explicacion' => 'Los ciudadanos y los poderes públicos están sujetos a la Constitución y al resto del ordenamiento jurídico.'],
                        ],
                    ],
                ],
            ],
            // Tema 2
            [
                'nombre' => 'Tema 2 - La Constitución española de 1978. Derechos y deberes fundamentales',
                'descripcion' => 'Derechos y deberes fundamentales recogidos en la Constitución.',
                'leyes' => [
                    [
                        'nombre' => 'Constitución Española de 1978',
                        'descripcion' => 'Constitución Española aprobada el 6 de diciembre de 1978',
                        'articulos' => [
                            ['numero' => '14', 'explicacion' => 'Los españoles son iguales ante la ley, sin que pueda prevalecer discriminación alguna por razón de nacimiento, raza, sexo, religión u opinión.'],
                            ['numero' => '15', 'explicacion' => 'Todos tienen derecho a la vida y a la integridad física y moral.'],
                            ['numero' => '17', 'explicacion' => 'Toda persona tiene derecho a la libertad y a la seguridad. Nadie puede ser privado de su libertad sino con la observancia de lo establecido en este artículo.'],
                            ['numero' => '18', 'explicacion' => 'Se garantiza el derecho al honor, a la intimidad personal y familiar y a la propia imagen.'],
                            ['numero' => '20', 'explicacion' => 'Se reconocen y protegen los derechos a expresar y difundir libremente los pensamientos, ideas y opiniones.'],
                            ['numero' => '24', 'explicacion' => 'Todas las personas tienen derecho a obtener la tutela efectiva de los jueces y tribunales en el ejercicio de sus derechos e intereses legítimos.'],
                        ],
                    ],
                ],
            ],
            // Tema 3
            [
                'nombre' => 'Tema 3 - La Constitución española de 1978. La Corona. Las Cortes Generales. El Gobierno y la Administración',
                'descripcion' => 'La Corona. Las Cortes Generales. El Gobierno y la Administración. Las relaciones entre las Cortes Generales y el Gobierno. El poder judicial. El Tribunal Constitucional.',
                'leyes' => [
                    [
                        'nombre' => 'Constitución Española de 1978',
                        'descripcion' => 'Constitución Española aprobada el 6 de diciembre de 1978',
                        'articulos' => [
                            ['numero' => '56', 'explicacion' => 'El Rey es el Jefe del Estado, símbolo de su unidad y permanencia.'],
                            ['numero' => '66', 'explicacion' => 'Las Cortes Generales representan al pueblo español y están formadas por el Congreso de los Diputados y el Senado.'],
                            ['numero' => '97', 'explicacion' => 'El Gobierno dirige la política interior y exterior, la Administración civil y militar y la defensa del Estado.'],
                            ['numero' => '117', 'explicacion' => 'La justicia emana del pueblo y se administra en nombre del Rey por Jueces y Magistrados integrantes del poder judicial.'],
                            ['numero' => '159', 'explicacion' => 'El Tribunal Constitucional se compone de 12 miembros nombrados por el Rey.'],
                        ],
                    ],
                ],
            ],
            // Tema 4
            [
                'nombre' => 'Tema 4 - La Constitución española de 1978. La organización territorial del Estado',
                'descripcion' => 'La organización territorial del Estado español.',
                'leyes' => [
                    [
                        'nombre' => 'Constitución Española de 1978',
                        'descripcion' => 'Constitución Española aprobada el 6 de diciembre de 1978',
                        'articulos' => [
                            ['numero' => '137', 'explicacion' => 'El Estado se organiza territorialmente en municipios, en provincias y en las Comunidades Autónomas que se constituyan.'],
                            ['numero' => '140', 'explicacion' => 'La Constitución garantiza la autonomía de los municipios.'],
                            ['numero' => '141', 'explicacion' => 'La provincia es una entidad local con personalidad jurídica propia.'],
                            ['numero' => '143', 'explicacion' => 'En el ejercicio del derecho a la autonomía reconocido en el artículo 2, las provincias limítrofes con características históricas, culturales y económicas comunes podrán acceder a su autogobierno.'],
                        ],
                    ],
                ],
            ],
            // Tema 5
            [
                'nombre' => 'Tema 5 - El Estatuto de autonomía de las Illes Balears',
                'descripcion' => 'Disposiciones generales. Las competencias de la Comunidad Autónoma de las Illes Balears. Instituciones de la Comunidad Autónoma de las Illes Balears: el Parlamento, el presidente, el Gobierno de las Illes Balears, los consejos insulares, los municipios y otras entidades locales. El poder judicial en las Illes Balears: el Tribunal Superior de Justicia de las Illes Balears y sus competencias, el presidente del Tribunal Superior de Justicia de las Illes Balears. Financiación e hisenda: principios generales.',
                'leyes' => [
                    [
                        'nombre' => 'Estatuto de Autonomía de las Illes Balears',
                        'descripcion' => 'Estatuto de Autonomía de las Illes Balears',
                        'articulos' => [
                            ['numero' => '1', 'explicacion' => 'Las Illes Balears, como Comunidad Autónoma, ejercen las competencias que le atribuyen la Constitución y el presente Estatuto.'],
                            ['numero' => '30', 'explicacion' => 'El Parlamento de las Illes Balears es el órgano legislativo y de control del Gobierno de la Comunidad Autónoma.'],
                            ['numero' => '50', 'explicacion' => 'El presidente de las Illes Balears dirige y coordina la acción del Gobierno.'],
                            ['numero' => '70', 'explicacion' => 'Los consejos insulares son órganos de gobierno, administración y representación de cada una de las islas.'],
                        ],
                    ],
                ],
            ],
            // Tema 6
            [
                'nombre' => 'Tema 6 - La Ley 20/2006, de 15 de diciembre, municipal y de régimen local de las Illes Balears',
                'descripcion' => 'Los municipios: los elementos del municipi. Identificación, la población municipal, la organización municipal y las competencias. Disposiciones comunes a las entidades locales: reglamentos, ordenanzas y bandos.',
                'leyes' => [
                    [
                        'nombre' => 'Ley 20/2006, de 15 de diciembre, municipal y de régimen local de las Illes Balears',
                        'descripcion' => 'Ley municipal y de régimen local de las Illes Balears',
                        'articulos' => [
                            ['numero' => '1', 'explicacion' => 'El municipio es la entidad local básica de la organización territorial del Estado.'],
                            ['numero' => '11', 'explicacion' => 'El municipio tiene personalidad jurídica plena y está integrado por el territorio, la población y la organización.'],
                            ['numero' => '20', 'explicacion' => 'El municipio ejercerá en régimen de competencia propia las materias que le atribuyan las leyes.'],
                            ['numero' => '46', 'explicacion' => 'Los reglamentos orgánicos, las ordenanzas y los bandos constituyen el conjunto de disposiciones de carácter general que dictan las entidades locales.'],
                        ],
                    ],
                ],
            ],
            // Tema 7
            [
                'nombre' => 'Tema 7 - El Real decreto legislativo 6/2015, de 30 de octubre, Ley sobre tráfico, circulación de vehículos a motor y seguridad vial',
                'descripcion' => 'Disposiciones generales. Ejercicio y coordinación de las competencias sobre tráfico, circulación de vehículos a motor y seguridad vial: competencias de los municipios. Anexo I: conceptos básicos.',
                'leyes' => [
                    [
                        'nombre' => 'Real Decreto Legislativo 6/2015, de 30 de octubre, Ley sobre tráfico, circulación de vehículos a motor y seguridad vial',
                        'descripcion' => 'Ley de Tráfico, Circulación de Vehículos a Motor y Seguridad Vial',
                        'articulos' => [
                            ['numero' => '1', 'explicacion' => 'Esta ley tiene por objeto regular el tráfico, la circulación de vehículos a motor y la seguridad vial.'],
                            ['numero' => '7', 'explicacion' => 'Los municipios ejercerán las competencias en materia de tráfico, circulación de vehículos a motor y seguridad vial en el ámbito de sus respectivos términos municipales.'],
                        ],
                    ],
                ],
            ],
            // Tema 8
            [
                'nombre' => 'Tema 8 - El Real decreto 1428/2003, de 21 de noviembre, Reglamento General de Circulación - Normas generales',
                'descripcion' => 'Normas generales de comportamiento en la circulación: normas generales; normas generales de los conductores; normes sobre bebidas alcohólicas, y normas sobre estupefacientes, psicotrópicos, estimulantes u otras sustancias análogas.',
                'leyes' => [
                    [
                        'nombre' => 'Real Decreto 1428/2003, de 21 de noviembre, Reglamento General de Circulación',
                        'descripcion' => 'Reglamento General de Circulación para la aplicación y desarrollo de la Ley sobre tráfico',
                        'articulos' => [
                            ['numero' => '3', 'explicacion' => 'Los usuarios de la vía están obligados a comportarse de forma que no entorpezcan la circulación ni causen peligro.'],
                            ['numero' => '25', 'explicacion' => 'Queda prohibido conducir con tasas de alcohol superiores a las establecidas reglamentariamente.'],
                            ['numero' => '27', 'explicacion' => 'Queda prohibida la conducción bajo la influencia de drogas tóxicas, estupefacientes, psicotrópicos y sustancias análogas.'],
                        ],
                    ],
                ],
            ],
            // Tema 9
            [
                'nombre' => 'Tema 9 - El Real decreto 1428/2003, Reglamento General de Circulación - La circulación de vehículos',
                'descripcion' => 'La circulación de vehículos según el Reglamento General de Circulación.',
                'leyes' => [
                    [
                        'nombre' => 'Real Decreto 1428/2003, de 21 de noviembre, Reglamento General de Circulación',
                        'descripcion' => 'Reglamento General de Circulación para la aplicación y desarrollo de la Ley sobre tráfico',
                        'articulos' => [
                            ['numero' => '29', 'explicacion' => 'La velocidad deberá adaptarse a las condiciones de la vía, circulación, meteorológicas y ambientales.'],
                            ['numero' => '38', 'explicacion' => 'En las vías con más de un carril por sentido, los conductores circularán normalmente por el carril situado más a su derecha.'],
                            ['numero' => '50', 'explicacion' => 'Está prohibido circular marcha atrás, salvo para estacionar, cambiar de dirección o sentido, o en situaciones de emergencia.'],
                        ],
                    ],
                ],
            ],
            // Tema 10
            [
                'nombre' => 'Tema 10 - El Real decreto 1428/2003, Reglamento General de Circulación - La señalización',
                'descripcion' => 'La señalización: normas generales y prioridad entre señales. Los señales y las órdenes de los agentes de circulación.',
                'leyes' => [
                    [
                        'nombre' => 'Real Decreto 1428/2003, de 21 de noviembre, Reglamento General de Circulación',
                        'descripcion' => 'Reglamento General de Circulación para la aplicación y desarrollo de la Ley sobre tráfico',
                        'articulos' => [
                            ['numero' => '131', 'explicacion' => 'Las señales de circulación tienen por objeto advertir e informar a los usuarios de la vía.'],
                            ['numero' => '132', 'explicacion' => 'Las señales y órdenes de los agentes de circulación prevalecerán sobre cualquier otra señal o norma de circulación.'],
                        ],
                    ],
                ],
            ],
            // Tema 11
            [
                'nombre' => 'Tema 11 - El Real decreto 818/2009, de 8 de mayo, Reglamento General de Conductores',
                'descripcion' => 'Las autorizaciones administrativas para conducir: el permiso y la licencia de conducción.',
                'leyes' => [
                    [
                        'nombre' => 'Real Decreto 818/2009, de 8 de mayo, Reglamento General de Conductores',
                        'descripcion' => 'Reglamento General de Conductores',
                        'articulos' => [
                            ['numero' => '2', 'explicacion' => 'Para conducir vehículos a motor y ciclomotores se requiere estar en posesión del correspondiente permiso o licencia de conducción.'],
                            ['numero' => '3', 'explicacion' => 'El permiso de conducción autoriza para conducir determinadas categorías de vehículos.'],
                            ['numero' => '4', 'explicacion' => 'La licencia de conducción autoriza para conducir ciclomotores y vehículos para personas de movilidad reducida.'],
                        ],
                    ],
                ],
            ],
            // Tema 12
            [
                'nombre' => 'Tema 12 - El Real decreto 2822/1998, de 23 de diciembre, Reglamento General de Vehículos - Normas generales',
                'descripcion' => 'Normas generales del Reglamento General de Vehículos.',
                'leyes' => [
                    [
                        'nombre' => 'Real Decreto 2822/1998, de 23 de diciembre, Reglamento General de Vehículos',
                        'descripcion' => 'Reglamento General de Vehículos',
                        'articulos' => [
                            ['numero' => '1', 'explicacion' => 'Este reglamento tiene por objeto establecer las condiciones técnicas y administrativas que deben cumplir los vehículos.'],
                            ['numero' => '2', 'explicacion' => 'Todos los vehículos deberán cumplir las condiciones técnicas establecidas en este reglamento.'],
                        ],
                    ],
                ],
            ],
            // Tema 13
            [
                'nombre' => 'Tema 13 - El Real decreto 2822/1998, Reglamento General de Vehículos - Ciclomotores, ciclos, vehículos de tracción animal y tranvías',
                'descripcion' => 'Ciclomotores, ciclos, vehículos de tracción animal y tranvías.',
                'leyes' => [
                    [
                        'nombre' => 'Real Decreto 2822/1998, de 23 de diciembre, Reglamento General de Vehículos',
                        'descripcion' => 'Reglamento General de Vehículos',
                        'articulos' => [
                            ['numero' => '22', 'explicacion' => 'Los ciclomotores son vehículos de dos o tres ruedas provistos de un motor de cilindrada no superior a 50 cm³.'],
                            ['numero' => '23', 'explicacion' => 'Los ciclos son vehículos de dos ruedas por lo menos, propulsados exclusivamente por el esfuerzo muscular de las personas que lo ocupan.'],
                        ],
                    ],
                ],
            ],
            // Tema 14
            [
                'nombre' => 'Tema 14 - El Real decreto 2822/1998, Reglamento General de Vehículos - Autorizaciones de circulación',
                'descripcion' => 'Autorizaciones de circulación de los vehículos: matriculación y matriculación ordinaria.',
                'leyes' => [
                    [
                        'nombre' => 'Real Decreto 2822/1998, de 23 de diciembre, Reglamento General de Vehículos',
                        'descripcion' => 'Reglamento General de Vehículos',
                        'articulos' => [
                            ['numero' => '5', 'explicacion' => 'Para circular por las vías públicas, los vehículos deberán estar matriculados.'],
                            ['numero' => '6', 'explicacion' => 'La matriculación ordinaria es la que se realiza de forma permanente para vehículos nuevos o usados.'],
                        ],
                    ],
                ],
            ],
            // Tema 15
            [
                'nombre' => 'Tema 15 - El Real decreto 2822/1998, Reglamento General de Vehículos - Anexo II',
                'descripcion' => 'Anexo II: definiciones y categorías de los vehículos.',
                'leyes' => [
                    [
                        'nombre' => 'Real Decreto 2822/1998, de 23 de diciembre, Reglamento General de Vehículos',
                        'descripcion' => 'Reglamento General de Vehículos',
                        'articulos' => [
                            ['numero' => 'Anexo II', 'explicacion' => 'El Anexo II contiene las definiciones y categorías de los vehículos según sus características técnicas y de uso.'],
                        ],
                    ],
                ],
            ],
            // Tema 16
            [
                'nombre' => 'Tema 16 - El Real decreto 2822/1998, Reglamento General de Vehículos - Anexo XVIII',
                'descripcion' => 'Anexo XVIII: placas de matrícula, colores e inscripciones y contraseñas de las placas.',
                'leyes' => [
                    [
                        'nombre' => 'Real Decreto 2822/1998, de 23 de diciembre, Reglamento General de Vehículos',
                        'descripcion' => 'Reglamento General de Vehículos',
                        'articulos' => [
                            ['numero' => 'Anexo XVIII', 'explicacion' => 'El Anexo XVIII regula las características de las placas de matrícula, sus colores, inscripciones y contraseñas.'],
                        ],
                    ],
                ],
            ],
            // Tema 17
            [
                'nombre' => 'Tema 17 - El accidente de tráfico',
                'descripcion' => 'Definición, tipos, causas y clases de accidentes. La actividad policial ante los accidentes de tráfico. El orden cronológico de las actuaciones.',
                'leyes' => [
                    [
                        'nombre' => 'Accidentes de Tráfico',
                        'descripcion' => 'Normativa y procedimientos sobre accidentes de tráfico',
                        'articulos' => [
                            ['numero' => 'Concepto', 'explicacion' => 'Un accidente de tráfico es un suceso eventual que altera la normal circulación de vehículos y puede causar daños a personas o bienes.'],
                            ['numero' => 'Tipos', 'explicacion' => 'Los accidentes pueden clasificarse según su gravedad (leves, graves, mortales), número de vehículos (simples, múltiples) o causas.'],
                            ['numero' => 'Actuación policial', 'explicacion' => 'La actuación policial en accidentes incluye: auxilio a víctimas, señalización, protección de la escena, identificación de testigos y levantamiento del atestado.'],
                        ],
                    ],
                ],
            ],
            // Tema 18
            [
                'nombre' => 'Tema 18 - La Ley 4/2013, de 17 de julio, de coordinación de las policías locales de las Illes Balears - Principios y estructura',
                'descripcion' => 'Principios generales. Cuerpos de policía local. Estructura y régimen de funcionamiento.',
                'leyes' => [
                    [
                        'nombre' => 'Ley 4/2013, de 17 de julio, de coordinación de las policías locales de las Illes Balears',
                        'descripcion' => 'Ley de coordinación de las policías locales de las Illes Balears',
                        'articulos' => [
                            ['numero' => '1', 'explicacion' => 'Esta ley tiene por objeto regular la coordinación de las policías locales de las Illes Balears.'],
                            ['numero' => '3', 'explicacion' => 'Los cuerpos de policía local son instituciones al servicio de la ciudadanía para garantizar la seguridad ciudadana.'],
                            ['numero' => '5', 'explicacion' => 'La estructura de los cuerpos de policía local se organiza según las necesidades del municipio.'],
                        ],
                    ],
                ],
            ],
            // Tema 19
            [
                'nombre' => 'Tema 19 - La Ley 4/2013, de coordinación de las policías locales de las Illes Balears - Régimen disciplinario',
                'descripcion' => 'Régimen disciplinario: principios generales, infracciones, sanciones y potestad sancionadora y extinción y prescripción.',
                'leyes' => [
                    [
                        'nombre' => 'Ley 4/2013, de 17 de julio, de coordinación de las policías locales de las Illes Balears',
                        'descripcion' => 'Ley de coordinación de las policías locales de las Illes Balears',
                        'articulos' => [
                            ['numero' => '45', 'explicacion' => 'El régimen disciplinario de los miembros de la policía local se rige por los principios de legalidad, tipicidad, proporcionalidad y presunción de inocencia.'],
                            ['numero' => '46', 'explicacion' => 'Las infracciones disciplinarias se clasifican en muy graves, graves y leves.'],
                            ['numero' => '50', 'explicacion' => 'Las sanciones disciplinarias pueden ser: separación del servicio, suspensión, traslado, pérdida de destino o amonestación.'],
                        ],
                    ],
                ],
            ],
            // Tema 20
            [
                'nombre' => 'Tema 20 - El Decreto 40/2019, de 24 de mayo, Reglamento marco de coordinación de las policías locales de las Illes Balears',
                'descripcion' => 'Uso del equipo básico de autodefensa y protección: disposiciones generales, normas generales sobre tenencia de armas, utilización de armas de fuego, utilización de la defensa y del bastón extensible, y utilización del aerosol de defensa. Uniformidad y equipamiento. Normas de apariencia externa, presentación y uniformidad.',
                'leyes' => [
                    [
                        'nombre' => 'Decreto 40/2019, de 24 de mayo, Reglamento marco de coordinación de las policías locales de las Illes Balears',
                        'descripcion' => 'Reglamento marco de coordinación de las policías locales de las Illes Balears',
                        'articulos' => [
                            ['numero' => '15', 'explicacion' => 'El uso de armas de fuego por los agentes de policía local se regirá por los principios de necesidad, proporcionalidad y oportunidad.'],
                            ['numero' => '18', 'explicacion' => 'El bastón extensible y el aerosol de defensa son elementos de protección que deben usarse con moderación y solo cuando sea necesario.'],
                            ['numero' => '25', 'explicacion' => 'La uniformidad de los agentes de policía local debe ser adecuada, limpia y en perfecto estado de conservación.'],
                        ],
                    ],
                ],
            ],
            // Tema 21
            [
                'nombre' => 'Tema 21 - La Ley orgánica 2/1986, de 13 de marzo, de fuerzas y cuerpos de seguridad',
                'descripcion' => 'Principios básicos de actuación. Disposiciones estatutarias comunes. Las policías locales.',
                'leyes' => [
                    [
                        'nombre' => 'Ley Orgánica 2/1986, de 13 de marzo, de Fuerzas y Cuerpos de Seguridad',
                        'descripcion' => 'Ley Orgánica de Fuerzas y Cuerpos de Seguridad',
                        'articulos' => [
                            ['numero' => '5', 'explicacion' => 'Los principios básicos de actuación de las Fuerzas y Cuerpos de Seguridad son: adecuación al ordenamiento jurídico, cooperación y coordinación.'],
                            ['numero' => '29', 'explicacion' => 'Las policías locales ejercen las funciones de policía administrativa, de seguridad ciudadana y de policía judicial en el ámbito de su competencia.'],
                        ],
                    ],
                ],
            ],
            // Tema 22
            [
                'nombre' => 'Tema 22 - La policía local como policía judicial. La detención',
                'descripcion' => 'La policía local como policía judicial. La detención. Concepto. Derechos y garantías del detenido. La Ley orgánica 6/1984, de 24 de mayo, reguladora del procedimiento de habeas corpus.',
                'leyes' => [
                    [
                        'nombre' => 'Ley Orgánica 6/1984, de 24 de mayo, reguladora del procedimiento de habeas corpus',
                        'descripcion' => 'Ley reguladora del procedimiento de habeas corpus',
                        'articulos' => [
                            ['numero' => '1', 'explicacion' => 'El habeas corpus es un procedimiento para poner inmediatamente a disposición judicial a cualquier persona detenida ilegalmente.'],
                            ['numero' => '3', 'explicacion' => 'Toda persona detenida tiene derecho a ser informada de forma inmediata de los hechos que se le imputan y de los derechos que le asisten.'],
                        ],
                    ],
                ],
            ],
            // Tema 23
            [
                'nombre' => 'Tema 23 - El Real decreto de 14 de septiembre de 1882, Ley de Enjuiciamiento Criminal - La denuncia',
                'descripcion' => 'La denuncia según la Ley de Enjuiciamiento Criminal.',
                'leyes' => [
                    [
                        'nombre' => 'Real Decreto de 14 de septiembre de 1882, Ley de Enjuiciamiento Criminal',
                        'descripcion' => 'Ley de Enjuiciamiento Criminal',
                        'articulos' => [
                            ['numero' => '259', 'explicacion' => 'La denuncia es la declaración de conocimiento de un delito o falta hecha ante el juez, tribunal o funcionario competente.'],
                            ['numero' => '262', 'explicacion' => 'Toda persona que tenga noticia de la comisión de un delito público está obligada a denunciarlo.'],
                        ],
                    ],
                ],
            ],
            // Tema 24
            [
                'nombre' => 'Tema 24 - La Ley orgánica 10/1995, de 23 de noviembre, del Código Penal - La infracción penal',
                'descripcion' => 'La infracción penal. Las personas criminalmente responsables de los delitos.',
                'leyes' => [
                    [
                        'nombre' => 'Ley Orgánica 10/1995, de 23 de noviembre, del Código Penal',
                        'descripcion' => 'Código Penal español',
                        'articulos' => [
                            ['numero' => '10', 'explicacion' => 'Son infracciones penales las acciones y omisiones dolosas o imprudentes penadas por la ley.'],
                            ['numero' => '27', 'explicacion' => 'Son responsables criminalmente de los delitos y faltas los autores y los cómplices.'],
                            ['numero' => '28', 'explicacion' => 'Son autores quienes realizan el hecho por sí solos, conjuntamente o por medio de otro del que se sirven como instrumento.'],
                        ],
                    ],
                ],
            ],
            // Tema 25
            [
                'nombre' => 'Tema 25 - La Ley orgánica 10/1995, del Código Penal - Delitos contra el patrimonio',
                'descripcion' => 'Delitos contra el patrimonio y contra el orden socioeconómico: los hurtos, de los robos, el robo y hurto de uso de vehículos, la usurpación, las defraudaciones, los daños.',
                'leyes' => [
                    [
                        'nombre' => 'Ley Orgánica 10/1995, de 23 de noviembre, del Código Penal',
                        'descripcion' => 'Código Penal español',
                        'articulos' => [
                            ['numero' => '234', 'explicacion' => 'El hurto consiste en tomar las cosas muebles ajenas sin la voluntad de su dueño.'],
                            ['numero' => '237', 'explicacion' => 'Son reos de robo los que, con ánimo de lucro, se apoderan de las cosas muebles ajenas empleando fuerza en las cosas o violencia o intimidación en las personas.'],
                            ['numero' => '244', 'explicacion' => 'El que sustrajere ilegítimamente un vehículo a motor o ciclomotor ajeno, sin ánimo de apropiárselo, será castigado con la pena de prisión.'],
                            ['numero' => '245', 'explicacion' => 'La usurpación consiste en apoderarse de una cosa inmueble o de derechos reales inmobiliarios de otro.'],
                            ['numero' => '248', 'explicacion' => 'Cometen defraudación los que, con ánimo de lucro, utilizaren engaño bastante para producir error en otro.'],
                            ['numero' => '263', 'explicacion' => 'El que causare daños en propiedad ajena será castigado con la pena de multa.'],
                        ],
                    ],
                ],
            ],
            // Tema 26
            [
                'nombre' => 'Tema 26 - La Ley orgánica 10/1995, del Código Penal - Delitos contra la seguridad vial',
                'descripcion' => 'Delitos contra la seguridad vial.',
                'leyes' => [
                    [
                        'nombre' => 'Ley Orgánica 10/1995, de 23 de noviembre, del Código Penal',
                        'descripcion' => 'Código Penal español',
                        'articulos' => [
                            ['numero' => '379', 'explicacion' => 'El que condujere un vehículo a motor o un ciclomotor bajo la influencia de bebidas alcohólicas será castigado con la pena de prisión.'],
                            ['numero' => '380', 'explicacion' => 'El que condujere un vehículo a motor o ciclomotor con temeridad manifiesta y pusiera en concreto peligro la vida o la integridad de las personas será castigado.'],
                            ['numero' => '381', 'explicacion' => 'El que negare a someterse a las pruebas legalmente establecidas para la comprobación de las tasas de alcoholemia será castigado.'],
                        ],
                    ],
                ],
            ],
            // Tema 27
            [
                'nombre' => 'Tema 27 - La Ley orgánica 10/1995, del Código Penal - Delitos contra derechos fundamentales',
                'descripcion' => 'Delitos cometidos con ocasión del ejercicio de los derechos fundamentales y de las libertades públicas garantizadas por la Constitución.',
                'leyes' => [
                    [
                        'nombre' => 'Ley Orgánica 10/1995, de 23 de noviembre, del Código Penal',
                        'descripcion' => 'Código Penal español',
                        'articulos' => [
                            ['numero' => '510', 'explicacion' => 'Los que provocaren a la discriminación, al odio o a la violencia contra grupos o asociaciones serán castigados.'],
                            ['numero' => '511', 'explicacion' => 'Los que en el ejercicio de sus actividades profesionales o empresariales denegaren a una persona una prestación a la que tenga derecho serán castigados.'],
                        ],
                    ],
                ],
            ],
            // Tema 28
            [
                'nombre' => 'Tema 28 - La Ley orgánica 3/2018, de 5 de diciembre, de protección de datos personales y garantía de los derechos digitales',
                'descripcion' => 'Disposiciones generales. Principios de protección de datos. Derechos de las personas.',
                'leyes' => [
                    [
                        'nombre' => 'Ley Orgánica 3/2018, de 5 de diciembre, de protección de datos personales y garantía de los derechos digitales',
                        'descripcion' => 'Ley Orgánica de Protección de Datos Personales y garantía de los derechos digitales',
                        'articulos' => [
                            ['numero' => '1', 'explicacion' => 'Esta ley tiene por objeto garantizar el derecho fundamental a la protección de datos personales.'],
                            ['numero' => '5', 'explicacion' => 'Los principios de protección de datos son: licitud, lealtad y transparencia; limitación de la finalidad; minimización de datos; exactitud; limitación del plazo de conservación; integridad y confidencialidad.'],
                            ['numero' => '15', 'explicacion' => 'Toda persona tiene derecho a obtener confirmación sobre si se están tratando datos personales que le conciernan.'],
                        ],
                    ],
                ],
            ],
            // Tema 29
            [
                'nombre' => 'Tema 29 - La Ley 11/2016, de 28 de julio, de igualdad de mujeres y hombres',
                'descripcion' => 'Disposiciones generales. Competencias, funciones, organización institucional y financiación. La violencia machista.',
                'leyes' => [
                    [
                        'nombre' => 'Ley 11/2016, de 28 de julio, de igualdad de mujeres y hombres',
                        'descripcion' => 'Ley de igualdad de mujeres y hombres',
                        'articulos' => [
                            ['numero' => '1', 'explicacion' => 'Esta ley tiene por objeto garantizar la igualdad efectiva de mujeres y hombres en todos los ámbitos de la vida.'],
                            ['numero' => '3', 'explicacion' => 'Las administraciones públicas competentes desarrollarán políticas de igualdad entre mujeres y hombres.'],
                            ['numero' => '45', 'explicacion' => 'La violencia machista es toda violencia que se ejerce contra las mujeres por el hecho de serlo.'],
                        ],
                    ],
                ],
            ],
            // Tema 30
            [
                'nombre' => 'Tema 30 - La Ley 8/2016, de 30 de mayo, por garantizar los derechos de lesbianas, gais, trans, bisexuales e intersexuales y por erradicar la LGTBI fobia',
                'descripcion' => 'Disposiciones generales. Políticas públicas para promover la igualdad efectiva de las personas LGTBI: profesionales que actúan en ámbitos sensibles. Mecanismos para garantizar el derecho a la igualdad: disposiciones generales.',
                'leyes' => [
                    [
                        'nombre' => 'Ley 8/2016, de 30 de mayo, por garantizar los derechos de lesbianas, gais, trans, bisexuales e intersexuales y por erradicar la LGTBI fobia',
                        'descripcion' => 'Ley de garantía de derechos LGTBI y erradicación de la LGTBI fobia',
                        'articulos' => [
                            ['numero' => '1', 'explicacion' => 'Esta ley tiene por objeto garantizar los derechos de las personas LGTBI y erradicar la discriminación y la violencia por razón de orientación sexual, identidad de género o expresión de género.'],
                            ['numero' => '12', 'explicacion' => 'Los profesionales que actúan en ámbitos sensibles recibirán formación específica sobre diversidad sexual y de género.'],
                            ['numero' => '20', 'explicacion' => 'Se establecerán mecanismos para garantizar el derecho a la igualdad y la no discriminación de las personas LGTBI.'],
                        ],
                    ],
                ],
            ],
        ];
    }
}

