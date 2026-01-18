<?php

namespace App\Service;

use App\Entity\Articulo;
use App\Entity\Examen;
use App\Entity\ExamenSemanal;
use App\Entity\Notificacion;
use App\Entity\PlanificacionPersonalizada;
use App\Entity\PlanificacionSemanal;
use App\Entity\Pregunta;
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

        // Verificar si es un examen semanal
        $esExamenSemanal = $examen->getExamenSemanal() !== null;
        $examenSemanal = $examen->getExamenSemanal();

        foreach ($profesores as $profesor) {
            $notificacion = new Notificacion();
            $notificacion->setTipo(Notificacion::TIPO_EXAMEN);
            
            if ($esExamenSemanal && $examenSemanal) {
                // Es un examen semanal
                $notificacion->setTitulo('Examen Semanal Completado');
                $notificacion->setMensaje(
                    sprintf(
                        '%s ha completado el examen semanal "%s" de dificultad %s con una nota de %s.',
                        $alumno->getUsername(),
                        $examenSemanal->getNombre(),
                        $examen->getDificultadLabel(),
                        number_format((float)$examen->getNota(), 2, ',', '.')
                    )
                );
                $notificacion->setExamenSemanal($examenSemanal);
            } else {
                // Es un examen normal
                $notificacion->setTitulo('Nuevo Examen Completado');
                $notificacion->setMensaje(
                    sprintf(
                        '%s ha completado un examen de dificultad %s con una nota de %s.',
                        $alumno->getUsername(),
                        $examen->getDificultadLabel(),
                        number_format((float)$examen->getNota(), 2, ',', '.')
                    )
                );
            }
            
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
     * Las notificaciones se envían a todos los profesores y administradores (excepto si el alumno es profesor/admin)
     */
    public function crearNotificacionErrorArticulo(Articulo $articulo, User $alumno, string $mensaje): void
    {
        // No notificar si el usuario que reporta es profesor o admin (no debería pasar, pero por seguridad)
        if (in_array('ROLE_PROFESOR', $alumno->getRoles()) || in_array('ROLE_ADMIN', $alumno->getRoles())) {
            return;
        }
        
        // Obtener todos los profesores y administradores
        $profesoresYAdmins = $this->userRepository->createQueryBuilder('u')
            ->where('u.roles LIKE :roleProfesor OR u.roles LIKE :roleAdmin')
            ->andWhere('u.activo = :activo')
            ->andWhere('u.id != :alumnoId') // Excluir al alumno que reporta por si acaso es profesor/admin
            ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
            ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
            ->setParameter('activo', true)
            ->setParameter('alumnoId', $alumno->getId())
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
    public function crearNotificacionPlanificacionCreada(PlanificacionPersonalizada $planificacion, User $alumno, User $profesor): void
    {
        // No notificar si el alumno es el mismo que el profesor que crea la planificación
        if ($alumno->getId() === $profesor->getId()) {
            return;
        }
        
        $notificacion = new Notificacion();
        $notificacion->setTipo(Notificacion::TIPO_PLANIFICACION_CREADA);
        $notificacion->setTitulo('Nueva Planificación Asignada');
        $notificacion->setMensaje(
            sprintf(
                'Se te ha asignado una nueva planificación: "%s" (del %s al %s).',
                $planificacion->getNombre(),
                $planificacion->getFechaInicio()->format('d/m/Y'),
                $planificacion->getFechaFin()->format('d/m/Y')
            )
        );
        // No establecer profesor: estas notificaciones son solo para alumnos
        $notificacion->setProfesor(null);
        $notificacion->setAlumno($alumno);
        $notificacion->setPlanificacionSemanal(null); // Ya no usamos PlanificacionSemanal
        
        $this->entityManager->persist($notificacion);
    }

    /**
     * Crea notificaciones para alumnos cuando se edita una planificación que tienen asignada
     */
    public function crearNotificacionPlanificacionEditada(PlanificacionPersonalizada $planificacion, User $profesor): void
    {
        $alumno = $planificacion->getUsuario();
        
        // No notificar si el alumno es el mismo que el profesor que edita la planificación
        if ($alumno->getId() === $profesor->getId()) {
            return;
        }
        
        $notificacion = new Notificacion();
        $notificacion->setTipo(Notificacion::TIPO_PLANIFICACION_EDITADA);
        $notificacion->setTitulo('Planificación Actualizada');
        $notificacion->setMensaje(
            sprintf(
                'La planificación "%s" ha sido actualizada.',
                $planificacion->getNombre()
            )
        );
        // No establecer profesor: estas notificaciones son solo para alumnos
        $notificacion->setProfesor(null);
        $notificacion->setAlumno($alumno);
        $notificacion->setPlanificacionSemanal(null);
        
        $this->entityManager->persist($notificacion);
        $this->entityManager->flush();
    }

    /**
     * Crea notificaciones para alumnos cuando se elimina una planificación que tienen asignada
     */
    public function crearNotificacionPlanificacionEliminada(string $nombrePlanificacion, array $alumnos, User $profesor): void
    {
        foreach ($alumnos as $alumno) {
            // No notificar si el alumno es el mismo que el profesor que elimina la planificación
            if ($alumno->getId() === $profesor->getId()) {
                continue;
            }
            
            $notificacion = new Notificacion();
            $notificacion->setTipo(Notificacion::TIPO_PLANIFICACION_ELIMINADA);
            $notificacion->setTitulo('Planificación Eliminada');
            $notificacion->setMensaje(
                sprintf(
                    'La planificación semanal "%s" ha sido eliminada.',
                    $nombrePlanificacion
                )
            );
            // No establecer profesor: estas notificaciones son solo para alumnos
            $notificacion->setProfesor(null);
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
        // No notificar si el alumno es el mismo que el profesor que crea la tarea
        if ($alumno->getId() === $profesor->getId()) {
            return;
        }
        
        $notificacion = new Notificacion();
        $notificacion->setTipo(Notificacion::TIPO_TAREA_CREADA);
        $notificacion->setTitulo('Nueva Tarea Asignada');
        $notificacion->setMensaje(
            sprintf(
                'Se te ha asignado una nueva tarea: "%s".',
                $tarea->getNombre()
            )
        );
        // No establecer profesor: estas notificaciones son solo para alumnos
        $notificacion->setProfesor(null);
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
            
            // No notificar si el alumno es el mismo que el profesor que edita la tarea
            if ($alumno->getId() === $profesor->getId()) {
                continue;
            }
            
            $notificacion = new Notificacion();
            $notificacion->setTipo(Notificacion::TIPO_TAREA_EDITADA);
            $notificacion->setTitulo('Tarea Actualizada');
            $notificacion->setMensaje(
                sprintf(
                    'La tarea "%s" ha sido actualizada.',
                    $tarea->getNombre()
                )
            );
            // No establecer profesor: estas notificaciones son solo para alumnos
            $notificacion->setProfesor(null);
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
            // No notificar si el alumno es el mismo que el profesor que elimina la tarea
            if ($alumno->getId() === $profesor->getId()) {
                continue;
            }
            
            $notificacion = new Notificacion();
            $notificacion->setTipo(Notificacion::TIPO_TAREA_ELIMINADA);
            $notificacion->setTitulo('Tarea Eliminada');
            $notificacion->setMensaje(
                sprintf(
                    'La tarea "%s" ha sido eliminada.',
                    $nombreTarea
                )
            );
            // No establecer profesor: estas notificaciones son solo para alumnos
            $notificacion->setProfesor(null);
            $notificacion->setAlumno($alumno);
            
            $this->entityManager->persist($notificacion);
        }
        
        $this->entityManager->flush();
    }

    /**
     * Crea una notificación cuando se publica un nuevo examen semanal
     */
    public function crearNotificacionExamenSemanal(ExamenSemanal $examenSemanal, User $alumno, User $profesor): void
    {
        // No notificar si el alumno es el mismo que el profesor que crea el examen
        if ($alumno->getId() === $profesor->getId()) {
            return;
        }
        
        $notificacion = new Notificacion();
        $notificacion->setTipo(Notificacion::TIPO_EXAMEN_SEMANAL);
        $notificacion->setTitulo('Nuevo Examen Semanal');
        $notificacion->setMensaje(
            sprintf(
                'Se ha publicado un nuevo examen semanal: "%s". Disponible hasta el %s.',
                $examenSemanal->getNombre(),
                $examenSemanal->getFechaCierre()->format('d/m/Y H:i')
            )
        );
        // No establecer profesor: estas notificaciones son solo para alumnos
        $notificacion->setProfesor(null);
        $notificacion->setAlumno($alumno);
        $notificacion->setExamenSemanal($examenSemanal);
        
        $this->entityManager->persist($notificacion);
    }

    /**
     * Crea una notificación cuando un profesor o admin responde a un mensaje de artículo de un alumno
     */
    public function crearNotificacionRespuestaArticulo(Articulo $articulo, User $alumno, User $profesor, string $respuestaTexto): void
    {
        // No notificar si el profesor y el alumno son la misma persona
        if ($profesor->getId() === $alumno->getId()) {
            return;
        }
        
        // Solo notificar al alumno que escribió el mensaje original
        $notificacion = new Notificacion();
        $notificacion->setTipo(Notificacion::TIPO_RESPUESTA_ARTICULO);
        $notificacion->setTitulo('Respuesta en Artículo');
        // Incluir la respuesta completa sin truncar para que el alumno la vea directamente
        $notificacion->setMensaje(
            sprintf(
                '%s ha respondido a tu mensaje en el Artículo %s%s: "%s"',
                $profesor->getUsername(),
                $articulo->getNumero(),
                $articulo->getNombre() ? ' - ' . $articulo->getNombre() : '',
                $respuestaTexto
            )
        );
        // No establecer profesor: estas notificaciones son solo para alumnos
        $notificacion->setProfesor(null);
        $notificacion->setAlumno($alumno);
        $notificacion->setArticulo($articulo);
        
        $this->entityManager->persist($notificacion);
        $this->entityManager->flush();
    }

    /**
     * Crea una notificación cuando un alumno reporta un error en una pregunta
     * Las notificaciones se envían a todos los profesores y administradores (excepto si el alumno es profesor/admin)
     */
    public function crearNotificacionErrorPregunta(Pregunta $pregunta, User $alumno, string $mensaje): void
    {
        // No notificar si el usuario que reporta es profesor o admin (no debería pasar, pero por seguridad)
        if (in_array('ROLE_PROFESOR', $alumno->getRoles()) || in_array('ROLE_ADMIN', $alumno->getRoles())) {
            return;
        }
        
        // Obtener todos los profesores y administradores
        $profesoresYAdmins = $this->userRepository->createQueryBuilder('u')
            ->where('u.roles LIKE :roleProfesor OR u.roles LIKE :roleAdmin')
            ->andWhere('u.activo = :activo')
            ->andWhere('u.id != :alumnoId') // Excluir al alumno que reporta por si acaso es profesor/admin
            ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
            ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
            ->setParameter('activo', true)
            ->setParameter('alumnoId', $alumno->getId())
            ->getQuery()
            ->getResult();

        foreach ($profesoresYAdmins as $profesor) {
            $notificacion = new Notificacion();
            $notificacion->setTipo(Notificacion::TIPO_ERROR_PREGUNTA);
            $notificacion->setTitulo('Error Reportado en Pregunta');
            $notificacion->setMensaje(
                sprintf(
                    '%s ha reportado un error o solicita corrección en una pregunta del Artículo %s%s. Mensaje: %s',
                    $alumno->getUsername(),
                    $pregunta->getArticulo()->getNumero(),
                    $pregunta->getArticulo()->getNombre() ? ' - ' . $pregunta->getArticulo()->getNombre() : '',
                    substr($mensaje, 0, 200) . (strlen($mensaje) > 200 ? '...' : '')
                )
            );
            $notificacion->setProfesor($profesor);
            $notificacion->setAlumno($alumno);
            $notificacion->setPregunta($pregunta);
            
            $this->entityManager->persist($notificacion);
        }
        
        $this->entityManager->flush();
    }

    /**
     * Crea una notificación cuando un profesor o admin responde a un mensaje de pregunta de un alumno
     */
    public function crearNotificacionRespuestaPregunta(Pregunta $pregunta, User $alumno, User $profesor, string $respuestaTexto): void
    {
        // No notificar si el profesor y el alumno son la misma persona
        if ($profesor->getId() === $alumno->getId()) {
            return;
        }
        
        // Solo notificar al alumno que escribió el mensaje original
        $notificacion = new Notificacion();
        $notificacion->setTipo(Notificacion::TIPO_RESPUESTA_PREGUNTA);
        $notificacion->setTitulo('Respuesta en Pregunta');
        // Incluir la respuesta completa sin truncar para que el alumno la vea directamente
        $notificacion->setMensaje(
            sprintf(
                '%s ha respondido a tu mensaje sobre una pregunta del Artículo %s%s: "%s"',
                $profesor->getUsername(),
                $pregunta->getArticulo()->getNumero(),
                $pregunta->getArticulo()->getNombre() ? ' - ' . $pregunta->getArticulo()->getNombre() : '',
                $respuestaTexto
            )
        );
        // No establecer profesor: estas notificaciones son solo para alumnos
        $notificacion->setProfesor(null);
        $notificacion->setAlumno($alumno);
        $notificacion->setPregunta($pregunta);
        
        $this->entityManager->persist($notificacion);
        $this->entityManager->flush();
    }

    /**
     * Crea notificaciones generales para todos los alumnos activos
     * Solo puede ser llamado por un administrador
     */
    public function crearNotificacionGeneral(string $titulo, string $mensaje, User $admin): void
    {
        // Obtener todos los alumnos activos (usuarios que no son profesores ni admins)
        $alumnos = $this->userRepository->createQueryBuilder('u')
            ->where('u.activo = :activo')
            ->andWhere('u.roles NOT LIKE :roleProfesor')
            ->andWhere('u.roles NOT LIKE :roleAdmin')
            ->setParameter('activo', true)
            ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
            ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
            ->getQuery()
            ->getResult();

        foreach ($alumnos as $alumno) {
            $notificacion = new Notificacion();
            $notificacion->setTipo(Notificacion::TIPO_GENERAL);
            $notificacion->setTitulo($titulo);
            $notificacion->setMensaje($mensaje);
            // No establecer profesor: estas notificaciones son generales para alumnos
            $notificacion->setProfesor(null);
            $notificacion->setAlumno($alumno);
            
            $this->entityManager->persist($notificacion);
        }
        
        $this->entityManager->flush();
    }
}

