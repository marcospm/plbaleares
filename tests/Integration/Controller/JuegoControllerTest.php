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
        
        // Puede redirigir a un juego específico, verificar que hay una respuesta válida
        $this->assertTrue($this->client->getResponse()->isRedirect() || $this->client->getResponse()->isSuccessful());
        if ($this->client->getResponse()->isRedirect()) {
            $location = $this->client->getResponse()->headers->get('Location');
            $this->assertStringContainsString('/juegos', $location);
        }
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
        
        $this->client->request('GET', '/api/juegos/articulos-texto-legal-lote');
        
        // La API puede devolver 404 si no hay artículos o 200 si los hay
        // Verificar que la respuesta es JSON válida
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
    }
}

