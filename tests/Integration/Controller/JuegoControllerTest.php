<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Integration\TestCase;

class JuegoControllerTest extends TestCase
{
    public function testJuegosIndexRequiresAuthentication(): void
    {
        $this->client->request('GET', '/juegos');
        
        $this->assertResponseRedirects('/login');
    }
    
    public function testJuegosIndexAccessibleForUser(): void
    {
        $user = $this->createTestUser();
        $this->loginAsUser($user);
        
        $this->client->request('GET', '/juegos');
        
        // Puede redirigir a un juego específico
        $this->assertResponseIsSuccessful();
    }
    
    public function testAdivinaNumeroArticuloAccessible(): void
    {
        $user = $this->createTestUser();
        $this->loginAsUser($user);
        
        $this->client->request('GET', '/juegos/adivina-numero-articulo');
        
        $this->assertResponseIsSuccessful();
    }
    
    public function testCompletaTextoLegalAccessible(): void
    {
        $user = $this->createTestUser();
        $this->loginAsUser($user);
        
        $this->client->request('GET', '/juegos/completa-texto-legal');
        
        $this->assertResponseIsSuccessful();
    }
    
    public function testApiArticulosTextoLegalLote(): void
    {
        $user = $this->createTestUser();
        $this->loginAsUser($user);
        
        $ley = $this->createTestLey();
        $this->createTestArticulo($ley, 1, 'Test', 'Texto legal de prueba');
        
        $this->client->request('GET', '/api/juegos/articulos-texto-legal-lote');
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
    }
}

