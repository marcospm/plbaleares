<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Integration\TestCase;
use App\Entity\Ley;

class LeyControllerTest extends TestCase
{
    public function testLeyIndexRequiresAuthentication(): void
    {
        $this->client->request('GET', '/ley');
        
        $this->assertResponseRedirects('/login');
    }
    
    public function testLeyIndexAccessibleForProfesor(): void
    {
        $this->loginAsProfesor();
        
        $this->client->request('GET', '/ley');
        
        $this->assertResponseIsSuccessful();
    }
    
    public function testLeyNew(): void
    {
        $this->loginAsProfesor();
        
        $crawler = $this->client->request('GET', '/ley/new');
        
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('Guardar')->form([
            'ley[nombre]' => 'Nueva Ley de Prueba',
            'ley[descripcion]' => 'Descripción de prueba',
        ]);
        
        $this->client->submit($form);
        
        $this->assertResponseRedirects();
        $ley = $this->entityManager->getRepository(Ley::class)
            ->findOneBy(['nombre' => 'Nueva Ley de Prueba']);
        
        $this->assertNotNull($ley);
    }
    
    public function testLeyShow(): void
    {
        $this->loginAsProfesor();
        $ley = $this->createTestLey();
        
        $this->client->request('GET', '/ley/' . $ley->getId());
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', $ley->getNombre());
    }
}

