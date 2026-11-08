<?php

namespace App\Form;

use App\Entity\Convocatoria;
use App\Entity\VideoConvocatoria;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VideoConvocatoriaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titulo', TextType::class, [
                'label' => 'Título',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('convocatoria', EntityType::class, [
                'class' => Convocatoria::class,
                'choice_label' => 'nombre',
                'label' => 'Convocatoria',
                'placeholder' => 'Selecciona una convocatoria',
                'attr' => ['class' => 'form-control'],
                'query_builder' => function ($repositorio) {
                    return $repositorio->createQueryBuilder('c')
                        ->orderBy('c.nombre', 'ASC');
                },
            ])
            ->add('descripcion', TextareaType::class, [
                'label' => 'Descripción (opcional)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4],
                'help' => 'Explica de qué trata el vídeo: presentación de la convocatoria, una prueba, un cambio de fechas u otra información importante.',
            ])
            ->add('orden', IntegerType::class, [
                'label' => 'Orden',
                'attr' => ['class' => 'form-control', 'min' => 0],
                'help' => 'Los vídeos con un número más bajo aparecen primero. Usa 1 para la presentación de la convocatoria.',
            ])
            ->add('enlaceVideo', TextareaType::class, [
                'label' => 'Código de video (Vimeo)',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Pega aquí el código iframe de Vimeo.',
                ],
                'help' => 'Pega el código completo del iframe de Vimeo, igual que en las sesiones.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => VideoConvocatoria::class,
        ]);
    }
}
