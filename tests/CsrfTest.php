<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/auth.php';

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testTokenCsrfTieneFormatoCorrecto(): void
    {
        $token = csrf_token();

        $this->assertSame(
            64,
            strlen($token)
        );

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $token
        );
    }

    public function testTokenCsrfSeMantieneEnLaSesion(): void
    {
        $token1 = csrf_token();
        $token2 = csrf_token();

        $this->assertSame(
            $token1,
            $token2
        );
    }

    public function testCampoCsrfContieneTokenDeSesion(): void
    {
        $token = csrf_token();
        $campo = csrf_field();

        $this->assertStringContainsString(
            'name="csrf_token"',
            $campo
        );

        $this->assertStringContainsString(
            $token,
            $campo
        );
    }
}