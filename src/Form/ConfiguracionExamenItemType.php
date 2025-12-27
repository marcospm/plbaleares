<?php

namespace App\Form;

use App\Entity\ConfiguracionExamen;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfiguracionExamenItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', HiddenType::class, [
                'mapped' => false,
            ])
            ->add('porcentaje', NumberType::class, [
                'label' => false,
                'required' => false,
                'scale' => 2,
                'attr' => [
                    'class' => 'form-control porcentaje-input',
                    'min' => 0,
                    'max' => 100,
                    'step' => 0.01,
                    'placeholder' => '0.00'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ConfiguracionExamen::class,
        ]);
    }
}

