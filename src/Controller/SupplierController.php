<?php

namespace App\Controller;

use App\Entity\Supplier;
use App\Form\SupplierType;
use App\Repository\SupplierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/supplier')]
#[IsGranted('ROLE_STAFF')]
class SupplierController extends AbstractController
{
    #[Route('', name: 'app_supplier_index')]
    public function index(SupplierRepository $repo): Response
    {
        return $this->render('supplier/index.html.twig', [
            'suppliers' => $repo->findBy([], ['createdAt' => 'DESC'])
        ]);
    }

    #[Route('/new', name: 'app_supplier_new')]
    public function new(Request $req, EntityManagerInterface $em): Response
    {
        $s = new Supplier();
        $form = $this->createForm(SupplierType::class, $s);
        $form->handleRequest($req);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($s); $em->flush();
            $this->addFlash('admin.success', 'Supplier created.');
            return $this->redirectToRoute('app_supplier_index');
        }
        return $this->render('supplier/form.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/{id}/edit', name: 'app_supplier_edit')]
    public function edit(Supplier $s, Request $req, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(SupplierType::class, $s);
        $form->handleRequest($req);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('admin.success', 'Supplier updated.');
            return $this->redirectToRoute('app_supplier_index');
        }
        return $this->render('supplier/form.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/{id}/delete', name: 'app_supplier_delete', methods: ['POST'])]
    public function delete(Supplier $s, EntityManagerInterface $em, Request $req): Response
    {
        if ($this->isCsrfTokenValid('del_sup_' . $s->getId(), $req->request->get('_token'))) {
            $em->remove($s); $em->flush();
            $this->addFlash('admin.success', 'Supplier deleted.');
        } else {
            $this->addFlash('admin.error', 'Invalid token.');
        }
        return $this->redirectToRoute('app_supplier_index');
    }
}
