<?php

namespace App\Form\Account;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;

class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Họ tên
            ->add('fullName', TextType::class, [
                'label' => 'Họ và tên',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Nhập họ tên',
                ],
            ])

            // Ngày sinh
            ->add('birthday', DateType::class, [
                'label' => 'Ngày sinh',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
            ])

            // Giới tính (None / Nam / Nữ)
            ->add('gender', ChoiceType::class, [
                'label' => 'Giới tính',
                'required' => false,
                'placeholder' => false,        // không thêm option rỗng riêng
                'expanded' => true,            // hiển thị dạng radio
                'multiple' => false,
                'choices' => [
                    'None'  => '',
                    'Nam'   => 'male',
                    'Nữ'    => 'female',
                ],
                'empty_data' => '',            // nếu không chọn sẽ lưu chuỗi rỗng
            ])

            // Số điện thoại
            ->add('phone', TextType::class, [
                'label' => 'Số điện thoại',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Nhập số điện thoại',
                ],
            ])

            // Email (readonly trong form)
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'disabled' => true,            // chỉ hiển thị, không cho sửa
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
