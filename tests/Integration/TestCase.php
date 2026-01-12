<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;

/**
 * Clase base para tests de integración
 * Configura la base de datos de prueba y proporciona utilidades comunes
 */
abstract class TestCase extends WebTestCase
{
    protected EntityManagerInterface $entityManager;
    protected Connection $connection;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Obtener el cliente HTTP (esto bootea el kernel automáticamente)
        $this->client = static::createClient();
        
        // Obtener el entity manager del contenedor del cliente
        $this->entityManager = $this->client->getContainer()->get('doctrine')->getManager();
        $this->connection = $this->entityManager->getConnection();
        
        // Limpiar y recrear el esquema de la base de datos de prueba
        $this->setupDatabase();
    }
    
    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Limpiar después de cada test
        if (isset($this->entityManager)) {
            $this->entityManager->close();
        }
    }
    
    /**
     * Configura la base de datos de prueba
     */
    protected function setupDatabase(): void
    {
        // La base de datos se debe crear manualmente o mediante migraciones
        // Por ahora, solo nos aseguramos de que el esquema esté actualizado
        // En producción, usarías: php bin/console doctrine:schema:create --env=test
    }
    
    /**
     * Crea un usuario de prueba
     */
    protected function createTestUser(
        string $username = 'testuser',
        string $email = 'test@test.com',
        string $password = 'password123',
        array $roles = ['ROLE_USER']
    ): \App\Entity\User {
        $userRepository = $this->entityManager->getRepository(\App\Entity\User::class);
        $user = $userRepository->findOneBy(['username' => $username]);
        
        if (!$user) {
            $user = new \App\Entity\User();
            $user->setUsername($username);
            $user->setEmail($email);
            $user->setPassword(password_hash($password, PASSWORD_DEFAULT));
            $user->setRoles($roles);
            $user->setNombre('Test User');
            $user->setActivo(true);
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }
        
        return $user;
    }
    
    /**
     * Crea una ley de prueba
     */
    protected function createTestLey(string $nombre = 'Ley de Prueba'): \App\Entity\Ley
    {
        $ley = new \App\Entity\Ley();
        $ley->setNombre($nombre);
        $ley->setActivo(true);
        
        $this->entityManager->persist($ley);
        $this->entityManager->flush();
        
        return $ley;
    }
    
    /**
     * Crea un artículo de prueba
     */
    protected function createTestArticulo(
        \App\Entity\Ley $ley,
        int $numero = 1,
        ?string $nombre = 'Artículo de Prueba',
        ?string $textoLegal = 'Texto legal de prueba para el artículo'
    ): \App\Entity\Articulo {
        $articulo = new \App\Entity\Articulo();
        $articulo->setNumero($numero);
        $articulo->setNombre($nombre);
        $articulo->setLey($ley);
        $articulo->setTextoLegal($textoLegal);
        $articulo->setExplicacion('Explicación de prueba');
        
        $this->entityManager->persist($articulo);
        $this->entityManager->flush();
        
        return $articulo;
    }
    
    /**
     * Crea un tema de prueba
     */
    protected function createTestTema(\App\Entity\Ley $ley, string $nombre = 'Tema de Prueba'): \App\Entity\Tema
    {
        $tema = new \App\Entity\Tema();
        $tema->setNombre($nombre);
        $tema->setDescripcion('Descripción del tema');
        $tema->setActivo(true);
        $tema->addLey($ley);
        
        $this->entityManager->persist($tema);
        $this->entityManager->flush();
        
        return $tema;
    }
    
    /**
     * Crea una pregunta de prueba
     */
    protected function createTestPregunta(
        \App\Entity\Tema $tema,
        \App\Entity\Ley $ley,
        \App\Entity\Articulo $articulo,
        string $dificultad = 'facil'
    ): \App\Entity\Pregunta {
        $pregunta = new \App\Entity\Pregunta();
        $pregunta->setTexto('¿Pregunta de prueba?');
        $pregunta->setDificultad($dificultad);
        $pregunta->setOpcionA('Opción A');
        $pregunta->setOpcionB('Opción B');
        $pregunta->setOpcionC('Opción C');
        $pregunta->setOpcionD('Opción D');
        $pregunta->setRespuestaCorrecta('A');
        $pregunta->setRetroalimentacion('Retroalimentación de prueba');
        $pregunta->setTema($tema);
        $pregunta->setLey($ley);
        $pregunta->setArticulo($articulo);
        
        $this->entityManager->persist($pregunta);
        $this->entityManager->flush();
        
        return $pregunta;
    }
    
    /**
     * Crea una planificación de prueba
     */
    protected function createTestPlanificacion(
        \App\Entity\User $usuario,
        \App\Entity\User $creadoPor,
        string $nombre = 'Planificación Test'
    ): \App\Entity\PlanificacionPersonalizada {
        $planificacion = new \App\Entity\PlanificacionPersonalizada();
        $planificacion->setUsuario($usuario);
        $planificacion->setCreadoPor($creadoPor);
        $planificacion->setNombre($nombre);
        $planificacion->setDescripcion('Descripción test');
        $planificacion->setFechaInicio(new \DateTime('+1 day'));
        $planificacion->setFechaFin(new \DateTime('+7 days'));
        
        $this->entityManager->persist($planificacion);
        $this->entityManager->flush();
        
        return $planificacion;
    }
    
    /**
     * Crea un examen semanal de prueba
     */
    protected function createTestExamenSemanal(
        \App\Entity\User $creadoPor,
        string $nombre = 'Examen Semanal Test',
        string $dificultad = 'facil'
    ): \App\Entity\ExamenSemanal {
        $examen = new \App\Entity\ExamenSemanal();
        $examen->setNombre($nombre);
        $examen->setDescripcion('Descripción test');
        $examen->setFechaApertura(new \DateTime('+1 day'));
        $examen->setFechaCierre(new \DateTime('+7 days'));
        $examen->setDificultad($dificultad);
        $examen->setCreadoPor($creadoPor);
        $examen->setActivo(true);
        
        $this->entityManager->persist($examen);
        $this->entityManager->flush();
        
        return $examen;
    }
    
    /**
     * Crea un examen de prueba
     */
    protected function createTestExamen(
        \App\Entity\User $usuario,
        float $nota = 10.0,
        string $dificultad = 'facil'
    ): \App\Entity\Examen {
        $examen = new \App\Entity\Examen();
        $examen->setUsuario($usuario);
        $examen->setNota((string) $nota);
        $examen->setFecha(new \DateTime());
        $examen->setDificultad($dificultad);
        $examen->setNumeroPreguntas(20);
        $examen->setAciertos(15);
        $examen->setErrores(3);
        $examen->setEnBlanco(2);
        $examen->setRespuestas([]);
        
        $this->entityManager->persist($examen);
        $this->entityManager->flush();
        
        return $examen;
    }
    
    /**
     * Hace login como usuario
     */
    protected function loginAsUser(\App\Entity\User $user): void
    {
        $this->client->loginUser($user);
    }
    
    /**
     * Hace login como profesor
     */
    protected function loginAsProfesor(): \App\Entity\User
    {
        $user = $this->createTestUser(
            'profesor',
            'profesor@test.com',
            'password123',
            ['ROLE_USER', 'ROLE_PROFESOR']
        );
        $this->loginAsUser($user);
        return $user;
    }
    
    /**
     * Hace login como admin
     */
    protected function loginAsAdmin(): \App\Entity\User
    {
        $user = $this->createTestUser(
            'admin',
            'admin@test.com',
            'password123',
            ['ROLE_USER', 'ROLE_ADMIN']
        );
        $this->loginAsUser($user);
        return $user;
    }
}

