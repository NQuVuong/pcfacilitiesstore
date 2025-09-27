<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create or update a hardcoded admin user'
)]
class CreateAdminUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // === THÔNG TIN ADMIN CỐ ĐỊNH (đổi sau khi vào hệ thống) ===
        $email = 'admin@pc.com';
        $plainPassword = 'Admin@123'; // đổi sau khi đăng nhập lần đầu

        $repo = $this->em->getRepository(User::class);

        // Nếu đã tồn tại => cập nhật role & password; nếu chưa => tạo mới
        $user = $repo->findOneBy(['email' => $email]) ?? new User();
        $isNew = null === $user->getId();

        $user->setEmail($email);

        // Bảo đảm có ROLE_USER và ROLE_ADMIN
        $currentRoles = $user->getRoles();
        $user->setRoles(array_values(array_unique(array_merge($currentRoles, ['ROLE_USER', 'ROLE_ADMIN']))));

        // Hash mật khẩu
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));

        if ($isNew) {
            $this->em->persist($user);
        }
        $this->em->flush();

        $output->writeln(sprintf(
            '<info>%s admin "%s" with password is "%s".</info>',
            $isNew ? 'Created' : 'Updated',
            $email,
            $plainPassword
        ));

        return Command::SUCCESS;
    }
}
