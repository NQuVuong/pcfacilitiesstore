<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        SessionInterface $session
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_homepage');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Chuẩn hoá email
            $email = strtolower(trim((string) $user->getEmail()));

            /** @var User|null $existing */
            $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);

            // 1) ĐÃ CÓ ACCOUNT & ĐÃ VERIFY  -> không cho đăng ký nữa
            if ($existing && $existing->isVerified()) {
                $form->get('email')->addError(
                    new FormError('This email is already registered. Please sign in or reset your password.')
                );

            } else {
                // 2) CHƯA CÓ -> tạo mới, 3) ĐÃ CÓ NHƯNG CHƯA VERIFY -> dùng lại record đó
                $targetUser = $existing ?: new User();

                $targetUser->setEmail($email);
                $targetUser->setRoles([User::ROLE_CUSTOMER]);

                // Hash password
                $plainPassword  = (string) $form->get('plainPassword')->getData();
                $hashedPassword = $passwordHasher->hashPassword($targetUser, $plainPassword);
                $targetUser->setPassword($hashedPassword);

                // Default avatar
                if (!$targetUser->getAvatar()) {
                    $targetUser->setAvatar('man.png');
                }

                // Đảm bảo account ở trạng thái chưa verify
                $targetUser->setIsVerified(false);

                // Generate OTP mới (ghi đè OTP cũ nếu có)
                $otp = (string) random_int(100000, 999999);
                $targetUser->setOtpCode($otp);
                $targetUser->setOtpExpiresAt(new \DateTimeImmutable('+10 minutes'));

                $em->persist($targetUser);
                $em->flush();

                // Lưu id cho flow verify
                $session->set('register_user_id', $targetUser->getId());

                // Gửi email OTP
                $emailMsg = (new Email())
                    ->to($targetUser->getEmail())
                    ->subject('PC Store - Your verification code')
                    ->html(
                        '<p>Your verification code is: 
                         <strong style="font-size: 18px;">' . $otp .
                        '</strong></p><p>This code is valid for 10 minutes.</p>'
                    );

                $mailer->send($emailMsg);

                // Redirect sang trang nhập OTP
                return $this->redirectToRoute('app_register_verify');
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/register/verify', name: 'app_register_verify', methods: ['GET', 'POST'])]
    public function verifyOtp(
        Request $request,
        EntityManagerInterface $em,
        SessionInterface $session,
        MailerInterface $mailer
    ): Response {
        $userId = $session->get('register_user_id');

        if (!$userId) {
            $this->addFlash('shop.error', 'Verification session not found. Please register again.');
            return $this->redirectToRoute('app_register');
        }

        /** @var User|null $user */
        $user = $em->getRepository(User::class)->find($userId);

        if (!$user) {
            $session->remove('register_user_id');
            $this->addFlash('shop.error', 'Account not found. Please register again.');
            return $this->redirectToRoute('app_register');
        }

        // Xử lý submit OTP
        if ($request->isMethod('POST')) {

            // Nút resend OTP
            if ($request->request->has('resend')) {
                $otp = (string) random_int(100000, 999999);
                $user->setOtpCode($otp);
                $user->setOtpExpiresAt(new \DateTimeImmutable('+10 minutes'));
                $em->flush();

                $emailMsg = (new Email())
                    ->to($user->getEmail())
                    ->subject('PC Store - New verification code')
                    ->html(
                        '<p>Your new verification code is: 
                         <strong style="font-size: 18px;">' . $otp .
                        '</strong></p><p>This code is valid for 10 minutes.</p>'
                    );

                $mailer->send($emailMsg);

                $this->addFlash('shop.info', 'A new verification code has been sent.');
                return $this->redirectToRoute('app_register_verify');
            }

            // Kiểm tra mã OTP
            $inputCode = trim((string) $request->request->get('otp', ''));
            $now       = new \DateTimeImmutable();

            if ($inputCode !== $user->getOtpCode()) {
                $this->addFlash('shop.error', 'Incorrect verification code.');
            } elseif (!$user->getOtpExpiresAt() || $user->getOtpExpiresAt() < $now) {
                $this->addFlash('shop.error', 'Verification code has expired.');
            } else {
                // Success
                $user->setIsVerified(true);
                $user->clearOtp();
                $em->flush();

                $session->remove('register_user_id');

                $this->addFlash('shop.success', 'Your account has been successfully verified! Please log in.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('registration/verify_otp.html.twig', [
            'email' => $user->getEmail(),
        ]);
    }
}
