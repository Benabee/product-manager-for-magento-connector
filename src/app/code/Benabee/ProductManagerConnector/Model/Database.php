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
 * Class Database
 * @package Benabee\ProductManagerConnector\Model
 */
class Database
{
    protected $_storeManager;
    protected $_resourceConnection;
    protected $_databaseAPI;

    /**
     * Database constructor
     *
     * @param \Magento\Store\Model\StoreManager $storeManager
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     */
    public function __construct(
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->_storeManager = $storeManager;
        $this->_resourceConnection = $resourceConnection;
    }

    /**
     *
     *
     * @param $jsonRpcResult
     * @param $databaseAPI
     */
    public function executeDatabaseConnection(&$jsonRpcResult, $databaseAPI)
    {
        $this->_databaseAPI = $databaseAPI;
        $jsonRpcResult->result = $databaseAPI;
    }

    /**
     * Execute SQL query
     *
     * @param $jsonRpcResult
     * @param $is_read
     * @param $sql
     * @param $binds
     */
    public function executeSqlQuery(&$jsonRpcResult, $is_read, $sql, $binds)
    {
        $connection = $this->_resourceConnection->getConnection(
            \Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION
        );

        $readonly = false;
        if ($readonly) {
            $isSelect = (strpos($sql, 'SELECT') === 0);
            $isShowColumns = (strpos($sql, 'SHOW') === 0);

            if (!$isSelect && !$isShowColumns) {
                return;
            }

            if (strpos($sql, ';') !== false) {
                return;
            }
        }

        try {
            if (count($binds) > 0) {
                $query = $connection->query($sql, $binds);
            } else {
                $query = $connection->query($sql);
            }

            if ($query === false) {
                $jsonRpcResult->error = $query->errorInfo();
            } else {
                if (strpos($sql, 'SELECT') === 0 || strpos($sql, 'SHOW') === 0) {
                    //starts with SELECT or SHOW
                    $jsonRpcResult->result = new \stdClass();
                    $jsonRpcResult->result->rows = $query->fetchAll(\Zend_Db::FETCH_NUM);

                } else {
                    $jsonRpcResult->result = $query->rowCount();
                }
            }
        } catch (\Exception $e) {
            $jsonRpcResult->error = $e->getMessage();
        }
    }
}
