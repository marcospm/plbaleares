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

    public function up(Schema $schema): void
    {
        // Índices para tabla Articulo
        // Usado en: findActivosOrdenadosPorNumero, buscarConFiltros
        $this->addSql('CREATE INDEX idx_articulo_ley_activo ON articulo (ley_id, activo)');
        $this->addSql('CREATE INDEX idx_articulo_activo ON articulo (activo)');
        $this->addSql('CREATE INDEX idx_articulo_numero ON articulo (numero)');

        // Índices para tabla PreguntaMunicipal
        // Usado en: consultas por tema, municipio, dificultad y activo
        $this->addSql('CREATE INDEX idx_pregunta_municipal_tema_activo ON pregunta_municipal (tema_municipal_id, activo)');
        $this->addSql('CREATE INDEX idx_pregunta_municipal_municipio_activo ON pregunta_municipal (municipio_id, activo)');
        $this->addSql('CREATE INDEX idx_pregunta_municipal_dificultad_activo ON pregunta_municipal (dificultad, activo)');
        $this->addSql('CREATE INDEX idx_pregunta_municipal_activo ON pregunta_municipal (activo)');

        // Índices adicionales para tabla Examen
        // Usado en: getRankingPorDificultad, getNotaMediaUsuario, etc.
        $this->addSql('CREATE INDEX idx_examen_usuario_dificultad_municipio ON examen (usuario_id, dificultad, municipio_id)');
        $this->addSql('CREATE INDEX idx_examen_usuario_dificultad_fecha ON examen (usuario_id, dificultad, fecha)');
        $this->addSql('CREATE INDEX idx_examen_convocatoria_dificultad ON examen (convocatoria_id, dificultad)');

        // Índices para tabla MensajeArticulo
        // Usado en: countMensajesPrincipales, countMensajesPrincipalesPorArticulos
        $this->addSql('CREATE INDEX idx_mensaje_articulo_articulo_padre ON mensaje_articulo (articulo_id, mensaje_padre_id)');
        $this->addSql('CREATE INDEX idx_mensaje_articulo_fecha_creacion ON mensaje_articulo (fecha_creacion)');

        // Índices para tabla RecursoEspecifico
        // Usado en: findByAlumno, findByProfesor
        $this->addSql('CREATE INDEX idx_recurso_especifico_profesor ON recurso_especifico (profesor_id)');
        $this->addSql('CREATE INDEX idx_recurso_especifico_grupo ON recurso_especifico (grupo_id)');
        $this->addSql('CREATE INDEX idx_recurso_especifico_fecha_creacion ON recurso_especifico (fecha_creacion)');

        // Índices para tablas principales
        // Usado en: findActivas, findActivosOrderedByNombre, etc.
        // Nota: Si estos índices ya existen, la migración fallará y deberás eliminarlos manualmente primero
        $this->addSql('CREATE INDEX idx_ley_activo ON ley (activo)');
        $this->addSql('CREATE INDEX idx_tema_activo ON tema (activo)');
        $this->addSql('CREATE INDEX idx_convocatoria_activo ON convocatoria (activo)');
        $this->addSql('CREATE INDEX idx_municipio_activo ON municipio (activo)');

        // Índices para tablas de relación ManyToMany
        // Usado en: JOINs en consultas de exámenes y recursos
        $this->addSql('CREATE INDEX idx_examen_tema_examen ON examen_tema (examen_id)');
        $this->addSql('CREATE INDEX idx_examen_tema_tema ON examen_tema (tema_id)');
        $this->addSql('CREATE INDEX idx_examen_tema_municipal_examen ON examen_tema_municipal (examen_id)');
        $this->addSql('CREATE INDEX idx_examen_tema_municipal_tema ON examen_tema_municipal (tema_municipal_id)');
        $this->addSql('CREATE INDEX idx_recurso_especifico_alumno_recurso ON recurso_especifico_alumno (recurso_especifico_id)');
        $this->addSql('CREATE INDEX idx_recurso_especifico_alumno_alumno ON recurso_especifico_alumno (alumno_id)');
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
