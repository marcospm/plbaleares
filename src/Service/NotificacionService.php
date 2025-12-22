<?php

namespace App\Service;

use App\Entity\Examen;
use App\Entity\Notificacion;
use App\Entity\TareaAsignada;
use App\Entity\User;
use App\Repository\NotificacionRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificacionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificacionRepository $notificacionRepository
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
}

