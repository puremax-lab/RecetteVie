<?php

namespace App\Controller;

use App\Form\UserForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use DateTimeImmutable;

final class AccountController extends AbstractController
{
    #[Route('/account', name: 'app_account')]
    public function index(): Response
    {
        return $this->render('account/index.html.twig', [
            'controller_name' => 'AccountController',
        ]);
    }

    #[Route('/account/edit', name: "app_account_edit")]
    public function edit(Request $request, EntityManagerInterface $em,
    TranslatorInterface $translator): Response{
        /**
        * @var User
        */
        $user=$this->getUser();
        $form = $this->createForm(UserForm::class, $user);
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            $user->setUpdatedAt(new DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', $translator->trans('Account successfuly
            updated !'));
            return $this->redirectToRoute('app_account');
        }
        return $this->render('account/edit.html.twig', [
        'user' => $user,
        'usermonForm' => $form->createView()
        ]);
    }
}
