-- Ejecutar una sola vez en cada base de datos SIGATI existente.
-- Añade el tipo de movimiento para pasar un TBA sin asignaciones previas a Disponible.
INSERT INTO tipo_movimiento (nombre_tipo)
SELECT 'Regularización a Disponible'
WHERE NOT EXISTS (
    SELECT 1 FROM tipo_movimiento
    WHERE nombre_tipo = 'Regularización a Disponible'
);
