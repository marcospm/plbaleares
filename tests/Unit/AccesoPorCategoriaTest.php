<?php

namespace App\Tests\Unit;

use App\Entity\CategoriaOposicion;
use App\Entity\User;
use App\Service\AccesoPorCategoria;
use PHPUnit\Framework\TestCase;

class AccesoPorCategoriaTest extends TestCase
{
    private AccesoPorCategoria $acceso;

    protected function setUp(): void
    {
        $this->acceso = new AccesoPorCategoria();
    }

    public function testPoliciaNoQuedaRestringido(): void
    {
        $user = new User();
        $user->setCategoria(CategoriaOposicion::POLICIA);

        self::assertFalse($this->acceso->restringe($user, false));
    }

    public function testOficialAlumnoQuedaRestringido(): void
    {
        $user = new User();
        $user->setCategoria(CategoriaOposicion::OFICIAL);

        self::assertTrue($this->acceso->restringe($user, false));
    }

    public function testProfesorNoQuedaRestringidoAunqueNoSeaPolicia(): void
    {
        $user = new User();
        $user->setCategoria(CategoriaOposicion::OFICIAL);

        self::assertFalse($this->acceso->restringe($user, true));
    }

    public function testRutasPermitidasDelMenuReducido(): void
    {
        self::assertTrue($this->acceso->rutaPermitida('app_dashboard'));
        self::assertTrue($this->acceso->rutaPermitida('app_recurso_publico_index'));
        self::assertTrue($this->acceso->rutaPermitida('app_tema_pdf_ver'));
        self::assertTrue($this->acceso->rutaPermitida('app_contacto'));
        self::assertFalse($this->acceso->rutaPermitida('app_examen_iniciar'));
        self::assertFalse($this->acceso->rutaPermitida('app_ley_publico_index'));
        self::assertFalse($this->acceso->rutaPermitida('app_dashboard_rankings'));
    }
}
