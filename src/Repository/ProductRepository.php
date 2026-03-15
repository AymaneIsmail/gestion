<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @return Product[]
     */
    public function findByOrganization(Organization $organization): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.price', 'pr')
            ->leftJoin('p.tags', 't')
            ->addSelect('c', 'pr', 't')
            ->where('p.organization = :organization')
            ->setParameter('organization', $organization)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche des produits pour la vue stock avec filtres optionnels.
     *
     * @return Product[]
     */
    /**
     * @param string[] $tagIds
     * @return Product[]
     */
    public function searchForStock(
        Organization $organization,
        ?string $query = null,
        ?string $categoryId = null,
        array $tagIds = [],
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.tags', 't')
            ->leftJoin('p.images', 'i')
            ->addSelect('c', 't', 'i')
            ->where('p.organization = :organization')
            ->setParameter('organization', $organization)
            ->orderBy('p.name', 'ASC');

        if ($query !== null && $query !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :query OR LOWER(p.reference) LIKE :query')
               ->setParameter('query', '%' . strtolower($query) . '%');
        }

        if ($categoryId !== null && $categoryId !== '') {
            $qb->andWhere('c.id = :categoryId')
               ->setParameter('categoryId', $categoryId);
        }

        if ($tagIds !== []) {
            $qb->andWhere('t.id IN (:tagIds)')
               ->setParameter('tagIds', $tagIds);
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneByOrganization(string $id, Organization $organization): ?Product
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.price', 'pr')
            ->leftJoin('p.tags', 't')
            ->leftJoin('p.images', 'i')
            ->addSelect('c', 'pr', 't', 'i')
            ->where('p.id = :id')
            ->andWhere('p.organization = :organization')
            ->setParameter('id', $id)
            ->setParameter('organization', $organization)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
