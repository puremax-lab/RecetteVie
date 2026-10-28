<?php

namespace App\Tests\Controller;

use App\Entity\Recipe;
use App\Entity\User;
use App\Repository\RecipeRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class RecipeControllerTest extends AppWebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testHomePageShowsLatestRecipesAndCategories(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Cuisinez');
        self::assertSelectorCount(6, '.category-tile');
        self::assertSelectorCount(6, '.recipe-card');
    }

    public function testListIsPaginated(): void
    {
        $crawler = $this->client->request('GET', '/recettes');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(9, '.recipe-card');
        self::assertCount(1, $crawler->filter('.pagination a.page-link:contains("2")'));

        $this->client->request('GET', '/recettes?page=2');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.recipe-card');
    }

    public function testSearchByTitleOrIngredient(): void
    {
        $this->client->request('GET', '/recettes?q=chocolat');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.recipe-card', 'Mousse au chocolat');

        // "lait de coco" only appears in the ingredients of the curry.
        $this->client->request('GET', '/recettes?q=lait de coco');
        self::assertSelectorCount(1, '.recipe-card');
        self::assertSelectorTextContains('.recipe-card', 'Curry de pois chiches');
    }

    public function testFilterByCategory(): void
    {
        $this->client->request('GET', '/recettes?categorie=boissons');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.recipe-card');
        self::assertSelectorTextContains('.recipe-card', 'Citronnade');
    }

    public function testShowRecipe(): void
    {
        $recipe = $this->findRecipe('Bœuf bourguignon');
        $this->client->request('GET', \sprintf('/recettes/%s-%d', $recipe->getSlug(), $recipe->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bœuf bourguignon');
        self::assertSelectorCount(\count($recipe->getIngredientList()), '.ingredient-list li');
        // Anonymous visitors don't see the edit button.
        self::assertSelectorNotExists('a[href$="/modifier"]');
    }

    public function testWrongSlugRedirectsToCanonicalUrl(): void
    {
        $recipe = $this->findRecipe('Tarte tatin');
        $this->client->request('GET', '/recettes/mauvais-slug-'.$recipe->getId());

        self::assertResponseRedirects(\sprintf('/recettes/tarte-tatin-%d', $recipe->getId()), 301);
    }

    public function testUnknownRecipeReturns404(): void
    {
        $this->client->request('GET', '/recettes/inconnue-99999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testCreateRequiresLogin(): void
    {
        $this->client->request('GET', '/recettes/nouvelle');

        self::assertResponseRedirects('/connexion');
    }

    public function testUnverifiedUserCannotCreate(): void
    {
        $user = $this->getUser('camille@recettevie.test');
        $user->setIsVerified(false);
        static::getContainer()->get('doctrine')->getManager()->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/recettes/nouvelle');

        self::assertResponseRedirects('/compte');

        $user->setIsVerified(true);
        static::getContainer()->get('doctrine')->getManager()->flush();
    }

    public function testCreateRecipeWithPhoto(): void
    {
        $this->client->loginUser($this->getUser('camille@recettevie.test'));
        $crawler = $this->client->request('GET', '/recettes/nouvelle');

        $form = $crawler->selectButton('Enregistrer la recette')->form([
            'recipe_form[title]' => 'Salade niçoise',
            'recipe_form[category]' => (string) $this->findRecipe('Velouté de potimarron')->getCategory()->getId(),
            'recipe_form[duration]' => '20',
            'recipe_form[ingredients]' => "Thon\nTomates\nŒufs durs",
            'recipe_form[content]' => "Coupez les tomates.\nAjoutez le thon et les œufs.",
        ]);
        $form['recipe_form[imageFile]']->upload($this->createTestImage());
        $this->client->submit($form);

        $recipe = $this->findRecipe('Salade niçoise');
        self::assertResponseRedirects(\sprintf('/recettes/salade-nicoise-%d', $recipe->getId()));
        self::assertSame('camille@recettevie.test', $recipe->getUser()->getEmail());
        self::assertNotNull($recipe->getImageName());
        self::assertFileExists(static::getContainer()->getParameter('kernel.project_dir').'/public/uploads/recipes/'.$recipe->getImageName());

        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'publiée');

        // Clean up the uploaded file and the recipe.
        $crawler = $this->client->getCrawler();
        $this->client->submit($crawler->selectButton('Supprimer')->form());
        self::assertResponseRedirects('/compte');
    }

    public function testInvalidRecipeShowsErrors(): void
    {
        $this->client->loginUser($this->getUser('camille@recettevie.test'));
        $crawler = $this->client->request('GET', '/recettes/nouvelle');

        $this->client->submit($crawler->selectButton('Enregistrer la recette')->form([
            'recipe_form[title]' => 'Tarte merde',
            'recipe_form[duration]' => '10',
            'recipe_form[ingredients]' => 'Pommes',
            'recipe_form[content]' => 'Trop court',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'inapproprié');
    }

    public function testAuthorCanEdit(): void
    {
        $recipe = $this->findRecipe('Tarte tatin');
        $this->client->loginUser($recipe->getUser());

        $crawler = $this->client->request('GET', \sprintf('/recettes/%d/modifier', $recipe->getId()));
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->selectButton('Enregistrer la recette')->form([
            'recipe_form[duration]' => '80',
        ]));

        self::assertResponseRedirects();
        self::assertSame(80, $this->findRecipe('Tarte tatin')->getDuration());
    }

    public function testOtherUserCannotEditOrDelete(): void
    {
        $recipe = $this->findRecipe('Tarte tatin'); // written by Lucas
        $this->client->loginUser($this->getUser('camille@recettevie.test'));

        $this->client->request('GET', \sprintf('/recettes/%d/modifier', $recipe->getId()));
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', \sprintf('/recettes/%d/supprimer', $recipe->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanEditAnyRecipe(): void
    {
        $recipe = $this->findRecipe('Tarte tatin');
        $this->client->loginUser($this->getUser('admin@recettevie.test'));

        $this->client->request('GET', \sprintf('/recettes/%d/modifier', $recipe->getId()));
        self::assertResponseIsSuccessful();
    }

    public function testDeleteRequiresValidCsrfToken(): void
    {
        $recipe = $this->findRecipe('Quiche lorraine');
        $this->client->loginUser($recipe->getUser());

        $this->client->request('POST', \sprintf('/recettes/%d/supprimer', $recipe->getId()), ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->findRecipe('Quiche lorraine'));
    }

    private function findRecipe(string $title): ?Recipe
    {
        $repository = static::getContainer()->get(RecipeRepository::class);
        static::getContainer()->get('doctrine')->getManager()->clear();

        return $repository->findOneBy(['title' => $title]);
    }

    private function getUser(string $email): User
    {
        return static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
    }

    private function createTestImage(): UploadedFile
    {
        // Smallest valid PNG (1x1 transparent pixel).
        $path = sys_get_temp_dir().'/recettevie-test.png';
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));

        return new UploadedFile($path, 'salade.png', 'image/png', null, true);
    }
}
