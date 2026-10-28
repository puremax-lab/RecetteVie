<?php

namespace App\Tests\Controller;

use App\Repository\UserRepository;

final class SecurityControllerTest extends AppWebTestCase
{
    public function testLoginWithDemoAccount(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/connexion');

        $client->submit($crawler->selectButton('Se connecter')->form([
            '_username' => 'camille@recettevie.test',
            '_password' => 'password',
        ]));

        self::assertResponseRedirects('/compte');
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Camille Dupont');
    }

    public function testLoginWithWrongPassword(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/connexion');

        $client->submit($crawler->selectButton('Se connecter')->form([
            '_username' => 'camille@recettevie.test',
            '_password' => 'wrong',
        ]));
        $client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'incorrect');
    }

    public function testRegistrationCreatesUnverifiedUserAndSendsEmail(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/inscription');

        $client->submit($crawler->selectButton('Créer mon compte')->form([
            'registration_form[firstname]' => 'Nina',
            'registration_form[lastname]' => 'Leroy',
            'registration_form[email]' => 'nina@example.test',
            'registration_form[plainPassword]' => 'un-mot-de-passe-solide',
            'registration_form[agreeTerms]' => true,
        ]));

        self::assertEmailCount(1);
        self::assertEmailHeaderSame(self::getMailerMessage(), 'To', 'nina@example.test');

        self::assertResponseRedirects('/compte');
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'nina@example.test']);
        self::assertNotNull($user);
        self::assertFalse($user->isVerified());
        self::assertNotSame('un-mot-de-passe-solide', $user->getPassword());
    }

    public function testRegistrationRejectsDuplicateEmail(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/inscription');

        $client->submit($crawler->selectButton('Créer mon compte')->form([
            'registration_form[firstname]' => 'Camille',
            'registration_form[lastname]' => 'Bis',
            'registration_form[email]' => 'camille@recettevie.test',
            'registration_form[plainPassword]' => 'un-mot-de-passe-solide',
            'registration_form[agreeTerms]' => true,
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'existe déjà');
    }

    public function testLanguageSwitch(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertSelectorTextContains('nav', 'Recettes');

        $client->request('GET', '/langue/en');
        $client->followRedirect();

        self::assertSelectorTextContains('nav', 'Recipes');
        self::assertSelectorTextContains('h1', 'Cook, share, enjoy.');
    }
}
