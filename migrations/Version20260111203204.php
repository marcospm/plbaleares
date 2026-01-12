<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Optimización de rendimiento: Agregar índices para mejorar el rendimiento de consultas frecuentes
 */
final class Version20260111203204 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agregar índices para optimizar consultas frecuentes y mejorar rendimiento';
    }

    /**
     * Elimina un índice si existe y no está siendo usado por una foreign key (compatible con MySQL/MariaDB)
     */
    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        // Verificar si el índice existe
        $sql = "SELECT COUNT(*) as count 
                FROM information_schema.statistics 
                WHERE table_schema = DATABASE() 
                AND table_name = ? 
                AND index_name = ?";
        
        $result = $this->connection->fetchAssociative($sql, [$tableName, $indexName]);
        
        if ($result && (int)$result['count'] > 0) {
            // Obtener las columnas del índice
            $columnsSql = "SELECT column_name 
                           FROM information_schema.statistics 
                           WHERE table_schema = DATABASE() 
                           AND table_name = ? 
                           AND index_name = ?
                           ORDER BY seq_in_index";
            
            $columns = $this->connection->fetchAllAssociative($columnsSql, [$tableName, $indexName]);
            
            if (!empty($columns)) {
                $columnNames = array_column($columns, 'column_name');
                $placeholders = implode(',', array_fill(0, count($columnNames), '?'));
                
                // Verificar si alguna de estas columnas está en una foreign key
                $fkCheckSql = "SELECT COUNT(*) as count
                               FROM information_schema.key_column_usage kcu
                               INNER JOIN information_schema.table_constraints tc 
                                   ON kcu.constraint_name = tc.constraint_name
                                   AND kcu.table_schema = tc.table_schema
                               WHERE kcu.table_schema = DATABASE()
                               AND kcu.table_name = ?
                               AND tc.constraint_type = 'FOREIGN KEY'
                               AND kcu.column_name IN ({$placeholders})";
                
                $params = array_merge([$tableName], $columnNames);
                $fkResult = $this->connection->fetchAssociative($fkCheckSql, $params);
                
                // Si el índice no está siendo usado por una foreign key, eliminarlo
                if (!$fkResult || (int)$fkResult['count'] == 0) {
                    $this->addSql("ALTER TABLE {$tableName} DROP INDEX {$indexName}");
                }
                // Si está siendo usado por una FK, simplemente no lo eliminamos (la FK ya tiene su índice)
            } else {
                // Si no hay columnas, intentar eliminar de todas formas
                $this->addSql("ALTER TABLE {$tableName} DROP INDEX {$indexName}");
            }
        }
    }

    /**
     * Crea un índice si no existe (compatible con MySQL/MariaDB)
     * Soporta índices simples y compuestos
     */
    private function createIndexIfNotExists(string $tableName, string $indexName, string $columns): void
    {
        $sql = "SELECT COUNT(*) as count 
                FROM information_schema.statistics 
                WHERE table_schema = DATABASE() 
                AND table_name = ? 
                AND index_name = ?";
        
        $result = $this->connection->fetchAssociative($sql, [$tableName, $indexName]);
        
        if (!$result || (int)$result['count'] == 0) {
            $this->addSql("CREATE INDEX {$indexName} ON {$tableName} ({$columns})");
        }
    }

    /**
     * Elimina un índice si existe (sin verificar FK) y luego lo crea si no existe
     * Útil para índices que pueden haber sido creados en ejecuciones anteriores
     */
    private function recreateIndexIfExists(string $tableName, string $indexName, string $columns): void
    {
        // Primero intentar eliminar el índice si existe (sin verificar FK)
        $sql = "SELECT COUNT(*) as count 
                FROM information_schema.statistics 
                WHERE table_schema = DATABASE() 
                AND table_name = ? 
                AND index_name = ?";
        
        $result = $this->connection->fetchAssociative($sql, [$tableName, $indexName]);
        
        if ($result && (int)$result['count'] > 0) {
            // Verificar si está siendo usado por una FK antes de eliminarlo
            $columnsSql = "SELECT column_name 
                           FROM information_schema.statistics 
                           WHERE table_schema = DATABASE() 
                           AND table_name = ? 
                           AND index_name = ?
                           ORDER BY seq_in_index";
            
            $indexColumns = $this->connection->fetchAllAssociative($columnsSql, [$tableName, $indexName]);
            
            if (!empty($indexColumns)) {
                $columnNames = array_column($indexColumns, 'column_name');
                $placeholders = implode(',', array_fill(0, count($columnNames), '?'));
                
                $fkCheckSql = "SELECT COUNT(*) as count
                               FROM information_schema.key_column_usage kcu
                               INNER JOIN information_schema.table_constraints tc 
                                   ON kcu.constraint_name = tc.constraint_name
                                   AND kcu.table_schema = tc.table_schema
                               WHERE kcu.table_schema = DATABASE()
                               AND kcu.table_name = ?
                               AND tc.constraint_type = 'FOREIGN KEY'
                               AND kcu.column_name IN ({$placeholders})";
                
                $params = array_merge([$tableName], $columnNames);
                $fkResult = $this->connection->fetchAssociative($fkCheckSql, $params);
                
                // Solo eliminar si no está siendo usado por una FK
                if (!$fkResult || (int)$fkResult['count'] == 0) {
                    $this->addSql("ALTER TABLE {$tableName} DROP INDEX {$indexName}");
                }
            }
        }
        
        // Luego crear el índice si no existe
        $this->createIndexIfNotExists($tableName, $indexName, $columns);
    }

    public function up(Schema $schema): void
    {
        // Índices para tabla Articulo
        // Usado en: findActivosOrdenadosPorNumero, buscarConFiltros
        // Usar recreateIndexIfExists para manejar índices que pueden existir de ejecuciones anteriores
        $this->recreateIndexIfExists('articulo', 'idx_articulo_ley_activo', 'ley_id, activo');
        $this->recreateIndexIfExists('articulo', 'idx_articulo_activo', 'activo');
        $this->recreateIndexIfExists('articulo', 'idx_articulo_numero', 'numero');

        // Índices para tabla PreguntaMunicipal
        // Usado en: consultas por tema, municipio, dificultad y activo
        $this->recreateIndexIfExists('pregunta_municipal', 'idx_pregunta_municipal_tema_activo', 'tema_municipal_id, activo');
        $this->recreateIndexIfExists('pregunta_municipal', 'idx_pregunta_municipal_municipio_activo', 'municipio_id, activo');
        $this->recreateIndexIfExists('pregunta_municipal', 'idx_pregunta_municipal_dificultad_activo', 'dificultad, activo');
        $this->recreateIndexIfExists('pregunta_municipal', 'idx_pregunta_municipal_activo', 'activo');

        // Índices adicionales para tabla Examen
        // Usado en: getRankingPorDificultad, getNotaMediaUsuario, etc.
        $this->recreateIndexIfExists('examen', 'idx_examen_usuario_dificultad_municipio', 'usuario_id, dificultad, municipio_id');
        $this->recreateIndexIfExists('examen', 'idx_examen_usuario_dificultad_fecha', 'usuario_id, dificultad, fecha');
        $this->recreateIndexIfExists('examen', 'idx_examen_convocatoria_dificultad', 'convocatoria_id, dificultad');

        // Índices para tabla MensajeArticulo
        // Usado en: countMensajesPrincipales, countMensajesPrincipalesPorArticulos
        $this->recreateIndexIfExists('mensaje_articulo', 'idx_mensaje_articulo_articulo_padre', 'articulo_id, mensaje_padre_id');
        $this->recreateIndexIfExists('mensaje_articulo', 'idx_mensaje_articulo_fecha_creacion', 'fecha_creacion');

        // Índices para tabla RecursoEspecifico
        // Usado en: findByAlumno, findByProfesor
        $this->recreateIndexIfExists('recurso_especifico', 'idx_recurso_especifico_profesor', 'profesor_id');
        
        // El índice idx_recurso_especifico_grupo puede estar siendo usado por una FK,
        // así que solo lo creamos si no existe (no intentamos eliminarlo primero)
        $this->createIndexIfNotExists('recurso_especifico', 'idx_recurso_especifico_grupo', 'grupo_id');
        
        $this->recreateIndexIfExists('recurso_especifico', 'idx_recurso_especifico_fecha_creacion', 'fecha_creacion');

        // Índices para tablas principales
        // Usado en: findActivas, findActivosOrderedByNombre, etc.
        $this->recreateIndexIfExists('ley', 'idx_ley_activo', 'activo');
        $this->recreateIndexIfExists('tema', 'idx_tema_activo', 'activo');
        $this->recreateIndexIfExists('convocatoria', 'idx_convocatoria_activo', 'activo');
        $this->recreateIndexIfExists('municipio', 'idx_municipio_activo', 'activo');

        // Índices para tablas de relación ManyToMany
        // Usado en: JOINs en consultas de exámenes y recursos
        $this->recreateIndexIfExists('examen_tema', 'idx_examen_tema_examen', 'examen_id');
        $this->recreateIndexIfExists('examen_tema', 'idx_examen_tema_tema', 'tema_id');
        $this->recreateIndexIfExists('examen_tema_municipal', 'idx_examen_tema_municipal_examen', 'examen_id');
        $this->recreateIndexIfExists('examen_tema_municipal', 'idx_examen_tema_municipal_tema', 'tema_municipal_id');
        $this->recreateIndexIfExists('recurso_especifico_alumno', 'idx_recurso_especifico_alumno_recurso', 'recurso_especifico_id');
        $this->recreateIndexIfExists('recurso_especifico_alumno', 'idx_recurso_especifico_alumno_alumno', 'alumno_id');
    }

    public function down(Schema $schema): void
    {
        // Eliminar índices en orden inverso
        $this->addSql('DROP INDEX idx_recurso_especifico_alumno_alumno ON recurso_especifico_alumno');
        $this->addSql('DROP INDEX idx_recurso_especifico_alumno_recurso ON recurso_especifico_alumno');
        $this->addSql('DROP INDEX idx_examen_tema_municipal_tema ON examen_tema_municipal');
        $this->addSql('DROP INDEX idx_examen_tema_municipal_examen ON examen_tema_municipal');
        $this->addSql('DROP INDEX idx_examen_tema_tema ON examen_tema');
        $this->addSql('DROP INDEX idx_examen_tema_examen ON examen_tema');
        
        $this->addSql('DROP INDEX idx_municipio_activo ON municipio');
        $this->addSql('DROP INDEX idx_convocatoria_activo ON convocatoria');
        $this->addSql('DROP INDEX idx_tema_activo ON tema');
        $this->addSql('DROP INDEX idx_ley_activo ON ley');
        
        $this->addSql('DROP INDEX idx_recurso_especifico_fecha_creacion ON recurso_especifico');
        $this->addSql('DROP INDEX idx_recurso_especifico_grupo ON recurso_especifico');
        $this->addSql('DROP INDEX idx_recurso_especifico_profesor ON recurso_especifico');
        
        $this->addSql('DROP INDEX idx_mensaje_articulo_fecha_creacion ON mensaje_articulo');
        $this->addSql('DROP INDEX idx_mensaje_articulo_articulo_padre ON mensaje_articulo');
        
        $this->addSql('DROP INDEX idx_examen_convocatoria_dificultad ON examen');
        $this->addSql('DROP INDEX idx_examen_usuario_dificultad_fecha ON examen');
        $this->addSql('DROP INDEX idx_examen_usuario_dificultad_municipio ON examen');
        
        $this->addSql('DROP INDEX idx_pregunta_municipal_activo ON pregunta_municipal');
        $this->addSql('DROP INDEX idx_pregunta_municipal_dificultad_activo ON pregunta_municipal');
        $this->addSql('DROP INDEX idx_pregunta_municipal_municipio_activo ON pregunta_municipal');
        $this->addSql('DROP INDEX idx_pregunta_municipal_tema_activo ON pregunta_municipal');
        
        $this->addSql('DROP INDEX idx_articulo_numero ON articulo');
        $this->addSql('DROP INDEX idx_articulo_activo ON articulo');
        $this->addSql('DROP INDEX idx_articulo_ley_activo ON articulo');
    }
}
