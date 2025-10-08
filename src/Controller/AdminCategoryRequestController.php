<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\CategoryRequest;
use App\Repository\CategoryRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/category-requests')]
#[IsGranted('ROLE_ADMIN')]
class AdminCategoryRequestController extends AbstractController
{
    #[Route('', name: 'app_admin_catreq_index')]
    public function index(CategoryRequestRepository $repo): Response
    {
        return $this->render('admin/catreq/index.html.twig', [
            'items' => $repo->findBy([], ['createdAt' => 'DESC'])
        ]);
    }

    #[Route('/{id}/approve', name: 'app_admin_catreq_approve')]
    public function approve(CategoryRequest $reqItem, EntityManagerInterface $em): Response
    {
        if ($reqItem->getStatus() !== CategoryRequest::STATUS_PENDING) {
            $this->addFlash('admin.warning', 'Request already processed.');
            return $this->redirectToRoute('app_admin_catreq_index');
        }

        $c = new Category();
        $c->setName($reqItem->getName());
        $em->persist($c);

        $reqItem->setStatus(CategoryRequest::STATUS_APPROVED);
        $reqItem->setDecidedBy($this->getUser());
        $reqItem->setDecidedAt(new \DateTimeImmutable());

        $em->flush();
        $this->addFlash('admin.success', 'Approved & category created.');
        return $this->redirectToRoute('app_admin_catreq_index');
    }

    #[Route('/{id}/reject', name: 'app_admin_catreq_reject')]
    public function reject(CategoryRequest $reqItem, EntityManagerInterface $em): Response
    {
        if ($reqItem->getStatus() !== CategoryRequest::STATUS_PENDING) {
            $this->addFlash('admin.warning', 'Request already processed.');
            return $this->redirectToRoute('app_admin_catreq_index');
        }
        $reqItem->setStatus(CategoryRequest::STATUS_REJECTED);
        $reqItem->setDecidedBy($this->getUser());
        $reqItem->setDecidedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('admin.info', 'Rejected.');
        return $this->redirectToRoute('app_admin_catreq_index');
    }
}
