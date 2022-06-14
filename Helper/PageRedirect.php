<?php
/**
 * @package   Benabee_ProductManagerConnector
 * @author    Maxime Coudreuse <contact@benabee.com>
 * @copyright 2019 Benabee
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0).
 * @link      https://www.benabee.com/
 */

namespace Benabee\ProductManagerConnector\Helper;

/**
 * Class PageRedirect
 * @package Benabee\ProductManagerConnector\Helper
 */
class PageRedirect
{
    protected $_storeManager;
    protected $_productModel;
    protected $_categoryModel;
    protected $_registry;
    protected $_backendHelper;

    /**
     * PageRedirect constructor
     *
     * @param \Magento\Store\Model\StoreManager $storeManager
     * @param \Magento\Catalog\Model\Product $productModel
     * @param \Magento\Catalog\Model\Category $categoryModel
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Backend\Helper\Data $backendHelper
     */
    public function __construct(
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Catalog\Model\Product $productModel,
        \Magento\Catalog\Model\Category $categoryModel,
        \Magento\Framework\Registry $registry,
        \Magento\Backend\Helper\Data $backendHelper
    ) {
        $this->_storeManager = $storeManager;
        $this->_productModel = $productModel;
        $this->_categoryModel = $categoryModel;
        $this->_registry = $registry;
        $this->_backendHelper = $backendHelper;
    }

    /**
     * Get URL to view product or category in Magento
     *
     * @param $action
     * @param $id
     * @param $storeId
     * @return string
     */
    public function getFrontendPageUrl($action, $id, $storeId)
    {
        if (!is_numeric($id) || $id < 0 || $id != round($id)) {
            return "";
        }

        if ($action == 'viewproduct') {
            $this->_storeManager->setCurrentStore($storeId);

            $product = $this->_productModel->load($id);
            $categories = $product->getCategoryIds();

            if (count($categories) > 0) {
                $category_id = current($categories);
                $category = $this->_categoryModel->load($category_id);

                $this->_registry->unregister('current_category');
                $this->_registry->register('current_category', $category);
            }

            $path = $product->getProductUrl(true);

            return $path;
        }

        if ($action == 'viewcategory') {
            $this->_storeManager->setCurrentStore($storeId);

            $category = $this->_categoryModel->load($id);
            $path = $category->getUrl();

            return $path;
        }

        return "";
    }

    /**
     * Get URL to edit product or category in Magento
     *
     * @param $action
     * @param $id
     * @param $adminurl
     * @return string
     */
    public function getAdminPageUrl($action, $id, $adminurl)
    {
        if (!is_numeric($id) || $id < 0 || $id != round($id)) {
            return "";
        }

        $a = $this->_backendHelper->getHomePageUrl();

        if ($adminurl != $this->_backendHelper->getHomePageUrl()) {
            return "";
        }

        if ($action == 'editproduct') {
            $path = $this->_backendHelper->getUrl(
                'catalog/product/edit',
                ['id' => $id]
            );

            return $path;
        }

        if ($action == 'editcategory') {
            $path = $this->_backendHelper->getUrl(
                'catalog/category/edit',
                ['id' => $id]
            );
            
            return $path;
        }

        return "";
    }
}
