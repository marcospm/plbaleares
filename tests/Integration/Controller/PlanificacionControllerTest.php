<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Integration\TestCase;
use App\Entity\PlanificacionPersonalizada;
use App\Entity\FranjaHorariaPersonalizada;

class PlanificacionControllerTest extends TestCase
{
    public function testPlanificacionIndexRequiresProfesor(): void
    {
        $this->client->request('GET', '/planificacion');
        
        $this->assertResponseRedirects('/login');
    }
    
    public function testPlanificacionIndexAccessibleForProfesor(): void
    {
        $this->loginAsProfesor();
        
        $this->client->request('GET', '/planificacion');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Planificaciones');
    }
    
    public function testPlanificacionNewForm(): void
    {
        $this->loginAsProfesor();
        
        $crawler = $this->client->request('GET', '/planificacion/new');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="planificacion_fecha_especifica"]');
    }
    
    public function testPlanificacionCreateWithFranjas(): void
    {
        $profesor = $this->loginAsProfesor();
        $alumno = $this->createTestUser('alumno1', 'alumno1@test.com');
        $ley = $this->createTestLey();
        $tema = $this->createTestTema($ley);
        
        $crawler = $this->client->request('GET', '/planificacion/new');
        
        $this->assertResponseIsSuccessful();
        
        $form = $crawler->selectButton('Guardar')->form([
            'planificacion_fecha_especifica[nombre]' => 'Planificación de Prueba',
            'planificacion_fecha_especifica[descripcion]' => 'Descripción de prueba',
            'planificacion_fecha_especifica[fechaInicio]' => (new \DateTime('+1 day'))->format('Y-m-d'),
            'planificacion_fecha_especifica[fechaFin]' => (new \DateTime('+7 days'))->format('Y-m-d'),
            'planificacion_fecha_especifica[usuarios]' => [$alumno->getId()],
        ]);
        
        $this->client->submit($form);
        
        // La planificación debería haberse creado
        $planificacion = $this->entityManager->getRepository(PlanificacionPersonalizada::class)
            ->findOneBy(['nombre' => 'Planificación de Prueba']);
        
        $this->assertNotNull($planificacion);
    }
    
    public function testPlanificacionShow(): void
    {
        $profesor = $this->loginAsProfesor();
        $alumno = $this->createTestUser('alumno1', 'alumno1@test.com');
        $planificacion = $this->createTestPlanificacion($alumno, $profesor);
        
        $this->client->request('GET', '/planificacion/' . $planificacion->getId());
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Planificación');
    }
    
    public function testPlanificacionEdit(): void
    {
        $profesor = $this->loginAsProfesor();
        $alumno = $this->createTestUser('alumno1', 'alumno1@test.com');
        $planificacion = $this->createTestPlanificacion($alumno, $profesor);
        
        $crawler = $this->client->request('GET', '/planificacion/' . $planificacion->getId() . '/edit');
        
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('Actualizar')->form([
            'planificacion[nombre]' => 'Nombre Actualizado',
        ]);
        
        $this->client->submit($form);
        
        $this->entityManager->refresh($planificacion);
        $this->assertEquals('Nombre Actualizado', $planificacion->getNombre());
    }
    
    public function testPlanificacionClonar(): void
    {
        $profesor = $this->loginAsProfesor();
        $alumnoOrigen = $this->createTestUser('alumno1', 'alumno1@test.com');
        $alumnoDestino = $this->createTestUser('alumno2', 'alumno2@test.com');
        $planificacion = $this->createTestPlanificacion($alumnoOrigen, $profesor);
        
        $crawler = $this->client->request('GET', '/planificacion/clonar');
        
        $this->assertResponseIsSuccessful();
        
        $form = $crawler->selectButton('Clonar Planificaciones')->form([
            'alumno_origen' => $alumnoOrigen->getId(),
            'alumno_destino' => $alumnoDestino->getId(),
        ]);
        
        $this->client->submit($form);
        
        // Verificar que se creó una planificación para el alumno destino
        $planificacionDestino = $this->entityManager->getRepository(PlanificacionPersonalizada::class)
            ->findOneBy(['usuario' => $alumnoDestino]);
        
        $this->assertNotNull($planificacionDestino);
    }
    
    /**
     * Helper para crear una planificación de prueba
     */
    protected function createTestPlanificacion($usuario, $creadoPor): PlanificacionPersonalizada
    {
        $planificacion = new PlanificacionPersonalizada();
        $planificacion->setUsuario($usuario);
        $planificacion->setCreadoPor($creadoPor);
        $planificacion->setNombre('Planificación Test');
        $planificacion->setDescripcion('Descripción test');
        $planificacion->setFechaInicio(new \DateTime('+1 day'));
        $planificacion->setFechaFin(new \DateTime('+7 days'));
        
        // Crear una franja horaria de prueba
        $franja = new FranjaHorariaPersonalizada();
        $franja->setPlanificacion($planificacion);
        $franja->setFechaEspecifica(new \DateTime('+1 day'));
        $franja->setHoraInicio(new \DateTime('09:00'));
        $franja->setHoraFin(new \DateTime('11:00'));
        $franja->setTipoActividad('Estudio');
        $franja->setDescripcionRepaso('Repaso de temas');
        $franja->setOrden(1);
        
        $planificacion->addFranjaHoraria($franja);
        
        $this->entityManager->persist($planificacion);
        $this->entityManager->flush();
        
        return $planificacion;
    }
    
    /**
     * Helper para crear un tema de prueba
     */
    protected function createTestTema($ley): \App\Entity\Tema
    {
        $tema = new \App\Entity\Tema();
        $tema->setNombre('Tema de Prueba');
        $tema->setDescripcion('Descripción del tema');
        $tema->addLey($ley);
        
        $this->entityManager->persist($tema);
        $this->entityManager->flush();
        
        return $tema;
    }
}
