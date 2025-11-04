<?php

namespace App\Controller\Account;

use App\Entity\User;
use App\Form\Account\ProfileFormType;
use App\Form\Account\ProfileAvatarType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // --- Form thông tin cá nhân ---
        $profileForm = $this->createForm(ProfileFormType::class, $user);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $em->flush();
            $this->addFlash('shop.success', 'Cập nhật thông tin cá nhân thành công.');
            return $this->redirectToRoute('account_profile');
        }

        // --- Form avatar (tách form riêng) ---
        $avatarForm = $this->createForm(ProfileAvatarType::class);
        $avatarForm->handleRequest($request);

        if ($avatarForm->isSubmitted() && $avatarForm->isValid()) {
            $file = $avatarForm->get('avatarFile')->getData();
            if ($file) {
                $safeName = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))->lower();
                $newName  = sprintf('%s-%s.%s',
                    $safeName,
                    uniqid(),
                    $file->guessExtension() ?: 'bin'
                );

                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars';
                try {
                    $file->move($uploadsDir, $newName);
                } catch (FileException $e) {
                    $this->addFlash('shop.error', 'Upload avatar thất bại: ' . $e->getMessage());
                    return $this->redirectToRoute('account_profile');
                }

                $user->setAvatar($newName);
                $em->flush();

                $this->addFlash('shop.success', 'Ảnh đại diện đã được cập nhật.');
                return $this->redirectToRoute('account_profile');
            }

            $this->addFlash('shop.info', 'Không có file nào được chọn.');
            return $this->redirectToRoute('account_profile');
        }

        return $this->render('account/profile/index.html.twig', [
            'profile_form' => $profileForm->createView(),
            'avatar_form'  => $avatarForm->createView(),
            'user'         => $user,
        ]);
    }
}
