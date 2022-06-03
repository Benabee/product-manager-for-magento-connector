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
 * Class ImageUploader
 * @package Benabee\ProductManagerConnector\Model
 */
class ImageUploader
{
    protected $_storeManager;
    protected $_productMediaConfig;
    protected $_filesystem;
    protected $_file;
    protected $_uploader;
    protected $_imageResize;

    /**
     * ImageUploader constructor
     *
     * @param \Magento\Store\Model\StoreManager $storeManager
     * @param \Magento\Catalog\Model\Product\Media\Config $productMediaConfig
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\MediaStorage\Model\File\UploaderFactory $fileUploader
     * @param \Magento\Framework\Filesystem\Driver\File $file
     * @param \Magento\MediaStorage\Service\ImageResize $imageResize
     */
    public function __construct(
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Catalog\Model\Product\Media\Config $productMediaConfig,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\MediaStorage\Model\File\UploaderFactory $fileUploader,
        \Magento\Framework\Filesystem\Driver\File $file,
        ImageResizeFactory $imageResizeFactory
    ) {
        $this->_storeManager = $storeManager;
        $this->_productMediaConfig = $productMediaConfig;
        $this->_filesystem = $filesystem;
        $this->_file = $file;
        $this->_uploader = $fileUploader;
        $this->_imageResize = $imageResizeFactory->create();
    }

    /**
     * Get filename with dispersion path
     *
     * @param $filename
     * @return string
     */
    public function getFilenameWithDispersionPath($filename)
    {
        $correctFileName = \Magento\MediaStorage\Model\File\Uploader::getCorrectFileName($filename);
        $dispretionPath = \Magento\MediaStorage\Model\File\Uploader::getDispretionPath($correctFileName);
        return $dispretionPath . '/' . $correctFileName;
    }

    /**
     * Upload an image
     *
     * @param $jsonRpcResult
     * @param $type
     * @param $filename
     * @param $data
     * @param $lastModificationTime
     * @param $failIfFileExists
     * @param $request
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function uploadImage(
        $jsonRpcResult,
        $type,
        $filename,
        $data,
        $lastModificationTime,
        $failIfFileExists,
        $request
    ) {
        $nbBytes = 0;
        $error = null;
        $errorCode = null;
        $noDuplicatedFileName = '';
        $remoteFileSize = -1;
        $remoteLastModificationTime = 0;
        $imagesDeletedInCache = 0;
       
        // \Magento\Framework\Api\ImageProcessor
        $base = '';
        $filePath  = '';
        $fileNameWithDispretionPath = $this->getFilenameWithDispersionPath($filename);

        if ($type == 'product') {
            $base = $this->_productMediaConfig->getBaseMediaPath();
            $filePath = $this->_productMediaConfig->getMediaPath($fileNameWithDispretionPath);

        } elseif ($type == 'category') {
            $base = $this->_storeManager->getStore()->getBaseMediaDir() .'catalog/category';
            $filePath = $base . $fileNameWithDispretionPath;
        }

        $mediaDirectory = $this->_filesystem->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
        $absoluteFilePath = $mediaDirectory->getAbsolutePath($filePath);

        // Test if the file already exists
        if ($this->_file->isExists($absoluteFilePath)) {

            $fileData = $this->_file->stat($absoluteFilePath);
            $remoteLastModificationTime = $fileData["mtime"];

            if ($failIfFileExists) {
                $error = 'ERROR_FILE_AREADY_EXISTS';

                // Get new recommended file name
                $noDuplicatedFileName = \Magento\MediaStorage\Model\File\Uploader::getNewFileName($absoluteFilePath);

            } elseif (!$this->_file->isWritable($absoluteFilePath)) {
                // The file is not writable
                $error = 'ERROR_FILE_NOT_WRITABLE';
            }

        } else {

            // Check that the directory is writable
            $destinationDirectory = $this->_file->getParentDirectory($absoluteFilePath);

            if ($this->_file->isExists($destinationDirectory) && !$this->_file->isWritable($destinationDirectory)) {
                $error = 'ERROR_DIRECTORY_NOT_WRITABLE';
            }
        }

        // Upload the image
        if ($error == null) {
            $fileUploaded = false;
            
            $file = $request->getFiles('image0');
          
            if ($file) {
                $uploader = $this->_uploader->create(['fileId' =>'image0']);
                $uploader->setAllowCreateFolders(true);
                $uploader->setAllowRenameFiles(false);

                $destinationDirectory = $this->_file->getParentDirectory($absoluteFilePath);
                $result = $uploader->save($destinationDirectory, $filename);

                if ($result['file']) {
                    $fileUploaded = true;
                }
        
                // Update cached images
                if ($fileUploaded) {
                    $this->_file->touch($absoluteFilePath, $lastModificationTime);
                    $fileData = $this->_file->stat($absoluteFilePath);
                    $remoteLastModificationTime =  $fileData["mtime"];

                    //if (!is_null($mode)) chmod($filename, $mode);

                    if ($type == 'product') {

                        $this->deleteImagesInCache(
                            $mediaDirectory->getAbsolutePath($base),
                            $fileNameWithDispretionPath
                        );

                        if ($this->_imageResize) {
                            // Create resized images in cache
                            $this->_imageResize->resizeFromImageName($fileNameWithDispretionPath);
                        }
                    }
                }
            }
        }

        if ($this->_file->isExists($absoluteFilePath)) {
            $fileData = $this->_file->stat($absoluteFilePath);
            $remoteFileSize =  $fileData["size"];
        }

        if ($error) {
            $jsonRpcResult->error = new \stdClass();
            $jsonRpcResult->error->errorcode = $error;
            $jsonRpcResult->error->remotefilename = $fileNameWithDispretionPath;
            $jsonRpcResult->error->remotefilepath = $filePath;
            $jsonRpcResult->error->remoteabsolutefilepath = $absoluteFilePath;
            $jsonRpcResult->error->remotefilesize = $remoteFileSize;
            $jsonRpcResult->error->remotelastmodificationtime = $remoteLastModificationTime;
            $jsonRpcResult->error->noduplicatedfilename = $noDuplicatedFileName;
        } else {
            $jsonRpcResult->result = new \stdClass();
            $jsonRpcResult->result->nbbytes = $nbBytes;
            $jsonRpcResult->result->remotefilename = $fileNameWithDispretionPath;
            $jsonRpcResult->result->remotefilepath = $filePath;
            $jsonRpcResult->result->remoteabsolutefilepath = $absoluteFilePath;
            $jsonRpcResult->result->remotefilesize = $remoteFileSize;
            $jsonRpcResult->result->remotelastmodificationtime = $remoteLastModificationTime;
            $jsonRpcResult->result->imagesDeletedInCache = $imagesDeletedInCache;
        }
    }

    /**
     * Delete cached images
     *
     * @param $baseAbsolutePath
     * @param $fileNameWithDispretionPath
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function deleteImagesInCache($baseAbsolutePath, $fileNameWithDispretionPath)
    {
        //remove image from cache
        $imagesToDelete = [];
        $search = $baseAbsolutePath . '/cache/*' . $fileNameWithDispretionPath;  //media\catalog\product\cache\1\small_image\135x\9df78eab33525d08d6e5fb8d27136e95\y\u\yulips.jpg
        $imagesToDelete = glob($search);
        
        foreach ($imagesToDelete as $imagePath) {
            if ($this->_file->isExists($imagePath)) {
                $this->_file->deleteFile($imagePath);
            }
        }
    }

    /**
     * Delete an image
     *
     * @param $jsonRpcResult
     * @param $type
     * @param $filename
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function deleteImage($jsonRpcResult, $type, $filename)
    {
        $error = null;

        $base = '';
        $filePath  = '';
        $fileNameWithDispretionPath = $this->getFilenameWithDispersionPath($filename);

        if ($type == 'product') {
            $base = $this->_productMediaConfig->getBaseMediaPath();
            $filePath = $this->_productMediaConfig->getMediaPath($fileNameWithDispretionPath);

        } elseif ($type == 'category') {
            $base = $this->_storeManager->getStore()->getBaseMediaDir() .'catalog/category';
            $filePath = $base . $fileNameWithDispretionPath;
        }

        $mediaDirectory = $this->_filesystem->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
        $absoluteFilePath = $mediaDirectory->getAbsolutePath($filePath);

        if (!$this->_file->isExists($filePath)) {
            $error = 'ERROR_FILE_DOESNT_EXIST';
        } else {
            if (!$this->_file->deleteFile($filePath)) {
                $error = 'ERROR_FILE_CANT_BE_DELETED';
            }
        }

        if ($error) {
            $jsonRpcResult->error = new \stdClass();
            $jsonRpcResult->error->errorcode = $error;
            $jsonRpcResult->error->remotefilename = $fileName;
            $jsonRpcResult->error->remotefilepath = $filePath;
        } else {
            $jsonRpcResult->result = new \stdClass();
            $jsonRpcResult->result->remotefilename = $fileName;
            $jsonRpcResult->result->remotefilepath = $filePath;
        }
    }
}
