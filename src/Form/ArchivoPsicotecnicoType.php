<?php

namespace App\Form;

use App\Entity\ArchivoPsicotecnico;
use App\Entity\CategoriaPsicotecnico;
use App\Repository\CategoriaPsicotecnicoRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;

class ArchivoPsicotecnicoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $archivo = $options['data'] ?? null;
        $seleccionadas = [];
        if ($archivo instanceof ArchivoPsicotecnico) {
            $seleccionadas = $archivo->getCategorias()->map(static fn (CategoriaPsicotecnico $categoria) => $categoria->getId())->toArray();
        }

        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('descripcion', TextareaType::class, [
                'label' => 'Descripción (opcional)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3],
            ])
            ->add('categorias', EntityType::class, [
                'class' => CategoriaPsicotecnico::class,
                'choice_label' => 'nombre',
                'multiple' => true,
                'expanded' => true,
                'label' => 'Categorías',
                'help' => 'Puedes marcar varias. Pulsa la tarjeta para seleccionarla.',
                'attr' => ['class' => 'categoria-opciones'],
                'query_builder' => function (CategoriaPsicotecnicoRepository $repositorio) use ($seleccionadas) {
                    $qb = $repositorio->createQueryBuilder('c')
                        ->orderBy('c.orden', 'ASC')
                        ->addOrderBy('c.nombre', 'ASC');

                    if ($seleccionadas !== []) {
                        $qb->where('c.activo = true OR c.id IN (:seleccionadas)')
                            ->setParameter('seleccionadas', $seleccionadas);
                    } else {
                        $qb->where('c.activo = true');
                    }

                    return $qb;
                },
            ])
            ->add('archivo', FileType::class, [
                'label' => $options['es_grupo'] ? 'Añadir archivos' : 'Archivos',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'constraints' => [
                    new All(
                        constraints: [
                            new File(
                                maxSize: '100M',
                                maxSizeMessage: 'Hay un archivo demasiado grande. Tamaño máximo: 100 MB por archivo.',
                            ),
                        ]
                    ),
                ],
                'help' => $this->textoAyuda((bool) $options['es_grupo'], (bool) $options['edicion']),
                'attr' => ['class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ArchivoPsicotecnico::class,
            'es_grupo' => false,
            'edicion' => false,
        ]);
        $resolver->setAllowedTypes('es_grupo', 'bool');
        $resolver->setAllowedTypes('edicion', 'bool');
    }

    private function textoAyuda(bool $esGrupo, bool $edicion): string
    {
        if ($esGrupo) {
            return 'Los archivos que elijas se añaden a los que ya hay. Máximo 100 MB por archivo.';
        }

        if ($edicion) {
            return 'Elige un archivo para sustituir el actual, o varios para dejarlos juntos. Máximo 100 MB por archivo.';
        }

        return 'Puedes elegir varios. Se verán juntos y podrás añadir o quitar al editar. Máximo 100 MB por archivo.';
    }
}
