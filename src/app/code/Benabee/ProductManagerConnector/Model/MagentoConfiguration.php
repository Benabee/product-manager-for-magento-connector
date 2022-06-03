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
 * Class MagentoConfiguration
 * @package Benabee\ProductManagerConnector\Model
 */
class MagentoConfiguration
{
    protected $_storeManager;
    protected $_file;
    protected $_productMediaConfig;
    protected $_productMetadata;
    protected $_giftMessageConfigProvider;
    protected $_resourceConnection;
    protected $_stockConfiguration;
    protected $_backendUrl;
    protected $_backendHelper;
    protected $_productAttributeCollectionFactory;
    protected $_categoryAttributeCollectionFactory;
    protected $_customerGroupsCollection;

    /**
     * MagentoConfiguration constructor
     *
     * @param \Magento\Store\Model\StoreManager $storeManager
     * @param \Magento\Framework\Filesystem\Driver\File $file
     * @param \Magento\Catalog\Model\Product\Media\Config $productMediaConfig
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadataInterface
     * @param \Magento\GiftMessage\Model\GiftMessageConfigProvider $giftMessageConfigProvider
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Magento\CatalogInventory\Api\StockConfigurationInterface $stockConfiguration
     * @param \Magento\Backend\Model\UrlInterface $backendUrl
     * @param \Magento\Backend\Helper\Data $backendHelper
     * @param \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $productAttributeCollectionFactory
     * @param \Magento\Catalog\Model\ResourceModel\Category\Attribute\CollectionFactory $categoryAttributeCollectionFactory
     * @param \Magento\Customer\Model\ResourceModel\Group\Collection $customerGroupsCollection
     */
    public function __construct(
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Framework\Filesystem\Driver\File $file,
        \Magento\Catalog\Model\Product\Media\Config $productMediaConfig,
        \Magento\Framework\App\ProductMetadataInterface $productMetadataInterface,
        \Magento\GiftMessage\Model\GiftMessageConfigProvider $giftMessageConfigProvider,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\CatalogInventory\Api\StockConfigurationInterface $stockConfiguration,
        \Magento\Backend\Model\UrlInterface $backendUrl,
        \Magento\Backend\Helper\Data $backendHelper,
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $productAttributeCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Category\Attribute\CollectionFactory $categoryAttributeCollectionFactory,
        \Magento\Customer\Model\ResourceModel\Group\Collection $customerGroupsCollection
    ) {
        $this->_storeManager = $storeManager;
        $this->_file = $file;
        $this->_productMediaConfig = $productMediaConfig;
        $this->_productMetadata = $productMetadataInterface;
        $this->_giftMessageConfigProvider = $giftMessageConfigProvider;
        $this->_resourceConnection = $resourceConnection;
        $this->_stockConfiguration = $stockConfiguration;
        $this->_backendUrl = $backendUrl;
        $this->_backendHelper = $backendHelper;
        $this->_productAttributeCollectionFactory = $productAttributeCollectionFactory;
        $this->_categoryAttributeCollectionFactory = $categoryAttributeCollectionFactory;
        $this->_customerGroupsCollection = $customerGroupsCollection;
    }

    /**
     * Get Magento configuration
     *
     * @param $jsonRpcResult
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getConfig(&$jsonRpcResult)
    {
        $jsonRpcResult->result = new \stdClass();

        $jsonRpcResult->result->magento_version = $version = $this->_productMetadata->getVersion();
        $jsonRpcResult->result->php_version = phpversion();
        $jsonRpcResult->result->max_execution_time = ini_get('max_execution_time');
        $jsonRpcResult->result->max_input_time = ini_get('max_input_time');
        $jsonRpcResult->result->memory_limit = ini_get('memory_limit');
        $jsonRpcResult->result->post_max_size = ini_get('post_max_size');
        $jsonRpcResult->result->upload_max_filesize = ini_get('upload_max_filesize');
        $jsonRpcResult->result->zlib_output_compression = ini_get('zlib.output_compression');

        $tableName = $this->_resourceConnection->getTableName('core_config_data');
        $pos = strrpos($tableName, 'core_config_data');
        if ($pos === false) {
            $prefix = '';
        } else {
            $prefix = substr($tableName, 0, $pos);
        }

        $jsonRpcResult->result->table_prefix = $prefix;
        
        $jsonRpcResult->result->admin_url = $this->_backendHelper->getHomePageUrl();

        $jsonRpcResult->result->media_product_base_url = $this->_productMediaConfig->getBaseMediaUrl();
        $jsonRpcResult->result->media_product_base_path = $this->_productMediaConfig->getBaseMediaPath();

        $baseMediaURL = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $baseMediaDir = $this->_storeManager->getStore()->getBaseMediaDir();

        $jsonRpcResult->result->media_category_base_url = $baseMediaURL . 'catalog/category';  //TODO stores + secureURL
        $jsonRpcResult->result->media_category_base_path = $baseMediaDir . '/' . 'catalog' . '/' . 'category';

        $jsonRpcResult->result->installation_in_pub_folder = !$this->_file->isExists('app/bootstrap.php');

        // Fix for https://github.com/magento/magento2/issues/8868
        if ($jsonRpcResult->result->installation_in_pub_folder) {
            $jsonRpcResult->result->original_media_category_base_url = $jsonRpcResult->result->media_category_base_url;
            $jsonRpcResult->result->original_media_product_base_url = $jsonRpcResult->result->media_product_base_url;

            if ($this->_file->isExists('media/catalog')) {

                $jsonRpcResult->result->media_category_base_url = str_replace(
                    '/pub/media/catalog/',
                    '/media/catalog/',
                    $jsonRpcResult->result->media_category_base_url
                );

                $jsonRpcResult->result->media_product_base_url = str_replace(
                    '/pub/media/catalog/',
                    '/media/catalog/',
                    $jsonRpcResult->result->media_product_base_url
                );
            }
        }

        $jsonRpcResult->result->locale_code = $this->_storeManager->getStore()->getLocaleCode();
        //$jsonrpcresult->result->date_format = Mage::app()->getLocale()->getDateFormat('short');
        //$jsonrpcresult->result->datetime_format = Mage::app()->getLocale()->getDateFormat('long');
        $baseCurrency = $this->_storeManager->getStore()->getBaseCurrency();

        $jsonRpcResult->result->base_currency = $this->_storeManager->getStore()->getBaseCurrencyCode();
        //$jsonrpcresult->result->base_currency_symbol = $baseCurrency->getSymbol();
        //$jsonrpcresult->result->base_currency_example = $baseCurrency->toCurrency(1234567.89);
        //$jsonrpcresult->result->base_currencies = Mage::getModel('directory/currency')->getConfigBaseCurrencies();
        //$jsonrpcresult->result->default_currencies = Mage::getModel('directory/currency')->getConfigDefaultCurrencies();

        $storeCollection = $this->_storeManager->getStores(true);

        $jsonRpcResult->result->stores = array_keys($storeCollection);
        $jsonRpcResult->result->storeCollection = $storeCollection;

        /* $this->_giftMessageConfigProvider

         $giftMessageConfigProvider = $objectManager->get('\Magento\GiftMessage\Model\GiftMessageConfigProvider');
         $itemLevelGiftMessageConfiguration = (bool)$this->scopeConfiguration->getValue(
             GiftMessageHelper::XPATH_CONFIG_GIFT_MESSAGE_ALLOW_ITEMS,
             \Magento\Store\Model\ScopeInterface::SCOPE_STORE
         );

         $gift_message_available = new \stdClass();

         for ($i = 0; $i < count($storeCollection); $i ++)
         {
             $storeid = $storeCollection[$i];
             $gift_message_available->$storeid = Mage::getStoreConfig(
                 Mage_GiftMessage_Helper_Message::XPATH_CONFIG_GIFT_MESSAGE_ALLOW_ITEMS,
                 $storeid
                );
         }
         $jsonrpcresult->result->gift_message_available  = $gift_message_available;*/

        $jsonRpcResult->result->cataloginventory_item_options_manage_stock = $this->_stockConfiguration->getManageStock();
        $jsonRpcResult->result->cataloginventory_item_options_backorders = $this->_stockConfiguration->getBackorders();
        $jsonRpcResult->result->cataloginventory_item_options_max_sale_qty = $this->_stockConfiguration->getMaxSaleQty();
        $jsonRpcResult->result->cataloginventory_item_options_min_qty = $this->_stockConfiguration->getMinQty();
        $jsonRpcResult->result->cataloginventory_item_options_min_sale_qty = $this->_stockConfiguration->getMinSaleQty();
        $jsonRpcResult->result->cataloginventory_item_options_notify_stock_qty = $this->_stockConfiguration->getNotifyStockQty();
        $jsonRpcResult->result->cataloginventory_item_options_enable_qty_increments = $this->_stockConfiguration->getEnableQtyIncrements();
        $jsonRpcResult->result->cataloginventory_item_options_qty_increments = $this->_stockConfiguration->getQtyIncrements();
    }

    /**
     * Get source models of all the attributes
     *
     * @param $entityType
     * @param $attributeCollectionFactory
     * @return array
     */
    public function getAttributeSourceModels($entityType, $attributeCollectionFactory)
    {
        $models = [];

        $attributeCollection = $attributeCollectionFactory->create();
        $attributeCollection->addFieldToFilter('source_model', ['neq' => 'NULL']);
        $attributes = $attributeCollection->getItems();

        foreach ($attributes as $attribute) {
            $sourceModel = new \stdClass();

            try {
                $sourceModel->entity_type = $entityType;
                $sourceModel->attribute_id = $attribute->getAttributeId();
                $sourceModel->attribute_code = $attribute->getAttributeCode();
                $sourceModel->attribute_frontend_label = $attribute->getFrontendLabel();
                $sourceModel->model_class = $attribute->getSourceModel();
                $sourceModel->options = $attribute->getSource()->getAllOptions();
            } catch (\Exception $e) {
                $sourceModel->error = 'Exception: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString();
            }
            $models[] = $sourceModel;
        }

        return $models;
    }

    /**
     * Get all options of a model
     *
     * @param $modelClass
     * @return \stdClass
     */
    public function modelGetAllOptions($modelClass)
    {
        $sourceModel = new \stdClass();
        $sourceModel->model_class = $modelClass;

        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $model = $objectManager->create($modelClass);

            if ($model) {
                $sourceModel->options = $model->getAllOptions(true);
            }
        } catch (\Exception $e) {
            $sourceModel->error = 'Exception: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString();
        }

        return $sourceModel;
    }

    /**
     * Get options of a model
     *
     * @param $modelClass
     * @return \stdClass
     */
    public function modelGetOptions($modelClass)
    {
        $sourceModel = new \stdClass();
        $sourceModel->model_class = $modelClass;

        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $model = $objectManager->create($modelClass);

            if ($model) {
                $sourceModel->options = $model->getOptions();
            }
        } catch (\Exception $e) {
            $sourceModel->error = 'Exception: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString();
        }

        return $sourceModel;
    }

    /**
     * Get all source models
     *
     * @param $jsonRpcResult
     * @param $store_id
     * @param $locale_code
     */
    public function getSourceModels(&$jsonRpcResult, $store_id, $locale_code)
    {
        $jsonRpcResult->result = new \stdClass();

        $models = [];

        $models[] = $this->modelGetAllOptions('Magento\Bundle\Model\Product\Attribute\Source\Price\View');
        $models[] = $this->modelGetAllOptions('Magento\Bundle\Model\Product\Attribute\Source\Shipment\Type');
        $models[] = $this->modelGetAllOptions('Magento\Catalog\Model\Category\Attribute\Source\Layout');
        $models[] = $this->modelGetAllOptions('Magento\Catalog\Model\Category\Attribute\Source\Mode');
        $models[] = $this->modelGetAllOptions('Magento\Catalog\Model\Category\Attribute\Source\Page');
        $models[] = $this->modelGetAllOptions('Magento\Catalog\Model\Entity\Product\Attribute\Design\Options\Container');
        $models[] = $this->modelGetAllOptions('Magento\Catalog\Model\Product\Attribute\Source\Boolean');
        $models[] = $this->modelGetAllOptions('Magento\Catalog\Model\Product\Attribute\Source\Countryofmanufacture');
        $models[] = $this->modelGetAllOptions('Magento\Catalog\Model\Product\Attribute\Source\Layout');
        $models[] = $this->modelGetAllOptions('Magento\Catalog\Model\Product\Attribute\Source\Status');
        $models[] = $this->modelGetOptions('Magento\Catalog\Model\Product\Type');
        $models[] = $this->modelGetAllOptions('Magento\Catalog\Model\Product\Visibility');
        $models[] = $this->modelGetAllOptions('Magento\CatalogInventory\Model\Source\Stock');
        $models[] = $this->modelGetAllOptions('Magento\Eav\Model\Entity\Attribute\Source\Boolean');
        $models[] = $this->modelGetAllOptions('Magento\Msrp\Model\Product\Attribute\Source\Type\Price');
        $models[] = $this->modelGetAllOptions('Magento\Tax\Model\TaxClass\Source\Product');
        $models[] = $this->modelGetAllOptions('Magento\Theme\Model\Theme\Source\Theme');

        $productAttributesSourceModels = $this->getAttributeSourceModels(
            'product',
            $this->_productAttributeCollectionFactory
        );

        $categoryAttributesSourceModels = $this->getAttributeSourceModels(
            'category',
            $this->_categoryAttributeCollectionFactory
        );

        $jsonRpcResult->result->source_models = array_merge(
            $models,
            $productAttributesSourceModels,
            $categoryAttributesSourceModels
        );

        $customerGroups = $this->_customerGroupsCollection->toOptionArray();
        $jsonRpcResult->result->customer_groups = $customerGroups;
    }
}
