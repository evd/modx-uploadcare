<?php
/**
 * @package uploadcare
 */
/**
 * Handles adding UploadcareMediaSource to Extension Packages
 *
 * @package uploadcare
 * @subpackage build
 */

 if ($transport->xpdo) {
    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            /** @var modX $modx */
            $modx =& $transport->xpdo;
            $modelPath = $modx->getOption('upload.core_path');
            if (empty($modelPath)) {
                $modelPath = '[[++core_path]]components/uploadcare/model/';
            }
            if ($modx instanceof modX) {
                $modx->addExtensionPackage('uploadcare',$modelPath);
            }
            break;
        case xPDOTransport::ACTION_UNINSTALL:
            $modx =& $transport->xpdo;
            $modelPath = $modx->getOption('uploadcare.core_path',null,$modx->getOption('core_path').'components/uploadcare/').'model/';
            if ($modx instanceof modX) {
                $modx->removeExtensionPackage('uploadcare');
            }
            break;
    }
}
return true;