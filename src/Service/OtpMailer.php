<?php

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class OtpMailer
{
    public function __construct(private MailerInterface $mailer) {}

    /**
     * $to: địa chỉ email người nhận
     * $otp: mã OTP (string, ví dụ "482913")
     * $purpose: mục đích ("register" | "reset")
     */
    public function sendOtp(string $to, string $otp, string $purpose = 'register'): void
    {
        $subject = $purpose === 'reset' ? 'Your password reset OTP' : 'Your registration OTP';

        $email = (new TemplatedEmail())
            ->to($to)
            ->subject($subject)
            ->htmlTemplate('emails/otp.html.twig')
            ->context([
                'otp'     => $otp,
                'purpose' => $purpose,
            ]);

        $this->mailer->send($email);
    }
}
