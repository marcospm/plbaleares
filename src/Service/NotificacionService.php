<?php

namespace App\Service;

use App\Entity\Articulo;
use App\Entity\Examen;
use App\Entity\Notificacion;
use App\Entity\PlanificacionSemanal;
use App\Entity\Tarea;
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

    /**
     * Crea una notificación cuando un alumno envía un mensaje a su profesor asignado
     */
    public function crearNotificacionMensajeAlumno(User $alumno, User $profesor, string $mensaje): void
    {
        // Verificar que el profesor está asignado al alumno
        if (!$alumno->getProfesores()->contains($profesor)) {
            throw new \InvalidArgumentException('El profesor no está asignado a este alumno.');
        }

        $notificacion = new Notificacion();
        $notificacion->setTipo(Notificacion::TIPO_MENSAJE_ALUMNO);
        $notificacion->setTitulo('Mensaje de Alumno');
        $notificacion->setMensaje(
            sprintf(
                '%s te ha enviado un mensaje: "%s"',
                $alumno->getUsername(),
                $mensaje
            )
        );
        $notificacion->setProfesor($profesor);
        $notificacion->setAlumno($alumno);
        
        $this->entityManager->persist($notificacion);
        $this->entityManager->flush();
    }

    /**
     * Crea notificaciones para alumnos cuando se crea una planificación y se les asigna
     */
    public function crearNotificacionPlanificacionCreada(PlanificacionSemanal $planificacion, User $alumno, User $profesor): void
    {
        $notificacion = new Notificacion();
        $notificacion->setTipo(Notificacion::TIPO_PLANIFICACION_CREADA);
        $notificacion->setTitulo('Nueva Planificación Asignada');
        $notificacion->setMensaje(
            sprintf(
                'Se te ha asignado una nueva planificación semanal: "%s".',
                $planificacion->getNombre()
            )
        );
        $notificacion->setProfesor($profesor);
        $notificacion->setAlumno($alumno);
        $notificacion->setPlanificacionSemanal($planificacion);
        
        $this->entityManager->persist($notificacion);
    }

    /**
     * Crea notificaciones para alumnos cuando se edita una planificación que tienen asignada
     */
    public function crearNotificacionPlanificacionEditada(PlanificacionSemanal $planificacion, User $profesor): void
    {
        // Obtener todos los alumnos que tienen esta planificación asignada
        $planificacionesPersonalizadas = $planificacion->getPlanificacionesPersonalizadas();
        
        foreach ($planificacionesPersonalizadas as $planificacionPersonalizada) {
            $alumno = $planificacionPersonalizada->getUsuario();
            
            $notificacion = new Notificacion();
            $notificacion->setTipo(Notificacion::TIPO_PLANIFICACION_EDITADA);
            $notificacion->setTitulo('Planificación Actualizada');
            $notificacion->setMensaje(
                sprintf(
                    'La planificación semanal "%s" ha sido actualizada.',
                    $planificacion->getNombre()
                )
            );
            $notificacion->setProfesor($profesor);
            $notificacion->setAlumno($alumno);
            $notificacion->setPlanificacionSemanal($planificacion);
            
            $this->entityManager->persist($notificacion);
        }
        
        $this->entityManager->flush();
    }

    /**
     * Crea notificaciones para alumnos cuando se elimina una planificación que tienen asignada
     */
    public function crearNotificacionPlanificacionEliminada(string $nombrePlanificacion, array $alumnos, User $profesor): void
    {
        foreach ($alumnos as $alumno) {
            $notificacion = new Notificacion();
            $notificacion->setTipo(Notificacion::TIPO_PLANIFICACION_ELIMINADA);
            $notificacion->setTitulo('Planificación Eliminada');
            $notificacion->setMensaje(
                sprintf(
                    'La planificación semanal "%s" ha sido eliminada.',
                    $nombrePlanificacion
                )
            );
            $notificacion->setProfesor($profesor);
            $notificacion->setAlumno($alumno);
            
            $this->entityManager->persist($notificacion);
        }
        
        $this->entityManager->flush();
    }

    /**
     * Crea notificaciones para alumnos cuando se crea una tarea y se les asigna
     */
    public function crearNotificacionTareaCreada(Tarea $tarea, User $alumno, User $profesor): void
    {
        $notificacion = new Notificacion();
        $notificacion->setTipo(Notificacion::TIPO_TAREA_CREADA);
        $notificacion->setTitulo('Nueva Tarea Asignada');
        $notificacion->setMensaje(
            sprintf(
                'Se te ha asignado una nueva tarea: "%s".',
                $tarea->getNombre()
            )
        );
        $notificacion->setProfesor($profesor);
        $notificacion->setAlumno($alumno);
        $notificacion->setTarea($tarea);
        
        $this->entityManager->persist($notificacion);
    }

    /**
     * Crea notificaciones para alumnos cuando se edita una tarea que tienen asignada
     */
    public function crearNotificacionTareaEditada(Tarea $tarea, User $profesor): void
    {
        // Obtener todos los alumnos que tienen esta tarea asignada
        $asignaciones = $tarea->getAsignaciones();
        
        foreach ($asignaciones as $asignacion) {
            $alumno = $asignacion->getUsuario();
            
            $notificacion = new Notificacion();
            $notificacion->setTipo(Notificacion::TIPO_TAREA_EDITADA);
            $notificacion->setTitulo('Tarea Actualizada');
            $notificacion->setMensaje(
                sprintf(
                    'La tarea "%s" ha sido actualizada.',
                    $tarea->getNombre()
                )
            );
            $notificacion->setProfesor($profesor);
            $notificacion->setAlumno($alumno);
            $notificacion->setTarea($tarea);
            
            $this->entityManager->persist($notificacion);
        }
        
        $this->entityManager->flush();
    }

    /**
     * Crea notificaciones para alumnos cuando se elimina una tarea que tienen asignada
     */
    public function crearNotificacionTareaEliminada(string $nombreTarea, array $alumnos, User $profesor): void
    {
        foreach ($alumnos as $alumno) {
            $notificacion = new Notificacion();
            $notificacion->setTipo(Notificacion::TIPO_TAREA_ELIMINADA);
            $notificacion->setTitulo('Tarea Eliminada');
            $notificacion->setMensaje(
                sprintf(
                    'La tarea "%s" ha sido eliminada.',
                    $nombreTarea
                )
            );
            $notificacion->setProfesor($profesor);
            $notificacion->setAlumno($alumno);
            
            $this->entityManager->persist($notificacion);
        }
        
        $this->entityManager->flush();
    }
}

