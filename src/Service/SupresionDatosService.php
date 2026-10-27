<?php

namespace App\Service;

use App\Entity\Examen;
use App\Entity\ExamenBorrador;
use App\Entity\Mensaje;
use App\Entity\MensajeArticulo;
use App\Entity\MensajeContacto;
use App\Entity\MensajePregunta;
use App\Entity\Notificacion;
use App\Entity\PartidaJuego;
use App\Entity\PlanificacionPersonalizada;
use App\Entity\RecursoEspecifico;
use App\Entity\TareaAsignada;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SupresionDatosService
{
    public const RETENCION_RESULTADOS_ANIOS = 2;

    public const RETENCION_CONTACTO_ANIOS = 2;

    private const TEXTO_MENSAJE_SUPRIMIDO = 'Mensaje suprimido por ejercicio del derecho de supresión.';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepository $userRepository,
    ) {
    }

    /**
     * Borra o anonimiza los datos identificativos y los mensajes.
     * Los resultados de exámenes, partidas y tareas quedan ligados a un identificador anónimo.
     *
     * @return string Nombre de usuario anterior, para el mensaje de confirmación
     */
    public function suprimir(User $user): string
    {
        if ($user->isSuprimido()) {
            throw new \RuntimeException('Los datos de este usuario ya están suprimidos.');
        }

        $usernameAnterior = (string) $user->getUsername();
        $emailAnterior = $user->getEmail();

        $this->userRepository->invalidateUserCache($user);

        $this->entityManager->wrapInTransaction(function () use ($user, $emailAnterior): void {
            $this->eliminarMensajesPrivados($user);
            $this->redactarComentarios($user);
            $this->eliminarNotificaciones($user);
            $this->eliminarBorradores($user);
            $this->eliminarMensajesContacto($user, $emailAnterior);
            $this->desvincularTareasDeFranjas($user);
            $this->eliminarPlanificaciones($user);
            $this->desvincularRelaciones($user);
            $this->anonimizar($user);
        });

        $this->userRepository->invalidateUserCache($user);

        return $usernameAnterior;
    }

    /**
     * Aplica los plazos de conservación: resultados de cuentas ya suprimidas y mensajes de contacto.
     *
     * @return array{examenes: int, partidas: int, tareas: int, contactos: int}
     */
    public function depurarConservacion(): array
    {
        $limiteResultados = new \DateTimeImmutable(sprintf('-%d years', self::RETENCION_RESULTADOS_ANIOS));
        $limiteContacto = new \DateTimeImmutable(sprintf('-%d years', self::RETENCION_CONTACTO_ANIOS));

        /** @var User[] $usuarios */
        $usuarios = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.suprimido = true')
            ->andWhere('u.suprimidoAt IS NOT NULL')
            ->andWhere('u.suprimidoAt <= :limite')
            ->setParameter('limite', $limiteResultados)
            ->getQuery()
            ->getResult();

        $examenes = 0;
        $partidas = 0;
        $tareas = 0;

        foreach ($usuarios as $usuario) {
            $examenes += $this->eliminarExamenes($usuario);
            $partidas += $this->eliminarPartidas($usuario);
            $tareas += $this->eliminarTareas($usuario);
        }

        $contactos = (int) $this->entityManager->createQueryBuilder()
            ->delete(MensajeContacto::class, 'c')
            ->where('c.fechaCreacion <= :limite')
            ->setParameter('limite', $limiteContacto)
            ->getQuery()
            ->execute();

        return [
            'examenes' => $examenes,
            'partidas' => $partidas,
            'tareas' => $tareas,
            'contactos' => $contactos,
        ];
    }

    private function eliminarMensajesPrivados(User $user): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(Mensaje::class, 'm')
            ->where('m.remitente = :usuario OR m.destinatario = :usuario')
            ->setParameter('usuario', $user)
            ->getQuery()
            ->execute();
    }

    private function redactarComentarios(User $user): void
    {
        $this->entityManager->createQueryBuilder()
            ->update(MensajePregunta::class, 'm')
            ->set('m.mensaje', ':texto')
            ->where('m.autor = :usuario')
            ->setParameter('texto', self::TEXTO_MENSAJE_SUPRIMIDO)
            ->setParameter('usuario', $user)
            ->getQuery()
            ->execute();

        $this->entityManager->createQueryBuilder()
            ->update(MensajeArticulo::class, 'm')
            ->set('m.mensaje', ':texto')
            ->where('m.autor = :usuario')
            ->setParameter('texto', self::TEXTO_MENSAJE_SUPRIMIDO)
            ->setParameter('usuario', $user)
            ->getQuery()
            ->execute();
    }

    private function eliminarNotificaciones(User $user): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(Notificacion::class, 'n')
            ->where('n.alumno = :usuario OR n.profesor = :usuario')
            ->setParameter('usuario', $user)
            ->getQuery()
            ->execute();
    }

    private function eliminarBorradores(User $user): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(ExamenBorrador::class, 'b')
            ->where('b.usuario = :usuario')
            ->setParameter('usuario', $user)
            ->getQuery()
            ->execute();
    }

    private function eliminarMensajesContacto(User $user, ?string $email): void
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->delete(MensajeContacto::class, 'c')
            ->where('c.usuario = :usuario')
            ->setParameter('usuario', $user);

        if ($email !== null && trim($email) !== '') {
            $qb->orWhere('LOWER(c.email) = :email')
                ->setParameter('email', mb_strtolower(trim($email)));
        }

        $qb->getQuery()->execute();
    }

    private function desvincularTareasDeFranjas(User $user): void
    {
        $tareas = $this->entityManager->getRepository(TareaAsignada::class)->findBy(['usuario' => $user]);
        foreach ($tareas as $tarea) {
            $tarea->setFranjaHoraria(null);
        }
    }

    private function eliminarPlanificaciones(User $user): void
    {
        $planes = $this->entityManager->getRepository(PlanificacionPersonalizada::class)->findBy(['usuario' => $user]);
        foreach ($planes as $plan) {
            $this->entityManager->remove($plan);
        }
    }

    private function desvincularRelaciones(User $user): void
    {
        foreach ($user->getConvocatorias()->toArray() as $convocatoria) {
            $user->removeConvocatoria($convocatoria);
        }

        foreach ($user->getGrupos()->toArray() as $grupo) {
            $user->removeGrupo($grupo);
        }

        foreach ($user->getProfesores()->toArray() as $profesor) {
            $user->removeProfesore($profesor);
        }

        foreach ($user->getAlumnos()->toArray() as $alumno) {
            $user->removeAlumno($alumno);
        }

        $recursos = $this->entityManager->getRepository(RecursoEspecifico::class)
            ->createQueryBuilder('r')
            ->innerJoin('r.alumnos', 'a')
            ->andWhere('a = :usuario')
            ->setParameter('usuario', $user)
            ->getQuery()
            ->getResult();

        foreach ($recursos as $recurso) {
            $recurso->removeAlumno($user);
        }
    }

    private function anonimizar(User $user): void
    {
        $id = $user->getId();
        $user->setUsername('suprimido-'.$id);
        $user->setNombre('Usuario suprimido');
        $user->setEmail(null);
        $user->setTelefono(null);
        $user->setDireccion(null);
        $user->setCodigoPostal(null);
        $user->setCiudad(null);
        $user->setProvincia(null);
        $user->setFechaNacimiento(null);
        $user->setSexo(null);
        $user->setNotas(null);
        $user->setResetPasswordToken(null);
        $user->setResetPasswordExpiresAt(null);
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
        $user->setRoles([]);
        $user->setActivo(false);
        $user->setEliminado(true);
        $user->setSuprimido(true);
        $user->setSuprimidoAt(new \DateTime());
    }

    private function eliminarExamenes(User $usuario): int
    {
        $examenes = $this->entityManager->getRepository(Examen::class)->findBy(['usuario' => $usuario]);
        foreach ($examenes as $examen) {
            $notificaciones = $this->entityManager->getRepository(Notificacion::class)->findBy(['examen' => $examen]);
            foreach ($notificaciones as $notificacion) {
                $notificacion->setExamen(null);
            }
            $this->entityManager->remove($examen);
        }

        if ($examenes !== []) {
            $this->entityManager->flush();
        }

        return count($examenes);
    }

    private function eliminarPartidas(User $usuario): int
    {
        $partidas = $this->entityManager->getRepository(PartidaJuego::class)->findBy(['usuario' => $usuario]);
        foreach ($partidas as $partida) {
            $this->entityManager->remove($partida);
        }

        if ($partidas !== []) {
            $this->entityManager->flush();
        }

        return count($partidas);
    }

    private function eliminarTareas(User $usuario): int
    {
        $tareas = $this->entityManager->getRepository(TareaAsignada::class)->findBy(['usuario' => $usuario]);
        foreach ($tareas as $tarea) {
            $notificaciones = $this->entityManager->getRepository(Notificacion::class)->findBy(['tareaAsignada' => $tarea]);
            foreach ($notificaciones as $notificacion) {
                $notificacion->setTareaAsignada(null);
            }
            $this->entityManager->remove($tarea);
        }

        if ($tareas !== []) {
            $this->entityManager->flush();
        }

        return count($tareas);
    }
}
