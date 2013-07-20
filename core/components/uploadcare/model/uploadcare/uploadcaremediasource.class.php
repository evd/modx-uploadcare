<?php

require_once MODX_CORE_PATH . 'model/modx/sources/modmediasource.class.php';
/**
 * Implements an Uploadcare storage media source, allowing basic manipulation, uploading and URL-retrieval of resources
 *
 * @package modx
 * @subpackage sources
 */
class UploadcareMediaSource extends modMediaSource implements modMediaSourceInterface {
    /** @var $api Uploadcare_Api */
    public $api;

    /** @var $autoStore boolean */
    public $autoStore = false;

    /**
     * Override the constructor to always force Uploadcare sources to not be streams.
     *
     * {@inheritDoc}
     *
     * @param xPDO $xpdo
     */
    public function __construct(xPDO & $xpdo) {
        parent::__construct($xpdo);
        $this->set('is_stream',false);
    }

    /**
     * Initializes Uploadcare media class and connect
     * @return boolean
     */
    public function initialize() {
        parent::initialize();
        $properties = $this->getPropertyList();
        $this->autoStore = $this->xpdo->getOption('autoStore', $properties, false);

        include_once dirname(__FILE__).'/lib/Uploadcare.php';

        $this->connect();

        return true;
    }

    /**
     * Get the name of this source type
     * @return string
     */
    public function getTypeName() {
        $this->xpdo->lexicon->load('uploadcare:default');
        return $this->xpdo->lexicon('source_type.uploadcare');
    }
    /**
     * Get the description of this source type
     * @return string
     */
    public function getTypeDescription() {
        $this->xpdo->lexicon->load('uploadcare:default');
        return $this->xpdo->lexicon('source_type.uploadcare_desc');
    }


    /**
     * Connect to Uploadcare
     * @return Uploadcare_Api
     */
    public function connect() {
        if (!isset($this->api)) {
            try {
                $properties = $this->getPropertyList();
                
                $public_key = $this->xpdo->getOption('public_key',$properties,'');
                $secret_key = $this->xpdo->getOption('secret_key',$properties,'');

                $this->api = new Uploadcare_Api($public_key, $secret_key);

            } catch (Exception $e) {
                $this->xpdo->log(modX::LOG_LEVEL_ERROR,'[UploadcareMediaSource] Could not connect to Uploadcare: '.$e->getMessage());
            }
        }
        return $this->api;
    }

    /**
     * Get a list of file objects
     * @return array
     */
    public function getUploadcareObjectList() {
        $data = $this->api->request('GET', '/files/');//, array('page' => 1, 'per_page' => 10));
        $files_raw = (array)$data->results;
        $result = array();
        foreach ($files_raw as $file_raw) {
            $result[] = $this->api->getFile($file_raw->uuid);
        }
        return $result;
        //$objects = $this->api->getFileList(0);
        //return $objects;
    }

    /**
     * Get a list of files
     * @return array
     */
    public function getUploadcareFileList() {
        $data = $this->api->request('GET', '/files/');//, array('page' => 1, 'per_page' => 10));
        return (array)$data->results;
    }


    /**
     * @param string $path
     * @return array
     */
    public function getContainerList($path) {
        $list = $this->getUploadcareFileList();

        $files = array();
        foreach ($list as $file) {
            /* var $file Uploadcare_File */
            $fileId = $file->uuid;
            $isDir = false;
            $obj = $this->api->getFile($fileId);

            $files[$fileId] = array(
                'id' => $fileId,
                'text' => $fileId,
                'cls' => 'icon-'. ($file->is_image?'jpeg':'file'),
                'type' => 'file',
                'leaf' => true,
                'path' => $fileId,
                'pathRelative' => $fileId,
                'directory' => '/',
                'url' => $obj->getUrl(),
                'file' => $fileId
            );
            $files[$fileId]['menu'] = array('items' => $this->getListContextMenu($fileId, $isDir, $files[$fileId], true));
        }

        $ls = array();
        ksort($files);
        foreach ($files as $file) {
            $ls[] = $file;
        }
        return $ls;
    }

    /**
     * Get the context menu for when viewing the source as a tree
     *
     * @param string $file
     * @param boolean $isDir
     * @param array $fileArray
     * @param $isBinary
     * @return array
     */
    public function getListContextMenu($file, $isDir, array $fileArray, $isBinary) {
        $menu = array();
        if ($this->hasPermission('file_view')) {
            $menu[] = array(
                'text' => $this->xpdo->lexicon('file_download'),
                'handler' => 'this.downloadFile',
            );
        }
        if ($this->hasPermission('file_remove')) {
            if (!empty($menu)) $menu[] = '-';
            $menu[] = array(
                'text' => $this->xpdo->lexicon('file_remove'),
                'handler' => 'this.removeFile',
            );
        }
        return $menu;
    }

    /**
     * Get all files in the directory and prepare thumbnail views
     * 
     * @param string $path
     * @return array
     */
    public function getObjectsInContainer($path) {
        $list = $this->getUploadcareFileList();

        /* get default settings */
        $thumbnailType = $this->getOption('thumbnailType',$this->properties,'png');

        /* iterate */
        $files = array();
        foreach ($list as $file) {

            $obj = $this->api->getFile($file->uuid);
            $fileUrl = $obj->getUrl();

            $fileArray = array(
                'id' => $file->uuid,
                'name' => $file->uuid,
                'url' => $obj->getUrl(),
                'relativeUrl' => $fileUrl,
                'fullRelativeUrl' => $obj->getUrl(),
                'pathname' => $obj->getUrl(),
                'size' => $file->size,
                'leaf' => true,
                'menu' => array(
                    array('text' => $this->xpdo->lexicon('file_remove'),'handler' => 'this.removeFile'),
                ),
            );

            /* get thumbnail */
            if ($file->is_image) {
                $imageWidth = $this->ctx->getOption('filemanager_image_width', 800);
                $imageHeight = $this->ctx->getOption('filemanager_image_height', 600);
                $thumbHeight = $this->ctx->getOption('filemanager_thumb_height', 60);
                $thumbWidth = $this->ctx->getOption('filemanager_thumb_width', 80);

                /* ensure max h/w */
                if ($thumbWidth > $imageWidth) $thumbWidth = $imageWidth;
                if ($thumbHeight > $imageHeight) $thumbHeight = $imageHeight;

                /* generate thumb/image URLs */
                $fileArray['thumb'] = $fileUrl . '-/stretch/fill/-/resize/'.$thumbWidth.'x'.$thumbHeight.'/-/format/'.$thumbnailType.'/';
                $fileArray['image'] = $fileUrl . '-/stretch/fill/-/resize/'.$imageWidth.'x'.$imageHeight.'/-/format/'.$thumbnailType.'/';

                $files[] = $fileArray;
            }
        }
        return $files;
    }


    /**
     * Create a Container
     *
     * @param string $name
     * @param string $parentContainer
     * @return boolean
     */
    public function createContainer($name,$parentContainer) {
        $this->addError('file',$this->xpdo->lexicon('uploadcare.dont_support_create_dir'));
        return false;
    }

    /**
     * Delete a file
     * 
     * @param string $objectPath
     * @return boolean
     */
    public function removeObject($objectPath) {
        try {
            $file = $this->api->getFile($objectPath);
            $file->delete();
        }
        catch (Exception $e) {
            //PHP API Bug, always generate exeption
        }

        /* log manager action */
        $this->xpdo->logManagerAction('file_remove','',$objectPath);
        return true;
    }


    /**
     * Create an object from a path
     *
     * @param string $objectPath
     * @param string $name
     * @param string $content
     * @return boolean|string
     */
    public function createObject($objectPath,$name,$content) {
        $this->addError('file',$this->xpdo->lexicon('uploadcare.dont_support_create_files'));
        return false;
    }

    /**
     *  Create temporary directory
     *  @return string
     */
    private function createTempDir() {
        $dir = rtrim(sys_get_temp_dir(), '/') . '/';
        $prefix = 'ucr';
        $mode = 0700;
        do
        {
            $path = $dir.$prefix.mt_rand(0, 9999999);
        } while (!mkdir($path, $mode));
        return rtrim($path, '/') .'/';
    }

    /**
     * Upload files to Uploadcare
     * 
     * @param string $container
     * @param array $objects
     * @return bool
     */
    public function uploadObjectsToContainer($container,array $objects = array()) {

        $allowedFileTypes = explode(',',$this->xpdo->getOption('upload_files',null,''));
        $allowedFileTypes = array_merge(explode(',',$this->xpdo->getOption('upload_images')),explode(',',$this->xpdo->getOption('upload_media')),explode(',',$this->xpdo->getOption('upload_flash')),$allowedFileTypes);
        $allowedFileTypes = array_unique($allowedFileTypes);
        $maxFileSize = $this->xpdo->getOption('upload_maxsize',null,1048576);

        /* loop through each file and upload */
        foreach ($objects as $file) {
            if ($file['error'] != 0) continue;
            if (empty($file['name'])) continue;
            $ext = @pathinfo($file['name'],PATHINFO_EXTENSION);
            $ext = strtolower($ext);

            if (empty($ext) || !in_array($ext,$allowedFileTypes)) {
                $this->addError('path',$this->xpdo->lexicon('file_err_ext_not_allowed',array(
                    'ext' => $ext,
                )));
                continue;
            }

            if ($file['size'] > $maxFileSize) {
                $this->addError('path',$this->xpdo->lexicon('file_err_too_large',array(
                    'size' => $file['size'],
                    'allowed' => $maxFileSize,
                )));
                continue;
            }

            //Create file in temporary directory for clean filename
            $tmpdir = $this->createTempDir();
            $filename = $file['name'];
            //Sanitize the filename
            $remove_these = array(' ','`','"','\'','\\','/');
            $filename = str_replace($remove_these, '', $filename);
            $filepath = $tmpdir.$filename;
            copy($file['tmp_name'], $filepath);
            $obj = $this->api->uploader->fromPath($filepath, $file['type']);
            unlink($filepath);
            rmdir($tmpdir);

//            $content = file_get_contents($file['tmp_name']);
//            $obj = $this->api->uploader->fromContent($content, $file['type']);
            if ($this->autoStore)
                $obj->store();
        }

        /* invoke event */
        $this->xpdo->invokeEvent('OnFileManagerUpload',array(
            'files' => &$objects,
            'directory' => $container,
            'source' => &$this,
        ));

        $this->xpdo->logManagerAction('file_upload','',$container);

        return true;
    }

    /**
     * @return array
     */
    public function getDefaultProperties() {
        return array(
            'public_key' => array(
                'name' => 'public_key',
                'desc' => 'prop_uploadcare.public_key_desc',
                'type' => 'textfield',
                'options' => '',
                'value' => '',
                'lexicon' => 'uploadcare:default',
            ),
            'secret_key' => array(
                'name' => 'secret_key',
                'desc' => 'prop_uploadcare.secret_key_desc',
                'type' => 'password',
                'options' => '',
                'value' => '',
                'lexicon' => 'uploadcare:default',
            ),
            'autoStore' => array(
                'name' => 'autoStore',
                'desc' => 'prop_uploadcare.autostore_desc',
                'type' => 'combo-boolean',
                'options' => '',
                'value' => '1',
                'lexicon' => 'uploadcare:default',
            ),
            'thumbnailType' => array(
                'name' => 'thumbnailType',
                'desc' => 'prop_file.thumbnailType_desc',
                'type' => 'list',
                'options' => array(
                    array('name' => 'PNG','value' => 'png'),
                    array('name' => 'JPG','value' => 'jpeg'),
                    array('name' => 'GIF','value' => 'gif'),
                ),
                'value' => 'png',
                'lexicon' => 'core:source',
            )
        );
    }

    /**
     * Prepare a src parameter to be rendered with phpThumb
     * 
     * @param string $src
     * @return string
     */
    public function prepareSrcForThumb($src) {
        return $src;
    }


    /**
     * Get the absolute URL for a specified object. Only applicable to sources that are streams.
     *
     * @param string $object
     * @return string
     */
    public function getObjectUrl($object = '') {
        $file = $this->api->getFile($object);
        return $file->getUrl();
    }


    /**
     * Get the contents of a specified file
     *
     * @param string $objectPath
     * @return array
     */
    public function getObjectContents($objectPath) {
        try {
            $file = $this->api->getFile($objectPath);
            $ch = curl_init($file->getUrl());
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $contents = curl_exec($ch);
            curl_close($ch);

            return array(
                'name' => $objectPath,
                'basename' => $file->data['original_filename'],
                'path' => $objectPath,
                'size' => $file->data['size'],
                'last_accessed' => '',
                'last_modified' => $file->data['upload_date'],
                'content' => $contents,
                'image' => $file->data['is_image'],
                'is_writable' => false,
                'is_readable' => true,
            );
        }
        catch (Exception $e) {
            $this->addError('file',$this->xpdo->lexicon('file_folder_err_ns').': '.$objectPath);
        }
    }
}