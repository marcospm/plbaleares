-- SQL Manual para añadir relación opcional con Grupo en ExamenSemanal
-- Ejecutar este SQL manualmente en la base de datos

-- Añadir columna grupo_id a la tabla examen_semanal
ALTER TABLE examen_semanal 
ADD COLUMN grupo_id INT DEFAULT NULL;

-- Añadir índice para mejorar el rendimiento de las consultas
CREATE INDEX IDX_examen_semanal_grupo ON examen_semanal(grupo_id);

-- Añadir foreign key constraint
ALTER TABLE examen_semanal 
ADD CONSTRAINT FK_examen_semanal_grupo 
FOREIGN KEY (grupo_id) REFERENCES grupo(id) 
ON DELETE SET NULL;

