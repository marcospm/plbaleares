<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Integration\TestCase;

class DashboardControllerTest extends TestCase
{
    public function testDashboardRequiresAuthentication(): void
    {
        $this->client->request('GET', '/dashboard');
        
        $this->assertResponseRedirects('/login');
    }
    
    public function testDashboardAccessibleForUser(): void
    {
        $user = $this->createTestUser();
        $this->loginAsUser($user);
        
        $this->client->request('GET', '/dashboard');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
    }
    
    public function testDashboardAccessibleForProfesor(): void
    {
        $this->loginAsProfesor();
        
        $this->client->request('GET', '/dashboard');
        
        $this->assertResponseIsSuccessful();
    }
}

