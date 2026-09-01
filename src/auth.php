<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SIGATI - AUTENTICACIÓN, SESIONES Y PROTECCIÓN CSRF
|--------------------------------------------------------------------------
|
| Este archivo administra:
|
| - Configuración segura de sesiones PHP.
| - Detección de HTTPS local y detrás de proxy.
| - Control de autenticación.
| - Control de roles.
| - Generación y validación de tokens CSRF.
|
*/


/*
|--------------------------------------------------------------------------
| DETECTAR HTTPS
|--------------------------------------------------------------------------
|
| En XAMPP normalmente SIGATI se ejecuta mediante HTTP.
|
| En InfinityFree el navegador utiliza HTTPS, pero el servidor PHP puede
| encontrarse detrás de un proxy. En ese escenario $_SERVER['HTTPS']
| no siempre indica correctamente que la conexión original fue segura.
|
| Por eso se revisan también cabeceras utilizadas por proxies.
|
*/

function sigati_usa_https(): bool
{
    /*
    |--------------------------------------------------------------------------
    | Detección HTTPS directa
    |--------------------------------------------------------------------------
    */

    $https = strtolower(
        (string) ($_SERVER['HTTPS'] ?? '')
    );

    if (
        $https !== ''
        && $https !== 'off'
        && $https !== '0'
    ) {
        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | Puerto HTTPS
    |--------------------------------------------------------------------------
    */

    if (
        isset($_SERVER['SERVER_PORT'])
        && (string) $_SERVER['SERVER_PORT'] === '443'
    ) {
        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | REQUEST_SCHEME
    |--------------------------------------------------------------------------
    */

    $request_scheme = strtolower(
        (string) ($_SERVER['REQUEST_SCHEME'] ?? '')
    );

    if ($request_scheme === 'https') {
        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | X-FORWARDED-PROTO
    |--------------------------------------------------------------------------
    |
    | Esta cabecera es utilizada habitualmente cuando la aplicación está
    | detrás de un proxy o balanceador.
    |
    | Puede contener valores como:
    |
    | https
    |
    | o:
    |
    | https,http
    |
    */

    $forwarded_proto = strtolower(
        (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')
    );

    if ($forwarded_proto !== '') {

        $protocolos = array_map(
            'trim',
            explode(',', $forwarded_proto)
        );

        if (in_array('https', $protocolos, true)) {
            return true;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | X-FORWARDED-SCHEME
    |--------------------------------------------------------------------------
    */

    $forwarded_scheme = strtolower(
        (string) ($_SERVER['HTTP_X_FORWARDED_SCHEME'] ?? '')
    );

    if ($forwarded_scheme === 'https') {
        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | FORWARDED
    |--------------------------------------------------------------------------
    |
    | También puede venir una cabecera estándar similar a:
    |
    | Forwarded: proto=https
    |
    */

    $forwarded = strtolower(
        (string) ($_SERVER['HTTP_FORWARDED'] ?? '')
    );

    if (
        $forwarded !== ''
        && preg_match(
            '/(?:^|[;,]\s*)proto=https(?:[;,]|$)/i',
            $forwarded
        ) === 1
    ) {
        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | CF-VISITOR
    |--------------------------------------------------------------------------
    |
    | Algunos servicios proxy/CDN indican el protocolo mediante:
    |
    | {"scheme":"https"}
    |
    */

    $cf_visitor =
        (string) ($_SERVER['HTTP_CF_VISITOR'] ?? '');

    if ($cf_visitor !== '') {

        $datos_cf =
            json_decode(
                $cf_visitor,
                true
            );

        if (
            is_array($datos_cf)
            && isset($datos_cf['scheme'])
            && strtolower(
                (string) $datos_cf['scheme']
            ) === 'https'
        ) {
            return true;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DETECCIÓN ESPECÍFICA DEL HOST DE PRODUCCIÓN
    |--------------------------------------------------------------------------
    |
    | SIGATI se publica actualmente en sigati.page.gd.
    |
    | Si PHP recibe ese host sabemos que corresponde al entorno de
    | producción configurado con HTTPS.
    |
    | Esto no afecta localhost ni XAMPP.
    |
    */

    $host = strtolower(
        (string) ($_SERVER['HTTP_HOST'] ?? '')
    );

    $host = preg_replace(
        '/:\d+$/',
        '',
        $host
    );

    if ($host === 'sigati.page.gd') {
        return true;
    }

    return false;
}


/*
|--------------------------------------------------------------------------
| OBTENER RUTA BASE DE SIGATI
|--------------------------------------------------------------------------
|
| En XAMPP:
|
| /sigati/public/login.php
|
| En InfinityFree:
|
| /public/login.php
|
| Esta función permite que el mismo código funcione en ambos ambientes.
|
*/

function sigati_base_path(): string
{
    $script_name = str_replace(
        '\\',
        '/',
        (string) ($_SERVER['SCRIPT_NAME'] ?? '')
    );

    if (
        str_starts_with(
            $script_name,
            '/sigati/'
        )
    ) {
        return '/sigati';
    }

    return '';
}


/*
|--------------------------------------------------------------------------
| CONSTRUIR UNA RUTA INTERNA
|--------------------------------------------------------------------------
*/

function sigati_path(string $ruta): string
{
    return
        sigati_base_path()
        . '/'
        . ltrim($ruta, '/');
}


/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN SEGURA DE SESIÓN
|--------------------------------------------------------------------------
|
| Toda esta configuración debe ejecutarse antes de session_start().
|
*/

if (session_status() !== PHP_SESSION_ACTIVE) {

    /*
    |--------------------------------------------------------------------------
    | MODO ESTRICTO
    |--------------------------------------------------------------------------
    |
    | PHP solamente aceptará identificadores de sesión válidos creados
    | previamente por el servidor.
    |
    */

    ini_set(
        'session.use_strict_mode',
        '1'
    );


    /*
    |--------------------------------------------------------------------------
    | SOLO COOKIES
    |--------------------------------------------------------------------------
    |
    | El identificador de sesión no podrá viajar mediante parámetros URL.
    |
    */

    ini_set(
        'session.use_only_cookies',
        '1'
    );


    /*
    |--------------------------------------------------------------------------
    | DETECTAR HTTPS
    |--------------------------------------------------------------------------
    */

    $usa_https =
        sigati_usa_https();


    /*
    |--------------------------------------------------------------------------
    | CONFIGURACIÓN DE COOKIE DE SESIÓN
    |--------------------------------------------------------------------------
    */

    session_set_cookie_params([

        /*
        |----------------------------------------------------------------------
        | lifetime
        |----------------------------------------------------------------------
        |
        | La cookie permanece mientras esté abierta la sesión del navegador.
        |
        */

        'lifetime' => 0,


        /*
        |----------------------------------------------------------------------
        | path
        |----------------------------------------------------------------------
        */

        'path' => '/',


        /*
        |----------------------------------------------------------------------
        | Secure
        |----------------------------------------------------------------------
        |
        | Cuando SIGATI está bajo HTTPS, el navegador solamente podrá enviar
        | la cookie mediante una conexión cifrada.
        |
        */

        'secure' => $usa_https,


        /*
        |----------------------------------------------------------------------
        | HttpOnly
        |----------------------------------------------------------------------
        |
        | Impide que JavaScript acceda directamente a la cookie PHPSESSID.
        |
        */

        'httponly' => true,


        /*
        |----------------------------------------------------------------------
        | SameSite
        |----------------------------------------------------------------------
        |
        | Reduce el envío de la cookie en solicitudes originadas desde otros
        | sitios y complementa la protección CSRF.
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
            'Location: '
            . sigati_path(
                'public/login.php'
            )
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