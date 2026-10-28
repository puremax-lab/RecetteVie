<?php

namespace App\Controller;

use App\Entity\Recipe;
use App\Entity\User;
use App\Service\RecipeTranslator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Preview of the DeepL translation in the recipe form ("Translate with DeepL" button):
 * returns the translated texts as JSON, without saving anything.
 */
final class RecipeTranslationController extends AbstractController
{
    public const CSRF_TOKEN_ID = 'recipe-translate';

    #[Route('/recettes/traduction', name: 'app_recipe_translate', methods: ['POST'], format: 'json')]
    #[IsGranted('ROLE_USER')]
    public function translate(
        Request $request,
        #[CurrentUser] User $user,
        RecipeTranslator $translator,
        RateLimiterFactoryInterface $deeplPreviewLimiter,
        TranslatorInterface $messages,
    ): JsonResponse {
        $payload = $request->toArray();
        $error = static fn (string $key, int $status) => new JsonResponse(['error' => $messages->trans($key)], $status);

        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, (string) ($payload['_token'] ?? ''))) {
            return $error('translate.error_session', Response::HTTP_FORBIDDEN);
        }

        $source = $payload['source'] ?? null;
        $target = ['fr' => 'en', 'en' => 'fr'][$source] ?? null;
        $texts = array_intersect_key((array) ($payload['texts'] ?? []), array_flip(Recipe::TEXT_FIELDS));
        $texts = array_filter(array_map(static fn ($text) => trim((string) $text), $texts), static fn (string $text) => '' !== $text);
        if (null === $target || [] === $texts) {
            return $error('translate.error_empty', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        // Same limits as the form: no huge texts sent to the API.
        if (array_sum(array_map('mb_strlen', $texts)) > 10000) {
            return $error('translate.error_too_long', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$deeplPreviewLimiter->create('user-'.$user->getId())->consume()->isAccepted()) {
            return $error('translate.error_limit', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $translated = $translator->translateTexts($texts, $source, $target);
        if (null === $translated) {
            return $error('translate.error_unavailable', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse(['target' => $target, 'texts' => $translated]);
    }
}
