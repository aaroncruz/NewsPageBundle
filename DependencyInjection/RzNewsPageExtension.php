<?php

namespace Rz\NewsPageBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Sonata\EasyExtendsBundle\Mapper\DoctrineCollector;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class RzNewsPageExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('orm.xml');
        $loader->load('admin.xml');
        $loader->load('block.xml');
        $loader->load('twig.xml');
        $this->configureManagerClass($config, $container);
        $this->configureClass($config, $container);
        $this->configureAdminClass($config, $container);
        $this->configureController($config, $container);
        $this->configureTranslationDomain($config, $container);

        $container->setParameter('rz.news_page.enable_controller',  $config['enable_controller']);
        $container->setParameter('rz.news_page.slugify_service',    $config['slugify_service']);

        $loader->load('permalink.xml');
        $this->configurePermalinks($container, $config);

        $loader->load('provider.xml');
        $this->configureProvider($container, $config);

        $loader->load('transformer.xml');
        $loader->load('page_service.xml');
        $this->configureServices($container, $config);

        $this->registerDoctrineMapping($config);
    }

    /**
     * @param array                                                   $config
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     *
     * @return void
     */
    public function configureClass($config, ContainerBuilder $container)
    {
        $container->setParameter('rz.news_page.admin.post_has_page.entity', $config['class']['post_has_page']);
        $container->setParameter('rz.news_page.post_has_page.entity',       $config['class']['post_has_page']);
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     */
    public function configureManagerClass($config, ContainerBuilder $container)
    {
        $container->setParameter('rz.news_page.entity.manager.post_has_page.class',     $config['manager_class']['orm']['post_has_page']);
        $container->setParameter('rz.news_page.entity.manager.post.class',              $config['manager_class']['orm']['post']);
    }

    /**
     * @param array                                                   $config
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     *
     * @return void
     */
    public function configureAdminClass($config, ContainerBuilder $container)
    {
        $container->setParameter('rz.news_page.admin.post_has_page.class',              $config['admin']['post_has_page']['class']);
    }

    /**
     * @param array                                                   $config
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     *
     * @return void
     */
    public function configureTranslationDomain($config, ContainerBuilder $container)
    {
        $container->setParameter('rz.news_page.admin.post_has_page.translation_domain', $config['admin']['post_has_page']['translation']);
    }

    /**
     * @param array                                                   $config
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     *
     * @return void
     */
    public function configureController($config, ContainerBuilder $container)
    {
        $container->setParameter('rz.news_page.admin.post_has_page.controller',         $config['admin']['post_has_page']['controller']);
    }

    /**
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     * @param array                                                   $config
     */
    public function configureServices(ContainerBuilder $container, $config)
    {
        $container->setParameter('rz.news_page.default_post_block_template', $config['block']['template']);
        $container->setParameter('rz.news_page.post_templates',              array($config['block']['template']));
        $container->setParameter('rz.news_page.post_block_service',          $config['block']['service']);

        $container->setParameter('rz.news_page.parent_slug',        $config['page']['parent_slug']);
        $container->setParameter('rz.news_page.transformer.class',  $config['page']['transformer']['class']);

        # Page Service
        $container->setParameter('rz.news_page.page.service.post.class',                $config['page_service']['post']['class']);
        $container->setParameter('rz.news_page.page.service.post_canonical.class',      $config['page_service']['post_canonical']['class']);

        $container->setParameter('rz.news_page.page.service.post.name',                 $config['page_service']['post']['name']);
        $container->setParameter('rz.news_page.page.service.post_canonical.name',       $config['page_service']['post_canonical']['name']);

        $pageService = [];
        $pageService['post'] = $config['page']['services']['post']['service'];
        $pageService['post_canonical'] = $config['page']['services']['post_canonical']['service'];
        $container->setParameter('rz.news_page.page.services',                          $pageService);

        if (!$config['page']['templates']) {
            throw new \RuntimeException(sprintf('Please define a default `page_templates` value for the class `%s`', get_class($this)));
        }

        $pageTemplates = [];
        foreach ($config['page']['templates'] as $key=>$value) {
            $pageTemplates[$value['template_code']] = $value['name'];
        }

        $container->setParameter('rz.news_page.page.templates', $pageTemplates);
    }

    /**
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     * @param array                                                   $config
     */
    public function configurePermalinks(ContainerBuilder $container, $config)
    {
        //set default permalinks
        $container->setParameter('rz.news_page.permalink.default_permalink', $config['permalink']['permalinks'][$config['permalink']['default']]['permalink']);
    }


    /**
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     * @param array                                                   $config
     */
    public function configureProvider(ContainerBuilder $container, $config)
    {
        # SEO Provider
        $container->setParameter('rz.news_page.default_seo_provider',    $config['providers']['seo']['default_provider']);
    }

    /**
     * @param array $config
     */
    public function registerDoctrineMapping(array $config)
    {
        foreach ($config['class'] as $type => $class) {
            if (!class_exists($class)) {
                return;
            }
        }

        $collector = DoctrineCollector::getInstance();

        if (interface_exists('Sonata\PageBundle\Model\PageInterface')) {
            $collector->addAssociation($config['class']['post_has_page'], 'mapManyToOne', array(
                'fieldName' => 'post',
                'targetEntity' => $config['class']['post'],
                'cascade' => array(
                    'persist',
                    'remove'
                ),
                'mappedBy' => null,
                'inversedBy' => 'postHasPage',
                'joinColumns' => array(
                    array(
                        'name' => 'post_id',
                        'referencedColumnName' => 'id',
                        'onDelete' => 'CASCADE'                    ),
                ),
                'orphanRemoval' => false,
            ));

            $collector->addAssociation($config['class']['post_has_page'], 'mapManyToOne', array(
                'fieldName' => 'page',
                'targetEntity' => $config['class']['page'],
                'cascade' => array(
                    1 => 'remove',
                ),
                'mappedBy' => null,
                'inversedBy' => null,
                'joinColumns' => array(
                    array(
                        'name' => 'page_id',
                        'referencedColumnName' => 'id',
                        'onDelete' => 'CASCADE',
                    ),
                ),
                'orphanRemoval' => false,
            ));

            $collector->addAssociation($config['class']['post_has_page'], 'mapManyToOne', array(
                'fieldName' => 'block',
                'targetEntity' => $config['class']['block'],
                'cascade' => array(
                    1 => 'detach',
                ),
                'mappedBy' => null,
                'inversedBy' => null,
                'joinColumns' => array(
                    array(
                        'name' => 'block_id',
                        'referencedColumnName' => 'id',
                        'onDelete' => 'SET NULL',
                    ),
                ),
                'orphanRemoval' => false,
            ));

            $collector->addAssociation($config['class']['post_has_page'], 'mapManyToOne', array(
                'fieldName' => 'sharedBlock',
                'targetEntity' => $config['class']['block'],
                'cascade' => array(
                    1 => 'detach',
                ),
                'mappedBy' => null,
                'inversedBy' => null,
                'joinColumns' => array(
                    array(
                        'name' => 'shared_block_id',
                        'referencedColumnName' => 'id',
                        'onDelete' => 'SET NULL',
                    ),
                ),
                'orphanRemoval' => false,
            ));

            $collector->addAssociation($config['class']['post'], 'mapManyToOne', array(
                'fieldName'     => 'site',
                'targetEntity'  => $config['class']['site'],
                'cascade'       => array(
                    'persist',
                ),
                'mappedBy'      => null,
                'inversedBy'    => null,
                'joinColumns'   => array(
                    array(
                        'name'                 => 'site_id',
                        'referencedColumnName' => 'id',
                        'onDelete'             => 'CASCADE',
                    ),
                ),
                'orphanRemoval' => false,
            ));

            $collector->addAssociation($config['class']['post'], 'mapOneToMany', array(
                'fieldName' => 'postHasPage',
                'targetEntity' => $config['class']['post_has_page'],
                'cascade' => array(
                    'persist',
                ),
                'mappedBy' => 'post',
                'orphanRemoval' => true,
                'orderBy' => array(
                    'position' => 'ASC',
                ),
            ));

            if (interface_exists('Sonata\ClassificationBundle\Model\CategoryInterface')) {
                $collector->addAssociation($config['class']['post_has_page'], 'mapManyToOne', array(
                    'fieldName' => 'category',
                    'targetEntity' => $config['class']['category'],
                    'cascade' => array(
                        'persist',
                    ),
                    'mappedBy' => null,
                    'inversedBy' => null,
                    'joinColumns' => array(
                        array(
                            'name' => 'category_id',
                            'referencedColumnName' => 'id',
                        ),
                    ),
                    'orphanRemoval' => false,
                ));
            }

            $collector->addAssociation($config['class']['related_articles'], 'mapManyToOne', array(
                'fieldName' => 'postHasPage',
                'targetEntity' => $config['class']['post_has_page'],
                'cascade' => array(
                    'persist',
                ),
                'mappedBy' => null,
                'inversedBy' => null,
                'joinColumns' => array(
                    array(
                        'name' => 'post_has_page_id',
                        'referencedColumnName' => 'id',
                    ),
                ),
                'orphanRemoval' => false,
            ));

            $collector->addAssociation($config['class']['suggested_articles'], 'mapManyToOne', array(
                'fieldName' => 'postHasPage',
                'targetEntity' => $config['class']['post_has_page'],
                'cascade' => array(
                    'persist',
                ),
                'mappedBy' => null,
                'inversedBy' => null,
                'joinColumns' => array(
                    array(
                        'name' => 'post_has_page_id',
                        'referencedColumnName' => 'id',
                    ),
                ),
                'orphanRemoval' => false,
            ));
        }
    }
}
