<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN SEGURA DE SESIÓN
|--------------------------------------------------------------------------
|
| La configuración debe realizarse ANTES de session_start().
|
| SIGATI utiliza sesiones PHP para mantener autenticado al usuario.
| Estas opciones endurecen la seguridad de la cookie de sesión.
|
*/

if (session_status() !== PHP_SESSION_ACTIVE) {

    /*
    |--------------------------------------------------------------------------
    | MODO ESTRICTO DE SESIÓN
    |--------------------------------------------------------------------------
    |
    | Evita que PHP acepte identificadores de sesión que no hayan sido
    | generados previamente por el servidor.
    |
    */

    ini_set('session.use_strict_mode', '1');


    /*
    |--------------------------------------------------------------------------
    | SOLO COOKIES
    |--------------------------------------------------------------------------
    |
    | El identificador de sesión solamente se acepta mediante cookies.
    | No se permite transportar el ID de sesión dentro de la URL.
    |
    */

    ini_set('session.use_only_cookies', '1');


    /*
    |--------------------------------------------------------------------------
    | DETECTAR HTTPS
    |--------------------------------------------------------------------------
    |
    | En el entorno local actual normalmente se utiliza HTTP.
    | Cuando SIGATI sea publicado mediante HTTPS, la propiedad Secure
    | se activará automáticamente.
    |
    */

    $usa_https =
        !empty($_SERVER['HTTPS'])
        && strtolower((string) $_SERVER['HTTPS']) !== 'off';


    /*
    |--------------------------------------------------------------------------
    | CONFIGURAR COOKIE DE SESIÓN
    |--------------------------------------------------------------------------
    */

    session_set_cookie_params([

        /*
        |----------------------------------------------------------------------
        | lifetime = 0
        |----------------------------------------------------------------------
        |
        | La cookie dura mientras permanezca abierta la sesión del navegador.
        |
        */

        'lifetime' => 0,


        /*
        |----------------------------------------------------------------------
        | path
        |----------------------------------------------------------------------
        |
        | La cookie puede utilizarse dentro de toda la aplicación.
        |
        */

        'path' => '/',


        /*
        |----------------------------------------------------------------------
        | secure
        |----------------------------------------------------------------------
        |
        | En producción con HTTPS será true.
        | En localhost HTTP permanece false para permitir el desarrollo.
        |
        */

        'secure' => $usa_https,


        /*
        |----------------------------------------------------------------------
        | httponly
        |----------------------------------------------------------------------
        |
        | Impide que JavaScript pueda leer directamente la cookie.
        |
        */

        'httponly' => true,


        /*
        |----------------------------------------------------------------------
        | samesite
        |----------------------------------------------------------------------
        |
        | Limita el envío de la cookie desde otros sitios web.
        | Complementa la protección mediante token CSRF.
        |
        */

        'samesite' => 'Lax'

    ]);


    /*
    |--------------------------------------------------------------------------
    | INICIAR SESIÓN
    |--------------------------------------------------------------------------
    */

    session_start();
}


/*
|--------------------------------------------------------------------------
| CONTROL DE AUTENTICACIÓN
|--------------------------------------------------------------------------
*/

function require_login(): void
{
    if (!isset($_SESSION['usuario_id'])) {

        header(
            'Location: /sigati/public/login.php'
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| CONTROL DE ROLES
|--------------------------------------------------------------------------
*/

function require_role(
    string $rol_requerido
): void {

    require_login();

    $rol_actual =
        $_SESSION['rol']
        ?? '';

    if ($rol_actual !== $rol_requerido) {

        http_response_code(403);

        exit(
            'Acceso denegado.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| COMPROBAR PERFIL ADMINISTRADOR
|--------------------------------------------------------------------------
*/

function is_admin(): bool
{
    return
        isset($_SESSION['rol'])
        &&
        $_SESSION['rol']
        === 'Administrador TI';
}


/*
|--------------------------------------------------------------------------
| COMPROBAR PERFIL CONSULTA
|--------------------------------------------------------------------------
*/

function is_consulta(): bool
{
    return
        isset($_SESSION['rol'])
        &&
        $_SESSION['rol']
        === 'Consulta';
}


/*
|--------------------------------------------------------------------------
| PROTECCIÓN CSRF
|--------------------------------------------------------------------------
|
| CSRF significa Cross-Site Request Forgery.
|
| El token permite comprobar que una solicitud enviada mediante un
| formulario realmente se originó dentro de SIGATI y pertenece a la
| sesión actual del usuario.
|
*/


/*
|--------------------------------------------------------------------------
| OBTENER / GENERAR TOKEN CSRF
|--------------------------------------------------------------------------
*/

function csrf_token(): string
{
    if (
        !isset($_SESSION['csrf_token'])
        ||
        !is_string($_SESSION['csrf_token'])
    ) {

        $_SESSION['csrf_token'] =
            bin2hex(
                random_bytes(32)
            );
    }

    return $_SESSION['csrf_token'];
}


/*
|--------------------------------------------------------------------------
| GENERAR CAMPO HTML CSRF
|--------------------------------------------------------------------------
|
| Esta función genera automáticamente:
|
| <input type="hidden" name="csrf_token" value="...">
|
| El usuario no ve este campo, pero el navegador lo envía junto con
| el formulario.
|
*/

function csrf_field(): string
{
    $token =
        htmlspecialchars(
            csrf_token(),
            ENT_QUOTES,
            'UTF-8'
        );

    return
        '<input type="hidden" name="csrf_token" value="'
        . $token
        . '">';
}


/*
|--------------------------------------------------------------------------
| VALIDAR TOKEN CSRF
|--------------------------------------------------------------------------
*/

function validate_csrf(): void
{
    $token_sesion =
        $_SESSION['csrf_token']
        ?? '';

    $token_recibido =
        $_POST['csrf_token']
        ?? '';

    if (
        !is_string($token_sesion)
        ||
        !is_string($token_recibido)
        ||
        $token_sesion === ''
        ||
        $token_recibido === ''
        ||
        !hash_equals(
            $token_sesion,
            $token_recibido
        )
    ) {

        http_response_code(403);

        exit(
            'Solicitud rechazada: token CSRF inválido o ausente.'
        );
    }
}