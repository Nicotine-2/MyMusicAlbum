<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 音乐专辑插件，文章使用短代码引用专辑 [MAG-歌手名称] [MAG-歌手名称-专辑1名称,专辑2名称] [MAG-专辑名称] for Typecho 1.3
 * 
 * @package MyMusicAlbum
 * @author Nicotine-2
 * @version 1.0.0
 * @link https://github.com/Nicotine-2/MyMusicAlbum
 */
class MyMusicAlbum_Plugin implements Typecho_Plugin_Interface
{
    const DEFAULT_UPLOAD_DIR = '/usr/uploads/MyMusicAlbum';
    const BG_OPACITY = 0.8;
    private static $id = 0;
    private static $styleLoaded = false;
    private static $uploadDir = null;

    private static function getUploadDir()
    {
        if (self::$uploadDir !== null) {
            return self::$uploadDir;
        }
        
        try {
            $options = Helper::options();
            $pluginConfig = $options->plugin('MyMusicAlbum');
            $dir = isset($pluginConfig->uploadDir) && !empty($pluginConfig->uploadDir) 
                ? trim($pluginConfig->uploadDir) 
                : self::DEFAULT_UPLOAD_DIR;
        } catch (Exception $e) {
            $dir = self::DEFAULT_UPLOAD_DIR;
        }
        
        self::$uploadDir = $dir;
        return $dir;
    }
    
    private static function getUploadFullPath()
    {
        $path = __TYPECHO_ROOT_DIR__ . self::getUploadDir();
        return is_link($path) ? readlink($path) : $path;
    }
    
    private static function getUploadUrl()
    {
        return rtrim(Helper::options()->siteUrl, '/') . '/' . trim(self::getUploadDir(), '/');
    }
    
    private static function getRealPath($path)
    {
        if (is_link($path)) {
            $linkTarget = readlink($path);
            $realPath = dirname($path) . '/' . $linkTarget;
            return is_dir($realPath) ? $realPath : $path;
        }
        return $path;
    }

    public static function activate()
    {
        $dir = __TYPECHO_ROOT_DIR__ . self::getUploadDir();
        if (!is_dir($dir) && !is_link($dir)) {
            @mkdir($dir, 0755, true);
        }
        Helper::addPanel(1, 'MyMusicAlbum/manage.php', '我的专辑', '管理专辑', 'administrator');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array(__CLASS__, 'parse');
        Typecho_Plugin::factory('Widget_Archive')->header = array(__CLASS__, 'header');
        return '启动成功，存储目录：' . self::getUploadDir();
    }

    public static function deactivate()
    {
        Helper::removePanel(1, 'MyMusicAlbum/manage.php');
        return '卸载成功';
    }

    public static function config($form)
    {
        $uploadDir = new Typecho_Widget_Helper_Form_Element_Text(
            'uploadDir',
            null,
            self::DEFAULT_UPLOAD_DIR,
            '音乐存储目录',
            '相对于网站根目录的路径，例如：/usr/uploads/MyMusicAlbum<br>' .
            '<span style="color:#ff6600;">⚠️ 注意：修改路径后，需要手动将原 MyMusicAlbum 文件夹移动到新路径，如果没有请新建该目录并设置好读写权限(755)。</span>'
        );
        $form->addInput($uploadDir);
        
        $currentPath = self::getUploadDir();
        $fullPath = self::getUploadFullPath();
        $isWritable = is_writable($fullPath) ? '可写' : '不可写，请检查权限';
        $isLink = is_link(__TYPECHO_ROOT_DIR__ . self::getUploadDir()) ? '是（软连接）' : '否';
        $info = new Typecho_Widget_Helper_Form_Element_Text(
            'pathInfo',
            null,
            null,
            '当前路径信息',
            '相对路径：' . $currentPath . '<br>' .
            '绝对路径：' . $fullPath . '<br>' .
            'URL路径：' . self::getUploadUrl() . '<br>' .
            '状态：' . (is_dir($fullPath) ? '目录存在' : '目录不存在') . ' | 权限：' . $isWritable . ' | 软连接：' . $isLink
        );
        $info->input->setAttribute('disabled', 'disabled');
        $form->addInput($info);
    }

    public static function personalConfig($c){}

    public static function header()
    {
        if (!self::$styleLoaded) {
            self::$styleLoaded = true;
            echo '<style>
/* 歌手卡片容器 - 固定宽度256px，高度2:3比例 */
.mag-singer-card {
    position: relative;
    display: inline-block;
    margin: 20px;
    text-align: center;
    width: 256px;
    height: 384px;
    overflow: hidden;
    border-radius: 16px;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-color: #2a2a2e;
    box-shadow: none;
    outline: none;
}
/* 歌手名称 - 绝对定位在底部 */
.mag-singer-name {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 56px;
    line-height: 56px;
    text-align: center;
    background: rgba(0,0,0,0.85);
    color: #fff;
    font-size: 18px;
    font-weight: bold;
    margin: 0;
    padding: 0 12px;
    pointer-events: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    z-index: 2;
}
/* 圆角遮罩 - 修复白边 */
.mag-singer-card::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    border-radius: 16px;
    pointer-events: none;
    z-index: 5;
    box-shadow: inset 0 0 0 1px rgba(0,0,0,0.3);
}
/* 透明蒙版 - 点击区域 */
.mag-singer-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border-radius: 16px;
    cursor: pointer;
    z-index: 10;
    background: transparent;
}
/* 模态框样式 */
.mag-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.95);
    z-index: 99999;
    overflow-y: auto;
}
.mag-overlay-close {
    position: fixed;
    top: 20px;
    right: 30px;
    font-size: 40px;
    color: #fff;
    cursor: pointer;
    z-index: 100000;
}
.mag-overlay-close:hover { color: #f00; }
.mag-overlay-content {
    width: 100%;
    max-width: 1200px;
    margin: 40px auto;
    padding: 20px;
    text-align: left;
}
.mag-overlay-title {
    text-align: center;
    color: #fff;
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 30px;
}
/* 专辑网格布局 */
.mag-albums-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    justify-content: flex-start;
    align-items: flex-start;
    margin: 0;
    padding: 0;
    width: 100%;
}
.mag-album-item {
    width: 260px;
    margin: 0;
    padding: 0;
    flex-shrink: 0;
}
</style>';
        }
    }

    public static function parse($text, $widget, $last)
    {
        $text = empty($last) ? $text : $last;
        return preg_replace_callback('/\[MAG\-([^\]]+)\]/', function($m){
            $param = trim($m[1]);
            $parts = explode('-', $param);
            $count = count($parts);
            
            if ($count === 1) {
                $name = $parts[0];
                $base = self::getUploadFullPath() . '/' . $name;
                if (is_dir($base) && self::isArtist($base)) {
                    return self::renderSinger($name, null);
                }
            }
            
            if ($count >= 2) {
                $artist = $parts[0];
                $albumPart = implode('-', array_slice($parts, 1));
                $albums = explode(',', $albumPart);
                $albums = array_map('trim', $albums);
                
                $base = self::getUploadFullPath() . '/' . $artist;
                if (is_dir($base) && self::isArtist($base)) {
                    return self::renderSinger($artist, $albums);
                }
            }
            
            return self::play($param);
        }, $text);
    }
    
    private static function isArtist($path)
    {
        $realPath = self::getRealPath($path);
        $audioFiles = glob($realPath . '/*.{mp3,flac,wav,ogg,aac,m4a}', GLOB_BRACE);
        if (!empty($audioFiles)) return false;
        $subDirs = glob($realPath . '/*', GLOB_ONLYDIR);
        return !empty($subDirs);
    }
    
    private static function renderSinger($artist, $albums = null)
    {
        $artistPath = self::getUploadFullPath() . '/' . $artist;
        $realPath = self::getRealPath($artistPath);
        $baseUrl = self::getUploadUrl();
        $avatarUrl = $baseUrl . '/' . $artist . '/avatar.jpg';
        $overlayId = 'mag_overlay_' . md5($artist . serialize($albums));
        
        $albumList = array();
        if ($albums === null) {
            $albumDirs = glob($realPath . '/*', GLOB_ONLYDIR);
            foreach ($albumDirs as $albumPath) {
                $albumList[] = basename($albumPath);
            }
        } else {
            foreach ($albums as $album) {
                $albumPath = $realPath . '/' . $album;
                if (is_dir($albumPath)) {
                    $albumList[] = $album;
                }
            }
        }
        
        $albumsHtml = '';
        foreach ($albumList as $album) {
            $albumPath = $realPath . '/' . $album;
            $audioFiles = glob($albumPath . '/*.{mp3,flac,wav,ogg,aac,m4a}', GLOB_BRACE);
            if (empty($audioFiles)) continue;
            
            $playerHtml = self::play($artist . '-' . $album);
            $albumsHtml .= '<div class="mag-album-item">' . $playerHtml . '</div>';
        }
        
        if (empty($albumsHtml)) {
            $albumsHtml = '<div style="color:#ccc;text-align:center;padding:40px;">该音乐人暂无专辑</div>';
        }
        
        $title = htmlspecialchars($artist) . ' 的专辑';
        
        // 使用背景图片方式，确保图片填满容器
        $html = '<div class="mag-singer-card" style="background-image: url(\'' . $avatarUrl . '\');">';
        $html .= '<div class="mag-singer-name">' . htmlspecialchars($artist) . '</div>';
        $html .= '<div class="mag-singer-overlay" onclick="document.getElementById(\'' . $overlayId . '\').style.display=\'flex\';"></div>';
        $html .= '</div>';
        $html .= '<div id="' . $overlayId . '" class="mag-overlay">';
        $html .= '<div class="mag-overlay-close" onclick="document.getElementById(\'' . $overlayId . '\').style.display=\'none\';">&times;</div>';
        $html .= '<div class="mag-overlay-content">';
        $html .= '<div class="mag-overlay-title">' . $title . '</div>';
        $html .= '<div class="mag-albums-grid">' . $albumsHtml . '</div>';
        $html .= '</div></div>';
        
        return $html;
    }

    public static function play($album)
    {
        self::$id++;
        $id = self::$id;
        $uploadFullPath = self::getUploadFullPath();
        $uploadUrl = self::getUploadUrl();
        
        $base = $uploadFullPath . '/' . $album;
        $url = $uploadUrl . '/' . $album . '/';
        $albumName = $album;
        
        $realBase = self::getRealPath($base);
        
        if (!is_dir($realBase) && strpos($album, '-') !== false) {
            $parts = explode('-', $album);
            if (count($parts) === 2) {
                $artist = $parts[0];
                $albumName = $parts[1];
                $base = $uploadFullPath . '/' . $artist . '/' . $albumName;
                $realBase = self::getRealPath($base);
                $url = $uploadUrl . '/' . $artist . '/' . $albumName . '/';
            }
        }
        
        if (!is_dir($realBase)) {
            $artistDirs = glob($uploadFullPath . '/*', GLOB_ONLYDIR);
            $found = false;
            foreach ($artistDirs as $artistDir) {
                $realArtistDir = self::getRealPath($artistDir);
                $albumPath = $realArtistDir . '/' . $album;
                if (is_dir($albumPath)) {
                    $artist = basename($artistDir);
                    $realBase = $albumPath;
                    $url = $uploadUrl . '/' . $artist . '/' . $album . '/';
                    $albumName = $album;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return '<div style="width:260px;background:rgba(0,0,0,'.self::BG_OPACITY.');border-radius:16px;padding:20px;text-align:center;color:#ccc;">专辑不存在: ' . htmlspecialchars($album) . '</div>';
            }
        }
        
        $o = self::BG_OPACITY;
        
        $cover = '';
        $c = glob($realBase . '/cover.{jpg,jpeg,png,gif}', GLOB_BRACE);
        if ($c) $cover = $url . basename($c[0]);

        $audioFiles = glob($realBase . '/*.{mp3,flac,wav,ogg,aac,m4a}', GLOB_BRACE);
        if (!$audioFiles) {
            return '<div style="width:260px;background:rgba(0,0,0,'.$o.');border-radius:16px;line-height:120px;text-align:center;color:#ccc;">无歌曲</div>';
        }

        $songs = [];
        foreach ($audioFiles as $f) {
            $n = pathinfo($f, PATHINFO_FILENAME);
            $songs[] = ['n' => $n, 'u' => $url . basename($f), 'l' => $url . $n . '.lrc'];
        }

        $songHtml = '';
        foreach ($songs as $i => $s) {
            $songHtml .= '<div onclick="p'.$id.'.play('.$i.')" class="mag-song-item"><span class="mag-song-num">'.($i+1).'</span><span class="mag-song-name">'.htmlspecialchars($s['n']).'</span></div>';
        }

        $coverHtml = $cover ? '<img src="'.$cover.'" class="mag-player-cover">' : '<div class="mag-player-cover mag-player-cover-empty"></div>';
        $j = json_encode($songs);

        $html = '<div class="mag-player" data-player-id="'.$id.'">'
        .'<div class="mag-player-cover-wrap">'
        .'<div class="mag-player-cover-container">'.$coverHtml.'</div>'
        .'<div class="mag-player-overlay">'
        .'<div class="mag-player-controls">'
        .'<div class="mag-player-btn" id="p'.$id.'"><span>▶</span></div>'
        .'<div class="mag-player-btn" onclick="p'.$id.'.r()"><span>⌘</span></div>'
        .'<div class="mag-player-btn" onclick="p'.$id.'.l()"><span>♫</span></div>'
        .'<div class="mag-player-btn" onclick="p'.$id.'.s()"><span>☰</span></div>'
        .'</div>'
        .'</div>'
        .'</div>'
        .'<div class="mag-player-info">'
        .'<div class="mag-player-status" id="t'.$id.'">' . htmlspecialchars($albumName) . '</div>'
        .'</div>'
        .'<div class="mag-song-list" id="b'.$id.'">'.$songHtml.'</div>'
        .'<div class="mag-lyrics" id="y'.$id.'"><div id="ly'.$id.'"></div></div>'
        .'<div class="mag-playlist-popup" id="pop'.$id.'">'
        .'<div class="mag-playlist-title">歌单</div>'
        .'<div id="pli'.$id.'">'.$songHtml.'</div>'
        .'<div class="mag-playlist-close" onclick="p'.$id.'.c()">关闭</div>'
        .'</div>'
        .'</div>'
        .'<style>
.mag-player {
    width: 260px;
    margin: 10px;
    background: rgba(0,0,0,0.8);
    border-radius: 16px;
    overflow: hidden;
    display: inline-block;
    vertical-align: top;
    position: relative;
}
.mag-player-cover-wrap {
    position: relative;
    width: 100%;
    height: 260px;
    overflow: hidden;
}
.mag-player-cover-container {
    width: 100%;
    height: 100%;
}
.mag-player-cover {
    width: 100%;
    height: 260px;
    object-fit: cover;
    display: block;
}
.mag-player-cover-empty {
    width: 100%;
    height: 260px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 64px;
    color: #ccc;
    background: #222;
}
.mag-player-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
    padding: 20px 0 12px 0;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.mag-player-cover-wrap:hover .mag-player-overlay {
    opacity: 1;
}
.mag-player-controls {
    display: flex;
    align-items: center;
    justify-content: space-around;
    padding: 0 15px;
}
.mag-player-btn {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    transition: all 0.2s;
}
.mag-player-btn:hover {
    background: rgba(255,255,255,0.4);
    transform: scale(1.05);
}
.mag-player-btn span {
    color: #fff;
    font-size: 18px;
    font-weight: bold;
}
.mag-player-info {
    padding: 12px 15px;
    text-align: center;
}
.mag-player-status {
    color: #fff;
    font-size: 14px;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.mag-song-list {
    display: none;
    max-height: 250px;
    overflow-y: auto;
    padding: 8px 12px;
    background: rgba(0,0,0,0.6);
}
.mag-song-item {
    height: 32px;
    line-height: 32px;
    padding: 0 12px;
    background: rgba(30,30,30,0.7);
    border-radius: 6px;
    margin-bottom: 6px;
    color: #ccc;
    font-size: 13px;
    display: flex;
    align-items: center;
    cursor: pointer;
    transition: background 0.2s;
}
.mag-song-item:hover {
    background: rgba(50,50,50,0.9);
}
.mag-song-num {
    width: 28px;
    color: #aaa;
}
.mag-song-name {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.mag-lyrics {
    display: none;
    padding: 12px;
    background: rgba(0,0,0,0.8);
    border-top: 1px solid #333;
    max-height: 160px;
    overflow-y: auto;
}
.mag-lyrics div {
    color: #aaa;
    font-size: 12px;
    line-height: 24px;
    text-align: center;
}
.mag-playlist-popup {
    display: none;
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.95);
    z-index: 1000;
    padding: 15px;
    overflow-y: auto;
}
.mag-playlist-title {
    color: #fff;
    font-size: 16px;
    font-weight: bold;
    margin-bottom: 15px;
    text-align: center;
}
.mag-playlist-close {
    margin-top: 20px;
    height: 40px;
    line-height: 40px;
    text-align: center;
    background: #333;
    color: #fff;
    border-radius: 20px;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.2s;
}
.mag-playlist-close:hover {
    background: #555;
}
</style>'
        .'<script>var p'.$id.'=(function(){var s='.$j.';var a=null;var i=0;var m=false;var sh=false;var lyricList=[];var lyricTimer=null;const showRow=5;function $(e){return document.getElementById(e)}function play(n){i=n;if(!a)a=new Audio();a.src=s[n].u;a.play();$("p'.$id.'").innerHTML="<span>⏸</span>";$("t'.$id.'").innerText=s[n].n;if(lyricTimer)clearInterval(lyricTimer);fetch(s[n].l).then(r=>r.text()).then(t=>{lyricList=[];t.replace(/\\[(\\d+):(\\d+)\\.(\\d+)\\](.+)/g,function(mm,h,mi,ms,txt){lyricList.push({time:~~h*60+~~mi+ms/1000,text:txt});});lyricTimer=setInterval(function(){if(!a||a.paused)return;var nowT=a.currentTime;var currIdx=-1;for(let k=0;k<lyricList.length;k++){if(lyricList[k].time<=nowT)currIdx=k;}if(currIdx===-1)return;
var startIdx=Math.max(0,currIdx-1);
var endIdx=startIdx+showRow;
if(endIdx>lyricList.length){endIdx=lyricList.length;startIdx=endIdx-showRow;if(startIdx<0)startIdx=0;}
var html="";for(let k=startIdx;k<endIdx;k++){var sty=currIdx===k?"color:#88aaff;font-weight:bold;":"";html+="<div style=\'"+sty+"\'>"+(lyricList[k].text||"")+"</div>";}
$("ly'.$id.'").innerHTML=html;},200);});a.onended=function(){play(m?Math.floor(Math.random()*s.length):(i+1)%s.length);};}
return{play:play,t:function(){if(!a){play(0);return;}if(a.paused){a.play();$("p'.$id.'").innerHTML="<span>⏸</span>";}else{a.pause();$("p'.$id.'").innerHTML="<span>▶</span>";}},r:function(){m=true;play(Math.floor(Math.random()*s.length));},l:function(){sh=!sh;$("b'.$id.'").style.display="none";$("y'.$id.'").style.display=sh?"block":"none";},s:function(){$("pli'.$id.'").innerHTML=$("b'.$id.'").innerHTML;$("pop'.$id.'").style.display="block";},c:function(){$("pop'.$id.'").style.display="none";}}})();
document.getElementById("p'.$id.'").onclick=function(){p'.$id.'.t();};</script>';

        return $html;
    }
}
?>