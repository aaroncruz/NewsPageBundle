<?php

namespace Rz\NewsPageBundle\Entity;

use Sonata\CoreBundle\Model\BaseEntityManager;
use Sonata\DatagridBundle\Pager\Doctrine\Pager;
use Sonata\DatagridBundle\ProxyQuery\Doctrine\ProxyQuery;
use Doctrine\Common\CommonException;

class PostHasPageManager extends BaseEntityManager
{
    public function categoryParentWalker($category, &$categories=array()) {
        while ($category->getParent()) {
            $categories[] = array('category'=>$category, 'parent'=>$category->getParent());
            return $this->categoryParentWalker($category->getParent(), $categories);
        }
        return $categories;
    }

    public function findOneByPost($criteria) {

        $query = $this->getRepository()
            ->createQueryBuilder('php')
            ->select('php');

        $parameters = array();

        if (isset($criteria['post'])) {
            $query->andWhere('php.post = :post');
            $parameters['post'] = $criteria['post'];
        }

        if (isset($criteria['is_canonical'])) {
            $query->andWhere('php.isCanonical = :isCanonical');
            $parameters['isCanonical'] = $criteria['is_canonical'];
        }

        $query->setParameters($parameters)
            ->setMaxResults(1);

        try {
            return $query->getQuery()->useResultCache(true, 3600)->getSingleResult();
        } catch(\Doctrine\ORM\NoResultException $e) {
            return;
        }
    }

    public function findOneByPostAndCategory($criteria) {

        $query = $this->getRepository()
            ->createQueryBuilder('php')
            ->select('php');

        $parameters = array();

        if (isset($criteria['post'])) {
            $query->andWhere('php.post = :post');
            $parameters['post'] = $criteria['post'];
        }

        if (isset($criteria['category'])) {
            $query->andWhere('php.category = :category');
            $parameters['category'] = $criteria['category'];
        }

        if (isset($criteria['is_canonical'])) {
            $query->andWhere('php.isCanonical = :isCanonical');
            $parameters['isCanonical'] = $criteria['is_canonical'];
        }

        $query->setParameters($parameters)
            ->setMaxResults(1);

        try {
            return $query->getQuery()->useResultCache(true, 3600)->getSingleResult();
        } catch(\Doctrine\ORM\NoResultException $e) {
            return;
        }
    }

    public function findOneByPageAndCategory($criteria) {

        $query = $this->getRepository()
            ->createQueryBuilder('php')
            ->select('php');

        $parameters = array();

        if (isset($criteria['post'])) {
            $query->andWhere('php.post = :post');
            $parameters['post'] = $criteria['post'];
        }

        if (isset($criteria['category'])) {
            $query->andWhere('php.category = :category');
            $parameters['category'] = $criteria['category'];
        }

        $query->setParameters($parameters)
              ->setMaxResults(1);

        try {
            return $query->getQuery()->useResultCache(true, 3600)->getSingleResult();
        } catch(\Doctrine\ORM\NoResultException $e) {
            return;
        }
    }

    public function findOneByPageAndPageHasPost($criteria) {

        $query = $this->getRepository()
            ->createQueryBuilder('php')
            ->select('php');

        $parameters = array();

        if (isset($criteria['post'])) {
            $query->andWhere('php.post = :post');
            $parameters['post'] = $criteria['post'];
        }

        if (isset($criteria['parent'])) {
            $query->join('php.page', 'p');
            $query->andWhere('p.parent = :parent');
            $parameters['parent'] = $criteria['parent'];
        }

        $query->setParameters($parameters)
              ->setMaxResults(1);

        try {
            return $query->getQuery()->useResultCache(true, 3600)->getSingleResult();
        } catch(\Doctrine\ORM\NoResultException $e) {
            return;
        }
    }


    /**
     * {@inheritdoc}
     */
    public function cleanupPostHasPage($post, $postHasCategories)
    {
        $qb = $this->getRepository()->createQueryBuilder('php');
        $qb->delete()
           ->where('php.post = :post')
           ->andWhere($qb->expr()->in('php.id', $postHasCategories))
           ->setParameter('post', $post);

        return $qb->getQuery()->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function cleanupOrphanData()
    {
        $qb = $this->getRepository()->createQueryBuilder('php');
        $qb->delete()
            ->where($qb->expr()->isNull('php.page'))
            ->andWhere($qb->expr()->isNull('php.sharedBlock'));

        return $qb->getQuery()->execute();
    }

    public function fetchCategoryPageForCleanup($post, $category)
    {
        $qb = $this->getRepository()->createQueryBuilder('php');
        $qb->select('php')
           ->where('php.post = :post')
           ->andWhere($qb->expr()->notIn('php.category', $category))
           ->andWhere($qb->expr()->isNotNull('php.category '))
           ->setParameter('post', $post);

        return $qb->getQuery()->useResultCache(true, 3600)->execute();
    }


    public function fetchCategoryPages($post)
    {
        $qb = $this->getRepository()->createQueryBuilder('php');
        $qb->select('php')
            ->where('php.post = :post')
            ->andWhere($qb->expr()->isNotNull('php.category '))
            ->setParameter('post', $post);

        return $qb->getQuery()->useResultCache(true, 3600)->execute();
    }

    public function fetchCanonicalPage($post)
    {
        $qb = $this->getRepository()->createQueryBuilder('php');
        $qb->select('php')
            ->where('php.post = :post')
            ->andWhere($qb->expr()->isNull('php.category '))
            ->setParameter('post', $post)
            ->setMaxResults(1);

        try {
            return $qb->getQuery()->useResultCache(true, 3600)->getSingleResult();
        } catch(\Doctrine\ORM\NoResultException $e) {
            return;
        }
    }

    /**
     * {@inheritdoc}
     *
     * Valid criteria are:
     *    enabled - boolean
     *    date - query
     *    tag - string
     *    author - 'NULL', 'NOT NULL', id, array of ids
     *    collections - CollectionInterface
     *    mode - string public|admin
     */
    public function getPostsByCategoryPager(array $criteria, $page, $limit = 10, array $sort = array())
    {
        $parameters = array();
        $query = $this->getRepository()
            ->createQueryBuilder('php')
            ->select('php');

        if (isset($criteria['category'])) {
            $query->andWhere('php.category = :category');
            $parameters['category'] = $criteria['category']->getId();
        }

        $query->setParameters($parameters);

        $pager = new Pager();
        $pager->setMaxPerPage($limit);
        $pager->setQuery(new ProxyQuery($query));
        $pager->setPage($page);
        $pager->init();

        return $pager;
    }

    public function fetchNewsPages($criteria = [])
    {
        $qb = $this->getRepository()->createQueryBuilder('php');
        $qb->select('php')
           ->join('php.page', 'p');

        $parameters = array();

        # get all news pages only
        $qb->where($qb->expr()->isNull('php.category'));
        $qb->andWhere('php.isCanonical = :isCanonical');
        $parameters['isCanonical'] = true;

        if (isset($criteria['site'])) {
            $qb->andWhere('p.site = :site');
            $parameters['site'] = $criteria['site'];
        }

        $qb->setParameters($parameters);

        return $qb->getQuery()->useResultCache(true, 3600)->execute();
    }

    public function findOneByPage($criteria = []) {

        $qb = $this->getRepository()->createQueryBuilder('php');
        $qb->select('php')->join('php.page', 'p');
        $parameters = array();

        $qb->where($qb->expr()->isNull('php.category'));
        $qb->andWhere('php.isCanonical = :isCanonical');
        $parameters['isCanonical'] = true;

        if (isset($criteria['site'])) {
            $qb->andWhere('p.site = :site');
            $parameters['site'] = $criteria['site'];
        }

        if (isset($criteria['page'])) {
            $qb->andWhere('php.page = :page');
            $parameters['page'] = $criteria['page'];
        } else {
            throw new CommonException('Paremeter post is required found null.');
        }

        $qb->setParameters($parameters)->setMaxResults(1);

        try {
            return $qb->getQuery()->useResultCache(true, 3600)->getSingleResult();
        } catch(\Doctrine\ORM\NoResultException $e) {
            return;
        }
    }
}
