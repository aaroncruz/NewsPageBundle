<?php

namespace Rz\NewsPageBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Sonata\CoreBundle\Model\ManagerInterface;
use Sonata\NewsBundle\Model\PostInterface;
use Sonata\NewsBundle\Model\PostManagerInterface;
use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\BlockBundle\Model\BlockManagerInterface;
use Sonata\ClassificationBundle\Model\CategoryManagerInterface;
use Sonata\ClassificationBundle\Model\CategoryInterface;
use Sonata\PageBundle\Model\PageManagerInterface;
use Sonata\PageBundle\Entity\BlockInteractor;
use Sonata\PageBundle\Model\Page;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Rz\NewsPageBundle\Model\TransformerInterface;

abstract class AbstractTransformer implements  TransformerInterface
{
    protected $pageManager;
    protected $blockManager;
    protected $postManager;
    protected $categoryManager;
    protected $postHasPageManager;
    protected $categoryHasPageManager;
    protected $categoryTemplates;
    protected $defaultNewsPageSlug;
    protected $slugify;
    protected $blockInteractor;
    protected $postBlockService;
    protected $categoryPostListService;
    protected $pageServices;
	protected $session;


    public function __construct(PostManagerInterface $postManager,
                                PageManagerInterface $pageManager,
                                BlockManagerInterface $blockManager,
                                CategoryManagerInterface $categoryManager,
                                ManagerInterface $postHasPageManager,
                                BlockInteractor  $blockInteractor,
                                RegistryInterface $registry,
								SessionInterface $session)
    {
        $this->postManager          = $postManager;
        $this->pageManager          = $pageManager;
        $this->blockManager         = $blockManager;
        $this->categoryManager      = $categoryManager;
        $this->postHasPageManager   = $postHasPageManager;
        $this->registry             = $registry;
        $this->blockInteractor      = $blockInteractor;
		$this->session              = $session;
    }

    /**
     * @return PostHasPageInterface
     */
    public function getPostHasPageManager()
    {
        return $this->postHasPageManager;
    }

    /**
     * @param PostHasPageInterface $postHasPageManager
     */
    public function setPostHasPageManager(ManagerInterface $postHasPageManager)
    {
        $this->postHasPageManager = $postHasPageManager;
    }

    /**
     * @return mixed
     */
    public function getCategoryHasPageManager()
    {
        return $this->categoryHasPageManager;
    }

    /**
     * @param mixed $categoryHasPageManager
     */
    public function setCategoryHasPageManager(ManagerInterface $categoryHasPageManager)
    {
        $this->categoryHasPageManager = $categoryHasPageManager;
    }

    /**
     * @return mixed
     */
    public function getCategoryManager()
    {
        return $this->categoryManager;
    }

    /**
     * @param mixed $categoryManager
     */
    public function setCategoryManager(ManagerInterface $categoryManager)
    {
        $this->categoryManager = $categoryManager;
    }

    /**
     * @return mixed
     */
    public function getDefaultNewsPageSlug()
    {
        return $this->defaultNewsPageSlug;
    }

    /**
     * @param mixed $defaultNewsPageSlug
     */
    public function setDefaultNewsPageSlug($defaultNewsPageSlug)
    {
        $this->defaultNewsPageSlug = $this->getSlugify()->slugify($defaultNewsPageSlug);
    }

    /**
     * @return mixed
     */
    public function getSlugify()
    {
        return $this->slugify;
    }

    /**
     * @param mixed $slugify
     */
    public function setSlugify($slugify)
    {
        $this->slugify = $slugify;
    }

    /**
     * @return BlockManagerInterface
     */
    public function getBlockManager()
    {
        return $this->blockManager;
    }

    /**
     * @param BlockManagerInterface $blockManager
     */
    public function setBlockManager(ManagerInterface $blockManager)
    {
        $this->blockManager = $blockManager;
    }

    /**
     * @return PostManagerInterface
     */
    public function getPostManager()
    {
        return $this->postManager;
    }

    /**
     * @param PostManagerInterface $postManager
     */
    public function setPostManager(ManagerInterface $postManager)
    {
        $this->postManager = $postManager;
    }

    /**
     * @return PageManagerInterface
     */
    public function getPageManager()
    {
        return $this->pageManager;
    }

    /**
     * @param PageManagerInterface $pageManager
     */
    public function setPageManager(ManagerInterface $pageManager)
    {
        $this->pageManager = $pageManager;
    }

    /**
     * @return mixed
     */
    public function getBlockInteractor()
    {
        return $this->blockInteractor;
    }

    /**
     * @param mixed $blockInteractor
     */
    public function setBlockInteractor(BlockInteractor $blockInteractor)
    {
        $this->blockInteractor = $blockInteractor;
    }

    /**
     * @return mixed
     */
    public function getPostBlockService()
    {
        return $this->postBlockService;
    }

    /**
     * @param mixed $postBlockService
     */
    public function setPostBlockService($postBlockService)
    {
        $this->postBlockService = $postBlockService;
    }

    /**
     * @return mixed
     */
    public function getCategoryPostListService()
    {
        return $this->categoryPostListService;
    }

    /**
     * @param mixed $categoryPostListService
     */
    public function setCategoryPostListService($categoryPostListService)
    {
        $this->categoryPostListService = $categoryPostListService;
    }

    /**
     * @return mixed
     */
    public function getPageServices()
    {
        return $this->pageServices;
    }

    /**
     * @param mixed $pageServices
     */
    public function setPageServices($pageServices)
    {
        $this->pageServices = $pageServices;
    }

    public function getPageService($name, $default= null)
    {
        return isset($this->pageServices[$name]) ? $this->pageServices[$name] : $default;
    }

    /**
     * @return mixed
     */
    public function getCategoryTemplates()
    {
        return $this->categoryTemplates;
    }

    /**
     * @param mixed $categoryTemplates
     */
    public function setCategoryTemplates($categoryTemplates)
    {
        $this->categoryTemplates = $categoryTemplates;
    }

    public function getCategoryTemplate($name, $default= null)
    {
        return isset($this->categoryTemplates[$name]) ? $this->categoryTemplates[$name] : $default;
    }

    protected function fetchRootCategories()
    {
        $rootCategories = $this->categoryManager->getRootCategories(false);
        $root = [];
        foreach ($rootCategories as $category) {
            $root[] = $category->getId();
        }

        return $root;
    }
}
