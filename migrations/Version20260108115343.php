<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260108115343 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade índices de base de datos para optimizar consultas frecuentes';
    }

    public function up(Schema $schema): void
    {
        // Índices para tabla examen
        $this->addSql('CREATE INDEX idx_examen_usuario_fecha ON examen (usuario_id, fecha)');
        $this->addSql('CREATE INDEX idx_examen_dificultad_municipio ON examen (dificultad, municipio_id)');
        $this->addSql('CREATE INDEX idx_examen_convocatoria_fecha ON examen (convocatoria_id, fecha)');
        $this->addSql('CREATE INDEX idx_examen_fecha ON examen (fecha)');
        
        // Índices para tabla user
        $this->addSql('CREATE INDEX idx_user_activo ON user (activo)');
        
        // Índices para tabla pregunta
        $this->addSql('CREATE INDEX idx_pregunta_tema_activo ON pregunta (tema_id, activo)');
        $this->addSql('CREATE INDEX idx_pregunta_dificultad_activo ON pregunta (dificultad, activo)');
        $this->addSql('CREATE INDEX idx_pregunta_activo ON pregunta (activo)');
        
        // Índices para tabla tarea_asignada
        $this->addSql('CREATE INDEX idx_tarea_asignada_usuario_completada ON tarea_asignada (usuario_id, completada)');
        $this->addSql('CREATE INDEX idx_tarea_asignada_tarea ON tarea_asignada (tarea_id)');
        
        // Índices para tabla notificacion
        $this->addSql('CREATE INDEX idx_notificacion_alumno_leida ON notificacion (alumno_id, leida)');
        $this->addSql('CREATE INDEX idx_notificacion_profesor_leida ON notificacion (profesor_id, leida)');
        $this->addSql('CREATE INDEX idx_notificacion_fecha_creacion ON notificacion (fecha_creacion)');
    }

    public function down(Schema $schema): void
    {
        // Eliminar índices en orden inverso
        $this->addSql('DROP INDEX idx_notificacion_fecha_creacion ON notificacion');
        $this->addSql('DROP INDEX idx_notificacion_profesor_leida ON notificacion');
        $this->addSql('DROP INDEX idx_notificacion_alumno_leida ON notificacion');
        
        $this->addSql('DROP INDEX idx_tarea_asignada_tarea ON tarea_asignada');
        $this->addSql('DROP INDEX idx_tarea_asignada_usuario_completada ON tarea_asignada');
        
        $this->addSql('DROP INDEX idx_pregunta_activo ON pregunta');
        $this->addSql('DROP INDEX idx_pregunta_dificultad_activo ON pregunta');
        $this->addSql('DROP INDEX idx_pregunta_tema_activo ON pregunta');
        
        $this->addSql('DROP INDEX idx_user_activo ON user');
        
        $this->addSql('DROP INDEX idx_examen_fecha ON examen');
        $this->addSql('DROP INDEX idx_examen_convocatoria_fecha ON examen');
        $this->addSql('DROP INDEX idx_examen_dificultad_municipio ON examen');
        $this->addSql('DROP INDEX idx_examen_usuario_fecha ON examen');
    }
}
