<?php

namespace App\Form;

use App\Entity\FechasPruebas;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FechasPruebasType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fechaTeorico', DateType::class, [
                'label' => 'Fecha Examen Teórico',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
            ->add('fechaFisicas', DateType::class, [
                'label' => 'Fecha Pruebas Físicas',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
            ->add('fechaPsicotecnico', DateType::class, [
                'label' => 'Fecha Psicotécnico',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FechasPruebas::class,
        ]);
    }
}








