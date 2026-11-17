<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Integration\TestCase;
use App\Entity\ExamenSemanal;
use App\Entity\Tema;

class ExamenSemanalControllerTest extends TestCase
{
    public function testExamenSemanalIndexRequiresProfesor(): void
    {
        $this->client->request('GET', '/examen-semanal');
        
        $this->assertResponseRedirects('/login');
    }
    
    public function testExamenSemanalIndexAccessibleForProfesor(): void
    {
        $this->loginAsProfesor();
        
        $this->client->request('GET', '/examen-semanal/');
        
        $this->assertResponseIsSuccessful();
    }
    
    public function testExamenSemanalNewForm(): void
    {
        $this->loginAsProfesor();
        
        $crawler = $this->client->request('GET', '/examen-semanal/new');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="examen_semanal"]');
    }
    
    public function testExamenSemanalCreate(): void
    {
        $profesor = $this->loginAsProfesor();
        $ley = $this->createTestLey();
        $tema = $this->createTestTema($ley);
        
        $crawler = $this->client->request('GET', '/examen-semanal/new');
        
        $this->assertResponseIsSuccessful();
        
        $fechaApertura = new \DateTime('+1 day');
        $fechaCierre = new \DateTime('+7 days');
        
        // Buscar el formulario por nombre en lugar del botón
        $form = $crawler->filter('form[name="examen_semanal"]')->form([
            'examen_semanal[nombre]' => 'Examen Semanal Test',
            'examen_semanal[descripcion]' => 'Descripción del examen',
            'examen_semanal[fechaApertura]' => $fechaApertura->format('Y-m-d H:i'),
            'examen_semanal[fechaCierre]' => $fechaCierre->format('Y-m-d H:i'),
            'examen_semanal[dificultad]' => 'facil',
            'examen_semanal[temas]' => [$tema->getId()],
        ]);
        
        $this->client->submit($form);
        
        // Verificar que el examen fue creado
        $examen = $this->entityManager->getRepository(ExamenSemanal::class)
            ->findOneBy(['nombre' => 'Examen Semanal Test']);
        
        $this->assertNotNull($examen);
        $this->assertEquals('Examen Semanal Test', $examen->getNombre());
    }
    
    public function testExamenSemanalShow(): void
    {
        $profesor = $this->loginAsProfesor();
        $examen = $this->createTestExamenSemanal($profesor);
        
        $this->client->request('GET', '/examen-semanal/' . $examen->getId());
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', $examen->getNombre());
    }
    
    public function testExamenSemanalEdit(): void
    {
        $profesor = $this->loginAsProfesor();
        $examen = $this->createTestExamenSemanal($profesor);
        
        $crawler = $this->client->request('GET', '/examen-semanal/' . $examen->getId() . '/edit');
        
        $this->assertResponseIsSuccessful();
        
        // Buscar el formulario por nombre en lugar del botón
        $form = $crawler->filter('form[name="examen_semanal"]')->form([
            'examen_semanal[nombre]' => 'Examen Actualizado',
        ]);
        
        $this->client->submit($form);
        
        // Buscar el examen actualizado desde la base de datos
        $this->entityManager->clear();
        $examenActualizado = $this->entityManager->getRepository(ExamenSemanal::class)
            ->find($examen->getId());
        $this->assertEquals('Examen Actualizado', $examenActualizado->getNombre());
    }
    
    public function testExamenSemanalNewConPreguntas(): void
    {
        $this->loginAsProfesor();
        
        $this->client->request('GET', '/examen-semanal/new-con-preguntas');
        
        $this->assertResponseIsSuccessful();
    }
    
    public function testExamenSemanalNewConPreguntasConvocatoria(): void
    {
        $this->loginAsProfesor();
        
        $this->client->request('GET', '/examen-semanal/new-con-preguntas-convocatoria');
        
        $this->assertResponseIsSuccessful();
    }
    
    public function testExamenSemanalTemasMunicipalesApi(): void
    {
        $this->loginAsProfesor();
        
        // Crear municipio y temas municipales para la prueba
        // Usar un nombre único para evitar duplicados
        $nombreUnico = 'Municipio Test ' . uniqid();
        $municipio = new \App\Entity\Municipio();
        $municipio->setNombre($nombreUnico);
        $municipio->setActivo(true);
        $this->entityManager->persist($municipio);
        $this->entityManager->flush();
        
        $this->client->request('GET', '/examen-semanal/temas-municipales/' . $municipio->getId());
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
    }
    
    public function testExamenSemanalArticulosApi(): void
    {
        $this->loginAsProfesor();
        $ley = $this->createTestLey();
        $this->createTestArticulo($ley, 1);
        
        $this->client->request('GET', '/examen-semanal/articulos/' . $ley->getId());
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function testEditarPreguntasDeExamenPendiente(): void
    {
        $profesor = $this->loginAsProfesor();
        $ley = $this->createTestLey('Ley examen pendiente');
        $tema = $this->createTestTema($ley, 'Tema examen pendiente');
        $articulo = $this->createTestArticulo($ley, 4, 'Artículo examen');
        $pregunta = $this->createTestPregunta($tema, $ley, $articulo);
        $examen = $this->createTestExamenSemanal($profesor, 'Examen con preguntas fijas');
        $examen->setModoCreacion('preguntas_especificas');
        $examen->addPregunta($pregunta);
        $examen->addTema($tema);
        $examen->setNumeroPreguntas(1);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/examen-semanal/'.$examen->getId().'/edit');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#anadir-pregunta');
        $this->assertSelectorTextContains('#preguntas-examen', '¿Pregunta de prueba?');

        $token = $crawler->filter('#preguntas-examen input[name="_token"]')->first()->attr('value');
        $this->client->request('POST', '/examen-semanal/'.$examen->getId().'/preguntas/'.$pregunta->getId(), [
            '_token' => $token,
            'texto' => 'Enunciado corregido',
            'opcionA' => 'Una',
            'opcionB' => 'Dos',
            'opcionC' => 'Tres',
            'opcionD' => 'Cuatro',
            'respuestaCorrecta' => 'B',
            'dificultad' => 'facil',
            'retroalimentacion' => 'Porque sí',
            'temaId' => (string) $tema->getId(),
            'leyId' => (string) $ley->getId(),
            'articuloId' => (string) $articulo->getId(),
        ]);
        $this->assertResponseRedirects('/examen-semanal/'.$examen->getId().'/edit#preguntas-examen');

        $this->client->request('POST', '/examen-semanal/'.$examen->getId().'/preguntas', [
            '_token' => $token,
            'texto' => 'Segunda pregunta',
            'opcionA' => 'A1',
            'opcionB' => 'B1',
            'opcionC' => 'C1',
            'opcionD' => 'D1',
            'respuestaCorrecta' => 'C',
            'dificultad' => 'facil',
            'temaId' => (string) $tema->getId(),
            'leyId' => (string) $ley->getId(),
            'articuloId' => (string) $articulo->getId(),
        ]);
        $this->assertResponseRedirects();

        $this->entityManager->clear();
        $examenActualizado = $this->entityManager->getRepository(\App\Entity\ExamenSemanal::class)->find($examen->getId());
        $this->assertCount(2, $examenActualizado->getPreguntas());
        $this->assertSame(2, $examenActualizado->getNumeroPreguntas());

        $segunda = null;
        $primera = null;
        foreach ($examenActualizado->getPreguntas() as $item) {
            if ($item->getTexto() === 'Segunda pregunta') {
                $segunda = $item;
            }
            if ($item->getTexto() === 'Enunciado corregido') {
                $primera = $item;
            }
        }
        $this->assertNotNull($primera);
        $this->assertSame('B', $primera->getRespuestaCorrecta());
        $this->assertNotNull($segunda);

        $this->client->request('POST', '/examen-semanal/'.$examen->getId().'/preguntas/'.$segunda->getId().'/quitar', [
            '_token' => $token,
        ]);
        $this->assertResponseRedirects();

        $this->entityManager->clear();
        $examenFinal = $this->entityManager->getRepository(\App\Entity\ExamenSemanal::class)->find($examen->getId());
        $this->assertCount(1, $examenFinal->getPreguntas());
        $this->assertSame('Enunciado corregido', $examenFinal->getPreguntas()->first()->getTexto());
        $this->assertSame(1, $examenFinal->getNumeroPreguntas());
    }

    public function testNoEditaPreguntasSiAlguienLoRealizo(): void
    {
        $profesor = $this->loginAsProfesor();
        $ley = $this->createTestLey('Ley examen realizado');
        $tema = $this->createTestTema($ley, 'Tema examen realizado');
        $articulo = $this->createTestArticulo($ley, 8);
        $pregunta = $this->createTestPregunta($tema, $ley, $articulo);
        $examen = $this->createTestExamenSemanal($profesor, 'Examen ya realizado');
        $examen->setModoCreacion('preguntas_especificas');
        $examen->addPregunta($pregunta);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/examen-semanal/'.$examen->getId().'/edit');
        $token = $crawler->filter('#preguntas-examen input[name="_token"]')->first()->attr('value');

        $alumno = $this->createTestUser('alumno_examen', 'alumno_examen@test.com');
        $realizado = $this->createTestExamen($alumno);
        $examen->addExamen($realizado);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/examen-semanal/'.$examen->getId().'/edit');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('#anadir-pregunta');
        $this->assertSelectorTextContains('#preguntas-examen', 'Ya hay alumnos');

        $this->client->request('POST', '/examen-semanal/'.$examen->getId().'/preguntas/'.$pregunta->getId(), [
            '_token' => $token,
            'texto' => 'No debería guardarse',
            'opcionA' => 'Una',
            'opcionB' => 'Dos',
            'opcionC' => 'Tres',
            'opcionD' => 'Cuatro',
            'respuestaCorrecta' => 'A',
            'dificultad' => 'facil',
            'temaId' => (string) $tema->getId(),
            'leyId' => (string) $ley->getId(),
            'articuloId' => (string) $articulo->getId(),
        ]);
        $this->assertResponseRedirects();

        $this->entityManager->clear();
        $sinCambios = $this->entityManager->getRepository(\App\Entity\Pregunta::class)->find($pregunta->getId());
        $this->assertSame('¿Pregunta de prueba?', $sinCambios->getTexto());
    }
}
