<?php
// src/Controller/AdminUserController.php

namespace App\Controller;

use App\Entity\User;
use App\Form\CreateStaffType;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class AdminUserController extends AbstractController
{
    #[Route('', name: 'admin_user_index', methods: ['GET'])]
    public function index(UserRepository $repo): Response
    {
        // Lấy entity từ DB
        $entities = $repo->findBy([], ['id' => 'DESC']);

        // Chuyển sang mảng đơn giản để tránh vòng lặp quan hệ
        $users = [];
        foreach ($entities as $u) {
            $users[] = [
                'id'       => $u->getId(),
                'email'    => $u->getEmail(),
                'fullName' => $u->getFullName(),
                'roles'    => $u->getRoles(),   // mảng string đơn giản
            ];
        }

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/create-staff', name: 'admin_user_create_staff', methods: ['GET', 'POST'])]
    public function createStaff(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em
    ): Response {
        $user = new User();

        $form = $this->createForm(CreateStaffType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setRoles([User::ROLE_STAFF]);

            $plain = (string) $form->get('plainPassword')->getData();
            $hashed = $hasher->hashPassword($user, $plain);
            $user->setPassword($hashed);

            if (!$user->getAvatar()) {
                $user->setAvatar('man.png');
            }

            $user->setIsVerified(true);

            try {
                $em->persist($user);
                $em->flush();

                $this->addFlash(
                    'admin.success',
                    sprintf('Staff account "%s" has been created.', $user->getEmail())
                );

                return $this->redirectToRoute('admin_user_index');
            } catch (UniqueConstraintViolationException $e) {
                $this->addFlash('admin.error', 'This email already exists.');
            }
        }

        return $this->render('admin/users/create_staff.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
