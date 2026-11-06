<?php

namespace App\Form;

use App\Entity\Ley;
use App\Entity\Tema;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LeyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $ley = $event->getData();
            if (!$ley instanceof Ley) {
                return;
            }
            foreach ($ley->getTemas()->toArray() as $tema) {
                if (!$tema->isActivo()) {
                    $ley->removeTema($tema);
                }
            }
        });

        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre de la Ley',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('descripcion', TextareaType::class, [
                'label' => 'Descripción',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4]
            ])
            ->add('boeLink', UrlType::class, [
                'label' => 'Link del BOE',
                'required' => false,
                'help' => 'URL completa del BOE (ejemplo: https://www.boe.es/buscar/act.php?id=BOE-A-2003-23514)',
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://www.boe.es/buscar/act.php?id=...']
            ])
            ->add('temas', EntityType::class, [
                'class' => Tema::class,
                'choice_label' => 'nombre',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'label' => 'Temas relacionados',
                'attr' => ['class' => 'form-control'],
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('t')
                        ->andWhere('t.activo = :activo')
                        ->setParameter('activo', true)
                        ->orderBy('t.id', 'ASC');
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ley::class,
        ]);
    }
}

