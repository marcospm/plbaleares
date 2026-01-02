<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-convocatoria-municipio-table',
    description: 'Crea la tabla convocatoria_municipio para la relación ManyToMany',
)]
class CreateConvocatoriaMunicipioTableCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $connection = $this->entityManager->getConnection();
            
            // Verificar si la tabla ya existe
            $schemaManager = $connection->createSchemaManager();
            $tables = $schemaManager->listTableNames();
            
            if (in_array('convocatoria_municipio', $tables)) {
                $io->success('La tabla convocatoria_municipio ya existe.');
                return Command::SUCCESS;
            }

            $io->info('Creando tabla convocatoria_municipio...');

            // Crear la tabla
            $connection->executeStatement('
                CREATE TABLE convocatoria_municipio (
                    convocatoria_id INT NOT NULL,
                    municipio_id INT NOT NULL,
                    INDEX IDX_CONVOCATORIA_MUNICIPIO_CONVOCATORIA (convocatoria_id),
                    INDEX IDX_CONVOCATORIA_MUNICIPIO_MUNICIPIO (municipio_id),
                    PRIMARY KEY(convocatoria_id, municipio_id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            ');

            $io->info('Migrando datos existentes...');

            // Migrar datos existentes
            $connection->executeStatement('
                INSERT IGNORE INTO convocatoria_municipio (convocatoria_id, municipio_id) 
                SELECT id, municipio_id 
                FROM convocatoria 
                WHERE municipio_id IS NOT NULL
            ');

            $io->info('Añadiendo claves foráneas...');

            // Añadir claves foráneas
            try {
                $connection->executeStatement('
                    ALTER TABLE convocatoria_municipio 
                    ADD CONSTRAINT FK_CONVOCATORIA_MUNICIPIO_CONVOCATORIA 
                    FOREIGN KEY (convocatoria_id) REFERENCES convocatoria (id) ON DELETE CASCADE
                ');
            } catch (\Exception $e) {
                $io->warning('La clave foránea FK_CONVOCATORIA_MUNICIPIO_CONVOCATORIA ya existe o hubo un error: ' . $e->getMessage());
            }

            try {
                $connection->executeStatement('
                    ALTER TABLE convocatoria_municipio 
                    ADD CONSTRAINT FK_CONVOCATORIA_MUNICIPIO_MUNICIPIO 
                    FOREIGN KEY (municipio_id) REFERENCES municipio (id) ON DELETE CASCADE
                ');
            } catch (\Exception $e) {
                $io->warning('La clave foránea FK_CONVOCATORIA_MUNICIPIO_MUNICIPIO ya existe o hubo un error: ' . $e->getMessage());
            }

            $io->success('Tabla convocatoria_municipio creada correctamente.');
            $io->note('Nota: La columna municipio_id de la tabla convocatoria aún existe. Puedes eliminarla manualmente después de verificar que todo funciona correctamente.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error al crear la tabla: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
