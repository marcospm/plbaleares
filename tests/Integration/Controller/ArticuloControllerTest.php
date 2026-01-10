<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Integration\TestCase;
use App\Entity\Articulo;

class ArticuloControllerTest extends TestCase
{
    public function testArticuloIndexRequiresAuthentication(): void
    {
        $this->client->request('GET', '/articulo');
        
        $this->assertResponseRedirects('/login');
    }
    
    public function testArticuloIndexAccessibleForProfesor(): void
    {
        $this->loginAsProfesor();
        
        $this->client->request('GET', '/articulo');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Artículos');
    }
    
    public function testArticuloNewForm(): void
    {
        $this->loginAsProfesor();
        $ley = $this->createTestLey();
        
        $crawler = $this->client->request('GET', '/articulo/new');
        
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('Guardar')->form([
            'articulo[numero]' => 1,
            'articulo[nombre]' => 'Test Artículo',
            'articulo[ley]' => $ley->getId(),
        ]);
        
        $this->client->submit($form);
        
        $this->assertResponseRedirects('/articulo');
        $articulo = $this->entityManager->getRepository(Articulo::class)
            ->findOneBy(['numero' => 1]);
        
        $this->assertNotNull($articulo);
        $this->assertEquals('Test Artículo', $articulo->getNombre());
    }
    
    public function testArticuloEdit(): void
    {
        $this->loginAsProfesor();
        $ley = $this->createTestLey();
        $articulo = $this->createTestArticulo($ley, 1, 'Original');
        
        $crawler = $this->client->request('GET', '/articulo/' . $articulo->getId() . '/edit');
        
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('Actualizar')->form([
            'articulo[nombre]' => 'Updated Name',
        ]);
        
        $this->client->submit($form);
        
        $this->entityManager->refresh($articulo);
        $this->assertEquals('Updated Name', $articulo->getNombre());
    }
    
    public function testArticuloShow(): void
    {
        $this->loginAsProfesor();
        $ley = $this->createTestLey();
        $articulo = $this->createTestArticulo($ley, 1, 'Test Article');
        
        $this->client->request('GET', '/articulo/' . $articulo->getId());
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Artículo');
    }
    
    public function testArticuloDelete(): void
    {
        $this->loginAsProfesor();
        $ley = $this->createTestLey();
        $articulo = $this->createTestArticulo($ley, 1);
        $articuloId = $articulo->getId();
        
        $crawler = $this->client->request('GET', '/articulo/' . $articuloId);
        $form = $crawler->selectButton('Eliminar')->form();
        
        $this->client->submit($form);
        
        $articulo = $this->entityManager->getRepository(Articulo::class)->find($articuloId);
        $this->assertNull($articulo);
    }
}

