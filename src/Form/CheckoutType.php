<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;

class CheckoutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customerName', TextType::class, [
                'label' => 'Full name',
                'constraints' => [new NotBlank()],
            ])
            ->add('customerEmail', EmailType::class, [
                'label' => 'Email',
                'constraints' => [new NotBlank()],
            ])
            ->add('customerPhone', TextType::class, [
                'label' => 'Phone',
                'constraints' => [new NotBlank()],
            ])
            ->add('shippingAddress', TextareaType::class, [
                'label' => 'Shipping address',
                'attr' => ['rows' => 3],
                'constraints' => [new NotBlank()],
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Payment',
                'choices' => [
                    'MoMo e-wallet'      => 'MOMO',
                    'Cash on delivery'    => 'COD',
                    'Bank transfer'       => 'BANK',
                ],
                'expanded' => true,
            ])
            ->add('note', TextareaType::class, [
                'label' => 'Note',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('agree', CheckboxType::class, [
                'label' => 'I agree to the terms',
                'mapped' => false,
                'constraints' => [new IsTrue(['message' => 'You must accept the terms.'])],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // dùng mảng thuần, không map entity
        $resolver->setDefaults(['data_class' => null]);
    }
}
