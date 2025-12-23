<?php

namespace App\Form;

use App\Entity\Tema;
use App\Entity\Municipio;
use App\Entity\TemaMunicipal;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;

class ExamenIniciarType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['user'] ?? null;
        
        $builder
            ->add('tipoExamen', ChoiceType::class, [
                'label' => 'Tipo de Examen',
                'choices' => [
                    'Temario General' => 'general',
                    'Municipio' => 'municipal',
                ],
                'required' => true,
                'attr' => ['class' => 'form-control', 'id' => 'tipo_examen'],
                'data' => 'general',
            ])
            ->add('municipio', EntityType::class, [
                'class' => Municipio::class,
                'choice_label' => 'nombre',
                'required' => false,
                'label' => 'Municipio (solo para exámenes municipales)',
                'attr' => ['class' => 'form-control', 'id' => 'municipio_select', 'style' => 'display: none;'],
                'query_builder' => function ($er) use ($user) {
                    $qb = $er->createQueryBuilder('m')
                        ->where('m.activo = :activo')
                        ->setParameter('activo', true);
                    if ($user) {
                        $qb->innerJoin('m.usuarios', 'u')
                           ->andWhere('u.id = :userId')
                           ->setParameter('userId', $user->getId());
                    }
                    return $qb->orderBy('m.nombre', 'ASC');
                },
            ])
            ->add('temas', EntityType::class, [
                'class' => Tema::class,
                'choice_label' => 'nombre',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'label' => 'Selecciona los Temas',
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('t')
                        ->where('t.activo = :activo')
                        ->setParameter('activo', true)
                        ->orderBy('t.id', 'ASC');
                },
                'constraints' => [
                    new Count(min: 1, minMessage: 'Debes seleccionar al menos un tema')
                ],
                'attr' => ['class' => 'form-check', 'id' => 'temas_general'],
                'placeholder' => false,
            ])
            ->add('temasMunicipales', EntityType::class, [
                'class' => TemaMunicipal::class,
                'choice_label' => 'nombre',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'label' => 'Selecciona los Temas Municipales',
                'query_builder' => function ($er) use ($user, $options) {
                    $municipioId = $options['municipio_id'] ?? null;
                    $qb = $er->createQueryBuilder('t')
                        ->where('t.activo = :activo')
                        ->setParameter('activo', true);
                    if ($municipioId) {
                        $qb->andWhere('t.municipio = :municipio')
                           ->setParameter('municipio', $municipioId);
                    }
                    return $qb->orderBy('t.id', 'ASC');
                },
                'attr' => ['class' => 'form-check', 'id' => 'temas_municipales', 'style' => 'display: none;'],
                'placeholder' => false,
            ])
            ->add('dificultad', ChoiceType::class, [
                'label' => 'Dificultad',
                'choices' => [
                    'Fácil' => 'facil',
                    'Moderada' => 'moderada',
                    'Difícil' => 'dificil',
                ],
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('numeroPreguntas', ChoiceType::class, [
                'label' => 'Número de Preguntas',
                'choices' => [
                    '20' => 20,
                    '30' => 30,
                    '40' => 40,
                    '50' => 50,
                    '60' => 60,
                    '70' => 70,
                    '80' => 80,
                    '90' => 90,
                    '100' => 100,
                ],
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'user' => null,
            'municipio_id' => null,
        ]);
    }
}

