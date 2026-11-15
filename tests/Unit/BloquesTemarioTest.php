<?php

namespace App\Tests\Unit;

use App\Service\BloquesTemario;
use PHPUnit\Framework\TestCase;

class BloquesTemarioTest extends TestCase
{
    public function testAgrupaPorBloqueYCalculaPorcentajes(): void
    {
        $servicio = new BloquesTemario();
        $bloques = $servicio->resumir([
            $this->pregunta('Tema 1 - Constitución', true),
            $this->pregunta('Tema 2 - Constitución', false),
            $this->pregunta('Tema 4 - Constitución', null),
            $this->pregunta('Tema 5 - Estatuto', true),
            $this->pregunta('Tema 10 - Señalización', true),
            $this->pregunta('Tema 17 - El accidente de tráfico', null),
            $this->pregunta('Tema 30 - Derechos LGTBI', false),
            $this->pregunta('Normativa interna', true),
        ]);

        $porNombre = [];
        foreach ($bloques as $bloque) {
            $porNombre[$bloque['nombre']] = $bloque;
        }

        self::assertSame('Temas 1 a 4', $porNombre['Constitución']['rango']);
        self::assertSame(3, $porNombre['Constitución']['total']);
        self::assertSame(1, $porNombre['Constitución']['aciertos']);
        self::assertSame(1, $porNombre['Constitución']['fallos']);
        self::assertSame(1, $porNombre['Constitución']['blanco']);
        self::assertSame('33,3%', $porNombre['Constitución']['pctAciertos']);
        self::assertSame('33,3%', $porNombre['Constitución']['pctFallos']);
        self::assertSame('33,3%', $porNombre['Constitución']['pctBlanco']);

        self::assertSame('Tema 5', $porNombre['Estatuto de las Illes Balears']['rango']);
        self::assertSame('100%', $porNombre['Estatuto de las Illes Balears']['pctAciertos']);
        self::assertSame('0%', $porNombre['Estatuto de las Illes Balears']['pctFallos']);

        self::assertSame('Temas 7 a 17', $porNombre['Tráfico']['rango']);
        self::assertSame(2, $porNombre['Tráfico']['total']);
        self::assertSame('50%', $porNombre['Tráfico']['pctAciertos']);
        self::assertSame('50%', $porNombre['Tráfico']['pctBlanco']);

        self::assertSame('100%', $porNombre['Derechos de lesbianas, gais, trans, bisexuales e intersexuales y erradicación de la LGTBI-fobia']['pctFallos']);
        self::assertSame(1, $porNombre['Otros temas']['aciertos']);
        self::assertArrayNotHasKey('Código penal', $porNombre);
    }

    public function testSinPreguntasNoDevuelveBloques(): void
    {
        self::assertSame([], (new BloquesTemario())->resumir([]));
    }

    private function pregunta(string $tema, ?bool $esCorrecta): array
    {
        return [
            'pregunta' => new PreguntaDeBloque($tema),
            'esCorrecta' => $esCorrecta,
        ];
    }
}

class PreguntaDeBloque
{
    public function __construct(private string $tema)
    {
    }

    public function getTema(): TemaDeBloque
    {
        return new TemaDeBloque($this->tema);
    }
}

class TemaDeBloque
{
    public function __construct(private string $nombre)
    {
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }
}
