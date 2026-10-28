<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Recipe;
use App\Pagination\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Recipe>
 */
class RecipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recipe::class);
    }

    /**
     * Recipes matching an optional search term and category, newest first.
     *
     * @return Page<Recipe>
     */
    public function search(?string $query, ?Category $category, int $page = 1, int $perPage = 9): Page
    {
        $qb = $this->createQueryBuilder('r')
            ->addSelect('c', 'u')
            ->join('r.category', 'c')
            ->join('r.user', 'u')
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC');

        $query = trim((string) $query);
        if ('' !== $query) {
            $qb->andWhere('r.title LIKE :q OR r.ingredients LIKE :q')
                ->setParameter('q', '%'.addcslashes($query, '%_').'%');
        }

        if (null !== $category) {
            $qb->andWhere('r.category = :category')
                ->setParameter('category', $category);
        }

        $page = max(1, $page);
        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        return new Page(new Paginator($qb), $page, $perPage);
    }

    /**
     * @return list<Recipe>
     */
    public function findLatest(int $limit): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('c', 'u')
            ->join('r.category', 'c')
            ->join('r.user', 'u')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Other recipes of the same category, to suggest on a recipe page.
     *
     * @return list<Recipe>
     */
    public function findSimilar(Recipe $recipe, int $limit = 3): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('c')
            ->join('r.category', 'c')
            ->andWhere('r.category = :category')
            ->andWhere('r.id != :id')
            ->setParameter('category', $recipe->getCategory())
            ->setParameter('id', $recipe->getId())
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
