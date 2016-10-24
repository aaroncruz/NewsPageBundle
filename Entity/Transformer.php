<?php

namespace Rz\NewsPageBundle\Entity;

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
use Rz\NewsPageBundle\Model\AbstractTransformer;

class Transformer extends AbstractTransformer
{
    /**
     * {@inheritdoc}
     */
    public function create(PostInterface &$post)
    {
        $this->update($post);
    }

    /**
     * {@inheritdoc}
     */
    public function update(PostInterface &$post)
    {
        $emPageManager = $this->getPageManager()->getEntityManager();
        $emBlockManager = $this->getBlockManager()->getEntityManager();
        $emPostHasPageManager = $this->getPostHasPageManager()->getEntityManager();
        $emCategoryHasPageManager = $this->getCategoryHasPageManager()->getEntityManager();

        //Begin Transaction
        $emPageManager->getConnection()->beginTransaction();
        $emBlockManager->getConnection()->beginTransaction();
        $emPostHasPageManager->getConnection()->beginTransaction();
        $emCategoryHasPageManager->getConnection()->beginTransaction();

        try {

            #TODO: should be transaction based
            $postHasPage = $post->getPostHasPage() ?: new ArrayCollection();

            ########################################
            # Create Canonical Category Page
            ########################################
            $pageCanonicalDefaultCategory = $this->createCanonicalCategoryPage($post);

            ########################################
            # Create Post Block
            ########################################
            $postBlock = $this->createPostBlock($post);

            ########################################
            # Create Canonical Page
            ########################################
            $newsCanonicalPage = $this->createCanonicalPage($post, $postBlock, $pageCanonicalDefaultCategory);

            ########################################
            # Create Category Pages
            ########################################
            $categoryPages = $this->createCategoryPages($post, $pageCanonicalDefaultCategory);

            ########################################
            # Create Category Post
            ########################################
            $newsCategoryPages = null;
            if (count($categoryPages) > 0 && ($newsCanonicalPage && isset($newsCanonicalPage['page'])) && $postBlock) {
                $newsCategoryPages = $this->createCategoryPostPages($categoryPages, $post, $postBlock, $newsCanonicalPage['page']);
            }

            ########################################
            # Create Post Has Page
            ########################################
            if ($newsCanonicalPage && isset($newsCanonicalPage['page']) && $postBlock) {
                if (count($newsCategoryPages) > 0) {
                    $newsCategoryPages = array_merge(array($newsCanonicalPage['page']->getId() => $newsCanonicalPage), $newsCategoryPages);
                } else {
                    $newsCategoryPages = array($newsCanonicalPage['page']->getId() => $newsCanonicalPage);
                }
                $this->createPostHasPage($newsCategoryPages, $post, $postBlock);
            }

            ########################################
            # check & remove delete Category
            ########################################
            $this->verifyCategoryPages($post);

            ########################################
            # update page name and slug
            ########################################
            $this->updatePages($post);

            //Rollback Transaction
            $emPageManager->getConnection()->commit();
            $emBlockManager->getConnection()->commit();
            $emCategoryHasPageManager->getConnection()->commit();
            $emPostHasPageManager->getConnection()->commit();
        } catch (\Exception $e) {
            //Rollback Transaction
            $emPageManager->getConnection()->rollback();
            $emBlockManager->getConnection()->rollback();
            $emPostHasPageManager->getConnection()->rollback();
            $emCategoryHasPageManager->getConnection()->rollback();
        }
    }

    protected function updatePages($post)
    {
        $postHasPage = $this->postHasPageManager->fetchCategoryPages($post);
        if (count($postHasPage)>0) {
            //update each page to trigger fix URL
            foreach ($postHasPage as $php) {
                $page = $php->getPage();
                if ($page && ($post->getTitle() != $page->getName())) {
                    $page->setName($post->getTitle());
                    $page->setTitle($post->getTitle());
                    $page->setSlug(Page::slugify($post->getTitle()));
                    $page->setEdited(true);
                    try {
                        $this->getPageManager()->save($page);
                    } catch (\Exception $e) {
                        throw $e;
                    }
                }
                #TODO add configurable pattern for category pages
                if ($page && !$page->getTitle()) {
                    $page->setTitle($post->getTitle());
                    $page->setEdited(true);
                    try {
                        $this->getPageManager()->save($page);
                    } catch (\Exception $e) {
                        throw $e;
                    }
                }
            }
        }
        //update canonical page
        $postHasPageCanonical = $this->postHasPageManager->fetchCanonicalPage($post);
        if ($postHasPageCanonical) {
            $page = $postHasPageCanonical->getPage();
            if ($page && ($post->getTitle() != $page->getName())) {
                $page->setName($post->getTitle());
                $page->setEdited(true);

                try {
                    $this->getPageManager()->save($page);
                } catch (\Exception $e) {
                    throw $e;
                }
            }
        }

        // cleanup orphan PostHasPage
        $this->postHasPageManager->cleanupOrphanData();
    }

    protected function verifyCategoryPages($post)
    {
        $postHasCategories = $post->getPostHasCategory();
        if (!empty($postHasCategories)) {
            $currentCategories = [];
            // loop through each post categegory
            foreach ($postHasCategories as $postHasCategory) {
                $currentCategories[] = $postHasCategory->getCategory()->getId();
            }

            $postHasPage = null;
            if (count($currentCategories)>0) {
                $postHasPage = $this->postHasPageManager->fetchCategoryPageForCleanup($post, $currentCategories);
            }

            if (count($postHasPage) >0) {
                $phpIDs = [];
                $pageIDs = [];
                foreach ($postHasPage as $php) {
                    $phpIDs[] = $php->getId();
                    $pageIDs[] = $php->getPage()->getId();
                }
                //remove PostHasPage
                $this->postHasPageManager->cleanupPostHasPage($post, $phpIDs);
                //remmove Page
                $this->pageManager->cleanupPages($pageIDs);
            }
        }
    }

    protected function createCategoryPage(CategoryInterface $category,
                                          CategoryInterface $currentCategory,
                                          PostInterface $post,
                                          $newsCanonicalPage,
                                          $rootCategories,
                                          $parent = null)
    {
        // check if parent has caegory
        if ($parent) {
            #$pageCategory = $this->pageManager->findOneBy(array('slug'=>$parent->getSlug(), 'site'=>$post->getSite()));
            # fix to prevent wrong association of page category
            $categoryHasPage = $this->getCategoryHasPageManager()->findOneBy(array('category'=>$parent));
            $pageCategory = $categoryHasPage ? $categoryHasPage->getPage() : null;

            if (!$pageCategory) {
                if (!in_array($parent->getId(), $rootCategories)) {
                    return $this->createCategoryPage($parent, $currentCategory, $post, $newsCanonicalPage, $rootCategories, $parent->getParent());
                }
            }
        }

        // create category page

        #$pageCategory = $this->pageManager->findOneBy(array('slug'=>$category->getSlug(), 'site'=>$post->getSite()));
        # fix to prevent wrong association of page category
        $categoryHasPage = $this->getCategoryHasPageManager()->findOneBy(array('category'=>$category));
        $pageCategory = $categoryHasPage ? $categoryHasPage->getPage() : null;

        if (!$pageCategory) {
            //fetch parent page
            if ($parent) {
                #$parentPageCategory = $this->pageManager->findOneBy(array('slug'=>$parent->getSlug(), 'site'=>$post->getSite()));
                # fix to prevent wrong association of page category
                $categoryHasPage = $this->getCategoryHasPageManager()->findOneBy(array('category'=>$parent));
                $parentPageCategory = $categoryHasPage ? $categoryHasPage->getPage() : null;

                if (!$parentPageCategory) {
                    $parentPageCategory = $this->pageManager->findOneBy(array('url'=>'/', 'site'=>$post->getSite()));
                }
            }

            $pageCategory = $this->createPage($post, $parentPageCategory, $newsCanonicalPage, $category->getName(), null, $this->getPageService('category'), $this->getCategoryTemplate('page'));

            ################################
            #Create category post list block
            ################################
            if (interface_exists('Rz\CategoryPageBundle\Model\CategoryHasPageInterface')) {
                $contentContainer = $pageCategory->getContainerByCode('content');
                if (!$contentContainer) {
                    // create container block
                    $pageCategory->addBlocks($contentContainer = $this->getBlockInteractor()->createNewContainer(array(
                        'enabled' => true,
                        'page' => $pageCategory,
                        'code' => 'content',
                    )));
                    $contentContainer->setName('The category post list content container');

                    try {
                        $this->getBlockManager()->save($contentContainer);
                    } catch (\Exception $e) {
                        throw $e;
                    }
                }

                $categoryPostBlocks = $pageCategory->getBlocksByType($this->getCategoryPostListService());

                if (empty($categoryPostBlocks)) {
                    $contentContainer->addChildren($categoryPostBlock = $this->getBlockManager()->create());
                    $categoryPostBlock->setType($this->getCategoryPostListService());
                    $categoryPostBlock->setName(sprintf('%s - %s', 'Category Post List Block', $category->getName()));
                    $categoryPostBlock->setSetting('categoryId', $category->getId());
                    //TODO: REQUIRED PARAMS
                    $categoryPostBlock->setSetting('template', $this->getCategoryTemplate('block'));
                    $categoryPostBlock->setPage($pageCategory);

                    try {
                        $this->getBlockManager()->save($categoryPostBlock);
                    } catch (\Exception $e) {
                        throw $e;
                    }
                }

                //check if block is existing on Category Has Page
                $categoryHasPage = $this->getCategoryHasPageManager()->findOneBy(array('category'=>$category, 'page'=>$pageCategory)) ?: null;
                if (!$categoryHasPage) {
                    $categoryHasPage = $this->getCategoryHasPageManager()->create();
                    $categoryHasPage->setCategory($category);
                    $categoryHasPage->setPage($pageCategory);
                    $categoryHasPage->setBlock($categoryPostBlock);

                    try {
                        $this->getCategoryHasPageManager()->save($categoryHasPage);
                    } catch (\Exception $e) {
                        throw $e;
                    }
                }
            }
        }

        if ($currentCategory->getId() === $category->getId()) {
            return $pageCategory;
        }
        return;
    }

    protected function createCategoryPages($post, $pageCanonicalDefaultCategory)
    {
        $categoryPages = [];
        #TODO: transfer some process to use notification
        #TODO: should be able to control Category Parent Page
        // create the base page for categories
        $postHasCategories = $post->getPostHasCategory();
        $rootCategories = $this->fetchRootCategories();
        //generate category pages if post has category
        if (!empty($postHasCategories)) {
            // loop through each post categegory
            foreach ($postHasCategories as $postHasCategory) {
                $currentCat = $postHasCategory->getCategory();
                //fetch parent categories of current category
                $cats = $this->postHasPageManager->categoryParentWalker($currentCat, $cats);
                krsort($cats);
                //traverse through current category tree
                foreach ($cats as $cat) {
                    $page = $this->createCategoryPage($cat['category'], $currentCat, $postHasCategory->getPost(), $pageCanonicalDefaultCategory, $rootCategories, $cat['parent']);
                    // create Post has Page
                    if ($page) {
                        $categoryPages[$cat['category']->getId()]['page'] = $page;
                        $categoryPages[$cat['category']->getId()]['category'] = $cat['category'];
                    }
                }
            }
        }
        return $categoryPages;
    }

    protected function createCanonicalPage($post, $postBlock, $pageCanonicalDefaultCategory)
    {
        //check if canonical page exist
        $postHasPage = $this->getPostHasPageManager()->findOneByPageAndPageHasPost(array('post'=>$post, 'parent'=>$pageCanonicalDefaultCategory)) ?: null;

        if (!$postHasPage) {
            // create canonical page
            $newsCanonicalPage = $this->createPage($post, $pageCanonicalDefaultCategory, null, $post->getTitle(), Page::slugify($post->getId().' '.$post->getTitle()), $this->getPageService('post_canonical'), $post->getSetting('pageTemplateCode'));

			$containerBlock = null;
			if ($newsCanonicalPage) {
				// make sure we only attach post content container once in every page
				$containerBlock = $this->blockManager->findOneBy(array('page' => $newsCanonicalPage, 'name' => 'post_content_container'));
			}
			
			if (!$containerBlock) { 
				// create container block
				$newsCanonicalPage->addBlocks($contentContainer = $this->getBlockInteractor()->createNewContainer(array(
					'enabled' => true,
					'page' => $newsCanonicalPage,
					'code' => 'content',
				)));
				$contentContainer->setName('post_content_container');

				try {
					$this->getBlockManager()->save($contentContainer);
				} catch (\Exception $e) {
					throw $e;
				}
			}

			$shareBlockName = sprintf('%s - %s', 'Shared Block', $post->getTitle());
			
			$sharedBlock = null;
			if ($newsCanonicalPage) {
				// make sure we only attach post content container once in every page
				$sharedBlock = $this->blockManager->findOneBy(array('page' => $newsCanonicalPage, 'name' => $shareBlockName));
			}
			
			if (!$sharedBlock) {
				// create shared block
				$contentContainer->addChildren($sharedBlock = $this->getBlockManager()->create());
				$sharedBlock->setType('sonata.page.block.shared_block');
				$sharedBlock->setName($shareBlockName);
				$sharedBlock->setSetting('blockId', $postBlock->getId());
				$sharedBlock->setPosition(1);
				$sharedBlock->setEnabled(true);
				$sharedBlock->setPage($newsCanonicalPage);

				try {
					$this->getPageManager()->save($newsCanonicalPage);
				} catch (\Exception $e) {
					throw $e;
				}
			}

            return array('page'=>$newsCanonicalPage, 'shared_block'=>$sharedBlock);
        } else {
            return array('page'=>$postHasPage->getPage(), 'shared_block'=>$postHasPage->getSharedBlock());
        }
    }

    protected function createPage($post, $parent, $newsCanonicalPage=null, $name='PAGE', $slug=null, $pageType=null, $templateCode = null)
    {
        $page = $this->pageManager->findOneBy(array('name'=>$name, 'parent'=>$parent, 'site'=>$post->getSite()));

        if (!$page) {
            $page = $this->pageManager->create();
            $page->setEnabled(true);
            $page->setName($name);
            $page->setTitle($name);
            $page->setRouteName(\Sonata\PageBundle\Model\PageInterface::PAGE_ROUTE_CMS_NAME);
            $page->setPosition(1);
            $page->setDecorate(true);
            $page->setRequestMethod('GET|POST|HEAD|DELETE|PUT');
            $page->setSite($post->getSite());
            $page->setCanonicalPage($newsCanonicalPage);
            $page->setParent($parent);
			if ($templateCode) {
				$page->setTemplateCode($templateCode);
			}

            if ($pageType) {
                $page->setType($pageType);
            }

            if (!$newsCanonicalPage) {
                $page->setSlug($slug);
            }

            try {
                $this->getPageManager()->save($page);
            } catch (\Exception $e) {
                throw $e;
            }
        }

        return $page;
    }

    protected function createCanonicalCategoryPage($post)
    {
        $pageCanonicalDefaultCategory = $this->pageManager->findOneBy(array('slug'=>$this->getDefaultNewsPageSlug(), 'site'=>$post->getSite()));
        if (!$pageCanonicalDefaultCategory) {
            #TODO home URL should be in a parameter
            $parent = $this->pageManager->findOneBy(array('url'=>'/', 'site'=>$post->getSite()));
            $pageCanonicalDefaultCategory = $this->createPage($post, $parent, null, $this->getDefaultNewsPageSlug(), null, $this->getPageService('category_canonical'), $this->getCategoryTemplate('page'));
        }
        return $pageCanonicalDefaultCategory;
    }

    protected function createPostBlock($post)
    {
        $postBlock = null;
        //check if block is existing on Post Has Page
        $postHasPage = $this->getPostHasPageManager()->findOneBy(array('post'=>$post)) ?: null;

        if ($postHasPage && $postHasPage->getBlock()) {
            return $postHasPage->getBlock();
        }

        $postBlock = $this->getBlockManager()->create();
        $postBlock->setType($this->getPostBlockService());
        $postBlock->setName(sprintf('%s - %s', 'Post Block', $post->getTitle()));
        $postBlock->setSetting('postId', $post->getId());
        $postBlock->setEnabled(true);
        $postBlock->setSetting('template', $post->getSetting('template'));

        try {
            $this->getBlockManager()->save($postBlock);
        } catch (\Exception $e) {
            throw $e;
        }

        return $postBlock;
    }

    protected function createCategoryPostPages($categoryPages, $post, $postBlock, $canonicalPage = null)
    {
        $newsCategoryPages = [];
        foreach ($categoryPages as $catPage) {
            $postHasPage = $this->getPostHasPageManager()->findOneByPageAndPageHasPost(array('post'=>$post, 'parent'=>$catPage['page'])) ?: null;
            if (!$postHasPage) {
                // create category post page
                $newsCategoryPage = $this->createPage($post, $catPage['page'], $canonicalPage, $post->getTitle(), null, $this->getPageService('post'), $post->getSetting('pageTemplateCode'));
                // create container block
                $newsCategoryPage->addBlocks($contentContainer = $this->getBlockInteractor()->createNewContainer(array(
                    'enabled' => true,
                    'page' => $newsCategoryPage,
                    'code' => 'content',
                )));
                $contentContainer->setName('The post content container');

                try {
                    $this->getBlockManager()->save($contentContainer);
                } catch (\Exception $e) {
                    throw $e;
                }

                // create shared block
                $contentContainer->addChildren($sharedBlock = $this->getBlockManager()->create());
                $sharedBlock->setType('sonata.page.block.shared_block');
                $sharedBlock->setName(sprintf('%s - %s', 'Shared Block', $post->getTitle()));
                $sharedBlock->setSetting('blockId', $postBlock->getId());
                $sharedBlock->setPosition(1);
                $sharedBlock->setEnabled(true);
                $sharedBlock->setPage($newsCategoryPage);

                try {
                    $this->getPageManager()->save($newsCategoryPage);
                } catch (\Exception $e) {
                    throw $e;
                }

                $newsCategoryPages[$catPage['page']->getId()]['page'] = $newsCategoryPage;
                $newsCategoryPages[$catPage['page']->getId()]['shared_block'] = $sharedBlock;
                $newsCategoryPages[$catPage['page']->getId()]['category'] = $catPage['category'];
            } else {
                $newsCategoryPages[$postHasPage->getId()]['page'] = $postHasPage->getPage();
                $newsCategoryPages[$postHasPage->getId()]['shared_block'] = $postHasPage->getSharedBlock();
                $newsCategoryPages[$postHasPage->getId()]['category'] =  $postHasPage->getCategory() ?: $catPage['category'];
            }
        }

        return $newsCategoryPages;
    }

    protected function createPostHasPage($newsCategoryPages, $post, $postBlock)
    {
        $postHasPage = null;
        foreach ($newsCategoryPages as $catPage) {
            $php = $this->getPostHasPageManager()->findOneBy(array('post'=>$post, 'page'=>$catPage['page']));
            if (!$php) {
                $php = $this->getPostHasPageManager()->create();
                $php->setPost($post);
                $php->setPage($catPage['page']);
                $php->setBlock($postBlock);
                $php->setSharedBlock($catPage['shared_block']);
                $category = isset($catPage['category']) ? $catPage['category'] : null;
                $php->setCategory($category);
                $isCanonical = $catPage['page']->getCanonicalPage() ? false : true;
                $php->setIsCanonical($isCanonical);

                try {
                    $this->getPostHasPageManager()->save($php);
                } catch (\Exception $e) {
                    throw $e;
                }

                $post->addPostHasPage($php);
            }
        }
    }
}
