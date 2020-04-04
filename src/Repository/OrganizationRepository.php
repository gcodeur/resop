<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @method Organization|null find($id, $lockMode = null, $lockVersion = null)
 * @method Organization|null findOneBy(array $criteria, array $orderBy = null)
 * @method Organization[]    findAll()
 * @method Organization[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrganizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organization::class);
    }

    public function loadUserByUsername(string $name): ?Organization
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * @return Organization[][]
     */
    public function loadActiveOrganizations(): array
    {
        /** @var Organization[] $items */
        $items = $this
            ->createActiveOrganizationQueryBuilder()
            ->getQuery()
            ->getResult()
        ;

        $result = [];
        // Return all organizations separated by parent
        foreach ($items as $item) {
            $result[$item->isParent() ? $item->name : $item->getParentName()][] = $item;
        }

        return $result;
    }

    public function createActiveOrganizationQueryBuilder(): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o');

        return $qb
            ->leftJoin('o.parent', 'p')
            ->where($qb->expr()->isNotNull('o.password'))
            ->addOrderBy('p.name', 'ASC')
            ->addOrderBy('o.name', 'ASC')
        ;
    }

    public function findAllWithParent(): array
    {
        return $this->createQueryBuilder('o')
            ->addSelect('p')
            ->leftJoin('o.parent', 'p')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Organization[]
     */
    public function findByParent(Organization $organization): iterable
    {
        return $this
            ->findByParentQueryBuilder($organization)
            ->getQuery()
            ->getResult();
    }

    public function findByParentQueryBuilder(Organization $organization): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o');

        $qb
            ->where($qb->expr()->orX('o.id = :orga', 'o.parent = :orga'))
            ->setParameter('orga', $organization->parent ?: $organization)
            ->addOrderBy('o.name', 'ASC');

        return $qb;
    }

    public function findByIdOrParentIdQueryBuilder(int $organizationId, QueryBuilder $qb = null): QueryBuilder
    {
        $alias = 'o';
        if (null === $qb) {
            $qb = $this->createQueryBuilder('o');
        } else {
            $alias = $qb->getRootAliases()[0];
            $qb->orderBy($alias.'.name', 'desc');
        }

        $qb
            ->where($qb->expr()->orX($alias.'.id = :orgId', $alias.'.parent = :orgId'))
            ->setParameter('orgId', $organizationId);

        return $qb;
    }

    public function findByIdOrParentId(int $organizationId): iterable
    {
        return $this->findByIdOrParentIdQueryBuilder($organizationId)->getQuery()->getResult();
    }
}
