-- Script para añadir la columna nombre a la tabla user
-- Ejecutar este script en la base de datos

ALTER TABLE user ADD nombre VARCHAR(255) DEFAULT NULL;

