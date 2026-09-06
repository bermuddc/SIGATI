<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/auth.php';

final class RoleTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testAdministradorEsReconocidoCorrectamente(): void
    {
        $_SESSION['rol'] = 'Administrador TI';

        $this->assertTrue(
            is_admin()
        );

        $this->assertFalse(
            is_consulta()
        );
    }

    public function testConsultaEsReconocidoCorrectamente(): void
    {
        $_SESSION['rol'] = 'Consulta';

        $this->assertTrue(
            is_consulta()
        );

        $this->assertFalse(
            is_admin()
        );
    }

    public function testSinRolNoTienePermisos(): void
    {
        $this->assertFalse(
            is_admin()
        );

        $this->assertFalse(
            is_consulta()
        );
    }
}