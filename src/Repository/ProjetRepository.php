<?php

namespace App\Repository;

use App\Entity\Projet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Projet>
 */
class ProjetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Projet::class);
    }

    /**
     * Retourne tous les projets avec leurs images en une seule requête (évite le N+1).
     *
     * @return Projet[]
     */
    public function findAllWithImages(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.images', 'i')
            ->addSelect('i')
            ->orderBy('p.creeLe', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne une page de projets avec leurs images (pagination "Voir plus").
     *
     * @return Projet[]
     */
    public function findPageWithImages(int $offset, int $limit): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.images', 'i')
            ->addSelect('i')
            ->orderBy('p.creeLe', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre total de projets publiés.
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}