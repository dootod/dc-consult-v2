<?php

namespace App\Repository;

use App\Entity\Document;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /**
     * Récupère tous les documents déposés par les utilisateurs,
     * avec JOIN sur l'utilisateur pour éviter les requêtes N+1.
     * Trié par date décroissante.
     *
     * @return Document[]
     */
    public function findAllWithUtilisateur(): array
    {
        return $this->createQueryBuilder('d')
            ->innerJoin('d.utilisateur', 'u')
            ->addSelect('u')
            ->orderBy('d.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}