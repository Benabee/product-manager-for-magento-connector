<?php
/**
 * @package   Benabee_ProductManagerConnector
 * @author    Maxime Coudreuse <contact@benabee.com>
 * @copyright 2019 Benabee
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0).
 * @link      https://www.benabee.com/
 */

namespace Benabee\ProductManagerConnector\Controller\Index;

/**
 * Class Index
 * @package Benabee\ProductManagerConnector\Controller\Index
 */
class Index extends \Magento\Framework\App\Action\Action
{
    protected $_context;
    protected $_resultPageFactory;
    protected $_jsonHelper;
    protected $_requestHandler;
    protected $_pageRedirect;
    protected $_data;

    /**
     * Index constructor
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Benabee\ProductManagerConnector\Model\RequestHandler $requestHandler
     * @param \Benabee\ProductManagerConnector\Helper\PageRedirect $pageRedirect
     * @param \Benabee\ProductManagerConnector\Helper\Data $data
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Benabee\ProductManagerConnector\Model\RequestHandler $requestHandler,
        \Benabee\ProductManagerConnector\Helper\PageRedirect $pageRedirect,
        \Benabee\ProductManagerConnector\Helper\Data $data
    ) {
        $this->_context = $context;
        $this->_resultPageFactory = $resultPageFactory;
        $this->_jsonHelper = $jsonHelper;
        $this->_requestHandler = $requestHandler;
        $this->_pageRedirect = $pageRedirect;
        $this->_data = $data;

        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        if (!$this->_data->isEnabled()) {
            return $this->jsonResponse('FATAL_ERROR_EXTENSION_NOT_ENABLED');
        }

        try {

            if ($this->getRequest()->getPost()->count() > 0) {

                //https://digitalcommerce.kaliop.com/magento-2-magento-1-cheat-sheet-snippets/

                $jsonRpcResult = $this->_requestHandler->execute($this->getRequest());

                if ($this->getRequest()->IsSecure()) {
                    return $this->getResponse()->representJson($jsonRpcResult);

                } else {
                    // TODO : encrypt response
                    return $this->getResponse()->representJson($jsonRpcResult);
                }

            } elseif ($this->getRequest()->getParam('viewproduct')) {

                $this->redirectFrontendPage(
                    'viewproduct',
                    $this->getRequest()->getParam('viewproduct')
                );

            } elseif ($this->getRequest()->getParam('viewcategory')) {

                $this->redirectFrontendPage(
                    'viewcategory',
                    $this->getRequest()->getParam('viewcategory')
                );

            } elseif ($this->getRequest()->getParam('editproduct')) {

                $this->redirectAdminPage(
                    'editproduct',
                    $this->getRequest()->getParam('editproduct'),
                    $this->getRequest()->getParam('adminurl')
                );

            } elseif ($this->getRequest()->getParam('editcategory')) {

                $this->redirectAdminPage(
                    'editcategory',
                    $this->getRequest()->getParam('editcategory'),
                    $this->getRequest()->getParam('adminurl')
                );
            }

        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            return $this->jsonResponse($e->getMessage());

        } catch (\Exception $e) {
            //$this->logger->critical($e);
            return $this->jsonResponse($e->getMessage());
        }
    }

    /**
     * Redirect to a product or category page on the frontend
     *
     * @param $action 'viewproduct' or 'viewcategory'
     * @param $id Id of the product or category
     */
    public function redirectFrontendPage($action, $id)
    {
        $url = $this->_pageRedirect->getFrontendPageUrl($action, $id, null);
        $this->getResponse()->setRedirect($url);
    }

    /**
     * Redirect to a product or category edit page in the backend
     *
     * @param $action 'editproduct' or 'editcategory'
     * @param $id Id of the product or category
     * @param $adminurl the admin url
     */
    public function redirectAdminPage($action, $id, $adminurl)
    {
        $url = $this->_pageRedirect->getAdminPageUrl($action, $id, $adminurl);
        $this->getResponse()->setRedirect($url);
    }

    /**
     * Create json response
     *
     * @param string $response
     * @return mixed
     */
    public function jsonResponse($response = '')
    {
        return $this->getResponse()->representJson(
            $this->_jsonHelper->jsonEncode($response)
        );
    }
}
