<?php

namespace App\Form;

use App\Entity\Supplier;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SupplierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $o): void
    {
        $b->add('name', TextType::class)
          ->add('email', EmailType::class, ['required' => false])
          ->add('phone', TextType::class, ['required' => false])
          ->add('address', TextType::class, ['required' => false])
          ->add('note', TextareaType::class, ['required' => false]);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Supplier::class]);
    }
}
