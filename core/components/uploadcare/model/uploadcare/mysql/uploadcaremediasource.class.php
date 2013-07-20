<?php
/**
 * @package uploadcare
 */
require_once (strtr(realpath(dirname(dirname(__FILE__))), '\\', '/') . '/uploadcaremediasource.class.php');
class UploadcareMediaSource_mysql extends UploadcareMediaSource {}
?>