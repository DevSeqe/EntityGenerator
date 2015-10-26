<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace CodeAge\EntityGeneratorBundle\Manager;

use CodeAge\EntityGeneratorBundle\Entity\AbstractEntity;
use CodeAge\EntityGeneratorBundle\Services\ManagerService;
use Symfony\Component\Serializer\Exception\Exception;

/**
 * Description of AbstractEntity
 *
 * @author Paweł
 */
class AbstractManager extends ManagerService {

    const SGN_lt = '<';
    const SGN_gt = '>';
    const SGN_lte = '<=';
    const SGN_gte = '>=';
    const SGN_eq = '=';
    const SGN_neq = '!=';
    const SGN_like = 'like';
    const SGN_IsNotNull = 'isnotnull';
    const SGN_IsNull = 'isnull';

    public function __construct($entityManager, $class = null) {
        $this->em = $entityManager;
        $this->class = $class;
        $this->dbal = $this->em->getConnection();
        if ($class)
        {
            $this->class = $class;
            $this->repository = $this->em->getRepository($this->class);
        }
    }

    public function remove($object, $flush = true) {
        $this->em->remove($object);
        if ($flush)
        {
            $this->em->flush();
        }
        return $this;
    }

    public function update($entity, $flush = true) {
        $this->em->persist($entity);
        if ($flush)
        {
            $this->em->flush();
        }
        return $this;
    }

    public function persist($entity) {
        $this->em->persist($entity);
        return $this;
    }

    public function flush() {
        $this->em->flush();
        return $this;
    }

    public function createEntity() {
        return new $this->class;
    }

    public function find($id) {
        return $this->repository->find($id);
    }

    public function findAll() {
        return $this->repository->findAll();
    }

    public function findOneBy($values) {
        return $this->repository->findOneBy($values);
    }

    public function createQueryBuilder($alias) {
        return $this->repository->createQueryBuilder($alias);
    }

    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null) {
        return $this->repository->findBy($criteria, $orderBy, $limit, $offset);
    }

    public function findBySigns(array $criteria = array(), array $signs = array(), array $orderBy = array(), $limit = null, $offset = null) {

        if (!$signs ||
                !($counts = array_count_values($signs)) ||
                (key_exists('=', $counts) && $counts['='] == count($signs)))
            return $this->findBy($criteria, $orderBy, $limit, $offset);

        $qb = $this->createQueryBuilder('q');
        $i = 0;
        foreach ($criteria as $field => $value)
        {
            $exp = null;
            $sgn = '=';
            if (key_exists($i, $signs))
                $sgn = $signs[$i];

            switch ($sgn) {
                case self::SGN_lt: $method = 'lt';
                    break;
                case self::SGN_lte: $method = 'lte';
                    break;
                case self::SGN_gt: $method = 'gt';
                    break;
                case self::SGN_gte: $method = 'gte';
                    break;
                case self::SGN_neq: $method = 'neq';
                    break;
                case self::SGN_like: $method = 'like';
                    break;
                case self::SGN_IsNotNull:
                    $exp = $qb->expr()->isNotNull($field);
                    $method = 'isNotNull';
                    break;
                case self::SGN_IsNull:
                    $exp = $qb->expr()->isNull($field);
                    $method = 'isNotNull';
                    break;

                default: $method = 'eq';
                    break;
            }
            if (!$exp)
            {
                $exp = $qb->expr()->$method('q.' . $field, ':param' . $i);
                $qb->andWhere($exp)->setParameter('param' . $i++, $value);
            }
        }

        if ($limit)
            $qb->setMaxResults($limit);
        if ($offset)
            $qb->setFirstResult($offset);

        foreach ($orderBy as $field => $directory)
            $qb->orderBy($field, $directory);

        return $qb->getQuery()->execute();
    }

    public function findOneBySigns(array $criteria = array(), array $signs = array(), array $orderBy = array(), $offset = null) {
        $result = $this->findBySigns($criteria, $signs, $orderBy, 1, $offset);
        return (count($result) > 0) ? $result[0] : null;
    }

    public function __call($method, $args) {
        $obj = $this;
        set_error_handler(function ($err_severity, $err_msg, $err_file, $err_line, array $err_context) use ($obj, $method) {
            throw new Exception("Method '$method' doesn't exist in object '" . AbstractEntity::getClassNamespace($obj) . "'");
        });
        $data = call_user_func_array(array($this->repository, $method), $args);
        restore_error_handler();
        return $data;
    }

}
