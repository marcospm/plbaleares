<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Integration\TestCase;

class SecurityControllerTest extends TestCase
{
    public function testLoginPageIsAccessible(): void
    {
        $this->client->request('GET', '/login');
        
        $this->assertResponseIsSuccessful();
        // El formulario de login puede no tener un nombre específico, verificar que existe un formulario
        $this->assertSelectorExists('form');
    }
    
    public function testLoginWithValidCredentials(): void
    {
        $user = $this->createTestUser('testuser', 'test@test.com', 'password123');
        
        $crawler = $this->client->request('GET', '/login');
        // Buscar el formulario directamente en lugar del botón
        $form = $crawler->filter('form')->first()->form([
            'username' => 'testuser',
            'password' => 'password123',
        ]);
        
        $this->client->submit($form);
        
        // Verificar que redirige (puede ser a /dashboard o /dashboard?login=success)
        $this->assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('/dashboard', $location);
    }
    
    public function testLoginWithInvalidCredentials(): void
    {
        $crawler = $this->client->request('GET', '/login');
        // Buscar el formulario directamente en lugar del botón
        $form = $crawler->filter('form')->first()->form([
            'username' => 'nonexistent',
            'password' => 'wrongpassword',
        ]);
        
        $this->client->submit($form);
        
        // Verificar que redirige a /login
        $this->assertResponseRedirects('/login');
    }
    
    public function testLogout(): void
    {
        $user = $this->createTestUser();
        $this->loginAsUser($user);
        
        $this->client->request('GET', '/logout');
        
        $this->assertResponseRedirects('/');
    }
}

