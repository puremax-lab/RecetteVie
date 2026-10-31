<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\MealPlanEntryRepository;
use App\State\MealPlanEntryProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One recipe planned for a meal (lunch or dinner) on a given day, in a member's meal plan.
 * Only used through the API, by the Semainier app. Each member only sees their own plan
 * (see CurrentUserExtension), and a new entry replaces the one already in that slot.
 */
#[ORM\Entity(repositoryClass: MealPlanEntryRepository::class)]
#[ORM\Table(name: 'meal_plan_entries')]
#[ORM\UniqueConstraint(name: 'uniq_meal_plan_slot', columns: ['owner_id', 'day', 'meal'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Post(processor: MealPlanEntryProcessor::class),
        new Delete(security: 'object.getOwner() === user'),
    ],
    normalizationContext: ['groups' => ['meal:read', 'recipe:read']],
    denormalizationContext: ['groups' => ['meal:write']],
    security: "is_granted('ROLE_USER')",
    order: ['day' => 'ASC', 'meal' => 'DESC'],
    paginationEnabled: false,
)]
#[ApiFilter(DateFilter::class, properties: ['day'])]
class MealPlanEntry
{
    public const MEALS = ['lunch', 'dinner'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['meal:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Groups(['meal:read', 'meal:write'])]
    private ?Recipe $recipe = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    #[Groups(['meal:read', 'meal:write'])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?\DateTimeImmutable $day = null;

    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: self::MEALS)]
    #[Groups(['meal:read', 'meal:write'])]
    private string $meal = 'dinner';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getRecipe(): ?Recipe
    {
        return $this->recipe;
    }

    public function setRecipe(?Recipe $recipe): static
    {
        $this->recipe = $recipe;

        return $this;
    }

    public function getDay(): ?\DateTimeImmutable
    {
        return $this->day;
    }

    public function setDay(?\DateTimeImmutable $day): static
    {
        $this->day = $day;

        return $this;
    }

    public function getMeal(): string
    {
        return $this->meal;
    }

    public function setMeal(string $meal): static
    {
        $this->meal = $meal;

        return $this;
    }
}
