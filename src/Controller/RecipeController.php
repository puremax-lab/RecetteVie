<?php

namespace App\Controller;

use App\Entity\Recipe;
use App\Entity\User;
use App\Form\RecipeForm;
use App\Repository\CategoryRepository;
use App\Repository\RecipeRepository;
use App\Security\Voter\RecipeVoter;
use App\Service\RecipeImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/recettes')]
final class RecipeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RecipeImageUploader $uploader,
    ) {
    }

    #[Route('', name: 'app_recipe_index', methods: ['GET'])]
    public function index(
        Request $request,
        RecipeRepository $recipes,
        CategoryRepository $categories,
        #[MapQueryParameter] ?string $q = null,
        #[MapQueryParameter] ?string $categorie = null,
        #[MapQueryParameter] int $page = 1,
    ): Response {
        $category = $categorie ? $categories->findOneBy(['slug' => $categorie]) : null;
        $results = $recipes->search($q, $category, $page);
        // Query parameters to keep when building the URL of another page.
        $params = array_filter(['q' => $q, 'categorie' => $category?->getSlug()]);

        // Infinite scroll: Turbo asks for the next page inside a lazy frame,
        // so only the cards of that page are returned.
        if ($request->headers->get('Turbo-Frame') === 'recipes-page-'.$results->current) {
            return $this->render('recipe/_frame.html.twig', [
                'recipes' => $results,
                'params' => $params,
            ]);
        }

        return $this->render('recipe/index.html.twig', [
            'recipes' => $results,
            'params' => $params,
            'categories' => $categories->findAllWithRecipeCount(),
            'currentCategory' => $category,
            'query' => $q,
        ]);
    }

    #[Route('/nouvelle', name: 'app_recipe_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request, #[CurrentUser] User $user): Response
    {
        if (!$user->isVerified()) {
            $this->addFlash('warning', 'flash.verify_email_first');

            return $this->redirectToRoute('app_account');
        }

        $recipe = (new Recipe())->setUser($user);
        $form = $this->createForm(RecipeForm::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImageUpload($form, $recipe);
            $this->em->persist($recipe);
            $this->em->flush();
            $this->addFlash('success', 'flash.recipe_created');

            return $this->redirectToRecipe($recipe);
        }

        return $this->render('recipe/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{slug}-{id}', name: 'app_recipe_show', requirements: ['id' => '\d+', 'slug' => '[a-z0-9-]*'], methods: ['GET'])]
    public function show(
        string $slug,
        #[MapEntity(id: 'id')] Recipe $recipe,
        RecipeRepository $recipes,
    ): Response {
        // Old or mistyped slug: redirect to the canonical URL.
        if ($recipe->getSlug() !== $slug) {
            return $this->redirectToRecipe($recipe, Response::HTTP_MOVED_PERMANENTLY);
        }

        return $this->render('recipe/show.html.twig', [
            'recipe' => $recipe,
            'similarRecipes' => $recipes->findSimilar($recipe),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_recipe_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(RecipeVoter::EDIT, 'recipe')]
    public function edit(Recipe $recipe, Request $request): Response
    {
        $form = $this->createForm(RecipeForm::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImageUpload($form, $recipe);
            $this->em->flush();
            $this->addFlash('success', 'flash.recipe_updated');

            return $this->redirectToRecipe($recipe);
        }

        return $this->render('recipe/edit.html.twig', [
            'recipe' => $recipe,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_recipe_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(RecipeVoter::DELETE, 'recipe')]
    public function delete(Recipe $recipe, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete-recipe-'.$recipe->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->uploader->remove($recipe->getImageName());
        $this->em->remove($recipe);
        $this->em->flush();
        $this->addFlash('success', 'flash.recipe_deleted');

        return $this->redirectToRoute('app_account');
    }

    private function handleImageUpload(FormInterface $form, Recipe $recipe): void
    {
        $file = $form->get('imageFile')->getData();
        if (!$file instanceof UploadedFile) {
            return;
        }

        $previous = $recipe->getImageName();
        $recipe->setImageName($this->uploader->upload($file));
        $this->uploader->remove($previous);
    }

    private function redirectToRecipe(Recipe $recipe, int $status = Response::HTTP_FOUND): Response
    {
        return $this->redirectToRoute('app_recipe_show', [
            'id' => $recipe->getId(),
            'slug' => $recipe->getSlug(),
        ], $status);
    }
}
