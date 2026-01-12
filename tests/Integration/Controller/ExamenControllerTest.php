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
        
        // Primero iniciar un examen para tener datos en la sesión
        $crawler = $this->client->request('GET', '/examen/iniciar');
        $this->assertResponseIsSuccessful();
        
        // Obtener el formulario y usar el primer tema disponible (ID 1)
        $form = $crawler->selectButton('Iniciar Examen')->form();
        $form['examen_iniciar[tipoExamen]'] = 'general';
        $form['examen_iniciar[dificultad]'] = 'facil';
        $form['examen_iniciar[numeroPreguntas]'] = 20;
        $form['examen_iniciar[tiempoLimite]'] = 30;
        // Usar el tema con ID 1 que está disponible en el formulario (como string)
        $form['examen_iniciar[temas]'] = ['1'];
        
        $this->client->submit($form);
        
        // Seguir la redirección para llegar a la primera pregunta
        $this->client->followRedirect();
        
        // Ahora guardar el borrador desde la primera pregunta
        $this->client->request('POST', '/examen/pregunta/1', [
            'accion' => 'guardar_borrador',
            'tiempo_restante' => 300,
        ]);
        
        // Debe redirigir a iniciar
        $this->assertResponseRedirects();
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
        
        // Primero iniciar un examen
        $crawler = $this->client->request('GET', '/examen/iniciar');
        $this->assertResponseIsSuccessful();
        
        // Obtener el formulario y usar el primer tema disponible (ID 1)
        $form = $crawler->selectButton('Iniciar Examen')->form();
        $form['examen_iniciar[tipoExamen]'] = 'general';
        $form['examen_iniciar[dificultad]'] = 'facil';
        $form['examen_iniciar[numeroPreguntas]'] = 20;
        $form['examen_iniciar[tiempoLimite]'] = 30;
        // Usar el tema con ID 1 que está disponible en el formulario
        $form['examen_iniciar[temas]'] = ['1'];
        
        $this->client->submit($form);
        
        // Seguir la redirección para llegar a la primera pregunta
        $this->client->followRedirect();
        
        // Responder la primera pregunta
        $this->client->request('POST', '/examen/pregunta/1', [
            'respuesta' => 'A',
            'accion' => 'siguiente',
        ]);
        
        // Verificar que hay una respuesta (puede ser redirección o éxito)
        $this->assertTrue($this->client->getResponse()->isRedirect() || $this->client->getResponse()->isSuccessful());
        
        // Si hay redirección, seguirla
        if ($this->client->getResponse()->isRedirect()) {
            $this->client->followRedirect();
        }
        
        // Responder la segunda pregunta y finalizar
        $this->client->request('POST', '/examen/pregunta/2', [
            'respuesta' => 'B',
            'accion' => 'finalizar',
        ]);
        
        // Verificar que hay una respuesta (puede ser redirección o éxito)
        $this->assertTrue($this->client->getResponse()->isRedirect() || $this->client->getResponse()->isSuccessful());
        
        // Verificar que se creó el examen
        $this->entityManager->clear();
        $examenes = $this->entityManager->getRepository(Examen::class)
            ->findBy(['usuario' => $user]);
        
        $this->assertNotEmpty($examenes);
    }
}
