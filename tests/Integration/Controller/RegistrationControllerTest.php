<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Integration\TestCase;
use App\Entity\User;

class RegistrationControllerTest extends TestCase
{
    public function testRegistrationPageIsAccessible(): void
    {
        $this->client->request('GET', '/register');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="registration_form"]');
    }
    
    public function testRegistrationWithValidData(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->selectButton('Registrarse')->form([
            'registration_form[username]' => 'newuser',
            'registration_form[email]' => 'newuser@test.com',
            'registration_form[plainPassword]' => 'password123',
            'registration_form[agreeTerms]' => true,
        ]);
        
        $this->client->submit($form);
        
        // La respuesta puede ser un redirect o una página de éxito
        $this->assertResponseIsSuccessful();
        
        // Verificar que el usuario fue creado
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['username' => 'newuser']);
        
        $this->assertNotNull($user);
    }
}

