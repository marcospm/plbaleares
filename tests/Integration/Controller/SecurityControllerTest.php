<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Integration\TestCase;

class SecurityControllerTest extends TestCase
{
    public function testLoginPageIsAccessible(): void
    {
        $this->client->request('GET', '/login');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="login"]');
    }
    
    public function testLoginWithValidCredentials(): void
    {
        $user = $this->createTestUser('testuser', 'test@test.com', 'password123');
        
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Iniciar sesión')->form([
            'username' => 'testuser',
            'password' => 'password123',
        ]);
        
        $this->client->submit($form);
        
        $this->assertResponseRedirects('/dashboard');
    }
    
    public function testLoginWithInvalidCredentials(): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Iniciar sesión')->form([
            'username' => 'nonexistent',
            'password' => 'wrongpassword',
        ]);
        
        $this->client->submit($form);
        
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

