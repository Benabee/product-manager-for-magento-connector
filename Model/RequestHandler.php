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
 * Class RequestHandler
 * @package Benabee\ProductManagerConnector\Model
 */
class RequestHandler
{
    const EXTENSION_VERSION = '1.2.0';
    const BRIDGE_VERSION = '2.4.1'; // The extension is based on this bridge file version

    protected $_magentoConfiguration;
    protected $_database;
    protected $_imageUploader;
    protected $_reindexer;
    protected $_data;
    protected $_userModel;
    protected $_authSession;
    protected $_scopeConfig;

    /**
     * RequestHandler constructor
     *
     * @param MagentoConfiguration $magentoConfiguration
     * @param Database $database
     * @param ImageUploader $imageUploader
     * @param Reindexer $reindexer
     * @param \Benabee\ProductManagerConnector\Helper\Data $data
     * @param \Magento\User\Model\User $userModel
     * @param \Magento\Backend\Model\Auth\Session $authSession
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        MagentoConfiguration $magentoConfiguration,
        Database $database,
        ImageUploader $imageUploader,
        Reindexer $reindexer,
        \Benabee\ProductManagerConnector\Helper\Data $data,
        \Magento\User\Model\User $userModel,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->_magentoConfiguration = $magentoConfiguration;
        $this->_database = $database;
        $this->_imageUploader = $imageUploader;
        $this->_reindexer = $reindexer;
        $this->_data = $data;
        $this->_userModel = $userModel;
        $this->_authSession = $authSession;
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * decipher encryted data
     *
     * @param $ciphertext
     * @param $key
     * @param $iv
     * @return bool|string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function decipher($ciphertext, $key, $iv)
    {
        if (function_exists('openssl_decrypt')) {
            $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        } elseif (function_exists('mcrypt_module_open')) {
            // @codingStandardsIgnoreStart
            $td = @mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
            @mcrypt_generic_init($td, $key, $iv);
            $plaintext = @mdecrypt_generic($td, $ciphertext);
            // @codingStandardsIgnoreEnd

            //remove PKCS7 padding
            $last = substr($plaintext, -1);
            $plaintext = substr($plaintext, 0, strlen($plaintext) - ord($last));

            // @codingStandardsIgnoreStart
            @mcrypt_generic_deinit($td);
            @mcrypt_module_close($td);
            // @codingStandardsIgnoreEnd
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                __("ERROR: OpenSSL (or mcrypt) is not installed. Request can't be decrypted ")
            );
        }

        return $plaintext;
    }

    /**
     * Check user username and password
     *
     * @param $username
     * @param $encryptedpassword
     * @param $encryptionKey
     * @param $iv
     * @return bool
     * @throws \Magento\Framework\Exception\AuthenticationException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function checkUser($username, $encryptedpassword, $encryptionKey, $iv)
    {
        $password = $this->decipher(
            $encryptedpassword,
            $encryptionKey,
            $iv
        );

        if (!$this->_authSession->isLoggedIn()) {
            $user = $this->_userModel->loadByUsername($username);

            if ($user->getId() === null) {
                throw new \Magento\Framework\Exception\LocalizedException(__('FATAL_ERROR_WRONG_USERNAME'));
            }

            $this->_authSession->setUser($user);

            try {
                $result = $this->_userModel->authenticate($username, $password);
            } catch (\Exception $e) {

                // AuthenticationException
                $mes = $e->getMessage();
                throw new \Magento\Framework\Exception\LocalizedException(__('FATAL_ERROR_AUTHENTIFICATION_EXCEPTION'));
            }

            if (!$result) {
                // InvalidEmailOrPasswordException
                throw new \Magento\Framework\Exception\LocalizedException(__('FATAL_ERROR_WRONG_USERNAME_OR_PASSWORD'));
            }

            try {
                $this->_authSession->processLogin();
            } catch (\Exception $e) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('FATAL_ERROR_AUTHENTICATION_EXCEPTION : ' . $e->getMessage())
                );
            }
        } else {
            $user = $this->_userModel->loadByUsername($username);

            if (!$this->_userModel->verifyIdentity($password)) {
                $this->_authSession->processLogout();
                throw new \Magento\Framework\Exception\LocalizedException(__('FATAL_ERROR_WRONG_USERNAME_OR_PASSWORD'));
            }
        }

        if ($this->_data->checkACL()) {
            if (!$this->_authSession->isAllowed('Magento_Catalog::products')) {
                throw new \Magento\Framework\Exception\LocalizedException(__('FATAL_ERROR_NOT_ALLOWED_IN_ACL'));
            }
        }

        return true;
    }

    /**
     * Execute a request
     *
     * @param $request
     * @return false|string
     * @throws \Magento\Framework\Exception\AuthenticationException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute($request)
    {
        $startTime = microtime(true);

        $securityKeyBase64 = $this->_data->getSecurityKey();

        if (strlen($securityKeyBase64) != 44) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('FATAL_ERROR_SECURITY_KEY : Security key must be 44 caracters long. Current length is ' . strlen($securityKeyBase64)
                    . '. You need to log in to your store admin panel, go to Store > Configuration > Catalog > Product Manager For Magento Connector, click "Generate new security key" button and click "Save Config" button.')
            );
        }

        $encryptionKey = base64_decode($securityKeyBase64);
        $post = $request->getPostValue();
        $iv = $request->getPostValue("iv");
        $c = $request->getPostValue("c");

        //check if all the fields are set
        if ($c == null || $iv == null) {
            throw new \Magento\Framework\Exception\LocalizedException(__('FATAL_ERROR_POST_FIELDS_MISSING'));
        }

        $iv = base64_decode($iv);
        $c = base64_decode($c);

        $plaintext = $this->decipher(
            $c,
            $encryptionKey,
            $iv
        );

        if ($plaintext == false) {
            throw new \Magento\Framework\Exception\LocalizedException(__('FATAL_ERROR_WRONG_SECURITY_KEY'));
        }

        $jsonRpc = json_decode($plaintext);

        if ($jsonRpc == null) {
            throw new \Magento\Framework\Exception\LocalizedException(__('FATAL_ERROR_INVALID_JSON : ' . $plaintext));
        }

        $username = $jsonRpc[0]->username;
        $encryptedpassword = base64_decode($jsonRpc[0]->encryptedpassword);

        $ok = $this->checkUser(
            $username,
            $encryptedpassword,
            $encryptionKey,
            $iv
        );

        if (!$ok) {
            throw new \Magento\Framework\Exception\LocalizedException(__(""));
        }

        $jsonRpcResult = $this->executeJsonRpc($jsonRpc[1], $request);
        $this->_database->closeConnection();

        $jsonRpcResult->executionTime = microtime(true) - $startTime;

        return json_encode($jsonRpcResult);
    }

    /**
     * Execute requests
     *
     * @param $jsonRpc
     * @param $request
     * @return \stdClass
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function executeJsonRpc(&$jsonRpc, $request)
    {
        $startTime = microtime(true);
        $jsonRpcResult = new \stdClass();

        if ($jsonRpc->method == 'batch') {
            $jsonRpcResult->result = [];
            $count = count($jsonRpc->params);

            for ($i = 0; $i < $count; ++$i) {
                $jsonRpcResult->result[$i] = $this->executeJsonRpc($jsonRpc->params[$i], $request);
            }
        } elseif ($jsonRpc->method == 'sqlquery') {
            $is_read = ($jsonRpc->params[0] == 'r');
            $sql = $jsonRpc->params[1];
            $binds = [];

            $parametersCount = count($jsonRpc->params);
            for ($i = 2; $i < $parametersCount; ++$i) {
                $binds[] = $jsonRpc->params[$i];
            }
            $this->_database->executeSqlQuery($jsonRpcResult, $is_read, $sql, $binds);
        } elseif ($jsonRpc->method == 'databaseconnection') {
            $databaseAPI = $jsonRpc->params[0];
            $this->_database->executeDatabaseConnection($jsonRpcResult, $databaseAPI);
        } elseif ($jsonRpc->method == 'uploadimage') {
            $type = $jsonRpc->params[0];
            $filename = $jsonRpc->params[1];
            $data = $jsonRpc->params[2]; //unused
            $lastModificationTime = $jsonRpc->params[3];
            $failIfFileExists = $jsonRpc->params[4];
            $useDispretionPath = true;

            // To keep compatibility with Product Manager version < 2.1.1.65
            if (count($jsonRpc->params) > 5) {
                $useDispretionPath = $jsonRpc->params[5];
            }

            $this->_imageUploader->uploadImage(
                $jsonRpcResult,
                $type,
                $filename,
                $data,
                $lastModificationTime,
                $failIfFileExists,
                $useDispretionPath,
                $request
            );
        } elseif ($jsonRpc->method == 'deleteimage') {
            $type = $jsonRpc->params[0];
            $filename = $jsonRpc->params[1];   // h/t/htc-touch-diamond.jpg
            $this->_imageUploader->deleteImage($jsonRpcResult, $type, $filename);
        } elseif ($jsonRpc->method == 'getconfig') {
            $this->_magentoConfiguration->getConfig($jsonRpcResult);
        } elseif ($jsonRpc->method == 'getsourcemodels') {
            $store_id = $jsonRpc->params[0];
            $locale_code = $jsonRpc->params[1];
            $this->_magentoConfiguration->getSourceModels($jsonRpcResult, $store_id, $locale_code);
        } elseif ($jsonRpc->method == 'loadandsaveproducts') {
            $productIds = $jsonRpc->params;
            $this->_reindexer->loadAndSaveProducts($jsonRpcResult, $productIds);
            //
        } elseif ($jsonRpc->method == 'regenerateproductsurlrewrites') {
            $productIds = $jsonRpc->params;
            $this->_reindexer->regenerateProductsUrlRewrites($jsonRpcResult, $productIds);
            //
        } elseif ($jsonRpc->method == 'reindexproductsusingindexers') {
            $productIds = $jsonRpc->params->productIds;
            $reindexerIds = $jsonRpc->params->reindexerIds;
            $this->_reindexer->reindexProductsUsingIndexers($jsonRpcResult, $productIds, $reindexerIds);
            //
        } elseif ($jsonRpc->method == 'cleanproductscache') {
            $productIds = $jsonRpc->params;
            $this->_reindexer->cleanProductsCache($jsonRpcResult, $productIds);
        } elseif ($jsonRpc->method == 'connect') {
            $jsonRpcResult->result = new \stdClass();
            $jsonRpcResult->result->platform = 'Magento 2';
            $jsonRpcResult->result->bridgetype = 'Extension';
            $jsonRpcResult->result->extensionversion = self::EXTENSION_VERSION;
            $jsonRpcResult->result->bridgeversion = self::BRIDGE_VERSION;
            $jsonRpcResult->result->bridgeapiversion = '2';
        }

        $jsonRpcResult->id = $jsonRpc->id;
        $jsonRpcResult->executionTime = microtime(true) - $startTime;

        return $jsonRpcResult;
    }
}
