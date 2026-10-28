<?php

namespace App\Entity;

use App\Entity\Traits\Timestampable;
use App\Repository\RecipeRepository;
use App\Validator\InappropriateWords;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RecipeRepository::class)]
#[ORM\Table(name: 'recipes')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity('title', message: 'recipe.title_taken')]
class Recipe
{
    use Timestampable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 80)]
    #[InappropriateWords]
    private ?string $title = null;

    #[ORM\Column(length: 100)]
    private ?string $slug = null;

    /** One ingredient per line. */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $ingredients = null;

    /** Preparation steps, one per line. */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 20)]
    #[InappropriateWords]
    private ?string $content = null;

    /** Optional English version; the French text is shown when it is empty. */
    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(min: 3, max: 80)]
    #[InappropriateWords]
    private ?string $titleEn = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $ingredientsEn = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[InappropriateWords]
    private ?string $contentEn = null;

    /** Total time in minutes. */
    #[ORM\Column]
    #[Assert\NotBlank]
    #[Assert\Positive]
    #[Assert\LessThan(1440)]
    private ?int $duration = null;

    /** File name of the uploaded photo in public/uploads/recipes, if any. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageName = null;

    /** True when the English version was produced by machine translation. */
    #[ORM\Column(options: ['default' => false])]
    private bool $autoTranslated = false;

    #[ORM\ManyToOne(inversedBy: 'recipes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Category $category = null;

    #[ORM\ManyToOne(inversedBy: 'recipes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function computeSlug(): void
    {
        $this->slug = (new AsciiSlugger())->slug((string) $this->title)->lower()->toString();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function getIngredients(): ?string
    {
        return $this->ingredients;
    }

    public function setIngredients(string $ingredients): static
    {
        $this->ingredients = $ingredients;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getIngredientList(): array
    {
        return self::splitLines($this->ingredients);
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getSteps(): array
    {
        return self::splitLines($this->content);
    }

    public function getTitleEn(): ?string
    {
        return $this->titleEn;
    }

    public function setTitleEn(?string $titleEn): static
    {
        $this->titleEn = $titleEn;

        return $this;
    }

    public function getIngredientsEn(): ?string
    {
        return $this->ingredientsEn;
    }

    public function setIngredientsEn(?string $ingredientsEn): static
    {
        $this->ingredientsEn = $ingredientsEn;

        return $this;
    }

    public function getContentEn(): ?string
    {
        return $this->contentEn;
    }

    public function setContentEn(?string $contentEn): static
    {
        $this->contentEn = $contentEn;

        return $this;
    }

    /**
     * Title in the visitor's language, falling back to French.
     */
    public function getLocalizedTitle(string $locale): ?string
    {
        return 'en' === $locale && $this->titleEn ? $this->titleEn : $this->title;
    }

    /**
     * @return list<string>
     */
    public function getLocalizedIngredientList(string $locale): array
    {
        return self::splitLines('en' === $locale && $this->ingredientsEn ? $this->ingredientsEn : $this->ingredients);
    }

    /**
     * @return list<string>
     */
    public function getLocalizedSteps(string $locale): array
    {
        return self::splitLines('en' === $locale && $this->contentEn ? $this->contentEn : $this->content);
    }

    /**
     * The three English fields, to detect whether the author edited them.
     *
     * @return array{?string, ?string, ?string}
     */
    public function getEnglishFields(): array
    {
        return [$this->titleEn, $this->ingredientsEn, $this->contentEn];
    }

    public function hasEnglishVersion(): bool
    {
        return [] !== array_filter($this->getEnglishFields(), static fn (?string $value) => null !== $value && '' !== trim($value));
    }

    public function isAutoTranslated(): bool
    {
        return $this->autoTranslated;
    }

    public function setAutoTranslated(bool $autoTranslated): static
    {
        $this->autoTranslated = $autoTranslated;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getImageName(): ?string
    {
        return $this->imageName;
    }

    public function setImageName(?string $imageName): static
    {
        $this->imageName = $imageName;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return list<string>
     */
    private static function splitLines(?string $text): array
    {
        $lines = array_map(
            // Strip list markers people often type: "-", "*", "1.", "2)"...
            static fn (string $line) => trim(preg_replace('/^\s*(?:[-*•]|\d+[.)])\s*/u', '', $line)),
            preg_split('/\R/u', (string) $text),
        );

        return array_values(array_filter($lines, static fn (string $line) => '' !== $line));
    }
}
