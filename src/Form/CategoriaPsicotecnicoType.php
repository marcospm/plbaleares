<?php

namespace App\Form;

use App\Entity\CategoriaPsicotecnico;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoriaPsicotecnicoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
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
            ->add('orden', IntegerType::class, [
                'label' => 'Orden',
                'required' => true,
                'attr' => ['class' => 'form-control', 'min' => 0],
                'help' => 'Un número menor aparece antes en el listado del alumno.',
            ])
            ->add('activo', CheckboxType::class, [
                'label' => 'Visible para los alumnos',
                'required' => false,
                'help' => 'Si la desmarcas, deja de usarse como filtro. Un archivo solo se oculta al alumno si todas sus categorías están desactivadas.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CategoriaPsicotecnico::class,
        ]);
    }
}
