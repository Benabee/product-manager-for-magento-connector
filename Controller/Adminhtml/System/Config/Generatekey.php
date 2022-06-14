<?php
/**
 * @package   Benabee_ProductManagerConnector
 * @author    Maxime Coudreuse <contact@benabee.com>
 * @copyright 2019 Benabee
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0).
 * @link      https://www.benabee.com/
 */

namespace Benabee\ProductManagerConnector\Controller\Adminhtml\System\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Class Generatekey
 * @package Benabee\ProductManagerConnector\Controller\Adminhtml\System\Config
 */
class Generatekey extends Action
{
    protected $resultJsonFactory;

    /**
     * Generatekey constructor
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }

    /**
     * Execute
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     * @throws \Exception
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultJsonFactory->create();

        $bytes = random_bytes(32);

        return $result->setData(['success' => true, 'key' => base64_encode($bytes)]);
    }

    /**
     * Is allowed to access resource (ACL)
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Benabee_ProductManagerConnector::config_productmanager_connector');
    }
}
