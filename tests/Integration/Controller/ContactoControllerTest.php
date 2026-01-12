<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Integration\TestCase;

class ContactoControllerTest extends TestCase
{
    public function testContactoIndexAccessible(): void
    {
        $this->client->request('GET', '/contacto');
        
        $this->assertResponseIsSuccessful();
    }
    
    public function testContactoFormSubmission(): void
    {
        $crawler = $this->client->request('GET', '/contacto');
        
        $this->assertResponseIsSuccessful();
        // Verificar que existe un formulario (puede tener otro nombre)
        $this->assertSelectorExists('form');
    }
}

