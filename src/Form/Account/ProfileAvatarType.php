<?php

namespace App\Form\Account;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;

class ProfileAvatarType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('avatarFile', FileType::class, [
            'label' => 'Upload new avatar',
            'mapped' => false,
            'required' => false,
            'constraints' => [
                new File([
                    'maxSize' => '5M',
                    'mimeTypes' => [
                        'image/png',
                        'image/jpeg',
                        'image/gif',
                        'image/webp',
                    ],
                    'mimeTypesMessage' => 'Please upload a valid image (PNG/JPG/GIF/WebP). Max 5MB.',
                ]),
            ],
        ]);
    }
}
