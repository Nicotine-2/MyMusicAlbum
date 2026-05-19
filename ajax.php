<?php
/**
 * AJAX 处理入口 - 用于获取简介等操作
 */
define('__TYPECHO_ROOT_DIR__', dirname(__FILE__, 4));
require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';

Typecho_Plugin::factory('admin/common.php')->begin();
Typecho_Widget::widget('Widget_User')->pass('administrator');

$uploadDir = Typecho_Widget::widget('Widget_Options')->plugin('MyMusicAlbum')->uploadDir ?: '/usr/uploads/MyMusicAlbum';
$uploadFullPath = __TYPECHO_ROOT_DIR__ . $uploadDir;

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action == 'get_desc') {
    $artist = isset($_GET['artist']) ? trim($_GET['artist']) : '';
    $album = isset($_GET['album']) ? trim($_GET['album']) : '';
    
    if ($album) {
        $path = $uploadFullPath . '/' . $artist . '/' . $album . '/description.txt';
    } else {
        $path = $uploadFullPath . '/' . $artist . '/description.txt';
    }
    
    if (file_exists($path)) {
        echo file_get_contents($path);
    } else {
        echo '';
    }
    exit;
}