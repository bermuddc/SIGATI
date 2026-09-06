<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PerformanceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        require __DIR__ . '/../config/database.php';

        $this->pdo = $pdo;
    }

    public function testConsultaListadoNotebooksRespondeCorrectamente(): void
    {
        $inicio = microtime(true);

        $stmt = $this->pdo->query(
            "SELECT
                n.id_notebook,
                n.numero_serie,
                n.marca,
                n.modelo,
                e.nombre_estado
             FROM notebook n
             INNER JOIN estado_notebook e
                ON n.id_estado = e.id_estado
             ORDER BY n.id_notebook DESC"
        );

        $resultado = $stmt->fetchAll();

        $fin = microtime(true);

        $tiempo = $fin - $inicio;

        echo PHP_EOL
            . 'Tiempo consulta notebooks: '
            . number_format($tiempo, 6)
            . ' segundos'
            . PHP_EOL;

        $this->assertIsArray(
            $resultado
        );

        $this->assertGreaterThanOrEqual(
            0,
            count($resultado)
        );
    }
}