<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Integration\TestCase;
use App\Entity\Tarea;
use App\Entity\TareaAsignada;
use App\Entity\Ley;
use App\Entity\Tema;
use App\Entity\Articulo;

class TareaControllerTest extends TestCase
{
    public function testTareaIndexRequiresProfesor(): void
    {
        $this->client->request('GET', '/tarea');
        
        $this->assertResponseRedirects('/login');
    }
    
    public function testTareaIndexAccessibleForProfesor(): void
    {
        $this->loginAsProfesor();
        
        $this->client->request('GET', '/tarea/');
        
        $this->assertResponseIsSuccessful();
    }
    
    public function testTareaNewForm(): void
    {
        $this->loginAsProfesor();
        
        $crawler = $this->client->request('GET', '/tarea/new');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="tarea"]');
    }
    
    public function testTareaCreate(): void
    {
        $profesor = $this->loginAsProfesor();
        $alumno = $this->createTestUser('alumno1', 'alumno1@test.com');
        $ley = $this->createTestLey();
        $tema = $this->createTestTema($ley);
        $articulo = $this->createTestArticulo($ley);
        
        $crawler = $this->client->request('GET', '/tarea/new');
        
        $this->assertResponseIsSuccessful();
        
        $semanaAsignacion = new \DateTime('next monday');
        
        // Buscar el formulario por nombre en lugar del botón
        $form = $crawler->filter('form[name="tarea"]')->form([
            'tarea[nombre]' => 'Tarea de Prueba',
            'tarea[descripcion]' => 'Descripción de la tarea',
            'tarea[semanaAsignacion]' => $semanaAsignacion->format('Y-m-d'),
            'tarea[tema]' => $tema->getId(),
            'tarea[ley]' => $ley->getId(),
            'tarea[articulo]' => $articulo->getId(),
            'tarea[usuarios]' => [$alumno->getId()],
        ]);
        
        $this->client->submit($form);
        
        // Verificar que la tarea fue creada
        $tarea = $this->entityManager->getRepository(Tarea::class)
            ->findOneBy(['nombre' => 'Tarea de Prueba']);
        
        $this->assertNotNull($tarea);
        $this->assertEquals('Tarea de Prueba', $tarea->getNombre());
        
        // Verificar que se creó la asignación
        $tareaAsignada = $this->entityManager->getRepository(TareaAsignada::class)
            ->findOneBy(['tarea' => $tarea, 'usuario' => $alumno]);
        
        $this->assertNotNull($tareaAsignada);
    }
    
    public function testTareaShow(): void
    {
        $profesor = $this->loginAsProfesor();
        $alumno = $this->createTestUser('alumno1', 'alumno1@test.com');
        $ley = $this->createTestLey();
        $tema = $this->createTestTema($ley);
        $tarea = $this->createTestTarea($profesor, $alumno, $tema, $ley);
        
        $this->client->request('GET', '/tarea/' . $tarea->getId());
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', $tarea->getNombre());
    }
    
    public function testTareaEdit(): void
    {
        $profesor = $this->loginAsProfesor();
        $alumno = $this->createTestUser('alumno1', 'alumno1@test.com');
        $ley = $this->createTestLey();
        $tema = $this->createTestTema($ley);
        $tarea = $this->createTestTarea($profesor, $alumno, $tema, $ley);
        
        $crawler = $this->client->request('GET', '/tarea/' . $tarea->getId() . '/edit');
        
        $this->assertResponseIsSuccessful();
        // Buscar el formulario por nombre en lugar del botón
        $form = $crawler->filter('form[name="tarea"]')->form([
            'tarea[nombre]' => 'Tarea Actualizada',
        ]);
        
        $this->client->submit($form);
        
        // Buscar la tarea actualizada desde la base de datos
        $this->entityManager->clear();
        $tareaActualizada = $this->entityManager->getRepository(Tarea::class)
            ->find($tarea->getId());
        $this->assertEquals('Tarea Actualizada', $tareaActualizada->getNombre());
    }
    
    public function testTareaDelete(): void
    {
        $profesor = $this->loginAsProfesor();
        $alumno = $this->createTestUser('alumno1', 'alumno1@test.com');
        $ley = $this->createTestLey();
        $tema = $this->createTestTema($ley);
        $tarea = $this->createTestTarea($profesor, $alumno, $tema, $ley);
        $tareaId = $tarea->getId();
        
        $crawler = $this->client->request('GET', '/tarea/' . $tareaId);
        $form = $crawler->selectButton('Eliminar')->form();
        
        $this->client->submit($form);
        
        $tarea = $this->entityManager->getRepository(Tarea::class)->find($tareaId);
        $this->assertNull($tarea);
    }
    
    /**
     * Helper para crear una tarea de prueba
     */
    protected function createTestTarea($profesor, $alumno, $tema, $ley): Tarea
    {
        $tarea = new Tarea();
        $tarea->setNombre('Tarea Test');
        $tarea->setDescripcion('Descripción test');
        $tarea->setSemanaAsignacion(new \DateTime('next monday'));
        $tarea->setTema($tema);
        $tarea->setLey($ley);
        $tarea->setCreadoPor($profesor);
        
        $tareaAsignada = new TareaAsignada();
        $tareaAsignada->setTarea($tarea);
        $tareaAsignada->setUsuario($alumno);
        $tarea->addAsignacion($tareaAsignada);
        
        $this->entityManager->persist($tarea);
        $this->entityManager->persist($tareaAsignada);
        $this->entityManager->flush();
        
        return $tarea;
    }
    
    /**
     * Helper para crear un tema de prueba
     */
    protected function createTestTema(\App\Entity\Ley $ley, string $nombre = 'Tema de Prueba'): \App\Entity\Tema
    {
        $tema = new Tema();
        $tema->setNombre($nombre);
        $tema->setDescripcion('Descripción del tema');
        $tema->addLey($ley);
        
        $this->entityManager->persist($tema);
        $this->entityManager->flush();
        
        return $tema;
    }
}
