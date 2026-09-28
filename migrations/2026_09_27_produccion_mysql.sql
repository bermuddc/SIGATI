-- SIGATI: registro de la migración aplicada a la base de producción.
-- Documento histórico: no volver a ejecutar sobre una base ya migrada.
-- Antes de adaptar esta migración a otra base, guardar y comprobar una copia.
-- Ejecutar una sentencia a la vez, verificar su resultado y detenerse ante
-- cualquier error. Las operaciones ALTER TABLE no se revierten con ROLLBACK.
-- No contiene DROP TABLE, TRUNCATE, DELETE ni datos de usuarios.

-- Fase 1: las 12 tablas actuales son MyISAM, que no admite transacciones.
-- InfinityFree informó en 2025 que su servicio admite InnoDB; verificar
-- también el resultado real de cada conversión en phpMyAdmin.
ALTER TABLE `area` ENGINE=InnoDB;
ALTER TABLE `estado_notebook` ENGINE=InnoDB;
ALTER TABLE `motivo_movimiento` ENGINE=InnoDB;
ALTER TABLE `rol` ENGINE=InnoDB;
ALTER TABLE `tipo_colaborador` ENGINE=InnoDB;
ALTER TABLE `tipo_movimiento` ENGINE=InnoDB;
ALTER TABLE `colaborador` ENGINE=InnoDB;
ALTER TABLE `notebook` ENGINE=InnoDB;
ALTER TABLE `usuario_sistema` ENGINE=InnoDB;
ALTER TABLE `asignacion` ENGINE=InnoDB;
ALTER TABLE `movimiento` ENGINE=InnoDB;
ALTER TABLE `recuperacion_password` ENGINE=InnoDB;

-- Fase 2: solo las cinco columnas que faltan frente al esquema de UAT.
-- El valor predeterminado activo=1 conserva habilitados los colaboradores
-- existentes. El área, RUT y cargo quedan NULL para completar sus fichas.
ALTER TABLE `colaborador`
    ADD COLUMN `rut` VARCHAR(12) NULL AFTER `nombre_completo`,
    ADD COLUMN `cargo` VARCHAR(100) NULL AFTER `rut`,
    ADD COLUMN `id_area` INT NULL AFTER `cargo`,
    ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1 AFTER `fecha_registro`,
    ADD UNIQUE KEY `rut` (`rut`),
    ADD KEY `idx_colaborador_area` (`id_area`);

ALTER TABLE `notebook`
    ADD COLUMN `tipo_equipo` ENUM('Notebook', 'Escritorio') NOT NULL
        DEFAULT 'Notebook' AFTER `id_notebook`;

-- Fase 3: verificaciones de solo lectura. Deben devolverse 12 filas InnoDB
-- en la primera consulta y las cinco columnas nuevas en la segunda.
SELECT TABLE_NAME, ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME;

SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND ((TABLE_NAME = 'colaborador'
         AND COLUMN_NAME IN ('rut', 'cargo', 'id_area', 'activo'))
    OR (TABLE_NAME = 'notebook' AND COLUMN_NAME = 'tipo_equipo'))
ORDER BY TABLE_NAME, ORDINAL_POSITION;
