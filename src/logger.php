<?php

declare(strict_types=1);

/**
 * SIGATI - Registro centralizado de eventos
 *
 * Registra eventos técnicos relevantes de la aplicación sin almacenar
 * contraseñas, tokens, cookies ni otras credenciales sensibles.
 */

function sigati_log(string $evento, array $contexto = []): void
{
    $directorioLogs = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';

    if (!is_dir($directorioLogs)) {
        @mkdir($directorioLogs, 0755, true);
    }

    if (!is_dir($directorioLogs) || !is_writable($directorioLogs)) {
        return;
    }

    $archivoLog = $directorioLogs . DIRECTORY_SEPARATOR . 'sigati.log';

    $evento = limpiar_valor_log($evento);

    $clavesSensibles = [
        'password',
        'password_hash',
        'contrasena',
        'contraseña',
        'token',
        'token_hash',
        'csrf',
        'csrf_token',
        'cookie',
        'smtp_password',
        'authorization',
    ];

    $contextoSeguro = [];

    foreach ($contexto as $clave => $valor) {
        $claveTexto = (string) $clave;
        $claveNormalizada = strtolower($claveTexto);

        $esSensible = false;

        foreach ($clavesSensibles as $claveSensible) {
            if (str_contains($claveNormalizada, $claveSensible)) {
                $esSensible = true;
                break;
            }
        }

        if ($esSensible) {
            $contextoSeguro[$claveTexto] = '[PROTEGIDO]';
            continue;
        }

        if (is_scalar($valor) || $valor === null) {
            $contextoSeguro[$claveTexto] = limpiar_valor_log((string) $valor);
        } else {
            $contextoSeguro[$claveTexto] = '[VALOR_NO_ESCALAR]';
        }
    }

    $fecha = date('Y-m-d H:i:s');

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $ip = limpiar_valor_log((string) $ip);

    $linea = '[' . $fecha . ']'
        . ' evento=' . $evento
        . ' ip=' . $ip;

    if ($contextoSeguro !== []) {
        $json = json_encode(
            $contextoSeguro,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json !== false) {
            $linea .= ' contexto=' . $json;
        }
    }

    $linea .= PHP_EOL;

    @file_put_contents(
        $archivoLog,
        $linea,
        FILE_APPEND | LOCK_EX
    );
}


/**
 * Evita que saltos de línea u otros caracteres puedan alterar
 * artificialmente la estructura del archivo de log.
 */
function limpiar_valor_log(string $valor): string
{
    $valor = str_replace(
        ["\r", "\n", "\t"],
        ' ',
        $valor
    );

    return trim($valor);
}