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
    public function index(UserRepository $repo, Request $request): Response
    {
        $page    = max(1, (int) $request->query->get('page', 1));
        $perPage = 10;

        $allUsers = $repo->findBy([], ['id' => 'DESC']);
        $total    = \count($allUsers);
        $pageCount = (int) ceil($total / $perPage);
        $page      = min($page, max(1, $pageCount));

        $offset = ($page - 1) * $perPage;
        $users  = \array_slice($allUsers, $offset, $perPage);

        return $this->render('admin/users/index.html.twig', [
            'users'      => $users,
            'page'       => $page,
            'pageCount'  => $pageCount,
            'totalUsers' => $total,
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

    #[Route('/create-admin', name: 'admin_user_create_admin', methods: ['GET', 'POST'])]
    public function createAdmin(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em
    ): Response {
        $user = new User();
        // dùng lại form CreateStaffType (email + password)
        $form = $this->createForm(CreateStaffType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setRoles([User::ROLE_ADMIN]);

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
                    sprintf('Admin account "%s" has been created.', $user->getEmail())
                );

                return $this->redirectToRoute('admin_user_index');
            } catch (UniqueConstraintViolationException $e) {
                $this->addFlash('admin.error', 'This email already exists.');
            }
        }

        return $this->render('admin/users/create_admin.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
