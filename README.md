# SIGATI

## Sistema de Gestión de Activos Tecnológicos de Infraestructura

SIGATI es un proyecto de titulación orientado a mejorar la gestión y trazabilidad de notebooks dentro de un entorno organizacional.

El sistema busca centralizar la información de los equipos, colaboradores, asignaciones, estados y movimientos, permitiendo conocer quién tuvo un notebook, dónde estuvo asignado, qué cambios tuvo y qué usuario TI realizó cada modificación.

---

## Objetivo del proyecto

Diseñar e implementar una solución web que permita gestionar activos tecnológicos de forma estructurada, segura y trazable.

Entre sus principales objetivos se encuentran:

- Registrar notebooks y sus características técnicas.
- Gestionar colaboradores y tipos de colaborador.
- Registrar asignaciones y reasignaciones.
- Mantener una Hoja de Vida Digital de cada notebook.
- Gestionar estados como Ingresado, En preparación, Asignado, TBA, Desactivado y Decomisado.
- Registrar al usuario TI responsable de cada cambio.
- Consultar información histórica de los equipos.
- Preservar la trazabilidad mediante la anulación lógica de movimientos.
- Generar indicadores y análisis sobre el comportamiento del inventario.

---

## Tecnologías propuestas

### Aplicación Web

- HTML5
- CSS3
- Bootstrap
- JavaScript
- PHP

### Base de Datos

- MySQL 8.0
- MySQL Workbench

### Analítica / Big Data

- Apache Spark como componente analítico complementario y alternativa de escalabilidad futura para el procesamiento de información histórica.

---

## Arquitectura

SIGATI utiliza una arquitectura web de tres capas:

1. **Capa de presentación**
   - Interfaz web utilizada por los usuarios.

2. **Capa de negocio**
   - Implementada mediante PHP.
   - Gestionará autenticación, permisos, reglas de negocio, asignaciones, movimientos y anulaciones.

3. **Capa de datos**
   - Implementada en MySQL.
   - Mantiene la estructura relacional, integridad referencial e historial de los activos.

Apache Spark se propone como componente analítico complementario y como alternativa de escalabilidad futura para el procesamiento de información histórica, mientras MySQL mantiene la operación transaccional principal del sistema.

---

## Base de Datos

La base de datos utiliza un modelo relacional normalizado.

Actualmente está compuesta por 11 tablas:

- `area`
- `asignacion`
- `colaborador`
- `estado_notebook`
- `motivo_movimiento`
- `movimiento`
- `notebook`
- `rol`
- `tipo_colaborador`
- `tipo_movimiento`
- `usuario_sistema`

Se aplican:

- Claves primarias.
- Claves foráneas.
- Restricciones UNIQUE.
- Restricciones NOT NULL.
- Restricciones CHECK.
- Integridad referencial.
- ON DELETE RESTRICT.
- Índices para consultas frecuentes.

La estructura permite conservar las relaciones entre notebooks, colaboradores, asignaciones, movimientos y usuarios responsables, evitando la eliminación de información histórica necesaria para la trazabilidad del sistema.

---

## Diagrama EER

El siguiente diagrama representa el modelo relacional actual de SIGATI:

![Diagrama EER de SIGATI](SIGATI_Diagrama_EER.png)

---

## Hoja de Vida Digital

Cada notebook mantiene un historial de movimientos que permite registrar:

- Tipo de movimiento.
- Estado anterior.
- Estado nuevo.
- Asignación anterior.
- Asignación nueva.
- Motivo.
- Fecha del movimiento.
- Observaciones.
- Usuario TI responsable.

Esto permite reconstruir el historial de cada activo y representar situaciones como:

> Reasignado de Juan Pérez a María Soto.

La Hoja de Vida Digital permite conservar la trazabilidad del notebook durante su permanencia dentro de la organización.

### Anulación lógica de movimientos

Para preservar la trazabilidad histórica de los activos, los movimientos registrados en SIGATI no se eliminan físicamente de la base de datos.

Cuando un movimiento requiera ser invalidado, el sistema realizará una anulación lógica registrando:

- Estado de anulación.
- Fecha de anulación.
- Usuario responsable de la anulación.
- Motivo de la anulación.

La tabla `movimiento` incorpora los campos:

- `anulado`
- `fecha_anulacion`
- `id_usuario_anulacion`
- `motivo_anulacion`

De esta forma, el movimiento original permanece almacenado como parte de la Hoja de Vida Digital del notebook, permitiendo mantener la integridad, trazabilidad y auditoría de las operaciones realizadas.

---

## Formularios CRUD

Como parte del prototipo funcional de SIGATI se consideran tres módulos principales para la gestión de información.

### 1. Gestión de Notebooks

Permite registrar, consultar y actualizar los datos de los notebooks, incluyendo:

- Número de serie.
- Marca.
- Modelo.
- Procesador.
- Memoria RAM.
- Capacidad de disco.
- Nombre del equipo.
- Estado.

Las operaciones que puedan afectar información relacionada deberán respetar las restricciones de integridad definidas en la base de datos.

### 2. Gestión de Colaboradores

Permite registrar, consultar y actualizar la información de los colaboradores, incluyendo:

- Nombre completo.
- Usuario de dominio.
- Correo corporativo.
- Tipo de colaborador.

La gestión de estos registros deberá respetar las relaciones existentes con las asignaciones para evitar la pérdida de información histórica.

### 3. Gestión de Asignaciones y Movimientos

Permite registrar, consultar y actualizar las operaciones relacionadas con la asignación, devolución y reasignación de notebooks.

Este módulo constituye el componente transaccional encargado de alimentar la Hoja de Vida Digital de los activos.

Debido a que los movimientos forman parte de la trazabilidad histórica de cada notebook, no se contempla su eliminación física. En su lugar, se utiliza una anulación lógica que conserva el movimiento original y registra:

- Fecha de anulación.
- Usuario responsable.
- Motivo de la anulación.

Esta decisión permite cumplir la funcionalidad requerida sin comprometer la integridad, trazabilidad y auditoría de la información histórica.

---

## Ciberseguridad

SIGATI considera las siguientes medidas:

- Autenticación de usuarios.
- Control de acceso mediante roles.
- Rol Administrador TI.
- Rol de Consulta.
- Almacenamiento de contraseñas mediante hash.
- Principio de mínimo privilegio.
- Integridad referencial en la base de datos.
- Auditoría de los movimientos realizados.
- Registro del usuario TI responsable de las operaciones.
- Anulación lógica de movimientos para preservar la trazabilidad.
- Registro del usuario responsable de cada anulación.
- Registro de fecha y motivo de las anulaciones.
- Uso de HTTPS en la aplicación web.

Estas medidas buscan proteger la información, restringir las operaciones según el perfil del usuario y mantener evidencia de las acciones realizadas dentro del sistema.

---

## Analítica y Big Data

El volumen inicial considerado para SIGATI corresponde aproximadamente a 682 notebooks más su historial de asignaciones y movimientos, por lo que MySQL es suficiente como base de datos operacional para la etapa actual del proyecto.

Sin embargo, la información histórica recopilada permitirá generar indicadores y análisis como:

- Modelos con mayor cantidad de fallas.
- Motivos más frecuentes de decomiso.
- Cantidad de reasignaciones por período.
- Vida útil promedio de los notebooks.
- Equipos candidatos a renovación.
- Frecuencia de movimientos por período.
- Patrones de asignación.
- Tendencias del parque tecnológico.
- Proyección de demanda futura de recursos tecnológicos.

Apache Spark se propone como componente analítico complementario y alternativa de escalabilidad futura ante un crecimiento significativo del volumen de información.

De esta forma, MySQL mantiene la operación transaccional principal de SIGATI, mientras que los datos históricos pueden ser explotados posteriormente con herramientas orientadas al procesamiento y análisis de mayores volúmenes de información.

---

## Estado actual del proyecto

Actualmente se encuentra desarrollado:

- Diseño del modelo relacional.
- Normalización de la base de datos.
- Creación de las 11 tablas.
- Claves primarias y foráneas.
- Integridad referencial.
- Restricciones e índices.
- Diagrama EER.
- Diccionario de datos.
- Reglas de negocio.
- Arquitectura propuesta.
- Árbol funcional.
- Prototipo visual de la aplicación.
- Pruebas con datos ficticios.
- Validación de asignaciones, TBA y reasignaciones.
- Validación de restricciones de integridad.
- Implementación de anulación lógica de movimientos.
- Registro de usuario, fecha y motivo de anulación.
- Validación de trazabilidad histórica de movimientos.
- Definición de controles básicos de seguridad y auditoría.
- Definición de indicadores para explotación analítica de los datos históricos.

---

## Archivos disponibles

### `SIGATI_BD_Final.sql`

Script SQL consolidado que permite crear desde cero la estructura de la base de datos SIGATI, incluyendo tablas, relaciones, restricciones, datos de prueba y mecanismos de trazabilidad y anulación lógica.

### `SIGATI_Diagrama_EER.png`

Diagrama Entidad-Relación generado desde MySQL Workbench y actualizado de acuerdo con la estructura actual de la base de datos.

---

## Proyección del proyecto

Como evolución futura de SIGATI se considera ampliar la gestión hacia otros activos tecnológicos de infraestructura.

Entre las posibles extensiones se encuentra la incorporación de máquinas virtuales de contingencia como recursos tecnológicos asignables, manteniendo un historial similar al utilizado para los notebooks.

Esta funcionalidad se considera una ampliación futura para evitar aumentar innecesariamente el alcance de la implementación actual.

---

## Proyecto académico

Este repositorio corresponde a un proyecto de titulación académico y utiliza datos ficticios para las pruebas y demostraciones.
