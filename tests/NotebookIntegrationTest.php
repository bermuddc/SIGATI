<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class NotebookIntegrationTest extends TestCase
{
    private PDO $pdo;
    private string $numeroSerie;

    protected function setUp(): void
    {
        require __DIR__ . '/../config/database.php';

        $this->pdo = $pdo;

        $this->numeroSerie =
            'TEST-' . strtoupper(bin2hex(random_bytes(4)));
    }

    protected function tearDown(): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM notebook
             WHERE numero_serie = :numero_serie"
        );

        $stmt->execute([
            ':numero_serie' => $this->numeroSerie
        ]);
    }

    public function testNotebookSeRegistraConEstadoIngresado(): void
    {
        /*
        |--------------------------------------------------------------------------
        | OBTENER ESTADO INGRESADO
        |--------------------------------------------------------------------------
        */

        $stmtEstado = $this->pdo->prepare(
            "SELECT id_estado
             FROM estado_notebook
             WHERE nombre_estado = :nombre_estado
             LIMIT 1"
        );

        $stmtEstado->execute([
            ':nombre_estado' => 'Ingresado'
        ]);

        $estado = $stmtEstado->fetch();

        $this->assertNotFalse($estado);

        /*
        |--------------------------------------------------------------------------
        | INSERTAR NOTEBOOK DE PRUEBA
        |--------------------------------------------------------------------------
        */

        $sql = "
            INSERT INTO notebook
            (
                numero_serie,
                marca,
                modelo,
                procesador,
                ram_gb,
                capacidad_disco_gb,
                nombre_equipo_actual,
                id_estado
            )
            VALUES
            (
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

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':numero_serie' =>
                $this->numeroSerie,

            ':marca' =>
                'Lenovo',

            ':modelo' =>
                'ThinkPad Test',

            ':procesador' =>
                'Intel Core i5',

            ':ram_gb' =>
                16,

            ':capacidad_disco_gb' =>
                512,

            ':id_estado' =>
                (int) $estado['id_estado']
        ]);

        /*
        |--------------------------------------------------------------------------
        | CONSULTAR NOTEBOOK CREADO
        |--------------------------------------------------------------------------
        */

        $stmtConsulta = $this->pdo->prepare(
            "SELECT
                n.numero_serie,
                n.marca,
                n.modelo,
                n.ram_gb,
                n.capacidad_disco_gb,
                n.nombre_equipo_actual,
                e.nombre_estado
             FROM notebook n
             INNER JOIN estado_notebook e
                ON n.id_estado = e.id_estado
             WHERE n.numero_serie = :numero_serie
             LIMIT 1"
        );

        $stmtConsulta->execute([
            ':numero_serie' =>
                $this->numeroSerie
        ]);

        $notebook = $stmtConsulta->fetch();

        $this->assertNotFalse($notebook);

        $this->assertSame(
            $this->numeroSerie,
            $notebook['numero_serie']
        );

        $this->assertSame(
            'Ingresado',
            $notebook['nombre_estado']
        );

        $this->assertSame(
            16,
            (int) $notebook['ram_gb']
        );

        $this->assertSame(
            512,
            (int) $notebook['capacidad_disco_gb']
        );

        $this->assertNull(
            $notebook['nombre_equipo_actual']
        );
    }

    public function testNumeroSerieDuplicadoEsRechazado(): void
    {
        $stmtEstado = $this->pdo->prepare(
            "SELECT id_estado
             FROM estado_notebook
             WHERE nombre_estado = 'Ingresado'
             LIMIT 1"
        );

        $stmtEstado->execute();

        $estado = $stmtEstado->fetch();

        $this->assertNotFalse($estado);

        $sql = "
            INSERT INTO notebook
            (
                numero_serie,
                marca,
                modelo,
                procesador,
                ram_gb,
                capacidad_disco_gb,
                nombre_equipo_actual,
                id_estado
            )
            VALUES
            (
                :numero_serie,
                'Lenovo',
                'ThinkPad Test',
                'Intel Core i5',
                16,
                512,
                NULL,
                :id_estado
            )
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':numero_serie' =>
                $this->numeroSerie,

            ':id_estado' =>
                (int) $estado['id_estado']
        ]);

        $this->expectException(
            PDOException::class
        );

        /*
        |--------------------------------------------------------------------------
        | SEGUNDO INSERT CON LA MISMA SERIE
        |--------------------------------------------------------------------------
        */

        $stmtDuplicado =
            $this->pdo->prepare($sql);

        $stmtDuplicado->execute([
            ':numero_serie' =>
                $this->numeroSerie,

            ':id_estado' =>
                (int) $estado['id_estado']
        ]);
    }

    public function testEstadoDisponibleExisteEnCatalogo(): void
    {
        /*
        |--------------------------------------------------------------------------
        | VALIDAR ESTADO DISPONIBLE
        |--------------------------------------------------------------------------
        */

        $stmt = $this->pdo->prepare(
            "SELECT
                id_estado,
                nombre_estado,
                descripcion
             FROM estado_notebook
             WHERE nombre_estado = :nombre_estado
             LIMIT 1"
        );

        $stmt->execute([
            ':nombre_estado' => 'Disponible'
        ]);

        $estado = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($estado);

        $this->assertSame(
            'Disponible',
            $estado['nombre_estado']
        );

        $this->assertGreaterThan(
            0,
            (int) $estado['id_estado']
        );

        $this->assertNotEmpty(
            $estado['descripcion']
        );
    }
}