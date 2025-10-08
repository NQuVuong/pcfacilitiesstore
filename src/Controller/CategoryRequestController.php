<?php

namespace App\Controller;

use App\Entity\CategoryRequest;
use App\Form\CategoryRequestType;
use App\Repository\CategoryRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/staff/category-requests')]
#[IsGranted('ROLE_STAFF')]
class CategoryRequestController extends AbstractController
{
    #[Route('', name: 'app_catreq_index')]
    public function index(CategoryRequestRepository $repo): Response
    {
        return $this->render('catreq/index.html.twig', [
            'items' => $repo->findBy(['requestedBy' => $this->getUser()], ['createdAt' => 'DESC'])
        ]);
    }

    #[Route('/new', name: 'app_catreq_new')]
    public function new(Request $req, EntityManagerInterface $em): Response
    {
        $cr = new CategoryRequest();
        $cr->setRequestedBy($this->getUser());
        $form = $this->createForm(CategoryRequestType::class, $cr);
        $form->handleRequest($req);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($cr); $em->flush();
            $this->addFlash('admin.success', 'Request sent.');
            return $this->redirectToRoute('app_catreq_index');
        }

        return $this->render('catreq/form.html.twig', ['form' => $form->createView()]);
    }
}
