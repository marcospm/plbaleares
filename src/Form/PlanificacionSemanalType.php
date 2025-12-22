<?php

namespace App\Form;

use App\Entity\PlanificacionSemanal;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlanificacionSemanalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre de la Planificación',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('descripcion', TextareaType::class, [
                'label' => 'Descripción (opcional)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3]
            ])
            ->add('fechaFin', DateType::class, [
                'label' => 'Fecha de Finalización (opcional)',
                'required' => false,
                'widget' => 'single_text',
                'help' => 'Si se especifica, la planificación solo se mostrará hasta esta fecha. Ejemplo: si la planificación es para los lunes, solo aparecerá hasta el último lunes antes de esta fecha.',
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlanificacionSemanal::class,
        ]);
    }
}

