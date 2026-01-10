<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Integration\TestCase;

class HomeControllerTest extends TestCase
{
    public function testHomePageIsAccessible(): void
    {
        $this->client->request('GET', '/');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'BISPOL');
    }
    
    public function testHomePageShowsRegistrationButtonForAnonymous(): void
    {
        $this->client->request('GET', '/');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href*="register"]');
    }
}

