<?php

namespace App\Controller\Auth;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

class OtpController extends AbstractController
{
    #[Route('/verify-otp', name: 'app_verify_otp', methods: ['GET','POST'])]
    public function verifyOtp(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em
    ): Response {
        $email = (string)$request->query->get('email', '');
        $user  = $email ? $users->findOneBy(['email' => $email]) : null;

        if (!$user) {
            $this->addFlash('shop.error', 'Invalid email.');
            return $this->redirectToRoute('app_register');
        }

        if ($request->isMethod('POST')) {
            $code = trim((string)$request->request->get('otp', ''));
            $now  = new \DateTimeImmutable();

            if ($user->isVerified()) {
                $this->addFlash('shop.success', 'Your account is already verified. Please sign in.');
                return $this->redirectToRoute('app_login');
            }

            if (!$user->getOtpCode() || !$user->getOtpExpiresAt() || $user->getOtpExpiresAt() < $now) {
                $this->addFlash('shop.error', 'Code is invalid or expired. Please resend.');
            } elseif (!hash_equals($user->getOtpCode(), $code)) {
                $this->addFlash('shop.error', 'Code is incorrect.');
            } else {
                $user->setIsVerified(true)->setOtpCode(null)->setOtpExpiresAt(null);
                $em->flush();
                $this->addFlash('shop.success', 'Email verified. You can sign in now.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/verify_otp.html.twig', [
            'email' => $email,
            'expires' => $user->getOtpExpiresAt(),
        ]);
    }

    #[Route('/resend-otp', name: 'app_resend_otp', methods: ['POST'])]
    public function resendOtp(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response {
        $email = trim((string)$request->request->get('email', ''));
        $user  = $users->findOneBy(['email' => $email]);
        if ($user && !$user->isVerified()) {
            $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $user->setOtpCode($otp)->setOtpExpiresAt(new \DateTimeImmutable('+10 minutes'));
            $em->flush();

            $mail = (new Email())
                ->to($email)
                ->subject('Your new verification code')
                ->html("<p>Your OTP code is: <strong style='font-size:18px'>{$otp}</strong> (valid for 10 minutes)</p>");
            $mailer->send($mail);

            $this->addFlash('shop.info', 'A new code has been sent.');
        }
        return $this->redirectToRoute('app_verify_otp', ['email' => $email]);
    }
}
