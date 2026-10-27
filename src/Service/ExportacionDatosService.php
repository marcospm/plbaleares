<?php

namespace App\Service;

use App\Entity\Examen;
use App\Entity\Mensaje;
use App\Entity\PartidaJuego;
use App\Entity\TareaAsignada;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ExportacionDatosService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function exportarJson(User $user): string
    {
        $datos = [
            'exportado_en' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'responsable' => 'Maria Magdalena Márquez Coll (BISPOL - Centre de Formació)',
            'perfil' => $this->perfil($user),
            'mensajes' => $this->mensajes($user),
            'examenes' => $this->examenes($user),
            'partidas' => $this->partidas($user),
            'tareas' => $this->tareas($user),
        ];

        return json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function perfil(User $user): array
    {
        return [
            'id' => $user->getId(),
            'usuario' => $user->getUsername(),
            'nombre' => $user->getNombre(),
            'email' => $user->getEmail(),
            'telefono' => $user->getTelefono(),
            'direccion' => $user->getDireccion(),
            'codigo_postal' => $user->getCodigoPostal(),
            'ciudad' => $user->getCiudad(),
            'provincia' => $user->getProvincia(),
            'fecha_nacimiento' => $user->getFechaNacimiento()?->format('Y-m-d'),
            'sexo' => $user->getSexo(),
            'notas' => $user->getNotas(),
            'roles' => $user->getRoles(),
            'activo' => $user->isActivo(),
            'ultimo_acceso' => $user->getUltimoLogin()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mensajes(User $user): array
    {
        /** @var Mensaje[] $mensajes */
        $mensajes = $this->entityManager->getRepository(Mensaje::class)
            ->createQueryBuilder('m')
            ->where('m.remitente = :usuario OR m.destinatario = :usuario')
            ->setParameter('usuario', $user)
            ->orderBy('m.fechaEnvio', 'ASC')
            ->getQuery()
            ->getResult();

        $filas = [];
        foreach ($mensajes as $mensaje) {
            $remitente = $mensaje->getRemitente();
            $destinatario = $mensaje->getDestinatario();
            $filas[] = [
                'fecha' => $mensaje->getFechaEnvio()?->format(\DateTimeInterface::ATOM),
                'de' => $remitente?->getNombreDisplay(),
                'para' => $destinatario?->getNombreDisplay(),
                'enviado_por_mi' => $remitente?->getId() === $user->getId(),
                'leido' => $mensaje->isLeido(),
                'contenido' => $mensaje->getContenido(),
            ];
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function examenes(User $user): array
    {
        /** @var Examen[] $examenes */
        $examenes = $this->entityManager->getRepository(Examen::class)->findBy(
            ['usuario' => $user],
            ['fecha' => 'ASC']
        );

        $filas = [];
        foreach ($examenes as $examen) {
            $filas[] = [
                'fecha' => $examen->getFecha()?->format(\DateTimeInterface::ATOM),
                'dificultad' => $examen->getDificultad(),
                'numero_preguntas' => $examen->getNumeroPreguntas(),
                'nota' => $examen->getNota(),
                'aciertos' => $examen->getAciertos(),
                'errores' => $examen->getErrores(),
                'en_blanco' => $examen->getEnBlanco(),
                'municipio' => $examen->getMunicipio()?->getNombre(),
                'convocatoria' => $examen->getConvocatoria()?->getNombre(),
            ];
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function partidas(User $user): array
    {
        /** @var PartidaJuego[] $partidas */
        $partidas = $this->entityManager->getRepository(PartidaJuego::class)->findBy(
            ['usuario' => $user],
            ['fechaCreacion' => 'ASC']
        );

        $filas = [];
        foreach ($partidas as $partida) {
            $filas[] = [
                'fecha' => $partida->getFechaCreacion()?->format(\DateTimeInterface::ATOM),
                'juego' => $partida->getNombreJuego(),
            ];
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tareas(User $user): array
    {
        /** @var TareaAsignada[] $tareas */
        $tareas = $this->entityManager->getRepository(TareaAsignada::class)->findBy(
            ['usuario' => $user],
            ['fechaAsignacion' => 'ASC']
        );

        $filas = [];
        foreach ($tareas as $tarea) {
            $filas[] = [
                'tarea' => $tarea->getTarea()?->getNombre(),
                'fecha_asignacion' => $tarea->getFechaAsignacion()?->format(\DateTimeInterface::ATOM),
                'completada' => $tarea->isCompletada(),
                'fecha_completada' => $tarea->getFechaCompletada()?->format(\DateTimeInterface::ATOM),
            ];
        }

        return $filas;
    }
}
