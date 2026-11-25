<?php

namespace App\Tests\Unit;

use App\Http\Consulta;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\InputBag;

class ConsultaTest extends TestCase
{
    public function testUnEnteroVacioOInvalidoCuentaComoCero(): void
    {
        self::assertSame(0, Consulta::entero(new InputBag(['ley' => '']), 'ley'));
        self::assertSame(0, Consulta::entero(new InputBag(['ley' => 'abc']), 'ley'));
        self::assertSame(0, Consulta::entero(new InputBag([]), 'ley'));
        self::assertSame(15, Consulta::entero(new InputBag(['ley' => '15']), 'ley'));
        self::assertSame(0, Consulta::entero(new InputBag(['ley' => '0']), 'ley'));
    }
}
