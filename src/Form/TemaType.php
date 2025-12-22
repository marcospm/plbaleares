<?php

namespace App\Form;

use App\Entity\Tema;
use App\Entity\Ley;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TemaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre del Tema',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('descripcion', TextareaType::class, [
                'label' => 'Descripción',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4]
            ])
            ->add('rutaPdf', TextType::class, [
                'label' => 'Ruta del PDF',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ej: /pdfs/tema1.pdf o https://...']
            ])
            ->add('leyes', EntityType::class, [
                'class' => Ley::class,
                'choice_label' => 'nombre',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'label' => 'Leyes relacionadas',
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tema::class,
        ]);
    }
}

