<?php

namespace App\Form;

use App\Entity\CategoriaOposicion;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;

final class AlumnosPorCategoria
{
    public static function etiqueta(User $user): string
    {
        return $user->getCategoria()->getLabel();
    }

    /**
     * @param iterable<User> $usuarios
     * @return array<string, list<User>>
     */
    public static function agrupar(iterable $usuarios): array
    {
        $porValor = [];
        foreach ($usuarios as $usuario) {
            if (!$usuario instanceof User) {
                continue;
            }
            $porValor[$usuario->getCategoria()->value][] = $usuario;
        }

        $grupos = [];
        foreach (CategoriaOposicion::cases() as $categoria) {
            if (empty($porValor[$categoria->value])) {
                continue;
            }
            $lista = $porValor[$categoria->value];
            usort($lista, static function (User $a, User $b): int {
                $nombre = strcasecmp($a->getNombreDisplay(), $b->getNombreDisplay());
                if ($nombre !== 0) {
                    return $nombre;
                }

                return strcasecmp($a->getUsername(), $b->getUsername());
            });
            $grupos[$categoria->getLabel()] = $lista;
        }

        return $grupos;
    }

    public static function ordenar(QueryBuilder $qb, string $alias = 'u'): QueryBuilder
    {
        $tramos = [];
        foreach (CategoriaOposicion::cases() as $indice => $categoria) {
            $tramos[] = sprintf("WHEN '%s' THEN %d", $categoria->value, $indice + 1);
        }
        $caso = sprintf(
            'CASE %s.categoria %s ELSE %d END',
            $alias,
            implode(' ', $tramos),
            count($tramos) + 1
        );

        return $qb
            ->addSelect($caso.' AS HIDDEN ordenCategoria')
            ->orderBy('ordenCategoria', 'ASC')
            ->addOrderBy($alias.'.nombre', 'ASC')
            ->addOrderBy($alias.'.username', 'ASC');
    }
}
