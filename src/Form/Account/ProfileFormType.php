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
            // Họ tên (luôn đứng đầu)
            ->add('fullName', TextType::class, [
                'label' => 'Họ tên',
                'required' => false,
                'attr' => [
                    'class' => 'input input-bordered w-full',
                    'placeholder' => 'Nhập họ tên',
                ],
            ])

            // Ngày sinh: single_text để hiện một ô input (tránh extension date picker can thiệp)
            ->add('birthday', DateType::class, [
                'label' => 'Ngày sinh',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true, // để trình duyệt render date picker native
                // Nếu muốn tránh extension nắm bắt: có thể đổi type text + định dạng ở server
                'attr' => [
                    'class' => 'input input-bordered w-full',
                    'placeholder' => 'YYYY-MM-DD',
                ],
            ])

            // Giới tính: radio (expanded)
            ->add('gender', ChoiceType::class, [
                'label' => 'Giới tính',
                'required' => false,
                'expanded' => true,
                'multiple' => false,
                'choices' => [
                    'None'  => '',        // để trống -> không xác định
                    'Nam'   => 'male',
                    'Nữ'    => 'female',
                ],
                // thêm class cho từng radio nếu muốn
                'choice_attr' => function () {
                    return ['class' => 'radio radio-sm'];
                },
            ])

            // Số điện thoại
            ->add('phone', TextType::class, [
                'label' => 'Số điện thoại',
                'required' => false,
                'attr' => [
                    'class' => 'input input-bordered w-full',
                    'placeholder' => 'Nhập số điện thoại',
                ],
            ])

            // Email chỉ hiển thị, không cho sửa
            ->add('email', EmailType::class, [
                'label' => 'Email',
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
