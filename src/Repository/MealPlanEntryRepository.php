<?php

namespace App\Repository;

use App\Entity\MealPlanEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MealPlanEntry>
 */
class MealPlanEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MealPlanEntry::class);
    }

    public function findSlot(User $owner, \DateTimeImmutable $day, string $meal): ?MealPlanEntry
    {
        return $this->findOneBy(['owner' => $owner, 'day' => $day, 'meal' => $meal]);
    }
}
