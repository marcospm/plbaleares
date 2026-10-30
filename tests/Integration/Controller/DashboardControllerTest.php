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
        $this->assertSelectorTextContains('#ver-rankings-btn', 'Ver Rankings');
    }

    public function testRankingsLoadOnlyWhenRequested(): void
    {
        $user = $this->createTestUser();
        $this->loginAsUser($user);

        $this->client->request('GET', '/dashboard/rankings');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', 'Mi Ranking (Temario General)');
    }
    
    public function testDashboardAccessibleForProfesor(): void
    {
        $this->loginAsProfesor();
        
        $this->client->request('GET', '/dashboard');
        
        $this->assertResponseIsSuccessful();
    }
}

