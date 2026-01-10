<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Integration\TestCase;

class ArticuloPublicoControllerTest extends TestCase
{
    public function testArticuloPublicoIndexRequiresAuthentication(): void
    {
        $this->client->request('GET', '/articulo-publico');
        
        $this->assertResponseRedirects('/login');
    }
    
    public function testArticuloPublicoIndexAccessibleForUser(): void
    {
        $user = $this->createTestUser();
        $this->loginAsUser($user);
        
        $this->client->request('GET', '/articulo-publico');
        
        $this->assertResponseIsSuccessful();
    }
    
    public function testArticuloPublicoShow(): void
    {
        $user = $this->createTestUser();
        $this->loginAsUser($user);
        $ley = $this->createTestLey();
        $articulo = $this->createTestArticulo($ley, 1, 'Test Article');
        
        $this->client->request('GET', '/articulo-publico/' . $articulo->getId());
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Artículo');
    }
}

