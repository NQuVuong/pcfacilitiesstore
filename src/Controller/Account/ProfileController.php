<?php

namespace App\Controller\Account;

use App\Entity\User;
use App\Form\Account\ProfileFormType;
use App\Form\Account\ProfileAvatarType;
use App\Form\Account\ChangePasswordType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/account', name: 'account_')]
class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'profile', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        ProductRepository $productRepo,
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Profile form
        $profileForm = $this->createForm(ProfileFormType::class, $user);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $em->flush();
            $this->addFlash('shop.success', 'Profile updated successfully.');
            return $this->redirectToRoute('account_profile');
        }

        // Avatar form
        $avatarForm = $this->createForm(ProfileAvatarType::class);
        $avatarForm->handleRequest($request);

        if ($avatarForm->isSubmitted() && $avatarForm->isValid()) {
            $file = $avatarForm->get('avatarFile')->getData();

            if ($file) {
                $safeName = $slugger
                    ->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                    ->lower();

                $newName = sprintf(
                    '%s-%s.%s',
                    $safeName,
                    uniqid(),
                    $file->guessExtension() ?: 'bin'
                );

                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars';

                try {
                    $file->move($uploadsDir, $newName);
                } catch (FileException $e) {
                    $this->addFlash('shop.error', 'Failed to upload avatar: ' . $e->getMessage());
                    return $this->redirectToRoute('account_profile');
                }

                $user->setAvatar($newName);
                $em->flush();

                $this->addFlash('shop.success', 'Avatar updated.');
                return $this->redirectToRoute('account_profile');
            }

            $this->addFlash('shop.info', 'No file selected.');
            return $this->redirectToRoute('account_profile');
        }

        // ===== Recently viewed products for this session =====
        $session = $request->getSession();
        $ids = $session->get('recent_products', []);
        $recentProducts = [];

        if ($ids) {
            $recentProducts = $productRepo->createQueryBuilder('p')
                ->where('p.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getResult();

            $map = [];
            foreach ($recentProducts as $prod) {
                $map[$prod->getId()] = $prod;
            }
            $ordered = [];
            foreach ($ids as $id) {
                if (isset($map[$id])) {
                    $ordered[] = $map[$id];
                }
            }
            $recentProducts = $ordered;
        }

        return $this->render('account/profile/index.html.twig', [
            'profile_form'    => $profileForm->createView(),
            'avatar_form'     => $avatarForm->createView(),
            'user'            => $user,
            'recentProducts'  => $recentProducts,
        ]);
    }

    #[Route('/change-password', name: 'change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();

            $current = (string) $form->get('currentPassword')->getData();
            if (!$hasher->isPasswordValid($user, $current)) {
                $this->addFlash('shop.error', 'Current password is incorrect.');
            } else {
                $new = (string) $form->get('newPassword')->getData();
                $user->setPassword($hasher->hashPassword($user, $new));
                $em->flush();

                $this->addFlash('shop.success', 'Password changed successfully.');
                return $this->redirectToRoute('account_profile');
            }
        }

        return $this->render('account/change_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
