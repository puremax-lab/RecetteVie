<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Traits\Timestampable;
use App\Repository\RecipeRepository;
use App\Validator\InappropriateWords;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RecipeRepository::class)]
#[ORM\Table(name: 'recipes')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity('title', message: 'recipe.title_taken')]
// Read-only in the API (used by the Semainier meal planner): recipes are written on the website.
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['recipe:read']],
    order: ['createdAt' => 'DESC'],
    paginationItemsPerPage: 12,
)]
#[ApiFilter(SearchFilter::class, properties: ['title' => 'partial', 'category.slug' => 'exact'])]
class Recipe
{
    use Timestampable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['recipe:read'])]
    private ?int $id = null;

    // Each recipe exists in French and in English. The author writes one language;
    // the other one is filled by RecipeTranslator (DeepL) before validation.
    // Both versions follow the same rules.

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'recipe.translation_required')]
    #[Assert\Length(min: 3, max: 80)]
    #[InappropriateWords]
    #[Groups(['recipe:read'])]
    private ?string $title = null;

    #[ORM\Column(length: 100)]
    #[Groups(['recipe:read'])]
    private ?string $slug = null;

    /** One ingredient per line. */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'recipe.translation_required')]
    private ?string $ingredients = null;

    /** Preparation steps, one per line. */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'recipe.translation_required')]
    #[Assert\Length(min: 20)]
    #[InappropriateWords]
    private ?string $content = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\NotBlank(message: 'recipe.translation_required')]
    #[Assert\Length(min: 3, max: 80)]
    #[InappropriateWords]
    private ?string $titleEn = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\NotBlank(message: 'recipe.translation_required')]
    private ?string $ingredientsEn = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\NotBlank(message: 'recipe.translation_required')]
    #[Assert\Length(min: 20)]
    #[InappropriateWords]
    private ?string $contentEn = null;

    /** Total time in minutes. */
    #[ORM\Column]
    #[Assert\NotBlank]
    #[Assert\Positive]
    #[Assert\LessThan(1440)]
    #[Groups(['recipe:read'])]
    private ?int $duration = null;

    /** File name of the uploaded photo in public/uploads/recipes, if any. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['recipe:read'])]
    private ?string $imageName = null;

    /** Language ("fr" or "en") whose text was produced by machine translation, if any. */
    #[ORM\Column(length: 2, nullable: true)]
    private ?string $machineTranslatedLocale = null;

    #[ORM\ManyToOne(inversedBy: 'recipes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['recipe:read'])]
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

    public function setTitle(?string $title): static
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

    public function setIngredients(?string $ingredients): static
    {
        $this->ingredients = $ingredients;

        return $this;
    }

    /**
     * @return list<string>
     */
    #[Groups(['recipe:read'])]
    #[SerializedName('ingredients')]
    public function getIngredientList(): array
    {
        return self::splitLines($this->ingredients);
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
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

    /** The translatable fields, in the same order in both languages. */
    public const TEXT_FIELDS = ['title', 'ingredients', 'content'];

    public function getText(string $field, string $locale): ?string
    {
        return match ([$field, $locale]) {
            ['title', 'fr'] => $this->title,
            ['ingredients', 'fr'] => $this->ingredients,
            ['content', 'fr'] => $this->content,
            ['title', 'en'] => $this->titleEn,
            ['ingredients', 'en'] => $this->ingredientsEn,
            ['content', 'en'] => $this->contentEn,
        };
    }

    public function setText(string $field, string $locale, ?string $value): static
    {
        match ([$field, $locale]) {
            ['title', 'fr'] => $this->title = $value,
            ['ingredients', 'fr'] => $this->ingredients = $value,
            ['content', 'fr'] => $this->content = $value,
            ['title', 'en'] => $this->titleEn = $value,
            ['ingredients', 'en'] => $this->ingredientsEn = $value,
            ['content', 'en'] => $this->contentEn = $value,
        };

        return $this;
    }

    /**
     * @return array<string, ?string> field => text
     */
    public function getTexts(string $locale): array
    {
        $texts = [];
        foreach (self::TEXT_FIELDS as $field) {
            $texts[$field] = $this->getText($field, $locale);
        }

        return $texts;
    }

    /**
     * @return list<string> the fields left empty in this language
     */
    public function getMissingFields(string $locale): array
    {
        return array_keys(array_filter($this->getTexts($locale), static fn (?string $text) => null === $text || '' === trim($text)));
    }

    public function isComplete(string $locale): bool
    {
        return [] === $this->getMissingFields($locale);
    }

    public function getMachineTranslatedLocale(): ?string
    {
        return $this->machineTranslatedLocale;
    }

    public function setMachineTranslatedLocale(?string $locale): static
    {
        $this->machineTranslatedLocale = $locale;

        return $this;
    }

    public function isMachineTranslated(string $locale): bool
    {
        return $this->machineTranslatedLocale === $locale;
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
    public static function splitLines(?string $text): array
    {
        $lines = array_map(
            // Strip list markers people often type: "-", "*", "1.", "2)"...
            static fn (string $line) => trim(preg_replace('/^\s*(?:[-*•]|\d+[.)])\s*/u', '', $line)),
            preg_split('/\R/u', (string) $text),
        );

        return array_values(array_filter($lines, static fn (string $line) => '' !== $line));
    }
}
