<?php

namespace App\Service;

use App\Entity\Articulo;
use App\Entity\Examen;
use App\Entity\Notificacion;
use App\Entity\TareaAsignada;
use App\Entity\User;
use App\Repository\NotificacionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificacionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificacionRepository $notificacionRepository,
        private UserRepository $userRepository
    ) {
    }

    /**
     * Crea una notificación cuando un alumno completa un examen
     */
    public function crearNotificacionExamen(Examen $examen): void
    {
        $alumno = $examen->getUsuario();
        
        // Obtener profesores asignados al alumno
        $profesores = $alumno->getProfesores();
        
        if ($profesores->isEmpty()) {
            return; // No hay profesores asignados
        }

        foreach ($profesores as $profesor) {
            $notificacion = new Notificacion();
            $notificacion->setTipo(Notificacion::TIPO_EXAMEN);
            $notificacion->setTitulo('Nuevo Examen Completado');
            $notificacion->setMensaje(
                sprintf(
                    '%s ha completado un examen de dificultad %s con una nota de %s.',
                    $alumno->getUsername(),
                    $examen->getDificultadLabel(),
                    number_format((float)$examen->getNota(), 2, ',', '.')
                )
            );
            $notificacion->setProfesor($profesor);
            $notificacion->setAlumno($alumno);
            $notificacion->setExamen($examen);
            
            $this->entityManager->persist($notificacion);
        }
        
        $this->entityManager->flush();
    }

    /**
     * Crea una notificación cuando un alumno completa una tarea
     */
    public function crearNotificacionTarea(TareaAsignada $tareaAsignada): void
    {
        $alumno = $tareaAsignada->getUsuario();
        $tarea = $tareaAsignada->getTarea();
        
        // Obtener profesores asignados al alumno
        $profesores = $alumno->getProfesores();
        
        if ($profesores->isEmpty()) {
            return; // No hay profesores asignados
        }

        foreach ($profesores as $profesor) {
            $notificacion = new Notificacion();
            $notificacion->setTipo(Notificacion::TIPO_TAREA);
            $notificacion->setTitulo('Tarea Completada');
            $notificacion->setMensaje(
                sprintf(
                    '%s ha completado la tarea: "%s".',
                    $alumno->getUsername(),
                    $tarea->getNombre()
                )
            );
            $notificacion->setProfesor($profesor);
            $notificacion->setAlumno($alumno);
            $notificacion->setTareaAsignada($tareaAsignada);
            
            $this->entityManager->persist($notificacion);
        }
        
        $this->entityManager->flush();
    }

    /**
     * Crea una notificación cuando un alumno reporta un error en un artículo
     * Las notificaciones se envían a todos los profesores y administradores
     */
    public function crearNotificacionErrorArticulo(Articulo $articulo, User $alumno, string $mensaje): void
    {
        // Obtener todos los profesores y administradores
        $profesoresYAdmins = $this->userRepository->createQueryBuilder('u')
            ->where('u.roles LIKE :roleProfesor OR u.roles LIKE :roleAdmin')
            ->andWhere('u.activo = :activo')
            ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
            ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
            ->setParameter('activo', true)
            ->getQuery()
            ->getResult();

        foreach ($profesoresYAdmins as $profesor) {
            $notificacion = new Notificacion();
            $notificacion->setTipo(Notificacion::TIPO_ERROR_ARTICULO);
            $notificacion->setTitulo('Error Reportado en Artículo');
            $notificacion->setMensaje(
                sprintf(
                    '%s ha reportado un error o solicita corrección en el Artículo %s%s. Mensaje: %s',
                    $alumno->getUsername(),
                    $articulo->getNumero(),
                    $articulo->getNombre() ? ' - ' . $articulo->getNombre() : '',
                    $mensaje
                )
            );
            $notificacion->setProfesor($profesor);
            $notificacion->setAlumno($alumno);
            $notificacion->setArticulo($articulo);
            
            $this->entityManager->persist($notificacion);
        }
        
        $this->entityManager->flush();
    }
}

