-- ============================================================
-- PROYECTO SIGATI
-- Sistema de Gestión de Activos Tecnológicos
-- Base de datos: MySQL 8.0
-- VERSIÓN FINAL DE PRUEBA
-- ============================================================

-- IMPORTANTE:
-- Esta base se utiliza para validar que el script definitivo
-- puede construir SIGATI completamente desde cero.
-- NO modifica la base original llamada "sigati".

DROP DATABASE IF EXISTS sigati_prueba_final;

CREATE DATABASE sigati_prueba_final
CHARACTER SET utf8mb4
COLLATE utf8mb4_spanish_ci;

USE sigati_prueba_final;


-- ============================================================
-- 1. TABLAS DE CATÁLOGO
-- ============================================================

CREATE TABLE area (
    id_area INT AUTO_INCREMENT PRIMARY KEY,
    nombre_area VARCHAR(100) NOT NULL UNIQUE
);


CREATE TABLE tipo_colaborador (
    id_tipo_colaborador INT AUTO_INCREMENT PRIMARY KEY,
    nombre_tipo VARCHAR(50) NOT NULL UNIQUE
);


CREATE TABLE estado_notebook (
    id_estado INT AUTO_INCREMENT PRIMARY KEY,
    nombre_estado VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(200) NULL
);


CREATE TABLE rol (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(200) NULL
);


CREATE TABLE tipo_movimiento (
    id_tipo_movimiento INT AUTO_INCREMENT PRIMARY KEY,
    nombre_tipo VARCHAR(60) NOT NULL UNIQUE
);


CREATE TABLE motivo_movimiento (
    id_motivo INT AUTO_INCREMENT PRIMARY KEY,
    nombre_motivo VARCHAR(100) NOT NULL UNIQUE
);


-- ============================================================
-- 2. TABLA COLABORADOR
-- ============================================================

CREATE TABLE colaborador (
    id_colaborador INT AUTO_INCREMENT PRIMARY KEY,
    nombre_completo VARCHAR(150) NOT NULL,
    usuario_dominio VARCHAR(100) NOT NULL UNIQUE,
    correo_corporativo VARCHAR(150) NOT NULL UNIQUE,
    id_tipo_colaborador INT NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_colaborador_tipo
        FOREIGN KEY (id_tipo_colaborador)
        REFERENCES tipo_colaborador(id_tipo_colaborador)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);


-- ============================================================
-- 3. TABLA NOTEBOOK
-- ============================================================

CREATE TABLE notebook (
    id_notebook INT AUTO_INCREMENT PRIMARY KEY,
    numero_serie VARCHAR(100) NOT NULL UNIQUE,
    marca VARCHAR(50) NOT NULL,
    modelo VARCHAR(100) NOT NULL,
    procesador VARCHAR(100) NOT NULL,
    ram_gb INT NOT NULL,
    capacidad_disco_gb INT NOT NULL,
    nombre_equipo_actual VARCHAR(100) NULL,
    id_estado INT NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notebook_estado
        FOREIGN KEY (id_estado)
        REFERENCES estado_notebook(id_estado)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_notebook_ram
        CHECK (ram_gb > 0),

    CONSTRAINT chk_notebook_disco
        CHECK (capacidad_disco_gb > 0)
);


-- ============================================================
-- 4. TABLA USUARIO DEL SISTEMA
-- ============================================================

CREATE TABLE usuario_sistema (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre_completo VARCHAR(150) NOT NULL,
    nombre_usuario VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    id_rol INT NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_usuario_rol
        FOREIGN KEY (id_rol)
        REFERENCES rol(id_rol)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);


-- ============================================================
-- 5. TABLA ASIGNACIÓN
-- ============================================================

CREATE TABLE asignacion (
    id_asignacion INT AUTO_INCREMENT PRIMARY KEY,
    id_notebook INT NOT NULL,
    id_colaborador INT NOT NULL,
    id_area INT NOT NULL,
    id_usuario_sistema INT NOT NULL,
    nombre_equipo VARCHAR(100) NOT NULL,
    piso INT NOT NULL,
    asiento VARCHAR(30) NOT NULL,
    fecha_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_fin DATETIME NULL,

    CONSTRAINT fk_asignacion_notebook
        FOREIGN KEY (id_notebook)
        REFERENCES notebook(id_notebook)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_asignacion_colaborador
        FOREIGN KEY (id_colaborador)
        REFERENCES colaborador(id_colaborador)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_asignacion_area
        FOREIGN KEY (id_area)
        REFERENCES area(id_area)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_asignacion_usuario
        FOREIGN KEY (id_usuario_sistema)
        REFERENCES usuario_sistema(id_usuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_asignacion_piso
        CHECK (piso BETWEEN 1 AND 4),

    CONSTRAINT chk_asignacion_fechas
        CHECK (
            fecha_fin IS NULL
            OR fecha_fin >= fecha_inicio
        )
);


-- ============================================================
-- 6. TABLA MOVIMIENTO
-- Hoja de Vida Digital del notebook
-- ============================================================

CREATE TABLE movimiento (
    id_movimiento INT AUTO_INCREMENT PRIMARY KEY,

    id_notebook INT NOT NULL,
    id_tipo_movimiento INT NOT NULL,
    id_motivo INT NULL,
    id_usuario_sistema INT NOT NULL,

    id_asignacion_origen INT NULL,
    id_asignacion_destino INT NULL,

    id_estado_anterior INT NULL,
    id_estado_nuevo INT NOT NULL,

    fecha_movimiento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observacion VARCHAR(500) NULL,

    CONSTRAINT fk_movimiento_notebook
        FOREIGN KEY (id_notebook)
        REFERENCES notebook(id_notebook)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_movimiento_tipo
        FOREIGN KEY (id_tipo_movimiento)
        REFERENCES tipo_movimiento(id_tipo_movimiento)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_movimiento_motivo
        FOREIGN KEY (id_motivo)
        REFERENCES motivo_movimiento(id_motivo)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_movimiento_usuario
        FOREIGN KEY (id_usuario_sistema)
        REFERENCES usuario_sistema(id_usuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_movimiento_asignacion_origen
        FOREIGN KEY (id_asignacion_origen)
        REFERENCES asignacion(id_asignacion)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_movimiento_asignacion_destino
        FOREIGN KEY (id_asignacion_destino)
        REFERENCES asignacion(id_asignacion)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_movimiento_estado_anterior
        FOREIGN KEY (id_estado_anterior)
        REFERENCES estado_notebook(id_estado)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_movimiento_estado_nuevo
        FOREIGN KEY (id_estado_nuevo)
        REFERENCES estado_notebook(id_estado)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);


-- ============================================================
-- 7. DATOS INICIALES - ESTADOS DEL NOTEBOOK
-- ============================================================

INSERT INTO estado_notebook (nombre_estado, descripcion)
VALUES
(
    'Ingresado',
    'Notebook registrado en SIGATI que aun puede permanecer en stock.'
),
(
    'En preparación',
    'Notebook que esta siendo configurado por Soporte TI.'
),
(
    'Asignado',
    'Notebook actualmente entregado a un colaborador.'
),
(
    'TBA',
    'To Be Assigned: notebook pendiente de asignacion o reasignacion.'
),
(
    'Desactivado',
    'Notebook retirado del dominio y sin nombre activo en Active Directory, pero reutilizable.'
),
(
    'Decomisado',
    'Notebook retirado definitivamente de circulacion, conservando su historial.'
);


-- ============================================================
-- 8. DATOS INICIALES - TIPOS DE COLABORADOR
-- ============================================================

INSERT INTO tipo_colaborador (nombre_tipo)
VALUES
('Practicante'),
('Training'),
('Externo'),
('Contratado directo');


-- ============================================================
-- 9. DATOS INICIALES - ROLES
-- ============================================================

INSERT INTO rol (nombre_rol, descripcion)
VALUES
(
    'Administrador TI',
    'Personal de Soporte TI con permisos para registrar y modificar informacion.'
),
(
    'Consulta',
    'Personal autorizado de Tecnologia/Infraestructura con acceso de solo lectura.'
);


-- ============================================================
-- 10. DATOS INICIALES - TIPOS DE MOVIMIENTO
-- ============================================================

INSERT INTO tipo_movimiento (nombre_tipo)
VALUES
('Ingreso'),
('Preparación'),
('Asignación'),
('Cambio de notebook'),
('Reasignación'),
('Cambio a TBA'),
('Desactivación'),
('Decomiso');


-- ============================================================
-- 11. DATOS INICIALES - MOTIVOS DE MOVIMIENTO
-- ============================================================

INSERT INTO motivo_movimiento (nombre_motivo)
VALUES
('Falla'),
('Renovación'),
('Antigüedad'),
('Cambio de funciones'),
('Desvinculación'),
('Fin de práctica'),
('Daño'),
('No cumple estándar'),
('Otro');


-- ============================================================
-- 12. ÍNDICES ADICIONALES
-- ============================================================

CREATE INDEX idx_notebook_estado
ON notebook(id_estado);

CREATE INDEX idx_asignacion_notebook
ON asignacion(id_notebook);

CREATE INDEX idx_asignacion_colaborador
ON asignacion(id_colaborador);

CREATE INDEX idx_movimiento_notebook
ON movimiento(id_notebook);

CREATE INDEX idx_movimiento_fecha
ON movimiento(fecha_movimiento);


-- ============================================================
-- 13. VERIFICACIONES
-- ============================================================

-- Mostrar todas las tablas creadas
SHOW TABLES;

-- Verificar estados
SELECT * FROM estado_notebook;

-- Verificar tipos de colaborador
SELECT * FROM tipo_colaborador;

-- Verificar roles
SELECT * FROM rol;

-- Verificar tipos de movimiento
SELECT * FROM tipo_movimiento;

-- Verificar motivos
SELECT * FROM motivo_movimiento;