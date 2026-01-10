<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Integration\TestCase;
use App\Entity\Pregunta;

class PreguntaControllerTest extends TestCase
{
    public function testPreguntaIndexRequiresProfesor(): void
    {
        $this->client->request('GET', '/pregunta');
        
        $this->assertResponseRedirects('/login');
    }
    
    public function testPreguntaIndexAccessibleForProfesor(): void
    {
        $this->loginAsProfesor();
        
        $this->client->request('GET', '/pregunta');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Preguntas');
    }
    
    public function testPreguntaNewForm(): void
    {
        $this->loginAsProfesor();
        
        $crawler = $this->client->request('GET', '/pregunta/new');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="pregunta"]');
    }
    
    public function testPreguntaCreate(): void
    {
        $this->loginAsProfesor();
        $ley = $this->createTestLey();
        $tema = $this->createTestTema($ley);
        $articulo = $this->createTestArticulo($ley);
        
        $crawler = $this->client->request('GET', '/pregunta/new');
        
        $this->assertResponseIsSuccessful();
        
        $form = $crawler->selectButton('Guardar')->form([
            'pregunta[texto]' => '¿Pregunta de prueba?',
            'pregunta[dificultad]' => 'facil',
            'pregunta[opcionA]' => 'Opción A correcta',
            'pregunta[opcionB]' => 'Opción B',
            'pregunta[opcionC]' => 'Opción C',
            'pregunta[opcionD]' => 'Opción D',
            'pregunta[respuestaCorrecta]' => 'A',
            'pregunta[retroalimentacion]' => 'Retroalimentación de prueba',
            'pregunta[tema]' => $tema->getId(),
            'pregunta[ley]' => $ley->getId(),
            'pregunta[articulo]' => $articulo->getId(),
        ]);
        
        $this->client->submit($form);
        
        // Verificar que la pregunta fue creada
        $pregunta = $this->entityManager->getRepository(Pregunta::class)
            ->findOneBy(['texto' => '¿Pregunta de prueba?']);
        
        $this->assertNotNull($pregunta);
        $this->assertEquals('¿Pregunta de prueba?', $pregunta->getTexto());
        $this->assertEquals('A', $pregunta->getRespuestaCorrecta());
    }
    
    public function testPreguntaShow(): void
    {
        $this->loginAsProfesor();
        $ley = $this->createTestLey();
        $tema = $this->createTestTema($ley);
        $articulo = $this->createTestArticulo($ley);
        $pregunta = $this->createTestPregunta($tema, $ley, $articulo);
        
        $this->client->request('GET', '/pregunta/' . $pregunta->getId());
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Pregunta');
    }
    
    public function testPreguntaEdit(): void
    {
        $this->loginAsProfesor();
        $ley = $this->createTestLey();
        $tema = $this->createTestTema($ley);
        $articulo = $this->createTestArticulo($ley);
        $pregunta = $this->createTestPregunta($tema, $ley, $articulo);
        
        $crawler = $this->client->request('GET', '/pregunta/' . $pregunta->getId() . '/edit');
        
        $this->assertResponseIsSuccessful();
        
        $form = $crawler->selectButton('Actualizar')->form([
            'pregunta[texto]' => '¿Pregunta actualizada?',
        ]);
        
        $this->client->submit($form);
        
        $this->entityManager->refresh($pregunta);
        $this->assertEquals('¿Pregunta actualizada?', $pregunta->getTexto());
    }
    
    public function testPreguntaDelete(): void
    {
        $this->loginAsProfesor();
        $ley = $this->createTestLey();
        $tema = $this->createTestTema($ley);
        $articulo = $this->createTestArticulo($ley);
        $pregunta = $this->createTestPregunta($tema, $ley, $articulo);
        $preguntaId = $pregunta->getId();
        
        $crawler = $this->client->request('GET', '/pregunta/' . $preguntaId);
        $form = $crawler->selectButton('Eliminar')->form();
        
        $this->client->submit($form);
        
        $pregunta = $this->entityManager->getRepository(Pregunta::class)->find($preguntaId);
        $this->assertNull($pregunta);
    }
    
    public function testPreguntaIndexWithFilters(): void
    {
        $this->loginAsProfesor();
        $ley = $this->createTestLey();
        $tema = $this->createTestTema($ley);
        $articulo = $this->createTestArticulo($ley);
        $pregunta1 = $this->createTestPregunta($tema, $ley, $articulo, 'facil');
        $pregunta2 = $this->createTestPregunta($tema, $ley, $articulo, 'moderada');
        
        // Filtrar por dificultad
        $this->client->request('GET', '/pregunta?dificultad=facil');
        
        $this->assertResponseIsSuccessful();
        
        // Filtrar por tema
        $this->client->request('GET', '/pregunta?tema=' . $tema->getId());
        
        $this->assertResponseIsSuccessful();
        
        // Filtrar por ley
        $this->client->request('GET', '/pregunta?ley=' . $ley->getId());
        
        $this->assertResponseIsSuccessful();
    }
}
