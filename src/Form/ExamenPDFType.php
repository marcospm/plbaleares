<?php

namespace App\Form;

use App\Entity\ExamenPDF;
use App\Entity\Tema;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ExamenPDFType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre del Examen',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('descripcion', TextareaType::class, [
                'label' => 'Descripción (opcional)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3]
            ])
            ->add('tema', EntityType::class, [
                'class' => Tema::class,
                'choice_label' => 'nombre',
                'label' => 'Tema (opcional)',
                'required' => false,
                'placeholder' => 'Selecciona un tema',
                'attr' => ['class' => 'form-control']
            ])
            ->add('archivoPDF', FileType::class, [
                'label' => 'Archivo PDF',
                'required' => $options['require_file'],
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.pdf'
                ],
                'constraints' => $options['require_file'] ? [
                    new File(
                        maxSize: '20M'
                    )
                ] : [],
                'help' => 'Tamaño máximo: 20MB. Solo archivos PDF (extensión .pdf).'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ExamenPDF::class,
            'require_file' => true,
        ]);
    }
}

