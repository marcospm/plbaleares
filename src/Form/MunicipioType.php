<?php

namespace App\Form;

use App\Entity\Municipio;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MunicipioType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre del Municipio',
                'required' => true,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ej: Palma, Ibiza, Mahón']
            ])
            ->add('numeroPlazas', IntegerType::class, [
                'label' => 'Número de Plazas',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ej: 10 (opcional)',
                    'min' => 0
                ],
                'help' => 'Número de plazas disponibles para este municipio (opcional)',
                'empty_data' => null,
                'invalid_message' => 'Por favor, introduce un número válido.'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Municipio::class,
        ]);
    }
}












