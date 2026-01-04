-- SQL Manual para añadir relación opcional con Grupo en RecursoEspecifico
-- Ejecutar este SQL si la migración falla

-- Añadir columna grupo_id
ALTER TABLE recurso_especifico 
ADD COLUMN grupo_id INT DEFAULT NULL;

-- Añadir índice
CREATE INDEX IDX_RECURSO_ESPECIFICO_GRUPO ON recurso_especifico (grupo_id);

-- Añadir foreign key
ALTER TABLE recurso_especifico 
ADD CONSTRAINT FK_RECURSO_ESPECIFICO_GRUPO 
FOREIGN KEY (grupo_id) REFERENCES grupo (id) 
ON DELETE SET NULL;

