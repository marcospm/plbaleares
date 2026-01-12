<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Integration\TestCase;

class BoibControllerTest extends TestCase
{
    public function testBoibIndexRequiresAuthentication(): void
    {
        $this->client->request('GET', '/boib');
        
        $this->assertResponseRedirects('/login');
    }
    
    public function testBoibIndexAccessibleForUser(): void
    {
        $user = $this->createTestUser();
        $this->loginAsUser($user);
        
        $this->client->request('GET', '/boib/');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'BOIB');
    }
}

