<?php

namespace App\Tests\Unit;

use App\Service\PromptExamenPsicotecnico;
use PHPUnit\Framework\TestCase;

class PromptExamenPsicotecnicoTest extends TestCase
{
    public function testNormalMantieneElSimulacroYBajaLaDificultad(): void
    {
        $prompt = (new PromptExamenPsicotecnico())->generar(PromptExamenPsicotecnico::NIVEL_NORMAL);

        self::assertStringContainsString('Dificultad: normal, la de una convocatoria estándar.', $prompt);
        self::assertStringNotContainsString('Dificultad: alto.', $prompt);
        self::assertStringNotContainsString('«Normal»', $prompt);
        self::assertStringNotContainsString('indica el nivel', $prompt);
        self::assertStringContainsString('Memoria visual (1–14)', $prompt);
        self::assertStringContainsString('Atención (71–84)', $prompt);
        self::assertStringContainsString('máximo 252', $prompt);
        self::assertStringContainsString('uno o dos pasos', $prompt);
        self::assertStringContainsString('Soluciones: número, letra correcta', $prompt);
        self::assertStringContainsString('Simulacro_psicotecnico_Bispol', $prompt);
        self::assertStringContainsString('No copies sus preguntas ni el tipo de ejercicio', $prompt);
        self::assertStringContainsString('No uses el tipo de lámina del PDF de ejemplo', $prompt);
        self::assertStringNotContainsString('cuadrícula 3×3', $prompt);
    }

    public function testAltoMantieneElSimulacroYSubeLaDificultad(): void
    {
        $prompt = (new PromptExamenPsicotecnico())->generar(PromptExamenPsicotecnico::NIVEL_ALTO);

        self::assertStringContainsString('Dificultad: alto.', $prompt);
        self::assertStringContainsString('muy difícil', $prompt);
        self::assertStringContainsString('dos reglas a la vez', $prompt);
        self::assertStringNotContainsString('«Alto (muy difícil)»', $prompt);
        self::assertStringNotContainsString('indica el nivel', $prompt);
        self::assertStringNotContainsString('Dificultad: normal,', $prompt);
        self::assertStringContainsString('84 preguntas', $prompt);
        self::assertStringContainsString('Atención (71–84)', $prompt);
        self::assertStringContainsString('#312d45', $prompt);
    }

    public function testRechazaUnNivelDesconocido(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new PromptExamenPsicotecnico())->generar('imposible');
    }

    public function testUnaSolaAreaRecortaElExamenYQuitaLaLamina(): void
    {
        $prompt = (new PromptExamenPsicotecnico())->generar(PromptExamenPsicotecnico::NIVEL_NORMAL, ['verbal']);

        self::assertStringContainsString('Es un simulacro de una sola aptitud: Razonamiento verbal.', $prompt);
        self::assertStringContainsString('1 aptitud × 14 preguntas', $prompt);
        self::assertStringContainsString('Razonamiento verbal (1–14)', $prompt);
        self::assertStringContainsString('10 min para las 14 preguntas', $prompt);
        self::assertStringContainsString('máximo 42', $prompt);
        self::assertStringContainsString('No incluyas lámina de memoria', $prompt);
        self::assertStringNotContainsString('Memoria visual', $prompt);
        self::assertStringNotContainsString('Atención (', $prompt);
        self::assertStringNotContainsString('- Numérico:', $prompt);
    }

    public function testOmnibusParcialRenumeraYMantieneElOrden(): void
    {
        $prompt = (new PromptExamenPsicotecnico())->generar(
            PromptExamenPsicotecnico::NIVEL_ALTO,
            ['atencion', 'memoria'],
        );

        self::assertStringContainsString('Es un ómnibus solo de estas aptitudes: Memoria visual y Atención.', $prompt);
        self::assertStringContainsString('Memoria visual (1–14)', $prompt);
        self::assertStringContainsString('Atención (15–28)', $prompt);
        self::assertStringContainsString('28 preguntas', $prompt);
        self::assertStringContainsString('máximo 84', $prompt);
        self::assertStringContainsString('20 min para las', $prompt);
        self::assertStringContainsString('2. Lámina de memoria.', $prompt);
        self::assertStringNotContainsString('Razonamiento verbal', $prompt);
        self::assertStringNotContainsString('- Numérico:', $prompt);
    }

    public function testSinAreasValidasNoGeneraPrompt(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new PromptExamenPsicotecnico())->generar(PromptExamenPsicotecnico::NIVEL_NORMAL, ['no-existe']);
    }
}
