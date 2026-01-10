<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Integration\TestCase;

class RecursoPublicoControllerTest extends TestCase
{
    public function testRecursoPublicoIndexRequiresAuthentication(): void
    {
        $this->client->request('GET', '/recurso-publico');
        
        $this->assertResponseRedirects('/login');
    }
    
    public function testRecursoPublicoIndexAccessibleForUser(): void
    {
        $user = $this->createTestUser();
        $this->loginAsUser($user);
        
        $this->client->request('GET', '/recurso-publico');
        
        $this->assertResponseIsSuccessful();
    }
}

