<?php
/**
 * @package   Benabee_ProductManagerConnector
 * @author    Maxime Coudreuse <contact@benabee.com>
 * @copyright 2019 Benabee
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0).
 * @link      https://www.benabee.com/
 */

namespace Benabee\ProductManagerConnector\Model\Config\Source;

/**
 * Class AclMode
 * @package Benabee\ProductManagerConnector\Model\Config\Source
 */
class AclMode implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Array of options for ACL check
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '0', 'label' => __('All admin users')],
            ['value' => '1', 'label' => __('Admin users allowed to manage catalog')]
        ];
    }
}
