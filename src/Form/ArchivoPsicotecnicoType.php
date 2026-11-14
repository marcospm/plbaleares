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
                'label' => $options['requerir_archivo'] ? 'Archivo' : 'Sustituir archivo (opcional)',
                'mapped' => false,
                'required' => $options['requerir_archivo'],
                'constraints' => [
                    new File(
                        maxSize: '100M',
                        maxSizeMessage: 'El archivo es demasiado grande. Tamaño máximo: 100 MB.',
                    ),
                ],
                'help' => 'PDF, Office, imagen, ZIP u ODT. Máximo 100 MB.',
                'attr' => ['class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ArchivoPsicotecnico::class,
            'requerir_archivo' => true,
        ]);
        $resolver->setAllowedTypes('requerir_archivo', 'bool');
    }
}
