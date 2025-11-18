<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $o): void
    {
        $b
          ->add('currentPassword', PasswordType::class, [
              'mapped' => false,
              'constraints' => [new Assert\NotBlank()],
              'attr' => ['data-toggle-password' => '1'],
          ])
          ->add('newPassword', PasswordType::class, [
              'mapped' => false,
              'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 6)],
              'attr' => ['data-toggle-password' => '1'],
          ]);
    }
}
