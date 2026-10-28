<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileForm;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte')]
#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    #[Route('', name: 'app_account', methods: ['GET'])]
    public function show(#[CurrentUser] User $user): Response
    {
        return $this->render('account/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/modifier', name: 'app_account_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProfileForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'flash.profile_updated');

            return $this->redirectToRoute('app_account');
        }

        return $this->render('account/edit.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/renvoyer-confirmation', name: 'app_account_resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request, #[CurrentUser] User $user, EmailVerifier $emailVerifier): Response
    {
        if (!$this->isCsrfTokenValid('resend-verification', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$user->isVerified()) {
            $emailVerifier->sendEmailConfirmation($user);
            $this->addFlash('info', 'flash.verification_sent');
        }

        return $this->redirectToRoute('app_account');
    }
}
