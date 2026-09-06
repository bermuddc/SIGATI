<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DatabaseIntegrationTest extends TestCase
{
    public function testConexionConBaseDeDatos(): void
    {
        require __DIR__ . '/../config/database.php';

        $this->assertInstanceOf(
            PDO::class,
            $pdo
        );

        $resultado = $pdo->query('SELECT 1 AS prueba')->fetch();

        $this->assertSame(
            1,
            (int) $resultado['prueba']
        );
    }

    public function testTablaUsuarioSistemaExiste(): void
    {
        require __DIR__ . '/../config/database.php';

        $stmt = $pdo->query(
            "SHOW TABLES LIKE 'usuario_sistema'"
        );

        $resultado = $stmt->fetch();

        $this->assertNotFalse(
            $resultado
        );
    }
}