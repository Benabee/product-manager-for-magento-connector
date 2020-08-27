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
    protected $_indexerFactory;
    protected $_indexerCollectionFactory;

    /**
     * Reindexer constructor
     *
     * @param \Magento\Store\Model\StoreManager $storeManager
     * @param \Magento\Catalog\Model\Product $productModel
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Indexer\Model\IndexerFactory $indexerFactory
     * @param \Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory
     */
    public function __construct(
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Catalog\Model\Product $productModel,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Indexer\Model\IndexerFactory $indexerFactory,
        \Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory
    ) {
        $this->_storeManager = $storeManager;
        $this->_productModel = $productModel;
        $this->_productFactory = $productFactory;
        $this->_productRepository = $productRepository;
        $this->_indexerFactory = $indexerFactory;
        $this->_indexerCollectionFactory = $indexerCollectionFactory;
    }

    /**
     * Reindex products
     *
     * @param $jsonRpcResult
     * @param $productIds
     */
    public function reindexProducts(&$jsonRpcResult, $productIds)
    {
        $count = count($productIds);
        $this->_storeManager->setCurrentStore('admin');

        $result = [];

        for ($i = 0; $i < $count; ++$i) {
            $productId = $productIds[$i];

            $startTime = microtime(true);

            try {
                $product = $this->_productRepository->getById($productId);

                if ($product) {
                    // $product->setIsChangedCategories(true);
                    // $product->setOrigData('url_key', 'ruJrisesdu3useeu2nrYlir23Iuietghp9tedlXuife9eshur');
                    
                    //Fix Magento 2.2 bug. See https://github.com/magento/magento2/issues/10687
                    $product->setMediaGalleryEntries($product->getMediaGalleryEntries());
                    
                    if (!$this->_productRepository->save($product)) {
                        $jsonRpcResult->error = "save failed product $productId";
                        return;
                    }

                    $r = new \StdClass();
                    $r->result = "save product $productId";
                    $r->executionTime = microtime(true) - $startTime;
                    $result[] = $r;

                    $indexer = $this->_indexerFactory->create();
                    $indexerCollection = $this->_indexerCollectionFactory->create();
                    $ids = $indexerCollection->getAllIds();

                    foreach ($ids as $id) {
                        $startTime = microtime(true);

                        $idx = $indexer->load($id);
                        $idx->reindexRow($productId);

                        $r = new \StdClass();
                        $r->result = "reindex $id product $productId";
                        $r->executionTime = microtime(true) - $startTime;
                        $result[] = $r;
                    }
                }
            } catch (\Exception $e) {
                $jsonRpcResult->error = $e->getMessage() . '   stack trace: ' . $e->getTraceAsString();
                return;
            }
        }
        $jsonRpcResult->result = $result;
    }
}
