<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The test browser announces English by default; our assertions use the
 * French interface, so every client says it prefers French.
 */
abstract class AppWebTestCase extends WebTestCase
{
    protected static function createClient(array $options = [], array $server = []): KernelBrowser
    {
        return parent::createClient($options, $server + ['HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9']);
    }
}
