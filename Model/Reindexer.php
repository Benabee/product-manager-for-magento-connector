<?php

/**
 * @package   Benabee_ProductManagerConnector
 * @author    Maxime Coudreuse <contact@benabee.com>
 * @copyright 2019 Benabee
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0).
 * @link      https://www.benabee.com/
 */

namespace Benabee\ProductManagerConnector\Model;

/**
 * Class Reindexer
 * @package Benabee\ProductManagerConnector\Model
 */
class Reindexer
{
    protected $_storeManager;
    protected $_productModel;
    protected $_productFactory;
    protected $_productRepository;
    protected $_indexerCollectionFactory;
    protected $_productCollectionFactory;
    protected $_urlRewriteGenerator;
    protected $_urlRewrite;
    protected $_urlPersist;
    protected $_urlFinder;
    protected $_productUrlPathGenerator;
    protected $_cacheManager;
    protected $_productMetadata;
    protected $_searchCriteriaBuilder;
    protected $_sourceItemsBySku;
    protected $_sourceItemRepository;



    /**
     * Constructor
     *
     * @param \Magento\Store\Model\StoreManager $storeManager
     * @param \Magento\Catalog\Model\Product $productModel
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator $urlRewriteGenerator
     * @param \Magento\UrlRewrite\Service\V1\Data\UrlRewrite $urlRewrite
     * @param \Magento\UrlRewrite\Model\UrlPersistInterface $urlPersist
     * @param \Magento\UrlRewrite\Model\UrlFinderInterface $urlFinder
     * @param \Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator $productUrlPathGenerator
     * @param \Magento\Framework\App\CacheInterface $cacheManager
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadata
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\InventoryApi\Api\GetSourceItemsBySkuInterface $sourceItemsBySku
     * @param \Magento\InventoryApi\Api\SourceItemRepositoryInterface $sourceItemRepository
     */
    public function __construct(
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Catalog\Model\Product $productModel,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator $urlRewriteGenerator,
        \Magento\UrlRewrite\Service\V1\Data\UrlRewrite $urlRewrite,
        \Magento\UrlRewrite\Model\UrlPersistInterface $urlPersist,
        \Magento\UrlRewrite\Model\UrlFinderInterface $urlFinder,
        \Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator $productUrlPathGenerator,
        \Magento\Framework\App\CacheInterface $cacheManager,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder, // Magento 2.2+ ?
        \Magento\InventoryApi\Api\GetSourceItemsBySkuInterface $sourceItemsBySku = null, // Magento 2.3+
        \Magento\InventoryApi\Api\SourceItemRepositoryInterface $sourceItemRepository = null //  Magento 2.3+
    ) {
        $this->_storeManager = $storeManager;
        $this->_productModel = $productModel;
        $this->_productFactory = $productFactory;
        $this->_productRepository = $productRepository;
        $this->_indexerCollectionFactory = $indexerCollectionFactory;
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->_urlRewriteGenerator = $urlRewriteGenerator;
        $this->_urlRewrite = $urlRewrite;
        $this->_urlPersist = $urlPersist;
        $this->_urlFinder = $urlFinder;
        $this->_productUrlPathGenerator = $productUrlPathGenerator;
        $this->_cacheManager = $cacheManager;
        $this->_productMetadata = $productMetadata;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_sourceItemsBySku = $sourceItemsBySku;
        $this->_sourceItemRepository = $sourceItemRepository;
    }



    /**
     * Load and save products
     * 
     * @param $result
     * @param $productIds
     */
    public function loadAndSaveProducts(&$result, $productIds)
    {
        $this->_storeManager->setCurrentStore('admin');
        $a = array();

        $count = count($productIds);

        for ($i = 0; $i < $count; ++$i) {
            $productId = $productIds[$i];

            $startTime = microtime(true);

            $r = new \StdClass();
            $r->productId = $productId;
            $r->comment = "Load and save product in Magento (product ID $productId)";

            try {
                $product = $this->_productRepository->getById($productId);

                if ($product) {
                    //$product->setIsChangedCategories(true);
                    //$product->setOrigData('url_key', 'ruJrisesdu3useeu2nrYlir23Iuietghp9tedlXuife9eshur');

                    //Fix Magento 2.2 bug. See https://github.com/magento/magento2/issues/10687
                    $version = $this->_productMetadata->getVersion();
                    if (strncmp($version, "2.1.", 4) == 0 || strncmp($version, "2.2.", 4) == 0) {
                        $product->setMediaGalleryEntries($product->getMediaGalleryEntries());
                    }

                    // Fix has_options for configurable products
                    // https://magento.stackexchange.com/questions/201587/magento-2-how-to-create-configurable-product-programmatically
                    if ($product->getTypeId() == 'configurable') {
                        $configurable_attributes_data = $product->getTypeInstance()->getConfigurableAttributesAsArray($product);
                        $product->setCanSaveConfigurableAttributes(true);
                        $product->setConfigurableAttributesData($configurable_attributes_data);
                    }

                    // Fix has_options for bundle products
                    if ($product->getTypeId() == 'bundle') {
                        $product->setCanSaveBundleSelections(true);
                        //$bundleSelections = $product->getTypeInstance()->getOptions($product);
                        //$options = $product->getBundleOptionsData();
                        //$product->setBundleSelectionsData($bundleSelections);
                    }

                    if (!$this->_productRepository->save($product)) {
                        $r->error = "Load and save product error (product ID $productId):" . " save failed product";
                    } else {
                        $r->result = "Load and save product successful (product ID $productId)";
                    }
                }
            } catch (\Exception $e) {
                $r->error = "Load and save product error (product ID $productId):" . $e->getMessage() . '   stack trace: ' . $e->getTraceAsString();
            }
            $this->_cacheManager->clean('catalog_product_' . $productId);

            $r->executionTime = microtime(true) - $startTime;
            $a[] = $r;
        }


        $result->result = $a;
    }

    /**
     * Regenerate URL rewrites
     * 
     * @param $result
     * @param $productIds
     */
    public function regenerateProductsUrlRewrites(&$result, $productIds)
    {
        $this->_storeManager->setCurrentStore('admin');

        $result->comment = "Regenerate URL rewrites for " . count($productIds) . ' product(s)';

        $stores = $this->_storeManager->getStores(false);
        $a = array();

        foreach ($stores as $store) {

            $collection = $this->_productCollectionFactory->create();
            $storeId = $store->getId();

            $collection->addStoreFilter($store->getId())
                ->setStoreId($store->getId());

            if (!empty($productIds)) {
                $collection->addIdFilter($productIds);
            }

            $collection->addAttributeToSelect(['url_path', 'url_key', 'visibility']);
            $productList = $collection->load();

            //$a[] = 'nb stores=' . count($stores) . ' store id=' . $storeId . 'nbProducts=' .  $productList->count();

            foreach ($productList as $product) {
                $startTime = microtime(true);
                $r = new \StdClass();
                $r->comment = 'Regenerate URL rewrites for product ID ' . $product->getId();
                $r->productId = $product->getId();
                $r->storeId = $store->getId();
                $r->storeCode = $store->getCode();
                $r->urlKey = $product->getUrlKey();
                $r->visibility = $product->getVisibility();

                switch ($product->getVisibility()) {
                    case \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE:
                        $r->visibilityString = "Not Visible Individually";
                        break;
                    case \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_CATALOG:
                        $r->visibilityString = "Catalog";
                        break;
                    case \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_SEARCH:
                        $r->visibilityString = "Search";
                        break;
                    case \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH:
                        $r->visibilityString = "Catalog, Search";
                        break;
                }

                // Find existing rewrites
                $existingUrls = $this->_urlFinder->findAllByData([
                    \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::ENTITY_ID => $product->getId(),
                    \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::ENTITY_TYPE => \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator::ENTITY_TYPE,
                    \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::REDIRECT_TYPE => 0,
                    \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::STORE_ID => $store->getId(),
                ]);

                $r->existingUrlRewrites = array();
                foreach ($existingUrls as &$urlRewrite) {
                    $u = new \StdClass();
                    $u->url_rewrite_id = $urlRewrite->getUrlRewriteId();
                    $u->entity_type = $urlRewrite->getEntityType();
                    $u->entity_id = $urlRewrite->getEntityId();
                    $u->request_path = $urlRewrite->getRequestPath();
                    $u->target_path = $urlRewrite->getTargetPath();
                    $u->redirect_type = $urlRewrite->getRedirectType();
                    $u->storeId = $urlRewrite->getStoreId();
                    $r->existingUrlRewrites[] = $u;
                }

                if ($product->isVisibleInSiteVisibility()) {
                    try {
                        $product->unsUrlPath();
                        $urlPath = $this->_productUrlPathGenerator->getUrlPath($product);
                        $product->setUrlPath($urlPath);

                        // Generate new rewrites
                        $newUrls = $this->_urlRewriteGenerator->generate($product);

                        $r->newUrlRewrites = array();
                        foreach ($newUrls as &$urlRewrite) {
                            $u = new \StdClass();
                            $u->entity_type = $urlRewrite->getEntityType();
                            $u->entity_id = $urlRewrite->getEntityId();
                            $u->request_path = $urlRewrite->getRequestPath();
                            $u->target_path = $urlRewrite->getTargetPath();
                            $u->storeId = $urlRewrite->getStoreId();
                            $r->newUrlRewrites[] = $u;
                        }

                        if (!$this->compareUrlRewriteArrays($newUrls, $existingUrls)) {
                            // The URL rewrites are not the same

                            // Remove conflicting 301 redirects
                            foreach ($newUrls as $newUrl) {
                                $this->_urlPersist->deleteByData([
                                    \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::REQUEST_PATH => $newUrl->getRequestPath(),
                                    \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::REDIRECT_TYPE => 301,
                                    \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::STORE_ID => $store->getId()
                                ]);
                            }

                            // Update URL rewrites in the database
                            $this->_urlPersist->replace($newUrls);

                            //$r->result = 'Regenerate url rewrite successful (product ' . $product->getId() . '). Urls=' . implode(', ', array_keys($newUrls));
                            $r->result = 'Url rewrites replaced';
                        } else {
                            // The URL rewrites are the same

                            $r->result = 'Url rewrites unchanged';
                            //$r->result = 'Regenerate url rewrite unchanged (product ' . $product->getId() . '). Urls=' . implode(', ', array_keys($newUrls));
                        }
                    } catch (\Exception $e) {
                        $r->error = 'Url rewrites error';
                        $r->message = $e->getMessage();
                    }

                    $r->executionTime = microtime(true) - $startTime;
                    $a[] = $r;
                } else {
                    //$product->setStoreId($store->getId());
                    $this->_urlPersist->deleteByData([
                        \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::ENTITY_ID => $product->getId(),
                        \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::ENTITY_TYPE => \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator::ENTITY_TYPE,
                        \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::REDIRECT_TYPE => 0,
                        \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::STORE_ID => $store->getId()
                    ]);

                    $r->result = 'Url rewrites deleted. Existing URLS deleted because product is not visible';
                    $a[] = $r;
                }
            }
        }

        $result->result = $a;
    }

    /**
     * Simplify URL array
     * 
     * @param array $urls
     * @return array
     */
    public function simplifyUrlRewriteArray(array $urls)
    {
        $a = array();

        foreach ($urls as $url) {

            // Ignore 301 redirect
            if ($url->getRedirectType() == 0) {
                $a[] = [$url->getRequestPath(), $url->getTargetPath()];
            }
        }

        asort($a);

        return $a;
    }

    /**
     * Compare URL arrays
     * 
     * @param array $newUrls
     * @param array $existingUrls
     * @return bool
     */
    public function compareUrlRewriteArrays(array $newUrls, array $existingUrls)
    {
        //shuffle($newUrls);
        $a = $this->simplifyUrlRewriteArray($newUrls);
        $b = $this->simplifyUrlRewriteArray($existingUrls);
        $result = ($a == $b);
        return $result;
    }


    /**
     * Reindex products using Magento indexers
     *
     * @param  mixed $jsonRpcResult
     * @param  mixed $productIds
     * @param  mixed $reindexerIds
     * @return void
     */
    public function reindexProductsUsingIndexers(&$jsonRpcResult, $productIds, $indexerIdsToUse)
    {
        // Set a default list of indexers to use to reindex products if $indexerIdsToUse is empty
        if (empty($indexerIdsToUse)) {
            $indexerIdsToUse = [
                'catalog_product_category',
                'catalog_product_attribute',
                'inventory',
                'catalogrule_product',
                'cataloginventory_stock',
                'catalog_product_price',
                'catalogsearch_fulltext'
            ];
        }

        // Do not use these indexers to reindex products
        $indexerIdsToSkip = [
            'design_config_grid',
            'customer_grid',
            'catalog_category_product',
            'catalogrule_rule',
            'elasticsuite_thesaurus'
        ];

        $this->reindexUsingIndexers($jsonRpcResult, $productIds, $indexerIdsToUse, $indexerIdsToSkip, 'product ID', 'product(s)');
    }


    public function reindexUsingIndexers(&$jsonRpcResult, $productIds, $indexerIdsToUse, $indexerIdsToSkip, $entityTypeIdString, $entityTypeString)
    {
        $a = array();

        $this->_storeManager->setCurrentStore('admin');

        $jsonRpcResult->comment = 'Reindex ' . count($productIds) . ' ' . $entityTypeString;
        $jsonRpcResult->indexerIdsToUse = $indexerIdsToUse;
        $jsonRpcResult->indexerIdsToSkip = $indexerIdsToSkip;

        $indexerCollection = $this->_indexerCollectionFactory->create();
        $indexerIds = $indexerCollection->getAllIds();

        $productsIdsString = implode(',', $productIds);

        foreach ($indexerCollection->getItems() as $indexer) {
            $indexerId = $indexer->getId();
            $skipIndexer = false;

            // Skip if $indexerId is in $indexerIdsToSkip
            if (in_array($indexerId, $indexerIdsToSkip)) {
                $skipIndexer = true;
            }

            // Skip if $indexerId is not in $reindexerIds
            if (!empty($indexerIds) && !in_array($indexerId, $indexerIdsToUse)) {
                $skipIndexer = true;
            }

            if ($skipIndexer) {
                $r = new \StdClass();
                $r->result = 'Skip reindex ' . $indexerId;
                $a[] = $r;
            } else {
                $startTime = microtime(true);
                if ($indexerId == 'inventory') {
                    $skus = $this->getSkus($productIds);
                    $sourceItems = $this->getSourceItems($skus);
                    // $sourceItems = ['2110', '2112', '2113'];
                    // vendor/magento/module-inventory-indexer/Indexer/SourceItem/SourceItemIndexer.php

                    if (!empty($sourceItems)) {
                        if (method_exists($indexer, 'executeList')) {
                            //Magento version >=  2.3
                            $indexer->executeList($sourceItems);
                        } else {
                            $indexer->reindexList($sourceItems);
                        }
                    }
                } else {
                    if (method_exists($indexer, 'executeList')) {
                        //Magento version >=  2.3
                        $indexer->executeList($productIds);
                    } else {
                        $indexer->reindexList($productIds);
                    }
                }

                $r = new \StdClass();
                $r->result = "Reindex $indexerId successful ($entityTypeIdString $productsIdsString)";
                $r->executionTime = microtime(true) - $startTime;
                $a[] = $r;
            }
        }

        $jsonRpcResult->result = $a;
    }

    /**
     * Get product SKUs
     *
     * @param  mixed $productIds
     * @return void
     */
    public function getSkus($productIds)
    {
        $collection = $this->_productCollectionFactory->create();

        if (!empty($productIds)) {
            $collection->addIdFilter($productIds);
        }

        $collection->addAttributeToSelect(['sku']);
        $productList = $collection->load();

        $skus = array();
        foreach ($collection as $product) {
            $skus[] = $product->getSku();
        }

        return $skus;
    }

    /**
     * Get source items associated to SKUs
     *
     * @param  mixed $skus
     * @return void
     */
    public function getSourceItems($skus)
    {
        $sourceItems = array();

        if ($this->_sourceItemRepository) { 
            $searchCriteria = $this->_searchCriteriaBuilder->addFilter(\Magento\Catalog\Api\Data\ProductInterface::SKU, $skus, 'in')->create();
            $sourceItemData = $this->_sourceItemRepository->getList($searchCriteria);

            foreach ($sourceItemData->getItems() as $sourceItem) {
                $sourceItems[] = $sourceItem->getSourceItemId();
            }
        }
        
        return $sourceItems;
    }


    /**
     * Clean product cache
     *
     * @param  mixed $result
     * @param  mixed $productIds
     * @return void
     */
    public function cleanProductsCache(&$result, $productIds)
    {
        $this->_storeManager->setCurrentStore('admin');
        $result->comment = 'Clean product cache for ' . count($productIds) . ' product(s)';

        $count = count($productIds);

        for ($i = 0; $i < $count; ++$i) {
            $productId = $productIds[$i];
            $this->_cacheManager->clean('catalog_product_' . $productId);
        }

        $productsIdsString = implode(',', $productIds);
        $result->result = "product cache cleaned (product ID $productsIdsString)";
    }
}
