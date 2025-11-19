<?php

namespace App\Form\Account;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Full name',
                'required' => false,
                'attr' => [
                    'class' => 'input input-bordered w-full',
                    'placeholder' => 'Enter your full name',
                ],
            ])
            ->add('birthday', DateType::class, [
                'label' => 'Birthday',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'input input-bordered w-full',
                    'placeholder' => 'YYYY-MM-DD',
                ],
            ])
            ->add('gender', ChoiceType::class, [
                'label' => 'Gender',
                'required' => false,
                'expanded' => true,
                'multiple' => false,
                'choices' => [
                    'Not set' => '',
                    'Male'    => 'male',
                    'Female'  => 'female',
                ],
                'choice_attr' => static fn () => ['class' => 'radio radio-sm'],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Phone number',
                'required' => false,
                'attr' => [
                    'class' => 'input input-bordered w-full',
                    'placeholder' => 'Enter phone number',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email address',
                'disabled' => true,
                'attr' => [
                    'class' => 'input input-bordered w-full',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
