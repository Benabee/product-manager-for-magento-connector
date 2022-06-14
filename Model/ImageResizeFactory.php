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
 * Class ImageResize
 * class used to maintain compatibility with Magento 2.2 (see di.xml)
 *
 * @package Benabee\ProductManagerConnector\Model
 */
class ImageResizeFactory
{
    protected $_objectManager;
    
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager
    ) {
        $this->_objectManager = $objectManager;
    }
    
    public function create(array $data = [])
    {
        $instanceName = '\Magento\MediaStorage\Service\ImageResize';
         
        if (class_exists($instanceName)) {
            return $this->_objectManager->create($instanceName, $data);
        }
        
        return null;
    }
}
