<?php

namespace App\Twig;

use App\Entity\Category;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFilter;

final class AppExtension
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * Translated category name ("category.<slug>" key), falling back to the stored name.
     */
    #[AsTwigFilter('category_name')]
    public function categoryName(Category $category): string
    {
        $key = 'category.'.$category->getSlug();
        $translated = $this->translator->trans($key);

        return $translated === $key ? (string) $category->getName() : $translated;
    }

    /**
     * 95 -> "1 h 35", 40 -> "40 min".
     */
    #[AsTwigFilter('duration')]
    public function duration(?int $minutes): string
    {
        if (null === $minutes) {
            return '';
        }

        if ($minutes < 60) {
            return $minutes.' min';
        }

        $rest = $minutes % 60;

        return intdiv($minutes, 60).' h'.($rest ? ' '.str_pad((string) $rest, 2, '0', \STR_PAD_LEFT) : '');
    }
}
