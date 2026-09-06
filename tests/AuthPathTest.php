<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/auth.php';

final class AuthPathTest extends TestCase
{
    public function testRutaLocalIncluyeSigati(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/sigati/public/dashboard.php';

        $resultado = sigati_path('public/login.php');

        $this->assertSame(
            '/sigati/public/login.php',
            $resultado
        );
    }

    public function testRutaHostingNoIncluyeSigati(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/public/dashboard.php';

        $resultado = sigati_path('public/login.php');

        $this->assertSame(
            '/public/login.php',
            $resultado
        );
    }
}