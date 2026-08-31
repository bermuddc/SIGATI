<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/auth.php';

header('Content-Type: application/json; charset=utf-8');


function responder(
    int $codigo,
    array $contenido
): never {

    http_response_code($codigo);

    echo json_encode(
        $contenido,
        JSON_UNESCAPED_UNICODE
        | JSON_PRETTY_PRINT
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Autenticación
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['usuario_id'])
    || (int) $_SESSION['usuario_id'] <= 0
) {

    responder(
        401,
        [
            'ok' => false,
            'error' => 'No autorizado.',
            'mensaje' =>
                'Debes iniciar sesión para utilizar la API de SIGATI.'
        ]
    );
}


$metodo = $_SERVER['REQUEST_METHOD'];


/*
|--------------------------------------------------------------------------
| Protección CSRF para operaciones de escritura
|--------------------------------------------------------------------------
|
| GET solamente consulta información.
|
| POST y PUT modifican información, por lo que deben enviar el token
| CSRF de la sesión mediante la cabecera HTTP:
|
| X-CSRF-Token
|
*/

if (
    $metodo === 'POST'
    || $metodo === 'PUT'
) {

    $tokenRecibido =
        $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';

    $tokenSesion =
        $_SESSION['csrf_token']
        ?? '';

    if (
        !is_string($tokenRecibido)
        || !is_string($tokenSesion)
        || $tokenRecibido === ''
        || $tokenSesion === ''
        || !hash_equals(
            $tokenSesion,
            $tokenRecibido
        )
    ) {

        responder(
            403,
            [
                'ok' => false,
                'error' => 'Solicitud bloqueada.',
                'mensaje' =>
                    'Token CSRF ausente o inválido.'
            ]
        );
    }
}


/*
|--------------------------------------------------------------------------
| GET
|--------------------------------------------------------------------------
*/

if ($metodo === 'GET') {

    $id_notebook = filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );

    try {

        if (isset($_GET['id'])) {

            if (
                $id_notebook === false
                || $id_notebook === null
                || $id_notebook <= 0
            ) {

                responder(
                    400,
                    [
                        'ok' => false,
                        'error' => 'Parámetro inválido.',
                        'mensaje' =>
                            'El ID del notebook debe ser un entero positivo.'
                    ]
                );
            }


            $sql = "
                SELECT
                    n.id_notebook,
                    n.numero_serie,
                    n.marca,
                    n.modelo,
                    n.procesador,
                    n.ram_gb,
                    n.capacidad_disco_gb,
                    n.nombre_equipo_actual,
                    e.nombre_estado AS estado,
                    n.fecha_registro
                FROM notebook n
                INNER JOIN estado_notebook e
                    ON n.id_estado = e.id_estado
                WHERE n.id_notebook = :id_notebook
                LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':id_notebook' => $id_notebook
            ]);

            $notebook =
                $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$notebook) {

                responder(
                    404,
                    [
                        'ok' => false,
                        'error' => 'Notebook no encontrado.',
                        'id_notebook' => $id_notebook
                    ]
                );
            }


            responder(
                200,
                [
                    'ok' => true,
                    'datos' => $notebook
                ]
            );
        }


        $sql = "
            SELECT
                n.id_notebook,
                n.numero_serie,
                n.marca,
                n.modelo,
                n.procesador,
                n.ram_gb,
                n.capacidad_disco_gb,
                n.nombre_equipo_actual,
                e.nombre_estado AS estado,
                n.fecha_registro
            FROM notebook n
            INNER JOIN estado_notebook e
                ON n.id_estado = e.id_estado
            ORDER BY n.id_notebook DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        $notebooks =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


        responder(
            200,
            [
                'ok' => true,
                'total' => count($notebooks),
                'datos' => $notebooks
            ]
        );


    } catch (PDOException $e) {

        responder(
            500,
            [
                'ok' => false,
                'error' => 'Error interno del servidor.',
                'mensaje' =>
                    'No fue posible consultar los notebooks.'
            ]
        );
    }
}


/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($metodo === 'POST') {

    if (!is_admin()) {

        responder(
            403,
            [
                'ok' => false,
                'error' => 'Acceso denegado.',
                'mensaje' =>
                    'Solo un Administrador TI puede registrar notebooks.'
            ]
        );
    }


    $contentType =
        $_SERVER['CONTENT_TYPE']
        ?? '';

    if (
        stripos(
            $contentType,
            'application/json'
        ) === false
    ) {

        responder(
            415,
            [
                'ok' => false,
                'error' =>
                    'Tipo de contenido no soportado.',
                'mensaje' =>
                    'La API espera datos en formato application/json.'
            ]
        );
    }


    $contenido =
        file_get_contents('php://input');

    if (
        $contenido === false
        || trim($contenido) === ''
    ) {

        responder(
            400,
            [
                'ok' => false,
                'error' => 'Solicitud vacía.',
                'mensaje' =>
                    'Debes enviar los datos del notebook en JSON.'
            ]
        );
    }


    try {

        $datos = json_decode(
            $contenido,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

    } catch (JsonException $e) {

        responder(
            400,
            [
                'ok' => false,
                'error' => 'JSON inválido.',
                'mensaje' =>
                    'El cuerpo de la solicitud no contiene un JSON válido.'
            ]
        );
    }


    if (!is_array($datos)) {

        responder(
            400,
            [
                'ok' => false,
                'error' => 'Formato inválido.',
                'mensaje' =>
                    'El cuerpo JSON debe representar un objeto.'
            ]
        );
    }


    $numero_serie =
        trim((string) ($datos['numero_serie'] ?? ''));

    $marca =
        trim((string) ($datos['marca'] ?? ''));

    $modelo =
        trim((string) ($datos['modelo'] ?? ''));

    $procesador =
        trim((string) ($datos['procesador'] ?? ''));

    $ram_gb =
        filter_var(
            $datos['ram_gb'] ?? null,
            FILTER_VALIDATE_INT
        );

    $capacidad_disco_gb =
        filter_var(
            $datos['capacidad_disco_gb'] ?? null,
            FILTER_VALIDATE_INT
        );


    $errores = [];


    if ($numero_serie === '') {
        $errores[] =
            'El número de serie es obligatorio.';
    }

    if ($marca === '') {
        $errores[] =
            'La marca es obligatoria.';
    }

    if ($modelo === '') {
        $errores[] =
            'El modelo es obligatorio.';
    }

    if ($procesador === '') {
        $errores[] =
            'El procesador es obligatorio.';
    }


    $ramPermitida = [8, 16, 32, 64, 128];

    if (
        $ram_gb === false
        || !in_array(
            $ram_gb,
            $ramPermitida,
            true
        )
    ) {

        $errores[] =
            'La RAM debe ser 8, 16, 32, 64 o 128 GB.';
    }


    $discosPermitidos = [
        256,
        512,
        1024,
        2048
    ];

    if (
        $capacidad_disco_gb === false
        || !in_array(
            $capacidad_disco_gb,
            $discosPermitidos,
            true
        )
    ) {

        $errores[] =
            'El disco debe ser 256, 512, 1024 o 2048 GB.';
    }


    if (!empty($errores)) {

        responder(
            422,
            [
                'ok' => false,
                'error' => 'Datos no válidos.',
                'errores' => $errores
            ]
        );
    }


    try {

        $sqlSerie = "
            SELECT COUNT(*)
            FROM notebook
            WHERE numero_serie = :numero_serie
        ";

        $stmtSerie =
            $pdo->prepare($sqlSerie);

        $stmtSerie->execute([
            ':numero_serie' =>
                $numero_serie
        ]);


        if (
            (int) $stmtSerie->fetchColumn()
            > 0
        ) {

            responder(
                409,
                [
                    'ok' => false,
                    'error' => 'Conflicto.',
                    'mensaje' =>
                        'El número de serie ya se encuentra registrado.'
                ]
            );
        }


        $sqlEstado = "
            SELECT id_estado
            FROM estado_notebook
            WHERE nombre_estado = 'Ingresado'
            LIMIT 1
        ";

        $stmtEstado =
            $pdo->prepare($sqlEstado);

        $stmtEstado->execute();

        $id_estado =
            (int) $stmtEstado->fetchColumn();


        if ($id_estado <= 0) {

            responder(
                500,
                [
                    'ok' => false,
                    'error' =>
                        'Configuración inválida.',
                    'mensaje' =>
                        'No se encontró el estado Ingresado.'
                ]
            );
        }


        $sqlInsert = "
            INSERT INTO notebook (
                numero_serie,
                marca,
                modelo,
                procesador,
                ram_gb,
                capacidad_disco_gb,
                nombre_equipo_actual,
                id_estado
            )
            VALUES (
                :numero_serie,
                :marca,
                :modelo,
                :procesador,
                :ram_gb,
                :capacidad_disco_gb,
                NULL,
                :id_estado
            )
        ";

        $stmtInsert =
            $pdo->prepare($sqlInsert);

        $stmtInsert->execute([
            ':numero_serie' =>
                $numero_serie,

            ':marca' =>
                $marca,

            ':modelo' =>
                $modelo,

            ':procesador' =>
                $procesador,

            ':ram_gb' =>
                $ram_gb,

            ':capacidad_disco_gb' =>
                $capacidad_disco_gb,

            ':id_estado' =>
                $id_estado
        ]);


        $nuevoId =
            (int) $pdo->lastInsertId();


        $sqlNuevo = "
            SELECT
                n.id_notebook,
                n.numero_serie,
                n.marca,
                n.modelo,
                n.procesador,
                n.ram_gb,
                n.capacidad_disco_gb,
                n.nombre_equipo_actual,
                e.nombre_estado AS estado,
                n.fecha_registro
            FROM notebook n
            INNER JOIN estado_notebook e
                ON n.id_estado = e.id_estado
            WHERE n.id_notebook = :id_notebook
            LIMIT 1
        ";

        $stmtNuevo =
            $pdo->prepare($sqlNuevo);

        $stmtNuevo->execute([
            ':id_notebook' => $nuevoId
        ]);

        $nuevoNotebook =
            $stmtNuevo->fetch(PDO::FETCH_ASSOC);


        responder(
            201,
            [
                'ok' => true,
                'mensaje' =>
                    'Notebook registrado correctamente mediante la API.',
                'datos' =>
                    $nuevoNotebook
            ]
        );


    } catch (PDOException $e) {

        if ($e->getCode() === '23000') {

            responder(
                409,
                [
                    'ok' => false,
                    'error' => 'Conflicto.',
                    'mensaje' =>
                        'El número de serie ya se encuentra registrado.'
                ]
            );
        }


        responder(
            500,
            [
                'ok' => false,
                'error' =>
                    'Error interno del servidor.',
                'mensaje' =>
                    'No fue posible registrar el notebook.'
            ]
        );
    }
}


/*
|--------------------------------------------------------------------------
| PUT
|--------------------------------------------------------------------------
*/

if ($metodo === 'PUT') {

    if (!is_admin()) {

        responder(
            403,
            [
                'ok' => false,
                'error' => 'Acceso denegado.',
                'mensaje' =>
                    'Solo un Administrador TI puede actualizar notebooks.'
            ]
        );
    }


    $id_notebook = filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );


    if (
        $id_notebook === false
        || $id_notebook === null
        || $id_notebook <= 0
    ) {

        responder(
            400,
            [
                'ok' => false,
                'error' => 'ID inválido.',
                'mensaje' =>
                    'Debes indicar un ID de notebook válido.'
            ]
        );
    }


    $contentType =
        $_SERVER['CONTENT_TYPE']
        ?? '';

    if (
        stripos(
            $contentType,
            'application/json'
        ) === false
    ) {

        responder(
            415,
            [
                'ok' => false,
                'error' =>
                    'Tipo de contenido no soportado.',
                'mensaje' =>
                    'La API espera application/json.'
            ]
        );
    }


    $contenido =
        file_get_contents('php://input');

    if (
        $contenido === false
        || trim($contenido) === ''
    ) {

        responder(
            400,
            [
                'ok' => false,
                'error' => 'Solicitud vacía.',
                'mensaje' =>
                    'Debes enviar los datos del notebook en JSON.'
            ]
        );
    }


    try {

        $datos = json_decode(
            $contenido,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

    } catch (JsonException $e) {

        responder(
            400,
            [
                'ok' => false,
                'error' => 'JSON inválido.',
                'mensaje' =>
                    'El cuerpo de la solicitud no contiene un JSON válido.'
            ]
        );
    }


    if (!is_array($datos)) {

        responder(
            400,
            [
                'ok' => false,
                'error' => 'Formato inválido.'
            ]
        );
    }


    $marca =
        trim((string) ($datos['marca'] ?? ''));

    $modelo =
        trim((string) ($datos['modelo'] ?? ''));

    $procesador =
        trim((string) ($datos['procesador'] ?? ''));

    $ram_gb =
        filter_var(
            $datos['ram_gb'] ?? null,
            FILTER_VALIDATE_INT
        );

    $capacidad_disco_gb =
        filter_var(
            $datos['capacidad_disco_gb'] ?? null,
            FILTER_VALIDATE_INT
        );


    $errores = [];


    if ($marca === '') {
        $errores[] =
            'La marca es obligatoria.';
    }

    if ($modelo === '') {
        $errores[] =
            'El modelo es obligatorio.';
    }

    if ($procesador === '') {
        $errores[] =
            'El procesador es obligatorio.';
    }


    if (
        $ram_gb === false
        || !in_array(
            $ram_gb,
            [8, 16, 32, 64, 128],
            true
        )
    ) {

        $errores[] =
            'La RAM no es válida.';
    }


    if (
        $capacidad_disco_gb === false
        || !in_array(
            $capacidad_disco_gb,
            [256, 512, 1024, 2048],
            true
        )
    ) {

        $errores[] =
            'La capacidad de disco no es válida.';
    }


    if (!empty($errores)) {

        responder(
            422,
            [
                'ok' => false,
                'error' => 'Datos no válidos.',
                'errores' => $errores
            ]
        );
    }


    try {

        $sqlActual = "
            SELECT
                n.id_notebook,
                e.nombre_estado
            FROM notebook n
            INNER JOIN estado_notebook e
                ON n.id_estado = e.id_estado
            WHERE n.id_notebook = :id_notebook
            LIMIT 1
        ";

        $stmtActual =
            $pdo->prepare($sqlActual);

        $stmtActual->execute([
            ':id_notebook' =>
                $id_notebook
        ]);

        $actual =
            $stmtActual->fetch(PDO::FETCH_ASSOC);


        if (!$actual) {

            responder(
                404,
                [
                    'ok' => false,
                    'error' =>
                        'Notebook no encontrado.'
                ]
            );
        }


        if (
            $actual['nombre_estado']
            !== 'Ingresado'
        ) {

            responder(
                409,
                [
                    'ok' => false,
                    'error' =>
                        'Actualización no permitida.',
                    'mensaje' =>
                        'Solo se pueden actualizar mediante esta operación notebooks en estado Ingresado.'
                ]
            );
        }


        $sqlUpdate = "
            UPDATE notebook
            SET
                marca = :marca,
                modelo = :modelo,
                procesador = :procesador,
                ram_gb = :ram_gb,
                capacidad_disco_gb = :capacidad_disco_gb
            WHERE id_notebook = :id_notebook
        ";

        $stmtUpdate =
            $pdo->prepare($sqlUpdate);

        $stmtUpdate->execute([
            ':marca' => $marca,
            ':modelo' => $modelo,
            ':procesador' => $procesador,
            ':ram_gb' => $ram_gb,
            ':capacidad_disco_gb' =>
                $capacidad_disco_gb,
            ':id_notebook' =>
                $id_notebook
        ]);


        $sqlResultado = "
            SELECT
                n.id_notebook,
                n.numero_serie,
                n.marca,
                n.modelo,
                n.procesador,
                n.ram_gb,
                n.capacidad_disco_gb,
                n.nombre_equipo_actual,
                e.nombre_estado AS estado,
                n.fecha_registro
            FROM notebook n
            INNER JOIN estado_notebook e
                ON n.id_estado = e.id_estado
            WHERE n.id_notebook = :id_notebook
            LIMIT 1
        ";

        $stmtResultado =
            $pdo->prepare($sqlResultado);

        $stmtResultado->execute([
            ':id_notebook' =>
                $id_notebook
        ]);

        $resultado =
            $stmtResultado->fetch(PDO::FETCH_ASSOC);


        responder(
            200,
            [
                'ok' => true,
                'mensaje' =>
                    'Notebook actualizado correctamente mediante la API.',
                'datos' => $resultado
            ]
        );


    } catch (PDOException $e) {

        responder(
            500,
            [
                'ok' => false,
                'error' =>
                    'Error interno del servidor.',
                'mensaje' =>
                    'No fue posible actualizar el notebook.'
            ]
        );
    }
}


/*
|--------------------------------------------------------------------------
| Métodos no permitidos
|--------------------------------------------------------------------------
*/

header('Allow: GET, POST, PUT');

responder(
    405,
    [
        'ok' => false,
        'error' => 'Método no permitido.',
        'metodos_permitidos' => [
            'GET',
            'POST',
            'PUT'
        ]
    ]
);