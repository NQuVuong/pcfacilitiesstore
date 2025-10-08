<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $o): void
    {
        $b->add('email', EmailType::class)
          ->add('password', PasswordType::class, ['mapped' => false, 'required' => false])
          ->add('roles', ChoiceType::class, [
              'choices'  => [
                  'Admin'    => User::ROLE_ADMIN,
                  'Staff'    => User::ROLE_STAFF,
                  'Customer' => User::ROLE_CUSTOMER,
              ],
              'expanded' => true,
              'multiple' => true
          ]);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
