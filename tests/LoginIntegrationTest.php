<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LoginIntegrationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        require __DIR__ . '/../config/database.php';

        $this->pdo = $pdo;

        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function testLoginConCredencialesValidas(): void
    {
        $stmtRol = $this->pdo->prepare(
            "SELECT id_rol
             FROM rol
             WHERE nombre_rol = :nombre_rol
             LIMIT 1"
        );

        $stmtRol->execute([
            ':nombre_rol' => 'Administrador TI'
        ]);

        $rol = $stmtRol->fetch();

        $this->assertNotFalse($rol);

        $nombreUsuario =
            'test_login_' . bin2hex(random_bytes(4));

        $password =
            'PruebaSIGATI2026!';

        $passwordHash =
            password_hash(
                $password,
                PASSWORD_DEFAULT
            );

        $stmtInsertar = $this->pdo->prepare(
            "INSERT INTO usuario_sistema (
                nombre_completo,
                nombre_usuario,
                correo,
                password_hash,
                id_rol,
                activo,
                fecha_creacion
            )
            VALUES (
                :nombre_completo,
                :nombre_usuario,
                NULL,
                :password_hash,
                :id_rol,
                1,
                NOW()
            )"
        );

        $stmtInsertar->execute([
            ':nombre_completo' =>
                'Usuario Prueba Integracion',

            ':nombre_usuario' =>
                $nombreUsuario,

            ':password_hash' =>
                $passwordHash,

            ':id_rol' =>
                $rol['id_rol']
        ]);

        /*
        |--------------------------------------------------------------------------
        | MISMA CONSULTA UTILIZADA POR EL LOGIN REAL DE SIGATI
        |--------------------------------------------------------------------------
        */

        $sql = "
            SELECT
                u.id_usuario,
                u.nombre_completo,
                u.nombre_usuario,
                u.password_hash,
                u.activo,
                r.nombre_rol
            FROM usuario_sistema u
            INNER JOIN rol r
                ON u.id_rol = r.id_rol
            WHERE u.nombre_usuario = :nombre_usuario
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':nombre_usuario' =>
                $nombreUsuario
        ]);

        $usuario =
            $stmt->fetch();

        $this->assertNotFalse(
            $usuario
        );

        $this->assertSame(
            1,
            (int) $usuario['activo']
        );

        $this->assertTrue(
            password_verify(
                $password,
                $usuario['password_hash']
            )
        );

        $this->assertSame(
            'Administrador TI',
            $usuario['nombre_rol']
        );
    }

    public function testLoginRechazaPasswordIncorrecto(): void
    {
        $passwordCorrecta =
            'PruebaSIGATI2026!';

        $hash =
            password_hash(
                $passwordCorrecta,
                PASSWORD_DEFAULT
            );

        $this->assertFalse(
            password_verify(
                'PasswordIncorrecto',
                $hash
            )
        );
    }
}