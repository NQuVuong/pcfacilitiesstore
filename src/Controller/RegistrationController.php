<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

            // Default role
            $user->setRoles([User::ROLE_CUSTOMER]);

            // Hash password
            $plainPassword = (string) $form->get('plainPassword')->getData();
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            // Default avatar
            if (!$user->getAvatar()) {
                $user->setAvatar('man.png');
            }

            // Mark user as unverified
            $user->setIsVerified(false);

            // Generate OTP
            $otp = (string) random_int(100000, 999999);
            $user->setOtpCode($otp);
            $user->setOtpExpiresAt(new \DateTimeImmutable('+10 minutes'));

            // Save user
            $em->persist($user);
            $em->flush();

            // Save user id for verification flow
            $session->set('register_user_id', $user->getId());

            // Send OTP email
            $email = (new Email())
                ->to($user->getEmail())
                ->subject('PC Store - Your verification code')
                ->html(
                    '<p>Your verification code is: 
                     <strong style="font-size: 18px;">' . $otp . 
                    '</strong></p><p>This code is valid for 10 minutes.</p>'
                );

            $mailer->send($email);

            // Redirect to OTP verification page
            return $this->redirectToRoute('app_register_verify');
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

        // Handle OTP submission
        if ($request->isMethod('POST')) {

            // Resend OTP request
            if ($request->request->has('resend')) {
                $otp = (string) random_int(100000, 999999);
                $user->setOtpCode($otp);
                $user->setOtpExpiresAt(new \DateTimeImmutable('+10 minutes'));
                $em->flush();

                $email = (new Email())
                    ->to($user->getEmail())
                    ->subject('PC Store - New verification code')
                    ->html(
                        '<p>Your new verification code is: 
                         <strong style="font-size: 18px;">' . $otp .
                        '</strong></p><p>This code is valid for 10 minutes.</p>'
                    );

                $mailer->send($email);

                $this->addFlash('shop.info', 'A new verification code has been sent.');
                return $this->redirectToRoute('app_register_verify');
            }

            // Normal OTP verification
            $inputCode = trim((string) $request->request->get('otp', ''));
            $now = new \DateTimeImmutable();

            if ($inputCode !== $user->getOtpCode()) {
                $this->addFlash('shop.error', 'Incorrect verification code.');
            } elseif (!$user->getOtpExpiresAt() || $user->getOtpExpiresAt() < $now) {
                $this->addFlash('shop.error', 'Verification code has expired.');
            } else {
                // Success: verify account
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
