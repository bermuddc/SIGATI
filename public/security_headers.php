<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SIGATI - CABECERAS DE SEGURIDAD HTTP
|--------------------------------------------------------------------------
|
| Este archivo centraliza cabeceras defensivas para reducir riesgos
| comunes en aplicaciones web.
|
*/


/*
|--------------------------------------------------------------------------
| EVITAR CARGA EN IFRAMES DE OTROS SITIOS
|--------------------------------------------------------------------------
*/

header(
    'X-Frame-Options: SAMEORIGIN'
);


/*
|--------------------------------------------------------------------------
| EVITAR INTERPRETACIÓN INCORRECTA DEL TIPO DE CONTENIDO
|--------------------------------------------------------------------------
*/

header(
    'X-Content-Type-Options: nosniff'
);


/*
|--------------------------------------------------------------------------
| CONTENT SECURITY POLICY
|--------------------------------------------------------------------------
|
| SIGATI utiliza CSS interno dentro de sus páginas, por eso se permite
| 'unsafe-inline' solamente para estilos.
|
| No se permiten scripts externos.
|
*/

header(
    "Content-Security-Policy: "
    . "default-src 'self'; "
    . "script-src 'self'; "
    . "style-src 'self' 'unsafe-inline'; "
    . "img-src 'self' data:; "
    . "font-src 'self'; "
    . "connect-src 'self'; "
    . "frame-ancestors 'self'; "
    . "base-uri 'self'; "
    . "form-action 'self';"
);


/*
|--------------------------------------------------------------------------
| POLÍTICA DE REFERENCIA
|--------------------------------------------------------------------------
*/

header(
    'Referrer-Policy: strict-origin-when-cross-origin'
);