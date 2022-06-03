<?php
/**
 * @package   Benabee_ProductManagerConnector
 * @author    Maxime Coudreuse <contact@benabee.com>
 * @copyright 2019 Benabee
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0).
 * @link      https://www.benabee.com/
 */

namespace Benabee\ProductManagerConnector\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Class Data
 * @package Benabee\ProductManagerConnector\Helper
 */
class Data extends AbstractHelper
{
    const XML_PATH_ENABLED = 'product_manager_connector/configuration/enabled';
    const XML_PATH_SECURITY_KEY = 'product_manager_connector/configuration/security_key';
    const XML_PATH_ACL_CHECK = 'product_manager_connector/configuration/acl_check';

    protected $encryptor;

    /**
     * Data constructor
     *
     * @param Context $context
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        Context $context,
        EncryptorInterface $encryptor
    ) {
        parent::__construct($context);
        $this->encryptor = $encryptor;
    }

    /**
     * Is enabled in configuration
     *
     * @param string $scope
     * @return bool
     */
    public function isEnabled($scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            $scope
        );
    }

    /**
     * Get security key
     *
     * @param string $scope
     * @return mixed
     */
    public function getSecurityKey($scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT)
    {
        $key = $this->scopeConfig->getValue(
            self::XML_PATH_SECURITY_KEY,
            $scope
        );

        //$key = $this->encryptor->decrypt($key);

        return $key;
    }

    /**
     * Get check ACL in configuration
     *
     * @param string $scope
     * @return bool
     */
    public function checkACL($scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ACL_CHECK,
            $scope
        );
    }
}
