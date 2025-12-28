<?php

namespace App\Form;

use App\Entity\Articulo;
use App\Entity\Ley;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ArticuloType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numero', IntegerType::class, [
                'label' => 'Número del Artículo',
                'required' => true,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ej: 1, 2, 3, 4, etc.']
            ])
            ->add('sufijo', TextType::class, [
                'label' => 'Sufijo del Artículo',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ej: bis, ter, quater, etc.']
            ])
            ->add('nombre', TextType::class, [
                'label' => 'Nombre del Artículo',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ej: Protección del honor, intimidad, propia imagen...']
            ])
            ->add('explicacion', TextareaType::class, [
                'label' => 'Explicación para el Opositor',
                'required' => false,
                'attr' => [
                    'class' => 'form-control tinymce-editor',
                    'rows' => 10
                ]
            ])
            ->add('videoFile', FileType::class, [
                'label' => 'Video Explicativo',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'video/mp4,video/webm,video/ogg'
                ],
                'constraints' => [
                    new File(
                        maxSize: '50M',
                        mimeTypes: [
                            'video/mp4',
                            'video/webm',
                            'video/ogg',
                        ],
                        mimeTypesMessage: 'Por favor, sube un archivo de video válido (MP4, WebM u OGG)'
                    )
                ],
                'help' => 'Formatos permitidos: MP4, WebM, OGG. Tamaño máximo: 50MB'
            ])
            ->add('ley', EntityType::class, [
                'class' => Ley::class,
                'choice_label' => 'nombre',
                'required' => true,
                'label' => 'Ley',
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Articulo::class,
        ]);
    }
}

