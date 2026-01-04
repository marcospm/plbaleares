-- SQL Manual para crear las tablas de grupos
-- Ejecutar este SQL manualmente si la migración falla

-- Crear tabla grupo
CREATE TABLE grupo (
    id INT AUTO_INCREMENT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    fecha_creacion DATETIME NOT NULL,
    fecha_actualizacion DATETIME NOT NULL,
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

-- Crear tabla de unión grupo_user (ManyToMany)
CREATE TABLE grupo_user (
    grupo_id INT NOT NULL,
    user_id INT NOT NULL,
    INDEX IDX_6DC044C59C833003 (grupo_id),
    INDEX IDX_6DC044C5A76ED395 (user_id),
    PRIMARY KEY(grupo_id, user_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

-- Añadir foreign keys
ALTER TABLE grupo_user 
    ADD CONSTRAINT FK_6DC044C59C833003 
    FOREIGN KEY (grupo_id) REFERENCES grupo (id) ON DELETE CASCADE;

ALTER TABLE grupo_user 
    ADD CONSTRAINT FK_6DC044C5A76ED395 
    FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE;

