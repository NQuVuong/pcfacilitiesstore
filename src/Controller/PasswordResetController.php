<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\PasswordResetToken;
use App\Repository\UserRepository;
use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

class PasswordResetController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password_request', methods: ['GET','POST'])]
    public function request(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response {
        if ($request->isMethod('POST')) {
            $email = trim((string)$request->request->get('email', ''));
            $user  = $users->findOneBy(['email' => $email]);

            if ($user) {
                // tạo token reset và lưu DB
                $token = (new PasswordResetToken())
                    ->setUser($user)
                    ->setToken(Uuid::v4()->toRfc4122())
                    ->setExpiresAt(new \DateTimeImmutable('+30 minutes'));

                $em->persist($token);
                $em->flush();

                // link reset gửi qua mailtrap
                $resetUrl = $this->generateUrl(
                    'app_reset_password',
                    ['token' => $token->getToken()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $mail = (new Email())
                    ->to($user->getEmail())
                    ->subject('Reset your password')
                    ->html(sprintf(
                        '<p>Click here to reset your password:</p><p><a href="%s">%s</a></p>',
                        $resetUrl,
                        $resetUrl
                    ));

                $mailer->send($mail);
            }

            $this->addFlash('shop.info', 'If the email exists, we have sent a reset link.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET','POST'])]
    public function reset(
        string $token,
        Request $request,
        PasswordResetTokenRepository $tokens,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $row = $tokens->findOneBy(['token' => $token]);

        if (!$row || $row->getExpiresAt() <= new \DateTimeImmutable()) {
            $this->addFlash('shop.error', 'Token is invalid or expired.');
            return $this->redirectToRoute('app_forgot_password_request');
        }

        if ($request->isMethod('POST')) {
            $pwd = (string)$request->request->get('password', '');

            if (strlen($pwd) < 6) {
                $this->addFlash('shop.error', 'Password must be at least 6 characters.');
            } else {
                /** @var User $user */
                $user = $row->getUser();
                $user->setPassword($hasher->hashPassword($user, $pwd));

                $em->remove($row);
                $em->flush();

                $this->addFlash('shop.success', 'Password updated. Please sign in.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'token' => $token,
        ]);
    }
}
