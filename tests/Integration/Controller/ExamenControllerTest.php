<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Integration\TestCase;
use App\Entity\Examen;
use App\Entity\Pregunta;

class ExamenControllerTest extends TestCase
{
    public function testExamenIniciarRequiresAuthentication(): void
    {
        $this->client->request('GET', '/examen/iniciar');
        
        $this->assertResponseRedirects('/login');
    }
    
    public function testExamenIniciarAccessibleForUser(): void
    {
        $user = $this->createTestUser();
        $this->loginAsUser($user);
        
        $this->client->request('GET', '/examen/iniciar');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="examen_iniciar"]');
    }
    
    public function testExamenIniciarForm(): void
    {
        $user = $this->createTestUser();
        $this->loginAsUser($user);
        
        $ley = $this->createTestLey();
        $tema = $this->createTestTema($ley);
        $articulo = $this->createTestArticulo($ley);
        $pregunta = $this->createTestPregunta($tema, $ley, $articulo);
        
        $crawler = $this->client->request('GET', '/examen/iniciar');
        
        $this->assertResponseIsSuccessful();
        
        // Verificar que el formulario está presente
        $this->assertSelectorExists('form[name="examen_iniciar"]');
    }
    
    public function testExamenHistorialAccessible(): void
    {
        $user = $this->createTestUser();
        $this->loginAsUser($user);
        
        // Crear algunos exámenes de prueba
        $this->createTestExamen($user, 10.0, 'facil');
        $this->createTestExamen($user, 15.5, 'moderada');
        
        $this->client->request('GET', '/examen/historial');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Historial');
    }
    
    public function testExamenHistorialWithFilters(): void
    {
        $user = $this->createTestUser();
        $this->loginAsUser($user);
        
        $this->createTestExamen($user, 10.0, 'facil');
        $this->createTestExamen($user, 15.5, 'moderada');
        
        // Filtrar por dificultad
        $this->client->request('GET', '/examen/historial?dificultad=facil');
        
        $this->assertResponseIsSuccessful();
    }
    
    public function testExamenBorradorSave(): void
    {
        $user = $this->createTestUser();
        $this->loginAsUser($user);
        
        $ley = $this->createTestLey();
        $tema = $this->createTestTema($ley);
        $articulo = $this->createTestArticulo($ley);
        $pregunta = $this->createTestPregunta($tema, $ley, $articulo);
        
        // Simular guardar un borrador
        $this->client->request('POST', '/examen/borrador/guardar', [
            'preguntas' => [$pregunta->getId()],
            'tipoExamen' => 'general',
            'dificultad' => 'facil',
        ]);
        
        // El endpoint puede retornar JSON o redirección
        $this->assertResponseIsSuccessful();
    }
    
    public function testExamenCompletar(): void
    {
        $user = $this->createTestUser();
        $this->loginAsUser($user);
        
        $ley = $this->createTestLey();
        $tema = $this->createTestTema($ley);
        $articulo = $this->createTestArticulo($ley);
        $pregunta1 = $this->createTestPregunta($tema, $ley, $articulo);
        $pregunta2 = $this->createTestPregunta($tema, $ley, $articulo, 'moderada');
        
        // Simular completar un examen
        $this->client->request('POST', '/examen/completar', [
            'preguntas' => [
                $pregunta1->getId() => 'A',
                $pregunta2->getId() => 'B',
            ],
            'tipoExamen' => 'general',
            'dificultad' => 'facil',
        ]);
        
        // Verificar que se creó el examen
        $examenes = $this->entityManager->getRepository(Examen::class)
            ->findBy(['usuario' => $user]);
        
        $this->assertNotEmpty($examenes);
    }
}
