<?php

namespace App\Tests\Unit;

use App\Entity\Articulo;
use App\Entity\Ley;
use App\Entity\Pregunta;
use App\Entity\PreguntaMunicipal;
use App\Service\FlashcardTexto;
use PHPUnit\Framework\TestCase;

class FlashcardTextoTest extends TestCase
{
    public function testSugiereLaPreguntaYLaRespuestaCorrecta(): void
    {
        $ley = new Ley();
        $ley->setNombre('Ley Orgánica 2/1986');

        $articulo = new Articulo();
        $articulo->setNumero(5);
        $articulo->setSufijo('bis');
        $articulo->setNombre('Colaboración');

        $pregunta = new Pregunta();
        $pregunta->setTexto('¿Quién colabora con las Fuerzas y Cuerpos de Seguridad?');
        $pregunta->setOpcionA('Solo la policía local');
        $pregunta->setOpcionB('Las autoridades y los particulares');
        $pregunta->setOpcionC('Nadie');
        $pregunta->setOpcionD('Solo los jueces');
        $pregunta->setRespuestaCorrecta('b');
        $pregunta->setRetroalimentacion("**Todos** deben colaborar.\n\nMás detalle en la [ley](https://ejemplo.test).");
        $pregunta->setLey($ley);
        $pregunta->setArticulo($articulo);

        $servicio = new FlashcardTexto();

        self::assertSame(
            '¿Quién colabora con las Fuerzas y Cuerpos de Seguridad?',
            $servicio->anverso($pregunta)
        );
        self::assertSame(
            "B) Las autoridades y los particulares\n\nTodos deben colaborar.\n\nMás detalle en la ley.\n\nLey Orgánica 2/1986 · Art. 5 bis - Colaboración",
            $servicio->reverso($pregunta)
        );
    }

    public function testOmiteElArticuloCeroYLaReferenciaMunicipal(): void
    {
        $articulo = new Articulo();
        $articulo->setNumero(0);

        $pregunta = new Pregunta();
        $pregunta->setTexto('  Pregunta de tráfico  ');
        $pregunta->setOpcionA('Sí');
        $pregunta->setOpcionB('No');
        $pregunta->setOpcionC('Depende');
        $pregunta->setOpcionD('Ninguna');
        $pregunta->setRespuestaCorrecta('A');
        $pregunta->setArticulo($articulo);

        $municipal = new PreguntaMunicipal();
        $municipal->setTexto('Pregunta municipal');
        $municipal->setOpcionA('Uno');
        $municipal->setOpcionB('Dos');
        $municipal->setOpcionC('Tres');
        $municipal->setOpcionD('Cuatro');
        $municipal->setRespuestaCorrecta('C');

        $servicio = new FlashcardTexto();

        self::assertSame('Pregunta de tráfico', $servicio->anverso($pregunta));
        self::assertSame('A) Sí', $servicio->reverso($pregunta));
        self::assertSame('C) Tres', $servicio->reverso($municipal));
    }
}
