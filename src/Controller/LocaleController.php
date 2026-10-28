<?php

namespace App\Controller;

use App\EventSubscriber\LocaleSubscriber;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController extends AbstractController
{
    #[Route('/langue/{locale}', name: 'app_locale', requirements: ['locale' => '%app.locales%'], methods: ['GET'])]
    public function switch(string $locale, Request $request): RedirectResponse
    {
        $request->getSession()->set(LocaleSubscriber::SESSION_KEY, $locale);

        // Go back to the page the visitor was on, but only within this site.
        $referer = (string) $request->headers->get('referer');
        if (str_starts_with($referer, $request->getSchemeAndHttpHost().'/')) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('home');
    }
}
