<?php

namespace App\Form;

use App\Entity\TemaMunicipal;
use App\Entity\Municipio;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class TemaMunicipalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre del Tema',
                'required' => true,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ej: Tema 1, Tema 2, etc.']
            ])
            ->add('municipio', EntityType::class, [
                'class' => Municipio::class,
                'choice_label' => 'nombre',
                'required' => true,
                'label' => 'Municipio',
                'attr' => ['class' => 'form-control']
            ])
            ->add('pdfFile', FileType::class, [
                'label' => 'PDF del Tema',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.pdf'
                ],
                'help' => 'Tamaño máximo: 10MB. Solo archivos PDF (extensión .pdf).'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TemaMunicipal::class,
        ]);
    }
}

