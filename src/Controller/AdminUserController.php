<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\CreateStaffType;
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
    public function index(EntityManagerInterface $em): Response
    {
        $users = $em->getRepository(User::class)->findBy([], ['id' => 'DESC']);

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/create-staff', name: 'admin_user_create_staff', methods: ['GET','POST'])]
    public function createStaff(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em
    ): Response {
        $user = new User();
        $form = $this->createForm(CreateStaffType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                // show lỗi ngay trên form
                $this->addFlash('admin_error', 'Vui lòng kiểm tra lại các trường bị lỗi.');
            } else {
                try {
                    // Chuẩn hoá email (tránh trùng hoa/thường)
                    $user->setEmail(mb_strtolower((string)$user->getEmail()));

                    // Mật khẩu
                    $plain = (string)$form->get('plainPassword')->getData();
                    $user->setPassword($hasher->hashPassword($user, $plain));

                    // Role mặc định: staff
                    $user->setRoles(['ROLE_STAFF']);

                    $em->persist($user);
                    $em->flush();

                    $this->addFlash('admin_success', 'Đã tạo tài khoản Staff: '.$user->getEmail());

                    // quay về danh sách để thấy kết quả
                    return $this->redirectToRoute('admin_user_index');
                } catch (UniqueConstraintViolationException $e) {
                    $this->addFlash('admin_error', 'Email đã tồn tại.');
                } catch (\Throwable $e) {
                    // tránh “nuốt” lỗi im lặng
                    $this->addFlash('admin_error', 'Không thể tạo tài khoản: '.$e->getMessage());
                }
            }
        }

        return $this->render('admin/users/create_staff.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
