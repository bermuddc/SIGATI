# SIGATI

## Sistema Web de Gestión y Trazabilidad de Activos Tecnológicos

SIGATI es un proyecto de titulación orientado a mejorar la gestión y
trazabilidad de notebooks dentro de una institución financiera. La
solución centraliza la información de los equipos, colaboradores,
asignaciones, estados y movimientos, permitiendo consultar el ciclo de
vida de cada activo y mantener evidencia del usuario TI responsable de
las operaciones.

> **Proyecto académico:** los datos utilizados en pruebas y
> demostraciones son ficticios.

------------------------------------------------------------------------

## Objetivo del proyecto

Diseñar e implementar una solución web que permita gestionar activos
tecnológicos de forma estructurada, segura y trazable.

SIGATI permite:

-   Registrar y actualizar notebooks y sus características técnicas.
-   Gestionar colaboradores y tipos de colaborador.
-   Preparar notebooks antes de su asignación.
-   Registrar asignaciones y reasignaciones.
-   Gestionar los estados Ingresado, En preparación, Asignado, TBA,
    Desactivado y Decomisado.
-   Mantener una Hoja de Vida Digital por notebook.
-   Registrar movimientos y al usuario TI responsable.
-   Consultar el historial de asignaciones y movimientos.
-   Aplicar control de acceso mediante perfiles.
-   Exponer operaciones de notebooks mediante una API REST.
-   Procesar información analítica mediante Apache Spark/PySpark.

------------------------------------------------------------------------

## Tecnologías utilizadas

### Aplicación web

-   PHP 8.2
-   HTML5
-   CSS3 personalizado y responsivo
-   JavaScript
-   Fetch API
-   Apache HTTP Server mediante XAMPP

La interfaz se desarrolló con CSS propio, sin Bootstrap ni plantillas
completas de terceros.

### Base de datos

-   MySQL 8.0
-   MySQL Workbench
-   PDO para acceso a datos desde PHP

### Seguridad y correo

-   Sesiones PHP
-   `password_hash()` y `password_verify()`
-   Consultas preparadas con PDO
-   Tokens CSRF
-   PHPMailer
-   SMTP con STARTTLS/TLS para recuperación de contraseña

### Analítica

-   Python
-   Apache Spark / PySpark 4.2
-   Archivos CSV como fuente y salida del procesamiento analítico

### Control de versiones

-   Git
-   GitHub
-   Composer para gestión de dependencias PHP

------------------------------------------------------------------------

## Arquitectura

SIGATI utiliza una arquitectura web organizada en tres capas
principales:

1.  **Presentación:** páginas PHP, HTML, CSS y JavaScript utilizadas
    desde el navegador.
2.  **Lógica de aplicación:** autenticación, autorización por roles,
    reglas de negocio, validaciones, ciclo de vida de notebooks,
    asignaciones, movimientos, API REST y recuperación de contraseña.
3.  **Datos:** MySQL como base de datos relacional operacional.

Apache Spark/PySpark se integra como componente analítico
complementario. MySQL conserva la operación transaccional del sistema,
mientras Spark permite procesar datos exportados para generar
indicadores y resultados analíticos.

------------------------------------------------------------------------

## Base de datos

El modelo relacional se encuentra normalizado hasta Tercera Forma Normal
(3FN) y actualmente está compuesto por **12 tablas**:

-   `area`
-   `asignacion`
-   `colaborador`
-   `estado_notebook`
-   `motivo_movimiento`
-   `movimiento`
-   `notebook`
-   `recuperacion_password`
-   `rol`
-   `tipo_colaborador`
-   `tipo_movimiento`
-   `usuario_sistema`

El modelo utiliza claves primarias y foráneas, restricciones `UNIQUE`,
`NOT NULL` y `CHECK`, integridad referencial e índices para consultas
frecuentes.

La tabla `recuperacion_password` permite administrar tokens de
recuperación asociados a usuarios del sistema. Los tokens se almacenan
mediante hash, tienen fecha de expiración y control de uso.

El script `SIGATI_BD_Final.sql` reconstruye una base independiente
denominada `sigati_prueba_final`, permitiendo validar la estructura
completa sin modificar la base operacional local `sigati`.

------------------------------------------------------------------------

## Diagrama EER

El modelo EER actualizado contempla las 12 tablas y sus relaciones:

![Diagrama EER de SIGATI](SIGATI_Diagrama_EER.png)

------------------------------------------------------------------------

## Ciclo de vida del notebook

SIGATI gestiona los siguientes estados:

1.  **Ingresado:** notebook registrado y pendiente de preparación.
2.  **En preparación:** equipo en proceso de configuración por Soporte
    TI.
3.  **Asignado:** equipo entregado a un colaborador.
4.  **TBA:** equipo pendiente de asignación o reasignación.
5.  **Desactivado:** equipo retirado del dominio; su nombre actual se
    elimina, pero el historial se conserva.
6.  **Decomisado:** equipo dado de baja definitivamente, conservando su
    trazabilidad histórica.

Las transiciones se realizan mediante operaciones específicas del
sistema y no mediante la edición manual del estado.

------------------------------------------------------------------------

## Hoja de Vida Digital

Cada notebook dispone de una Hoja de Vida Digital construida a partir de
sus movimientos. El historial permite consultar, entre otros datos:

-   Tipo de movimiento.
-   Estado anterior y nuevo.
-   Asignación de origen y destino.
-   Motivo.
-   Fecha.
-   Observación.
-   Usuario TI responsable.

La tabla `movimiento` también contiene campos para soportar anulación
lógica (`anulado`, `fecha_anulacion`, `id_usuario_anulacion` y
`motivo_anulacion`), preservando el registro histórico en lugar de
depender de una eliminación física.

------------------------------------------------------------------------

## Módulos funcionales

### Gestión de notebooks

Permite registrar, consultar y actualizar notebooks. Además, implementa
operaciones de ciclo de vida como preparación, cambio a TBA,
reasignación, desactivación y decomiso, aplicando validaciones y
transacciones según corresponda.

### Gestión de colaboradores

Permite registrar, consultar y actualizar colaboradores, incluyendo
nombre, usuario de dominio, correo corporativo y tipo de colaborador.

### Gestión de asignaciones y movimientos

Permite crear asignaciones, consultar su historial, registrar
movimientos y visualizar la Hoja de Vida Digital de cada notebook.

### Gestión de cuenta

Los usuarios autenticados pueden consultar y actualizar sus datos de
perfil. El módulo también permite cambiar la contraseña validando
previamente la contraseña actual.

------------------------------------------------------------------------

## Autenticación y perfiles

SIGATI implementa dos perfiles:

-   **Administrador TI:** puede ejecutar las operaciones administrativas
    del sistema.
-   **Consulta:** dispone de acceso de solo lectura a la información
    autorizada.

La autenticación utiliza sesiones PHP y contraseñas almacenadas mediante
hash. Después de un inicio de sesión correcto se regenera el
identificador de sesión para reducir el riesgo de fijación de sesión.

------------------------------------------------------------------------

## Recuperación de contraseña por correo

El sistema implementa recuperación real de credenciales por correo
electrónico mediante PHPMailer y SMTP.

El flujo incluye:

1.  Solicitud mediante correo registrado.
2.  Generación de un token criptográficamente aleatorio.
3.  Almacenamiento únicamente del hash del token.
4.  Vigencia limitada del enlace.
5.  Uso único del token.
6.  Actualización de la contraseña mediante `password_hash()`.
7.  Invalidación de tokens pendientes después del restablecimiento.

La respuesta de solicitud es genérica para evitar revelar si un correo
pertenece o no a un usuario registrado.

Las credenciales SMTP se mantienen en configuración local privada y no
forman parte del repositorio.

------------------------------------------------------------------------

## API REST

SIGATI dispone de una API REST para notebooks:

-   `GET`: consulta de notebooks.
-   `GET` por identificador: consulta de un notebook específico.
-   `POST`: creación de notebooks para Administrador TI.
-   `PUT`: actualización de notebooks para Administrador TI.

La API utiliza JSON, sesiones, control de roles, consultas preparadas y
validación de datos.

Las operaciones `POST` y `PUT` están protegidas mediante un token CSRF
enviado en el encabezado `X-CSRF-Token`. Una solicitud de modificación
sin un token válido es rechazada con HTTP 403.

No se implementa `DELETE` físico para notebooks debido a los requisitos
de trazabilidad del sistema.

------------------------------------------------------------------------

## Ciberseguridad implementada

Entre los controles aplicados se encuentran:

-   Autenticación mediante sesiones PHP.
-   Autorización por roles.
-   Principio de mínimo privilegio para el usuario de aplicación de
    MySQL.
-   Hash seguro de contraseñas.
-   Consultas preparadas con PDO contra inyección SQL.
-   Escape de salida para reducir riesgos de XSS.
-   Protección CSRF en formularios y operaciones de modificación de la
    API.
-   Regeneración del identificador de sesión después de autenticación y
    cambio de contraseña.
-   Cookies de sesión con `HttpOnly` y `SameSite=Lax`.
-   `Secure` habilitable cuando la aplicación opera mediante HTTPS.
-   Recuperación de contraseña mediante token aleatorio, hash,
    expiración y uso único.
-   Integridad referencial mediante claves foráneas.
-   Conservación del historial de movimientos.

Los archivos locales que contienen credenciales se excluyen del control
de versiones mediante `.gitignore`.

------------------------------------------------------------------------

## Analítica con Apache Spark

SIGATI incorpora un módulo analítico implementado con Apache
Spark/PySpark.

El proyecto contiene:

-   `analytics/analisis_sigati.py`
-   `analytics/notebooks_sigati.csv`
-   archivos de resultados analíticos en `analytics/resultados/`
-   `public/analitica.php` para visualizar los resultados desde la
    aplicación web

Actualmente el volumen de datos de prueba no constituye Big Data por sí
mismo. Spark se utiliza como demostración funcional de una arquitectura
preparada para procesamiento analítico y escalabilidad futura.

Entre los indicadores generados se incluyen distribuciones de notebooks
por estado, marca, RAM y capacidad de disco.

------------------------------------------------------------------------

## Estructura principal del repositorio

``` text
SIGATI/
├── analytics/
│   ├── analisis_sigati.py
│   ├── notebooks_sigati.csv
│   └── resultados/
├── api/
│   └── notebooks.php
├── config/
│   ├── database.php          # Local, excluido de Git
│   └── mail_config.php       # Local, excluido de Git
├── public/
│   ├── login.php
│   ├── dashboard.php
│   ├── notebooks.php
│   ├── colaboradores.php
│   ├── asignaciones.php
│   ├── movimientos.php
│   ├── hoja_vida.php
│   ├── analitica.php
│   ├── api_notebook_demo.php
│   ├── recuperar_password.php
│   ├── restablecer_password.php
│   └── mi_cuenta.php
├── src/
│   ├── auth.php
│   └── mailer.php
├── .gitignore
├── composer.json
├── composer.lock
├── SIGATI_BD_Final.sql
├── SIGATI_Diagrama_EER.png
└── README.md
```

La carpeta `vendor/` y los archivos privados de configuración no se
publican en GitHub.

------------------------------------------------------------------------

## Instalación local

### Requisitos

-   Apache
-   PHP 8.2 o compatible
-   MySQL 8.0
-   Composer
-   Extensión ZIP de PHP
-   Python y PySpark para el componente analítico

### Preparación general

1.  Ubicar el proyecto dentro del directorio web local, por ejemplo
    `C:\xampp\htdocs\sigati`.
2.  Instalar las dependencias PHP con `composer install`.
3.  Crear la configuración local de conexión a MySQL en
    `config/database.php`.
4.  Crear la configuración SMTP local en `config/mail_config.php` si se
    utilizará recuperación por correo.
5.  Crear/importar la base de datos operacional requerida por la
    aplicación.
6.  Acceder al sistema desde el servidor web local.

Los archivos de configuración deben contener credenciales propias del
entorno y no deben subirse al repositorio.

------------------------------------------------------------------------

## Estado actual

Actualmente se encuentran implementados y probados:

-   Modelo relacional normalizado.
-   12 tablas con integridad referencial.
-   Diagrama EER actualizado.
-   Autenticación y cierre de sesión.
-   Perfiles Administrador TI y Consulta.
-   Gestión de cuenta de usuario.
-   Recuperación de contraseña mediante correo.
-   Gestión de notebooks.
-   Gestión de colaboradores.
-   Gestión de asignaciones.
-   Ciclo de vida del notebook.
-   Movimientos e historial.
-   Hoja de Vida Digital.
-   API REST GET/POST/PUT.
-   Consumo de API mediante JavaScript Fetch.
-   Protección CSRF en formularios y API.
-   Consultas preparadas con PDO.
-   Endurecimiento básico de sesiones.
-   Procesamiento analítico con Apache Spark/PySpark.
-   Panel web de analítica.
-   CSS personalizado y responsivo.
-   Control de versiones con Git y GitHub.

------------------------------------------------------------------------

## Aspectos pendientes de despliegue

La implementación local se encuentra funcional. Como etapa posterior
corresponde completar el despliegue en un servicio de hosting y
habilitar HTTPS en el entorno publicado.

Estas tareas se mantienen diferenciadas de las funcionalidades ya
implementadas para no presentar como terminado aquello que todavía
depende del entorno de despliegue.

------------------------------------------------------------------------

## Archivos principales

### `SIGATI_BD_Final.sql`

Script SQL consolidado para reconstruir y verificar la estructura actual
de 12 tablas en una base independiente de prueba.

### `SIGATI_Diagrama_EER.png`

Diagrama EER generado mediante MySQL Workbench a partir de la estructura
actual de la base de datos.

### `composer.json` y `composer.lock`

Definen las dependencias PHP del proyecto, incluyendo PHPMailer. La
carpeta `vendor/` se genera localmente mediante Composer y se excluye
del repositorio.

------------------------------------------------------------------------

## Proyección

Como evolución futura se considera ampliar SIGATI hacia otros activos
tecnológicos y aumentar las capacidades analíticas sobre información
histórica. El modelo actual mantiene MySQL como base operacional y
Apache Spark como componente complementario para escenarios de mayor
volumen y procesamiento analítico.

------------------------------------------------------------------------

## Autor

**David Bermudez**\
Proyecto de Titulación --- Técnico en Informática\
IPLACEX
