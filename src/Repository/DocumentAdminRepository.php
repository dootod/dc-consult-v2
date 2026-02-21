<?php

namespace App\Repository;

use App\Entity\DocumentAdmin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentAdmin>
 */
class DocumentAdminRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentAdmin::class);
    }

    /**
     * Récupère les N derniers documents déposés par les admins,
     * triés par date décroissante, directement en BDD.
     *
     * ✅ Remplace l'anti-pattern findAll() + usort() + array_slice() en PHP
     * qui chargeait TOUS les documents en mémoire juste pour en afficher 5.
     *
     * @return DocumentAdmin[]
     */
    public function findRecentOrderedByDate(int $limit = 5): array
    {
        return $this->createQueryBuilder('d')
            ->innerJoin('d.destinataire', 'dest')
            ->innerJoin('d.deposePar', 'dep')
            ->addSelect('dest', 'dep')
            ->orderBy('d.deposeLe', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}