<?php

namespace App\Controller;

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
    /** Generate random OTP code */
    private function generateOtp(int $length = 6): string
    {
        $min = (int) pow(10, $length - 1);
        $max = (int) pow(10, $length) - 1;
        return (string) random_int($min, $max);
    }

    /** Send OTP email */
    private function sendOtp(MailerInterface $mailer, string $to, string $otp): void
    {
        $html = <<<HTML
            <p>Your verification code is:</p>
            <h2 style="font-family:Arial;letter-spacing:3px;">$otp</h2>
            <p>This code will expire in <b>10 minutes</b>.</p>
        HTML;

        $email = (new Email())
            ->to($to)
            ->subject('Your OTP code')
            ->html($html);

        $mailer->send($email);
    }

    /**
     * OTP verification page for registration
     */
    #[Route('/otp/register', name: 'app_otp_register', methods: ['GET', 'POST'])]
    public function registerOtp(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response {

        // Get email from query string
        $email = (string) $request->query->get('email', '');

        if ($email === '') {
            $this->addFlash('shop.error', 'Missing email parameter.');
            return $this->redirectToRoute('app_register');
        }

        /** @var User|null $user */
        $user = $users->findOneBy(['email' => $email]);

        if (!$user) {
            $this->addFlash('shop.error', 'Account not found.');
            return $this->redirectToRoute('app_register');
        }

        // If OTP missing or expired → generate new one
        if (
            !$user->getOtpCode() ||
            !$user->getOtpExpiresAt() ||
            $user->getOtpExpiresAt() <= new \DateTimeImmutable()
        ) {
            $otp = $this->generateOtp();
            $user->setOtpCode($otp);
            $user->setOtpExpiresAt(new \DateTimeImmutable('+10 minutes'));
            $em->flush();

            $this->sendOtp($mailer, $user->getEmail(), $otp);
        }

        // Handle OTP submit
        if ($request->isMethod('POST')) {
            $code = trim((string) $request->request->get('otp', ''));

            if ($code === '') {
                $this->addFlash('shop.error', 'Please enter the OTP.');
                return $this->redirectToRoute('app_otp_register', ['email' => $email]);
            }

            if (
                !$user->getOtpCode() ||
                !$user->getOtpExpiresAt() ||
                $user->getOtpExpiresAt() < new \DateTimeImmutable()
            ) {
                $this->addFlash('shop.error', 'OTP has expired. A new one has been sent.');
                return $this->redirectToRoute('app_otp_register', ['email' => $email]);
            }

            if ($code !== $user->getOtpCode()) {
                $this->addFlash('shop.error', 'Incorrect OTP.');
                return $this->redirectToRoute('app_otp_register', ['email' => $email]);
            }

            // OTP correct → verify account
            $user->setIsVerified(true);
            $user->clearOtp();
            $em->flush();

            $this->addFlash('shop.success', 'Your account has been verified!');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/otp_register.html.twig', [
            'email' => $email,
        ]);
    }

    /**
     * Resend OTP
     */
    #[Route('/otp/register/resend', name: 'app_otp_register_resend', methods: ['POST'])]
    public function resendOtp(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response {

        $email = (string) $request->request->get('email', '');

        /** @var User|null $user */
        $user = $users->findOneBy(['email' => $email]);

        if ($user && !$user->isVerified()) {
            $otp = $this->generateOtp();
            $user->setOtpCode($otp);
            $user->setOtpExpiresAt(new \DateTimeImmutable('+10 minutes'));
            $em->flush();

            $this->sendOtp($mailer, $email, $otp);

            $this->addFlash('shop.info', 'A new OTP has been sent.');
        }

        return $this->redirectToRoute('app_otp_register', ['email' => $email]);
    }
}
