<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Integration\TestCase;
use App\Entity\ExamenSemanal;
use App\Entity\Tema;

class ExamenSemanalControllerTest extends TestCase
{
    public function testExamenSemanalIndexRequiresProfesor(): void
    {
        $this->client->request('GET', '/examen-semanal');
        
        $this->assertResponseRedirects('/login');
    }
    
    public function testExamenSemanalIndexAccessibleForProfesor(): void
    {
        $this->loginAsProfesor();
        
        $this->client->request('GET', '/examen-semanal');
        
        $this->assertResponseIsSuccessful();
    }
    
    public function testExamenSemanalNewForm(): void
    {
        $this->loginAsProfesor();
        
        $crawler = $this->client->request('GET', '/examen-semanal/new');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="examen_semanal"]');
    }
    
    public function testExamenSemanalCreate(): void
    {
        $profesor = $this->loginAsProfesor();
        $ley = $this->createTestLey();
        $tema = $this->createTestTema($ley);
        
        $crawler = $this->client->request('GET', '/examen-semanal/new');
        
        $this->assertResponseIsSuccessful();
        
        $fechaApertura = new \DateTime('+1 day');
        $fechaCierre = new \DateTime('+7 days');
        
        $form = $crawler->selectButton('Guardar')->form([
            'examen_semanal[nombre]' => 'Examen Semanal Test',
            'examen_semanal[descripcion]' => 'Descripción del examen',
            'examen_semanal[fechaApertura]' => $fechaApertura->format('Y-m-d H:i'),
            'examen_semanal[fechaCierre]' => $fechaCierre->format('Y-m-d H:i'),
            'examen_semanal[dificultad]' => 'facil',
            'examen_semanal[temas]' => [$tema->getId()],
        ]);
        
        $this->client->submit($form);
        
        // Verificar que el examen fue creado
        $examen = $this->entityManager->getRepository(ExamenSemanal::class)
            ->findOneBy(['nombre' => 'Examen Semanal Test']);
        
        $this->assertNotNull($examen);
        $this->assertEquals('Examen Semanal Test', $examen->getNombre());
    }
    
    public function testExamenSemanalShow(): void
    {
        $profesor = $this->loginAsProfesor();
        $examen = $this->createTestExamenSemanal($profesor);
        
        $this->client->request('GET', '/examen-semanal/' . $examen->getId());
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', $examen->getNombre());
    }
    
    public function testExamenSemanalEdit(): void
    {
        $profesor = $this->loginAsProfesor();
        $examen = $this->createTestExamenSemanal($profesor);
        
        $crawler = $this->client->request('GET', '/examen-semanal/' . $examen->getId() . '/edit');
        
        $this->assertResponseIsSuccessful();
        
        $form = $crawler->selectButton('Actualizar')->form([
            'examen_semanal[nombre]' => 'Examen Actualizado',
        ]);
        
        $this->client->submit($form);
        
        $this->entityManager->refresh($examen);
        $this->assertEquals('Examen Actualizado', $examen->getNombre());
    }
    
    public function testExamenSemanalNewConPreguntas(): void
    {
        $this->loginAsProfesor();
        
        $this->client->request('GET', '/examen-semanal/new-con-preguntas');
        
        $this->assertResponseIsSuccessful();
    }
    
    public function testExamenSemanalNewConPreguntasConvocatoria(): void
    {
        $this->loginAsProfesor();
        
        $this->client->request('GET', '/examen-semanal/new-con-preguntas-convocatoria');
        
        $this->assertResponseIsSuccessful();
    }
    
    public function testExamenSemanalTemasMunicipalesApi(): void
    {
        $this->loginAsProfesor();
        
        // Crear municipio y temas municipales para la prueba
        $municipio = new \App\Entity\Municipio();
        $municipio->setNombre('Municipio Test');
        $municipio->setActivo(true);
        $this->entityManager->persist($municipio);
        $this->entityManager->flush();
        
        $this->client->request('GET', '/examen-semanal/temas-municipales/' . $municipio->getId());
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
    }
    
    public function testExamenSemanalArticulosApi(): void
    {
        $this->loginAsProfesor();
        $ley = $this->createTestLey();
        $this->createTestArticulo($ley, 1);
        
        $this->client->request('GET', '/examen-semanal/articulos/' . $ley->getId());
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
    }
}
