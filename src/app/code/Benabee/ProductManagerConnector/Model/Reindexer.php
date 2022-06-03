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
    protected $_indexerFactory;
    protected $_indexerCollectionFactory;
    protected $_productCollectionFactory;
    protected $_urlRewriteGenerator;
    protected $_urlRewrite;
    protected $_urlPersist;
    protected $_urlFinder;
    protected $_productUrlPathGenerator;
    protected $_cacheManager;
    protected $_productMetadata;

    /**
     * Reindexer constructor
     *
     * @param \Magento\Store\Model\StoreManager $storeManager
     * @param \Magento\Catalog\Model\Product $productModel
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Indexer\Model\IndexerFactory $indexerFactory
     * @param \Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator $urlRewriteGenerator
     * @param \Magento\UrlRewrite\Service\V1\Data\UrlRewrite $urlRewrite
     * @param \Magento\UrlRewrite\Model\UrlPersistInterface $urlPersist
     * @param \Magento\UrlRewrite\Model\UrlFinderInterface $urlFinder
     * @param \Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator $productUrlPathGenerator
     * @param \Magento\Framework\App\CacheInterface $cacheManager
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadata
     */
    public function __construct(
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Catalog\Model\Product $productModel,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Indexer\Model\IndexerFactory $indexerFactory,
        \Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator $urlRewriteGenerator,
        \Magento\UrlRewrite\Service\V1\Data\UrlRewrite $urlRewrite,
        \Magento\UrlRewrite\Model\UrlPersistInterface $urlPersist,
        \Magento\UrlRewrite\Model\UrlFinderInterface $urlFinder,
        \Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator $productUrlPathGenerator,
        \Magento\Framework\App\CacheInterface $cacheManager,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata
    ) {
        $this->_storeManager = $storeManager;
        $this->_productModel = $productModel;
        $this->_productFactory = $productFactory;
        $this->_productRepository = $productRepository;
        $this->_indexerFactory = $indexerFactory;
        $this->_indexerCollectionFactory = $indexerCollectionFactory;
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->_urlRewriteGenerator = $urlRewriteGenerator;
        $this->_urlRewrite = $urlRewrite;
        $this->_urlPersist = $urlPersist;
        $this->_urlFinder = $urlFinder;
        $this->_productUrlPathGenerator = $productUrlPathGenerator;
        $this->_cacheManager = $cacheManager;
        $this->_productMetadata = $productMetadata;
    }

    /**
     * Reindex products
     *
     * @param $jsonRpcResult
     * @param $productIds
     */
    public function reindexProducts(&$jsonRpcResult, $productIds)
    {
        $startTime = microtime(true);

        $this->_storeManager->setCurrentStore('admin');
        $result = [];

        $this->loadAndSaveProducts($result, $productIds);
        $this->regenerateUrlRewrite($result, $productIds);
        $this->reindexProductsWithAllIndexers($result, $productIds);

        $jsonRpcResult->result = $result;

        $jsonRpcResult->executionTime = microtime(true) - $startTime;
    }

    /**
     * Load and save products
     * 
     * @param $result
     * @param $productIds
     */
    public function loadAndSaveProducts(&$result, $productIds)
    {
        $count = count($productIds);

        for ($i = 0; $i < $count; ++$i) {
            $productId = $productIds[$i];

            $startTime = microtime(true);

            $r = new \StdClass();
            $r->productId = $productId;

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

                    if (!$this->_productRepository->save($product)) {
                        $r->error = "Load and save product error (product $productId):" . " save failed product";
                    } else {
                        $r->result = "Load and save product successful (product $productId)";
                    }
                }
            } catch (\Exception $e) {
                $r->error = "Load and save product error (product $productId):" . $e->getMessage() . '   stack trace: ' . $e->getTraceAsString();
            }
            $this->_cacheManager->clean('catalog_product_' . $productId);
        }

        $r->executionTime = microtime(true) - $startTime;
        $result[] = $r;
    }

    /**
     * Regenerate URL rewrites
     * 
     * @param $result
     * @param $productIds
     */
    public function regenerateUrlRewrite(&$result, $productIds)
    {
        $stores = $this->_storeManager->getStores(false);
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

            $a = [];

            $nbProducts = $productList->count();

            foreach ($productList as $product) {
                $startTime = microtime(true);
                $r = new \StdClass();
                $r->productId = $product->getId();
                $r->storeId = $product->getStoreId();
                $r->urlKey = $product->getUrlKey();

                if ($product->isVisibleInSiteVisibility()) {
                    // Product is visible in current store
                    try {
                        $product->unsUrlPath();
                        $urlPath = $this->_productUrlPathGenerator->getUrlPath($product);
                        $product->setUrlPath($urlPath);

                        // Generate new rewrites
                        $newUrls = $this->_urlRewriteGenerator->generate($product);

                        // Find existing rewrites
                        $existingUrls = $this->_urlFinder->findAllByData([
                            \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::ENTITY_ID => $product->getId(),
                            \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::ENTITY_TYPE => "product",
                            \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::REDIRECT_TYPE => 0,
                            \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::STORE_ID => $product->getStoreId()
                        ]);

                        if (!$this->compareUrlRewriteArrays($newUrls, $existingUrls)) {
                            // The URL rewrites are not the same

                            // Remove conflicting 301 redirects
                            foreach ($newUrls as $newUrl) {
                                $this->_urlPersist->deleteByData([
                                    \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::REQUEST_PATH => $newUrl->getRequestPath(),
                                    \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::REDIRECT_TYPE => 301,
                                    \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::STORE_ID => $product->getStoreId()
                                ]);
                            }

                            // Update URL rewrites in the database
                            $this->_urlPersist->replace($newUrls);
                            $r->result = 'Regenerate url rewrite successful (product ' . $product->getId() . '). Urls=' . implode(', ', array_keys($newUrls));
                        } else {
                            // The URL rewrites are the same
                            $r->result = 'Regenerate url rewrite unchanged (product ' . $product->getId() . '). Urls=' . implode(', ', array_keys($newUrls));
                        }
                    } catch (\Exception $e) {
                        $r->error = 'Regenerate url rewrite error (product: ' . $product->getId() . '): storeId= ' . $product->getStoreId() . ' Message= ' . $e->getMessage() . '      Urls=' . implode(', ', array_keys($newUrls));
                    }

                    $r->executionTime = microtime(true) - $startTime;
                    $a[] = $r;
                } else {
                    // Product is not visible in current store
                    $product->setStoreId($store->getId());
                    $this->_urlPersist->deleteByData([
                        \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::ENTITY_ID => $product->getId(),
                        \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::ENTITY_TYPE => \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator::ENTITY_TYPE,
                        \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::REDIRECT_TYPE => 0,
                        \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::STORE_ID => $product->getStoreId()
                    ]);
                }
            }
        }

        $result[] = $a;
    }

    /**
     * Simplify URL array
     * 
     * @param array $urls
     * @return array
     */
    public function simplifyUrlRewriteArray(array $urls)
    {
        $a = [];

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
        $a = $this->simplifyUrlRewriteArray($newUrls);
        $b = $this->simplifyUrlRewriteArray($existingUrls);
        return ($a == $b);
    }

    /**
     * Reindex product
     * 
     * @param $result
     * @param $productIds
     */
    public function reindexProductsWithAllIndexers(&$result, $productIds)
    {
        $indexer = $this->_indexerFactory->create();
        $indexerCollection = $this->_indexerCollectionFactory->create();
        $indexerIds = $indexerCollection->getAllIds();

        $productsIdsString = implode(',', $productIds);

        foreach ($indexerIds as $indexId) {
            $startTime = microtime(true);

            $idx = $indexer->load($indexId);

            if (method_exists($idx, 'executeList')) {
                //Magento version >=  2.3
                $idx->executeList($productIds);
            } else {
                $idx->reindexList($productIds);
            }

            $r = new \StdClass();
            $r->result = "Reindex $indexId successful (product $productsIdsString)";
            $r->executionTime = microtime(true) - $startTime;
            $result[] = $r;
        }
    }
}
