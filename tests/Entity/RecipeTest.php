<?php

namespace App\Tests\Entity;

use App\Entity\Recipe;
use App\Twig\AppExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RecipeTest extends TestCase
{
    public function testSlugIsComputedFromTitle(): void
    {
        $recipe = (new Recipe())->setTitle('Bœuf Bourguignon à l\'ancienne');
        $recipe->computeSlug();

        self::assertSame('boeuf-bourguignon-a-l-ancienne', $recipe->getSlug());
    }

    public function testStepsIgnoreBlankLinesAndListMarkers(): void
    {
        $recipe = (new Recipe())->setContent("1. Préchauffez le four.\n\n2) Mélangez.\n- Enfournez.\n   \n");

        self::assertSame(['Préchauffez le four.', 'Mélangez.', 'Enfournez.'], $recipe->getSteps());
    }

    public function testDurationFormatting(): void
    {
        $extension = new AppExtension($this->createStub(TranslatorInterface::class));

        self::assertSame('45 min', $extension->duration(45));
        self::assertSame('1 h', $extension->duration(60));
        self::assertSame('3 h 30', $extension->duration(210));
        self::assertSame('1 h 05', $extension->duration(65));
    }
}
