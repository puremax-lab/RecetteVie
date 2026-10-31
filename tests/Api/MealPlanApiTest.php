<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** The JSON API used by the Semainier app: public recipes, JWT login and each member's meal plan. */
final class MealPlanApiTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testRecipesArePublicWithIngredientsAsAList(): void
    {
        $recipes = $this->json('GET', '/api/recipes');

        self::assertResponseIsSuccessful();
        self::assertCount(12, $recipes);
        self::assertSame(['id', 'title', 'slug', 'duration', 'imageName', 'category', 'ingredients'], array_keys($recipes[0]));
        self::assertIsList($recipes[0]['ingredients']);
    }

    public function testRecipesCanBeSearchedByTitle(): void
    {
        $recipes = $this->json('GET', '/api/recipes?title=tatin');

        self::assertSame(['Tarte tatin'], array_column($recipes, 'title'));
    }

    public function testLoginGivesATokenWithTheFirstName(): void
    {
        $token = $this->login('camille@recettevie.test');
        $payload = json_decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);

        self::assertSame('Camille', $payload['firstname']);
    }

    public function testLoginWithAWrongPasswordFails(): void
    {
        $this->json('POST', '/api/login', ['email' => 'camille@recettevie.test', 'password' => 'wrong']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testMealPlanRequiresAToken(): void
    {
        $this->json('GET', '/api/meal_plan_entries');

        self::assertResponseStatusCodeSame(401);
    }

    public function testANewRecipeReplacesTheOneAlreadyInTheSlot(): void
    {
        $token = $this->login('camille@recettevie.test');
        [$first, $second] = $this->json('GET', '/api/recipes');

        $this->plan($token, $first['id'], '2030-01-07', 'dinner');
        $entry = $this->plan($token, $second['id'], '2030-01-07', 'dinner');

        self::assertResponseStatusCodeSame(201);
        self::assertSame('2030-01-07', $entry['day']);
        $week = $this->json('GET', '/api/meal_plan_entries?day[after]=2030-01-07&day[before]=2030-01-13', token: $token);
        self::assertSame([$second['title']], array_column(array_column($week, 'recipe'), 'title'));
    }

    public function testAMealMustBeLunchOrDinner(): void
    {
        $token = $this->login('camille@recettevie.test');
        $recipe = $this->json('GET', '/api/recipes')[0];

        $this->plan($token, $recipe['id'], '2030-01-08', 'breakfast');

        self::assertResponseStatusCodeSame(422);
    }

    public function testMembersOnlySeeAndDeleteTheirOwnPlan(): void
    {
        $camille = $this->login('camille@recettevie.test');
        $lucas = $this->login('lucas@recettevie.test');
        $recipe = $this->json('GET', '/api/recipes')[0];
        $entry = $this->plan($camille, $recipe['id'], '2030-01-09', 'lunch');

        self::assertNotContains($entry['id'], array_column($this->json('GET', '/api/meal_plan_entries', token: $lucas), 'id'));

        $this->json('DELETE', '/api/meal_plan_entries/'.$entry['id'], token: $lucas);
        self::assertResponseStatusCodeSame(403);

        $this->json('DELETE', '/api/meal_plan_entries/'.$entry['id'], token: $camille);
        self::assertResponseStatusCodeSame(204);
    }

    private function login(string $email): string
    {
        return $this->json('POST', '/api/login', ['email' => $email, 'password' => 'password'])['token'];
    }

    private function plan(string $token, int $recipeId, string $day, string $meal): ?array
    {
        return $this->json('POST', '/api/meal_plan_entries', [
            'recipe' => '/api/recipes/'.$recipeId,
            'day' => $day,
            'meal' => $meal,
        ], $token);
    }

    private function json(string $method, string $uri, ?array $body = null, ?string $token = null): ?array
    {
        $headers = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];
        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $this->client->request($method, $uri, server: $headers, content: $body !== null ? json_encode($body) : null);

        return json_decode($this->client->getResponse()->getContent() ?: 'null', true);
    }
}
