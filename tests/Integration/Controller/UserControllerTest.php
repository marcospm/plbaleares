<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Integration\TestCase;

class UserControllerTest extends TestCase
{
    public function testUserIndexRequiresAdmin(): void
    {
        $this->client->request('GET', '/user');
        
        $this->assertResponseRedirects('/login');
    }
    
    public function testUserIndexAccessibleForAdmin(): void
    {
        $this->loginAsAdmin();
        
        $this->client->request('GET', '/user');
        
        $this->assertResponseIsSuccessful();
    }
    
    public function testUserIndexNotAccessibleForProfesor(): void
    {
        $this->loginAsProfesor();
        
        $this->client->request('GET', '/user');
        
        $this->assertResponseStatusCodeSame(403);
    }
}

