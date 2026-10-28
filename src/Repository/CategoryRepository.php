<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * @return list<array{category: Category, recipeCount: int}>
     */
    public function findAllWithRecipeCount(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c AS category', 'COUNT(r.id) AS recipeCount')
            ->leftJoin('c.recipes', 'r')
            ->groupBy('c.id')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row) => ['category' => $row['category'], 'recipeCount' => (int) $row['recipeCount']],
            $rows,
        );
    }
}
