<?php

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Build a fresh SQLite database with the demo data before the test suite runs.
$kernel = new Kernel('test', true);
$kernel->boot();
$application = new Application($kernel);
$application->setAutoExit(false);

foreach ([
    ['command' => 'doctrine:schema:drop', '--force' => true, '--full-database' => true],
    ['command' => 'doctrine:schema:create'],
    ['command' => 'doctrine:fixtures:load', '--no-interaction' => true],
] as $command) {
    if (0 !== $application->run(new ArrayInput($command), new NullOutput())) {
        throw new RuntimeException(sprintf('Test database setup failed on "%s".', $command['command']));
    }
}

$kernel->shutdown();
