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

    public function testOtroUsaElTextoLibreYNoLasRecetasFijas(): void
    {
        $servicio = new PromptExamenPsicotecnico();
        $prompt = $servicio->generar(
            PromptExamenPsicotecnico::NIVEL_OTRO,
            ['verbal', 'numerico'],
            null,
            "  primero   de la ESO \n",
        );

        self::assertSame('primero de la ESO', $servicio->interpretarNivelLibre("  primero   de la ESO \n"));
        self::assertStringContainsString('Dificultad: primero de la ESO.', $prompt);
        self::assertStringContainsString('Ese nivel manda sobre el de una convocatoria estándar', $prompt);
        self::assertStringContainsString('Razonamiento verbal (1–14)', $prompt);
        self::assertStringContainsString('Razonamiento numérico (15–28)', $prompt);
        self::assertStringNotContainsString('Dificultad: normal,', $prompt);
        self::assertStringNotContainsString('Dificultad: alto.', $prompt);
        self::assertStringNotContainsString('- Numérico: series de un solo patrón', $prompt);
        self::assertStringNotContainsString('dos patrones alternos', $prompt);
        self::assertStringNotContainsString('- Verbal: relaciones simples', $prompt);
    }

    public function testOtroSinTextoNoGeneraPrompt(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new PromptExamenPsicotecnico())->generar(PromptExamenPsicotecnico::NIVEL_OTRO, null, null, '   ');
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

    public function testSinTotalSigueElSimulacroDe14PorArea(): void
    {
        $servicio = new PromptExamenPsicotecnico();

        self::assertNull($servicio->interpretarTotal('  '));
        $prompt = $servicio->generar(PromptExamenPsicotecnico::NIVEL_NORMAL, ['numerico'], null);

        self::assertStringContainsString('1 aptitud × 14 preguntas', $prompt);
        self::assertStringNotContainsString('manda sobre las 14', $prompt);
    }

    public function testUnTotalEnUnAreaSustituyeAlSimulacro(): void
    {
        $prompt = (new PromptExamenPsicotecnico())->generar(
            PromptExamenPsicotecnico::NIVEL_NORMAL,
            ['numerico'],
            50,
        );

        self::assertStringContainsString('50 preguntas', $prompt);
        self::assertStringContainsString('1 aptitud × 50 preguntas', $prompt);
        self::assertStringContainsString('Razonamiento numérico (1–50)', $prompt);
        self::assertStringContainsString('Este total (50) manda sobre las 14', $prompt);
        self::assertStringContainsString('36 min para las 50 preguntas', $prompt);
        self::assertStringContainsString('máximo 150', $prompt);
        self::assertStringNotContainsString('× 14 preguntas', $prompt);
        self::assertStringNotContainsString('Razonamiento verbal', $prompt);
    }

    public function testUnTotalEntreDosAreasSeReparteAPartesIguales(): void
    {
        $prompt = (new PromptExamenPsicotecnico())->generar(
            PromptExamenPsicotecnico::NIVEL_ALTO,
            ['numerico', 'verbal'],
            50,
        );

        self::assertStringContainsString('2 aptitudes × 25 preguntas', $prompt);
        self::assertStringContainsString('Razonamiento verbal (1–25)', $prompt);
        self::assertStringContainsString('Razonamiento numérico (26–50)', $prompt);
        self::assertStringContainsString('36 min para las 50 preguntas', $prompt);
        self::assertStringContainsString('máximo 150', $prompt);
        self::assertStringNotContainsString('Memoria visual', $prompt);
        self::assertStringNotContainsString('× 14 preguntas', $prompt);
    }

    public function testElRestoDeUnTotalImparVaEnLasPrimerasAreas(): void
    {
        $prompt = (new PromptExamenPsicotecnico())->generar(
            PromptExamenPsicotecnico::NIVEL_NORMAL,
            ['verbal', 'numerico', 'abstracto'],
            50,
        );

        self::assertStringContainsString('3 aptitudes, 50 preguntas repartidas', $prompt);
        self::assertStringContainsString('Razonamiento verbal (1–17)', $prompt);
        self::assertStringContainsString('Razonamiento numérico (18–34)', $prompt);
        self::assertStringContainsString('Razonamiento abstracto (35–50)', $prompt);
    }

    public function testLaLaminaDeMemoriaCuentaSoloSuTramo(): void
    {
        $prompt = (new PromptExamenPsicotecnico())->generar(
            PromptExamenPsicotecnico::NIVEL_NORMAL,
            ['memoria', 'atencion'],
            30,
        );

        self::assertStringContainsString('Tiene que dar para 15 preguntas distintas.', $prompt);
        self::assertStringContainsString('las 15 primeras sobre la lámina', $prompt);
        self::assertStringContainsString('Memoria visual (1–15)', $prompt);
        self::assertStringContainsString('Atención (16–30)', $prompt);
    }

    public function testRechazaUnTotalQueNoCubreLasAreas(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new PromptExamenPsicotecnico())->generar(
            PromptExamenPsicotecnico::NIVEL_NORMAL,
            ['verbal', 'numerico'],
            1,
        );
    }

    public function testRechazaUnTotalQueNoEsUnEnteroUtil(): void
    {
        $servicio = new PromptExamenPsicotecnico();

        $this->expectException(\InvalidArgumentException::class);

        $servicio->interpretarTotal('0');
    }
}
