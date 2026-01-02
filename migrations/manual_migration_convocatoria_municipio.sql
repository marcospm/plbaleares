-- Migración manual para cambiar relación Convocatoria-Municipio de ManyToOne a ManyToMany
-- Ejecutar este script SQL directamente en la base de datos MySQL

-- 1. Crear la nueva tabla para la relación ManyToMany
CREATE TABLE IF NOT EXISTS convocatoria_municipio (
    convocatoria_id INT NOT NULL,
    municipio_id INT NOT NULL,
    INDEX IDX_CONVOCATORIA_MUNICIPIO_CONVOCATORIA (convocatoria_id),
    INDEX IDX_CONVOCATORIA_MUNICIPIO_MUNICIPIO (municipio_id),
    PRIMARY KEY(convocatoria_id, municipio_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

-- 2. Migrar los datos existentes de municipio_id a la nueva tabla
INSERT IGNORE INTO convocatoria_municipio (convocatoria_id, municipio_id) 
SELECT id, municipio_id 
FROM convocatoria 
WHERE municipio_id IS NOT NULL;

-- 3. Añadir las claves foráneas
ALTER TABLE convocatoria_municipio 
ADD CONSTRAINT FK_CONVOCATORIA_MUNICIPIO_CONVOCATORIA 
FOREIGN KEY (convocatoria_id) REFERENCES convocatoria (id) ON DELETE CASCADE;

ALTER TABLE convocatoria_municipio 
ADD CONSTRAINT FK_CONVOCATORIA_MUNICIPIO_MUNICIPIO 
FOREIGN KEY (municipio_id) REFERENCES municipio (id) ON DELETE CASCADE;

-- 4. Eliminar la columna municipio_id y su clave foránea
-- Primero eliminar la clave foránea
ALTER TABLE convocatoria DROP FOREIGN KEY IF EXISTS FK_6D77302158BC1BE0;

-- Eliminar el índice
DROP INDEX IF EXISTS IDX_6D77302158BC1BE0 ON convocatoria;

-- Eliminar la columna municipio_id
ALTER TABLE convocatoria DROP COLUMN IF EXISTS municipio_id;
