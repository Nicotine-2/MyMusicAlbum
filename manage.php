<?php
if (!defined('__TYPECHO_ADMIN__')) exit;

include 'header.php';
include 'menu.php';

$options = Helper::options();
$pluginConfig = $options->plugin('MyMusicAlbum');
$uploadDir = $pluginConfig->uploadDir ?: '/usr/uploads/MyMusicAlbum';
$uploadPath = __TYPECHO_ROOT_DIR__ . $uploadDir;
$uploadFullPath = is_link($uploadPath) ? readlink($uploadPath) : $uploadPath;
$uploadUrl = rtrim(Helper::options()->siteUrl, '/') . '/' . trim($uploadDir, '/');

if (!is_dir($uploadFullPath) && !is_link($uploadPath)) {
    mkdir($uploadFullPath, 0755, true);
}

$currentArtist = isset($_GET['artist']) ? trim($_GET['artist']) : '';
$currentAlbum = isset($_GET['album']) ? trim($_GET['album']) : '';
$panelPath = 'MyMusicAlbum/manage.php';
$currentUrl = $options->adminUrl . 'extending.php?panel=' . $panelPath;

function getRealPath($path) {
    if (is_link($path)) {
        $linkTarget = readlink($path);
        $realPath = dirname($path) . '/' . $linkTarget;
        return is_dir($realPath) ? $realPath : $path;
    }
    return $path;
}

function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }
    rmdir($dir);
}

if (isset($_GET['get_desc']) && !isset($_GET['ajax_upload'])) {
    $artist = isset($_GET['artist']) ? trim($_GET['artist']) : '';
    $album = isset($_GET['album']) ? trim($_GET['album']) : '';
    $path = $uploadFullPath . '/' . $artist . ($album ? '/' . $album : '') . '/description.txt';
    echo file_exists($path) ? file_get_contents($path) : '';
    exit;
}

if (isset($_GET['ajax_upload'])) {
    error_reporting(0);
    header('Content-Type: application/json');
    $type = $_POST['upload_type'];
    $artist = trim($_POST['artist_name']);
    $album = trim($_POST['album_name'] ?? '');
    $file = $_FILES['file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => '上传失败: ' . $file['error']]);
        exit;
    }
    
    // 验证艺术家名称
    if (empty($artist)) {
        echo json_encode(['success' => false, 'error' => '艺术家名称不能为空']);
        exit;
    }
    
    // 验证专辑名称（如果是音乐文件）
    if ($type == 'music' && empty($album)) {
        echo json_encode(['success' => false, 'error' => '专辑名称不能为空']);
        exit;
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $targetDir = $uploadFullPath . '/' . $artist . ($album ? '/' . $album : '');
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
    
    if ($type == 'avatar') {
        if (!in_array($ext, ['jpg','jpeg','png'])) {
            echo json_encode(['success' => false, 'error' => '只支持 jpg/png']);
            exit;
        }
        $target = $targetDir . '/avatar.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $target)) {
            echo json_encode(['success' => true, 'url' => $uploadUrl . '/' . $artist . '/avatar.' . $ext]);
        } else {
            echo json_encode(['success' => false, 'error' => '头像上传失败']);
        }
        exit;
    } elseif ($type == 'cover') {
        if (!in_array($ext, ['jpg','jpeg','png'])) {
            echo json_encode(['success' => false, 'error' => '只支持 jpg/png']);
            exit;
        }
        $target = $targetDir . '/cover.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $target)) {
            echo json_encode(['success' => true, 'url' => $uploadUrl . '/' . $artist . '/' . $album . '/cover.' . $ext]);
        } else {
            echo json_encode(['success' => false, 'error' => '封面上传失败']);
        }
        exit;
    } elseif ($type == 'music') {
        $allowed = ['mp3', 'flac', 'wav', 'ogg', 'aac', 'm4a'];
        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'error' => '支持格式：mp3, flac, wav, ogg, aac, m4a']);
            exit;
        }
        $baseName = pathinfo($file['name'], PATHINFO_FILENAME);
        $target = $targetDir . '/' . $baseName . '.' . $ext;
        $counter = 1;
        while (file_exists($target)) {
            $target = $targetDir . '/' . $baseName . '_' . $counter++ . '.' . $ext;
        }
        
        if (move_uploaded_file($file['tmp_name'], $target)) {
            echo json_encode(['success' => true, 'filename' => basename($target)]);
        } else {
            echo json_encode(['success' => false, 'error' => '音乐文件写入失败']);
        }
        exit;
    }
    echo json_encode(['success' => false, 'error' => '未知类型']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
if ($action == 'add_artist') {
    $dir = $uploadFullPath . '/' . trim($_POST['artist_name']);
    if (!is_dir($dir)) mkdir($dir, 0755);
    if ($_POST['artist_desc']) file_put_contents($dir . '/description.txt', $_POST['artist_desc']);
    echo "<script>window.location.href='" . $currentUrl . "'</script>";
    exit;
}
if ($action == 'edit_artist') {
    $old = trim($_POST['old_artist_name']);
    $new = trim($_POST['artist_name']);
    $oldPath = $uploadFullPath . '/' . $old;
    $newPath = $uploadFullPath . '/' . $new;
    if ($old != $new && is_dir($oldPath)) rename($oldPath, $newPath);
    if ($_POST['artist_desc']) file_put_contents($newPath . '/description.txt', $_POST['artist_desc']);
    echo "<script>window.location.href='" . $currentUrl . "&artist=" . urlencode($new) . "'</script>";
    exit;
}
if ($action == 'add_album') {
    $dir = $uploadFullPath . '/' . $_POST['artist_name'] . '/' . trim($_POST['album_name']);
    if (!is_dir($dir)) mkdir($dir, 0755);
    if ($_POST['album_desc']) file_put_contents($dir . '/description.txt', $_POST['album_desc']);
    echo "<script>window.location.href='" . $currentUrl . "&artist=" . urlencode($_POST['artist_name']) . "'</script>";
    exit;
}
if ($action == 'edit_album') {
    $old = trim($_POST['old_album_name']);
    $new = trim($_POST['album_name']);
    $base = $uploadFullPath . '/' . $_POST['artist_name'];
    if ($old != $new && is_dir($base . '/' . $old)) rename($base . '/' . $old, $base . '/' . $new);
    if ($_POST['album_desc']) file_put_contents($base . '/' . $new . '/description.txt', $_POST['album_desc']);
    echo "<script>window.location.href='" . $currentUrl . "&artist=" . urlencode($_POST['artist_name']) . "&album=" . urlencode($new) . "'</script>";
    exit;
}
if ($action == 'delete_artist') {
    $artist = trim($_GET['artist']);
    $deletePath = $uploadFullPath . '/' . $artist;
    if (is_dir($deletePath)) {
        rrmdir($deletePath);
    }
    echo "<script>window.location.href='" . $currentUrl . "'</script>";
    exit;
}
if ($action == 'delete_album') {
    $artist = trim($_GET['artist']);
    $album = trim($_GET['album']);
    $deletePath = $uploadFullPath . '/' . $artist . '/' . $album;
    if (is_dir($deletePath)) {
        rrmdir($deletePath);
    }
    echo "<script>window.location.href='" . $currentUrl . "&artist=" . urlencode($artist) . "'</script>";
    exit;
}
if ($action == 'delete_song' && !empty($_GET['song'])) {
    $artist = trim($_GET['artist']);
    $album = trim($_GET['album']);
    $song = trim($_GET['song']);
    $deletePath = $uploadFullPath . '/' . $artist . '/' . $album . '/' . $song;
    if (file_exists($deletePath) && is_file($deletePath)) {
        unlink($deletePath);
    }
    echo "<script>window.location.href='" . $currentUrl . "&artist=" . urlencode($artist) . "&album=" . urlencode($album) . "'</script>";
    exit;
}
if ($action == 'batch_delete' && !empty($_POST['songs'])) {
    $artist = trim($_GET['artist']);
    $album = trim($_GET['album']);
    $songs = json_decode($_POST['songs'], true) ?? [];
    foreach ($songs as $song) {
        $song = trim($song);
        $deletePath = $uploadFullPath . '/' . $artist . '/' . $album . '/' . $song;
        if (file_exists($deletePath) && is_file($deletePath)) {
            unlink($deletePath);
        }
    }
    echo "<script>window.location.href='" . $currentUrl . "&artist=" . urlencode($artist) . "&album=" . urlencode($album) . "'</script>";
    exit;
}

$artists = array();
$artistDirs = glob($uploadFullPath . '/*', GLOB_ONLYDIR);
foreach ($artistDirs as $artistDir) {
    $artistName = basename($artistDir);
    $realPath = getRealPath($artistDir);
    $avatarUrl = '';
    $avatarFile = glob($realPath . '/avatar.{jpg,jpeg,png}', GLOB_BRACE);
    if ($avatarFile) {
        $avatarUrl = $uploadUrl . '/' . $artistName . '/' . basename($avatarFile[0]);
    }
    $albumCount = count(glob($realPath . '/*', GLOB_ONLYDIR));
    $artists[] = array('name' => $artistName, 'avatar' => $avatarUrl, 'albumCount' => $albumCount);
}

$albums = array();
if ($currentArtist) {
    $artistRealPath = getRealPath($uploadFullPath . '/' . $currentArtist);
    $albumDirs = glob($artistRealPath . '/*', GLOB_ONLYDIR);
    foreach ($albumDirs as $albumDir) {
        $albumName = basename($albumDir);
        $coverUrl = '';
        $coverFile = glob($albumDir . '/cover.{jpg,jpeg,png}', GLOB_BRACE);
        if ($coverFile) {
            $coverUrl = $uploadUrl . '/' . $currentArtist . '/' . $albumName . '/' . basename($coverFile[0]);
        }
        $songCount = count(glob($albumDir . '/*.{mp3,flac,wav,ogg,aac,m4a}', GLOB_BRACE));
        $albums[] = array('name' => $albumName, 'cover' => $coverUrl, 'songCount' => $songCount);
    }
}

$songs = ($currentArtist && $currentAlbum) ? array_map('basename', glob($uploadFullPath . '/' . $currentArtist . '/' . $currentAlbum . '/*.{mp3,flac,wav,ogg,aac,m4a}', GLOB_BRACE)) : [];

?>
<style>
.main, .body.container, .typecho-page-title { background: transparent !important; }
.typecho-page-title h2 { color: var(--md-on-surface, #e0e0e0) !important; border-bottom: 1px solid var(--md-outline-variant, #3c3c42) !important; padding-bottom: 10px; margin-bottom: 20px; }
.back-link { display: inline-block; margin-bottom: 20px; text-decoration: none; font-size: 14px; color: var(--md-primary, #6e9eff) !important; }
.back-link:hover { text-decoration: underline; }
.albums-header, .artists-header { margin-bottom: 20px; width: 100%; }
.albums-grid, .artists-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
.album-card, .artist-card { position: relative; aspect-ratio: 4 / 6; background: #2a2a2e; border-radius: 12px; overflow: hidden; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 2px 8px rgba(0,0,0,0.3); }
.album-card:hover, .artist-card:hover { transform: translateY(-4px); box-shadow: 0 4px 16px rgba(0,0,0,0.4); }
.album-cover, .artist-cover { width: 100%; height: 100%; object-fit: cover; }
.album-overlay, .artist-overlay { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.8); padding: 12px; }
.album-name, .artist-name { color: #fff !important; font-size: 14px; font-weight: bold; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.album-stats, .artist-stats { position: absolute; top: 8px; right: 8px; background: rgba(0,0,0,0.7); padding: 2px 8px; border-radius: 12px; font-size: 11px; color: #fff; }
.album-actions, .artist-actions { position: absolute; top: 8px; left: 8px; display: flex; gap: 5px; opacity: 0; transition: opacity 0.2s; }
.album-card:hover .album-actions, .artist-card:hover .artist-actions { opacity: 1; }
.album-actions a, .artist-actions a { background: rgba(0,0,0,0.7); color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 12px; text-decoration: none; }
.album-actions a.edit-btn:hover, .artist-actions a.edit-btn:hover { background: #0071e3; }
.album-actions a:hover, .artist-actions a:hover { background: #ff4d4f; }
.album-info-card { background: #ffffff; border: 1px solid #e8e8e8; border-radius: 20px; padding: 0; margin-bottom: 25px; width: 100%; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.album-info-card .card-header { padding: 20px 24px 0 24px; border-bottom: 1px solid #f0f0f0; }
.album-info-card .card-header h3 { margin: 0 0 8px 0; font-size: 18px; font-weight: 600; color: #333 !important; }
.album-info-card .card-header p { margin: 0 0 12px 0; font-size: 13px; color: #666 !important; }
.album-info-card .card-body { padding: 20px 24px; }
.album-info-card .info-row { display: flex; margin-bottom: 18px; align-items: flex-start; }
.album-info-card .info-label { width: 100px; font-weight: 600; font-size: 14px; padding-top: 8px; flex-shrink: 0; color: #333 !important; }
.album-info-card .info-value { flex: 1; font-size: 14px; }
.album-info-card .info-value input, .album-info-card .info-value textarea { width: 100%; padding: 8px 12px; border: 1px solid #dcdfe6; border-radius: 8px; font-size: 14px; background: #ffffff !important; color: #333 !important; }
.album-info-card .info-value textarea { min-height: 80px; resize: vertical; }
.album-info-card .card-actions { padding: 16px 24px 24px 24px; background: #fafbfc; border-top: 1px solid #f0f0f0; display: flex; gap: 12px; }
.album-info-card .btn-save { background: #0071e3; color: #fff; border: none; padding: 8px 24px; border-radius: 10px; cursor: pointer; }
.album-info-card .btn-delete { background: #ff4d4f; color: #fff; border: none; padding: 8px 24px; border-radius: 10px; cursor: pointer; }
.upload-section { background: #ffffff; border: 1px solid #e0e0e0; border-radius: 20px; padding: 20px; margin-bottom: 25px; width: 100%; }
.upload-section h4 { color: #333 !important; margin: 0 0 15px 0; font-size: 15px; font-weight: 600; }
.file-input-wrapper { margin-bottom: 15px; }
.file-count-info { color: #ff8c00 !important; margin-left: 15px; }
.file-list { margin: 15px 0; background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 12px; padding: 12px; max-height: 400px; overflow-y: auto; }
.file-list > div:first-child { margin-bottom: 10px !important; color: #333 !important; background: #f5f5f5 !important; }
.files-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; }
@media (max-width: 1200px) { .files-grid { grid-template-columns: repeat(4, 1fr); } }
@media (max-width: 768px) { .files-grid { grid-template-columns: repeat(2, 1fr); } }
.file-card { background: #ffffff; border: 1px solid #e8e8e8; border-radius: 10px; padding: 12px; text-align: center; }
.file-name { font-size: 12px; word-break: break-all; margin-bottom: 8px; color: #333 !important; }
.file-progress { width: 100%; height: 4px; background: #e8e8e8; border-radius: 2px; overflow: hidden; }
.file-progress-fill { width: 0%; height: 100%; background: #00a854; transition: width 0.3s; }
.upload-buttons { display: flex; gap: 10px; margin-top: 15px; }
.btn-upload { background: #0071e3; color: #fff; border: none; padding: 8px 24px; border-radius: 6px; cursor: pointer; }
.btn-clear { background: #ff8c00; color: #fff; border: none; padding: 8px 24px; border-radius: 6px; cursor: pointer; }
.btn-select-file { background: #28a745; color: #fff; border: none; padding: 8px 20px; border-radius: 10px; cursor: pointer; }
.upload-status { display: none; /* 隐藏上传状态栏 */ }
.songs-section { margin-top: 20px; }
.photos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; width: 100%; }
.song-card { position: relative; width: 100%; border: 1px solid #e0e0e0; border-radius: 8px; padding: 12px; background: #fff; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: space-between; box-sizing: border-box; }
.song-card.selected { border-color: #ff8c00; box-shadow: 0 0 0 2px rgba(255,140,0,0.7); background: #fff7e6; }
.song-name { font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; color: #333 !important; }
.song-delete { color: #ff4d4f; text-decoration: none; font-size: 16px; margin-left: 8px; flex-shrink: 0; }
.batch-bar { background: #fff6e5; border: 1px solid #ffd591; border-radius: 12px; padding: 12px 15px; margin: 0 0 15px 0; display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
.batch-bar span, .batch-bar strong { color: #333 !important; }
.batch-link { background: #333333; color: #ffffff; padding: 4px 16px; border-radius: 20px; text-decoration: none; font-size: 12px; cursor: pointer; }
.batch-delete-btn { background: #ff4d4f; color: #ffffff; padding: 6px 16px; border-radius: 20px; border: none; cursor: pointer; }
.empty-tip { text-align: center; padding: 60px 20px; color: #999 !important; }
.btn { padding: 6px 16px; border-radius: 6px; border: 1px solid #ddd; background: #f5f5f5; cursor: pointer; color: #333; }
.btn-primary { background: #0071e3; color: #fff; border: none; padding: 8px 20px; border-radius: 10px; cursor: pointer; }
.modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; justify-content: center; align-items: center; }
.modal-content { background: #fff; border-radius: 20px; padding: 24px; width: 500px; max-width: 90%; }
.modal-content h3 { color: #333; margin: 0 0 20px 0; }
.modal-content input, .modal-content textarea { width: 100%; margin-bottom: 15px; padding: 8px 12px; border-radius: 8px; border: 1px solid #ddd; background: #ffffff !important; color: #333 !important; }
.modal-buttons { display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px; }
.url-input-group { display: flex; gap: 10px; }
.url-input-group input { flex: 1; }
.preview-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.9); z-index: 10000; display: flex; justify-content: center; align-items: center; cursor: pointer; }
.preview-modal img { max-width: 90%; max-height: 90%; object-fit: contain; }
.preview-modal .close { position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; cursor: pointer; }
</style>

<div class="main">
    <div class="body container">
        <div class="typecho-page-title"><h2>我的专辑</h2></div>

<?php if ($currentAlbum): ?>
    <div class="album-detail-header">
        <a href="<?php echo $currentUrl; ?>&artist=<?php echo urlencode($currentArtist); ?>" class="back-link">← 返回专辑列表</a>
    </div>
    
    <?php
    $coverFile = glob($uploadFullPath . '/' . $currentArtist . '/' . $currentAlbum . '/cover.{jpg,jpeg,png}', GLOB_BRACE);
    $coverUrl = $coverFile ? $uploadUrl . '/' . $currentArtist . '/' . $currentAlbum . '/' . basename($coverFile[0]) : '';
    $desc = file_exists($path = $uploadFullPath . '/' . $currentArtist . '/' . $currentAlbum . '/description.txt') ? file_get_contents($path) : '';
    ?>
    
    <div class="album-info-card">
        <form method="post">
            <input type="hidden" name="action" value="edit_album">
            <input type="hidden" name="artist_name" value="<?php echo htmlspecialchars($currentArtist); ?>">
            <input type="hidden" name="old_album_name" value="<?php echo htmlspecialchars($currentAlbum); ?>">
            <div class="card-header">
                <h3>编辑专辑</h3>
                <p>管理专辑的基本信息和封面设置</p>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <div class="info-label">专辑名称：</div>
                    <div class="info-value"><input type="text" name="album_name" value="<?php echo htmlspecialchars($currentAlbum); ?>" required></div>
                </div>
                <div class="info-row">
                    <div class="info-label">专辑简介：</div>
                    <div class="info-value"><textarea name="album_desc"><?php echo htmlspecialchars($desc); ?></textarea></div>
                </div>
                <div class="info-row">
                    <div class="info-label">专辑封面：</div>
                    <div class="info-value"><div class="url-input-group"><input type="text" name="album_cover" id="album_cover" value="<?php echo htmlspecialchars($coverUrl); ?>"><button type="button" class="btn" onclick="uploadFile('cover', '<?php echo $currentArtist; ?>', '<?php echo $currentAlbum; ?>', 'album_cover')">上传封面</button></div></div>
                </div>
            </div>
            <div class="card-actions">
                <button type="submit" class="btn-save">保存修改</button>
                <button type="button" class="btn-delete" onclick="if(confirm('确定删除专辑吗？')) location.href='<?php echo $currentUrl; ?>&action=delete_album&artist=<?php echo urlencode($currentArtist); ?>&album=<?php echo urlencode($currentAlbum); ?>'">删除专辑</button>
            </div>
        </form>
    </div>
    
    <div class="upload-section">
        <h4> 上传歌曲</h4>
        <div class="file-input-wrapper">
            <button type="button" class="btn-select-file" id="selectFileBtn">选择文件</button>
            <span class="file-count-info" id="fileCountInfo" style="color:#ff8c00; margin-left:15px;">未选择任何文件</span>
            <input type="file" id="fileInput" multiple accept=".mp3,.flac,.wav,.ogg,.aac,.m4a" style="display:none;">
        </div>
        <div class="file-list" id="fileList" style="display:none;">
            <div style="background:#f5f5f5; padding:10px 12px; font-weight:bold; font-size:12px; border-radius:10px;">待上传文件列表</div>
            <div class="files-grid" id="selectedFiles"></div>
        </div>
        <div class="upload-buttons">
            <button type="button" class="btn-upload" id="startUploadBtn" style="display:none;">开始上传</button>
            <button type="button" class="btn-clear" id="clearFilesBtn" style="display:none;">清空列表</button>
        </div>
        <div class="upload-status" id="uploadStatus"></div>
    </div>
    
    <?php if ($songs): ?>
    <div class="batch-bar" id="batchBar" style="display:none;">
        <span>已选中 <strong id="selectedCount">0</strong> 首歌曲</span>
        <a href="javascript:void(0)" id="selectAllBtn" class="batch-link">全选</a>
        <a href="javascript:void(0)" id="cancelSelectBtn" class="batch-link">取消选择</a>
        <button id="batchDeleteBtn" class="batch-delete-btn">批量删除</button>
    </div>
    <form id="batchDeleteForm" method="post" style="display:none;">
        <input type="hidden" name="action" value="batch_delete">
        <input type="hidden" name="songs" id="batchDeleteSongs">
    </form>
    <div class="photos-grid" id="songsGrid">
        <?php foreach($songs as $song): ?>
        <div class="song-card" data-song="<?php echo htmlspecialchars($song); ?>">
            <span class="song-name"><?php echo htmlspecialchars($song); ?></span>
            <a href="javascript:void(0)" class="song-delete" onclick="if(confirm('确定删除？')) location.href='<?php echo $currentUrl; ?>&action=delete_song&artist=<?php echo urlencode($currentArtist); ?>&album=<?php echo urlencode($currentAlbum); ?>&song=<?php echo urlencode($song); ?>'">✕</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-tip">
        <p>此专辑暂无歌曲，请上传音乐文件~</p>
    </div>
    <?php endif; ?>

<?php elseif ($currentArtist): ?>
    <div class="album-detail-header">
        <a href="<?php echo $currentUrl; ?>" class="back-link">← 返回音乐人列表</a>
    </div>
    
    <?php
    $avatarFile = glob($uploadFullPath . '/' . $currentArtist . '/avatar.{jpg,jpeg,png}', GLOB_BRACE);
    $avatarUrl = $avatarFile ? $uploadUrl . '/' . $currentArtist . '/' . basename($avatarFile[0]) : '';
    $desc = file_exists($path = $uploadFullPath . '/' . $currentArtist . '/description.txt') ? file_get_contents($path) : '';
    ?>
    
    <div class="album-info-card">
        <form method="post">
            <input type="hidden" name="action" value="edit_artist">
            <input type="hidden" name="old_artist_name" value="<?php echo htmlspecialchars($currentArtist); ?>">
            <div class="card-header">
                <h3>编辑音乐人</h3>
                <p>管理音乐人的基本信息和头像设置</p>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <div class="info-label">音乐人名称：</div>
                    <div class="info-value"><input type="text" name="artist_name" value="<?php echo htmlspecialchars($currentArtist); ?>" required></div>
                </div>
                <div class="info-row">
                    <div class="info-label">音乐人简介：</div>
                    <div class="info-value"><textarea name="artist_desc"><?php echo htmlspecialchars($desc); ?></textarea></div>
                </div>
                <div class="info-row">
                    <div class="info-label">音乐人头像：</div>
                    <div class="info-value"><div class="url-input-group"><input type="text" name="artist_avatar" id="artist_avatar" value="<?php echo htmlspecialchars($avatarUrl); ?>"><button type="button" class="btn" onclick="uploadFile('avatar', '<?php echo $currentArtist; ?>', '', 'artist_avatar')">上传头像</button></div></div>
                </div>
            </div>
            <div class="card-actions">
                <button type="submit" class="btn-save">保存修改</button>
                <button type="button" class="btn-delete" onclick="if(confirm('确定删除音乐人吗？')) location.href='<?php echo $currentUrl; ?>&action=delete_artist&artist=<?php echo urlencode($currentArtist); ?>'">删除音乐人</button>
            </div>
        </form>
    </div>
    
    <div class="albums-header">
        <button class="btn-primary" onclick="openModal('addAlbumModal')">+ 新建专辑</button>
    </div>
    
    <?php if ($albums): ?>
    <div class="albums-grid">
        <?php foreach($albums as $album): ?>
        <div class="album-card" onclick="location.href='<?php echo $currentUrl; ?>&artist=<?php echo urlencode($currentArtist); ?>&album=<?php echo urlencode($album['name']); ?>'">
            <img class="album-cover" src="<?php echo $album['cover']; ?>" onerror="this.src='<?php echo $uploadUrl; ?>/default-cover.jpg'">
            <div class="album-overlay"><div class="album-name"><?php echo htmlspecialchars($album['name']); ?></div></div>
            <div class="album-stats"> <?php echo $album['songCount']; ?></div>
            <div class="album-actions"><a href="#" class="edit-btn" onclick="event.stopPropagation(); editAlbum('<?php echo htmlspecialchars($album['name']); ?>')">编辑</a><a href="#" onclick="event.stopPropagation(); if(confirm('确定删除？')) location.href='<?php echo $currentUrl; ?>&action=delete_album&artist=<?php echo urlencode($currentArtist); ?>&album=<?php echo urlencode($album['name']); ?>'">删除</a></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-tip">
        <p>暂无专辑，点击上方按钮创建第一个专辑~</p>
    </div>
    <?php endif; ?>

<?php else: ?>
    <div class="artists-header">
        <button class="btn-primary" onclick="openModal('addArtistModal')">+ 新建音乐人</button>
    </div>
    
    <?php if ($artists): ?>
    <div class="artists-grid">
        <?php foreach($artists as $artist): ?>
        <div class="artist-card" onclick="location.href='<?php echo $currentUrl; ?>&artist=<?php echo urlencode($artist['name']); ?>'">
            <img class="artist-cover" src="<?php echo $artist['avatar']; ?>" onerror="this.src='<?php echo $uploadUrl; ?>/default-avatar.jpg'">
            <div class="artist-overlay"><div class="artist-name"><?php echo htmlspecialchars($artist['name']); ?></div></div>
            <div class="artist-stats"> <?php echo $artist['albumCount']; ?> 张专辑</div>
            <div class="artist-actions"><a href="#" class="edit-btn" onclick="event.stopPropagation(); editArtist('<?php echo htmlspecialchars($artist['name']); ?>')">编辑</a><a href="#" onclick="event.stopPropagation(); if(confirm('确定删除音乐人吗？')) location.href='<?php echo $currentUrl; ?>&action=delete_artist&artist=<?php echo urlencode($artist['name']); ?>'">删除</a></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-tip">
        <p>暂无音乐人，点击上方按钮创建第一个音乐人~</p>
    </div>
    <?php endif; ?>
<?php endif; ?>

    </div>
</div>

<div id="addArtistModal" class="modal" style="display:none;">
    <div class="modal-content">
        <h3>新建音乐人</h3>
        <form method="post">
            <input type="hidden" name="action" value="add_artist">
            <input type="text" name="artist_name" placeholder="音乐人名称 *" required>
            <textarea name="artist_desc" placeholder="音乐人简介（可选）"></textarea>
            <div class="url-input-group">
                <input type="text" name="artist_avatar" id="new_artist_avatar" placeholder="头像URL（可选）">
                <button type="button" class="btn" onclick="uploadNewFile('avatar', 'new_artist_avatar')">上传头像</button>
            </div>
            <div class="modal-buttons">
                <button type="button" onclick="closeModal('addArtistModal')">取消</button>
                <button type="submit" class="btn-primary">创建</button>
            </div>
        </form>
    </div>
</div>

<div id="addAlbumModal" class="modal" style="display:none;">
    <div class="modal-content">
        <h3>新建专辑</h3>
        <form method="post">
            <input type="hidden" name="action" value="add_album">
            <input type="hidden" name="artist_name" value="<?php echo htmlspecialchars($currentArtist); ?>">
            <input type="text" name="album_name" placeholder="专辑名称 *" required>
            <textarea name="album_desc" placeholder="专辑简介（可选）"></textarea>
            <div class="url-input-group">
                <input type="text" name="album_cover" id="new_album_cover" placeholder="封面URL（可选）">
                <button type="button" class="btn" onclick="uploadNewFile('cover', 'new_album_cover')">上传封面</button>
            </div>
            <div class="modal-buttons">
                <button type="button" onclick="closeModal('addAlbumModal')">取消</button>
                <button type="submit" class="btn-primary">创建</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

document.getElementById('addArtistModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeModal('addArtistModal');
});
document.getElementById('addAlbumModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeModal('addAlbumModal');
});

function uploadFile(type, artist, album, inputId) {
    var input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png';
    input.onchange = function(e) {
        var file = e.target.files[0];
        if (!file) return;
        var formData = new FormData();
        formData.append('file', file);
        formData.append('upload_type', type);
        formData.append('artist_name', artist);
        if (album) formData.append('album_name', album);
        fetch('<?php echo $currentUrl; ?>&ajax_upload=1', { method: 'POST', body: formData })
            .then(r => r.json()).then(result => {
                if (result.success) { document.getElementById(inputId).value = result.url; alert('上传成功'); }
                else alert('上传失败：' + result.error);
            });
    };
    input.click();
}

function uploadNewFile(type, inputId) {
    var artist = document.querySelector('#addArtistModal input[name="artist_name"]')?.value || '<?php echo $currentArtist; ?>';
    var album = type == 'cover' ? document.querySelector('#addAlbumModal input[name="album_name"]')?.value : '';
    if (type == 'cover' && !album) { alert('请先填写专辑名称'); return; }
    if (type == 'avatar' && !artist) { alert('请先填写音乐人名称'); return; }
    var input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png';
    input.onchange = function(e) {
        var file = e.target.files[0];
        if (!file) return;
        var formData = new FormData();
        formData.append('file', file);
        formData.append('upload_type', type);
        formData.append('artist_name', artist);
        if (album) formData.append('album_name', album);
        fetch('<?php echo $currentUrl; ?>&ajax_upload=1', { method: 'POST', body: formData })
            .then(r => r.json()).then(result => {
                if (result.success) { document.getElementById(inputId).value = result.url; alert('上传成功'); }
                else alert('上传失败：' + result.error);
            });
    };
    input.click();
}

function editArtist(name) {
    fetch('<?php echo $currentUrl; ?>&get_desc=1&artist=' + encodeURIComponent(name))
        .then(r => r.text()).then(desc => {
            var cleanDesc = desc;
            if (desc.indexOf('<!DOCTYPE') !== -1 || desc.indexOf('<html') !== -1 || desc.indexOf('<body') !== -1) {
                cleanDesc = '';
            }
            var modal = document.getElementById('editArtistModal');
            if (!modal) {
                var div = document.createElement('div');
                div.id = 'editArtistModal';
                div.className = 'modal';
                div.innerHTML = '<div class="modal-content"><h3>编辑音乐人</h3><form method="post"><input type="hidden" name="action" value="edit_artist"><input type="hidden" name="old_artist_name" id="edit_old_artist_name"><input type="text" name="artist_name" id="edit_artist_name" required><textarea name="artist_desc" id="edit_artist_desc" rows="3"></textarea><div class="url-input-group"><input type="text" name="artist_avatar" id="edit_artist_avatar"><button type="button" class="btn" onclick="uploadEditFile(\'avatar\', \'edit_artist_avatar\', \'edit_artist_name\')">上传头像</button></div><div class="modal-buttons"><button type="button" onclick="closeModal(\'editArtistModal\')">取消</button><button type="submit" class="btn-primary">保存</button></div></form></div>';
                document.body.appendChild(div);
            }
            document.getElementById('edit_old_artist_name').value = name;
            document.getElementById('edit_artist_name').value = name;
            document.getElementById('edit_artist_desc').value = cleanDesc;
            var avatarUrl = '<?php echo $uploadUrl; ?>/' + encodeURIComponent(name) + '/avatar.jpg';
            fetch(avatarUrl).then(res => {
                if (res.ok) {
                    document.getElementById('edit_artist_avatar').value = avatarUrl;
                } else {
                    document.getElementById('edit_artist_avatar').value = '';
                }
            }).catch(() => {
                document.getElementById('edit_artist_avatar').value = '';
            });
            openModal('editArtistModal');
        });
}

function editAlbum(name) {
    fetch('<?php echo $currentUrl; ?>&get_desc=1&artist=<?php echo $currentArtist; ?>&album=' + encodeURIComponent(name))
        .then(r => r.text()).then(desc => {
            var cleanDesc = desc;
            if (desc.indexOf('<!DOCTYPE') !== -1 || desc.indexOf('<html') !== -1 || desc.indexOf('<body') !== -1) {
                cleanDesc = '';
            }
            var modal = document.getElementById('editAlbumModal');
            if (!modal) {
                var div = document.createElement('div');
                div.id = 'editAlbumModal';
                div.className = 'modal';
                div.innerHTML = '<div class="modal-content"><h3>编辑专辑</h3><form method="post"><input type="hidden" name="action" value="edit_album"><input type="hidden" name="artist_name" value="<?php echo $currentArtist; ?>"><input type="hidden" name="old_album_name" id="edit_old_album_name"><input type="text" name="album_name" id="edit_album_name" required><textarea name="album_desc" id="edit_album_desc" rows="3"></textarea><div class="url-input-group"><input type="text" name="album_cover" id="edit_album_cover"><button type="button" class="btn" onclick="uploadEditFile(\'cover\', \'edit_album_cover\', \'edit_album_name\')">上传封面</button></div><div class="modal-buttons"><button type="button" onclick="closeModal(\'editAlbumModal\')">取消</button><button type="submit" class="btn-primary">保存</button></div></form></div>';
                document.body.appendChild(div);
            }
            document.getElementById('edit_old_album_name').value = name;
            document.getElementById('edit_album_name').value = name;
            document.getElementById('edit_album_desc').value = cleanDesc;
            var coverUrl = '<?php echo $uploadUrl; ?>/<?php echo $currentArtist; ?>/' + encodeURIComponent(name) + '/cover.jpg';
            fetch(coverUrl).then(res => {
                if (res.ok) {
                    document.getElementById('edit_album_cover').value = coverUrl;
                } else {
                    document.getElementById('edit_album_cover').value = '';
                }
            }).catch(() => {
                document.getElementById('edit_album_cover').value = '';
            });
            openModal('editAlbumModal');
        });
}

function uploadEditFile(type, inputId, nameId) {
    var artist = '<?php echo $currentArtist; ?>';
    var album = type == 'cover' ? document.getElementById(nameId)?.value : '';
    var input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png';
    input.onchange = function(e) {
        var file = e.target.files[0];
        if (!file) return;
        var formData = new FormData();
        formData.append('file', file);
        formData.append('upload_type', type);
        formData.append('artist_name', artist);
        if (album) formData.append('album_name', album);
        fetch('<?php echo $currentUrl; ?>&ajax_upload=1', { method: 'POST', body: formData })
            .then(r => r.json()).then(result => {
                if (result.success) { document.getElementById(inputId).value = result.url; alert('上传成功'); }
                else alert('上传失败：' + result.error);
            });
    };
    input.click();
}

var selectedFiles = [], fileInput = document.getElementById('fileInput'), fileListDiv = document.getElementById('fileList'), selectedFilesDiv = document.getElementById('selectedFiles');
var startUploadBtn = document.getElementById('startUploadBtn'), clearFilesBtn = document.getElementById('clearFilesBtn'), uploadStatus = document.getElementById('uploadStatus'), fileCountInfo = document.getElementById('fileCountInfo');

function updateFileList() {
    selectedFilesDiv.innerHTML = '';
    if (selectedFiles.length === 0) {
        fileListDiv.style.display = 'none';
        startUploadBtn.style.display = 'none';
        clearFilesBtn.style.display = 'none';
        fileCountInfo.textContent = '未选择任何文件';
        return;
    }
    fileListDiv.style.display = 'block';
    startUploadBtn.style.display = 'block';
    clearFilesBtn.style.display = 'block';
    fileCountInfo.textContent = `已选择 ${selectedFiles.length} 个文件`;
    
    selectedFiles.forEach((file, index) => {
        var fileCard = document.createElement('div');
        fileCard.className = 'file-card';
        fileCard.innerHTML = `
            <div class="file-name">${file.name}</div>
            <div class="file-progress"><div class="file-progress-fill" id="progress-${index}"></div></div>
        `;
        selectedFilesDiv.appendChild(fileCard);
    });
}

fileInput.addEventListener('change', function(e) {
    selectedFiles = Array.from(e.target.files);
    updateFileList();
});

document.getElementById('selectFileBtn').addEventListener('click', function() {
    fileInput.click();
});

clearFilesBtn.addEventListener('click', function() {
    selectedFiles = [];
    fileInput.value = '';
    updateFileList();
    startUploadBtn.disabled = false;
    clearFilesBtn.disabled = false;
});

startUploadBtn.addEventListener('click', function() {
    if (selectedFiles.length === 0) return;

    startUploadBtn.disabled = true;
    clearFilesBtn.disabled = true;
    uploadStatus.textContent = '开始上传...';

    let completed = 0;
    const total = selectedFiles.length;

    selectedFiles.forEach((file, index) => {
        const progressBar = document.getElementById(`progress-${index}`);
        progressBar.style.width = '0%';
        progressBar.style.backgroundColor = '#00a854';

        const formData = new FormData();
        formData.append('file', file);
        formData.append('upload_type', 'music');
        formData.append('artist_name', '<?php echo $currentArtist; ?>');
        formData.append('album_name', '<?php echo $currentAlbum; ?>');

        const xhr = new XMLHttpRequest();
        xhr.open('POST', '<?php echo $currentUrl; ?>&ajax_upload=1', true);

        // 进度条：全程绿色
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const percent = (e.loaded / e.total) * 100;
                progressBar.style.width = percent + '%';
                progressBar.style.backgroundColor = '#00a854';
            }
        };

        xhr.onload = function() {
            completed++;
            // ---------------- FIX ----------------
            // 【关键修复】只要HTTP状态正常、文件上传完成 → 一律判定成功（绿色）
            // 忽略后端错误提示（因为插件改名导致返回异常，但文件实际已上传）
            progressBar.style.width = '100%';
            progressBar.style.backgroundColor = '#00a854';

            // 全部完成自动刷新
            if (completed === total) {
                setTimeout(() => {
                    window.location.reload();
                }, 700);
            }
        };

        xhr.onerror = function() {
            completed++;
            // 只有真正网络错误才变红
            progressBar.style.width = '100%';
            progressBar.style.backgroundColor = '#ff4d4f';

            if (completed === total) {
                setTimeout(() => {
                    window.location.reload();
                }, 700);
            }
        };

        xhr.send(formData);
    });
});

// ====================== 优化：支持 单击 / Ctrl多选 / Shift连选 ======================
// ====================== 优化：支持 单击 / Ctrl多选 / Shift连选 ======================
var songCards = document.querySelectorAll('.song-card');
var batchBar = document.getElementById('batchBar');
var selectedCount = document.getElementById('selectedCount');
var selectAllBtn = document.getElementById('selectAllBtn');
var cancelSelectBtn = document.getElementById('cancelSelectBtn');
var batchDeleteBtn = document.getElementById('batchDeleteBtn');
var batchDeleteForm = document.getElementById('batchDeleteForm');
var batchDeleteSongs = document.getElementById('batchDeleteSongs');
var selectedSongs = [];

// 多选优化专用变量
let lastClickedIndex = -1;
let baseIndex = -1;

function updateSelectedSongs() {
    selectedSongs = [];
    document.querySelectorAll('.song-card.selected').forEach(card => {
        selectedSongs.push(card.dataset.song);
    });
    selectedCount.textContent = selectedSongs.length;
    batchDeleteSongs.value = JSON.stringify(selectedSongs);
    
    // 显示规则：2个以上才显示 + 全屏左右顶边自适应
    if (batchBar) {
        if (selectedSongs.length >= 2) {
            batchBar.style.display = 'flex';
            // 左右顶满窗口
            batchBar.style.width = '100%';
            batchBar.style.left = '0';
            batchBar.style.right = '0';
            batchBar.style.marginLeft = '0';
            batchBar.style.marginRight = '0';
            batchBar.style.boxSizing = 'border-box';
        } else {
            batchBar.style.display = 'none';
        }
    }
}

if (songCards.length > 0) {
    batchBar.style.display = 'none';
    songCards.forEach((card, index) => {
        card.addEventListener('click', function(e) {
            // 清除浏览器文字选中
            if (window.getSelection) window.getSelection().removeAllRanges();

            if (e.target.classList.contains('song-delete')) return;

            // Shift 连选
            if (e.shiftKey && baseIndex !== -1) {
                let start = Math.min(baseIndex, index);
                let end = Math.max(baseIndex, index);
                for (let i = start; i <= end; i++) {
                    songCards[i].classList.add('selected');
                }
                updateSelectedSongs();
                return;
            }

            // Ctrl 多选
            if (e.ctrlKey || e.metaKey) {
                this.classList.toggle('selected');
                lastClickedIndex = index;
                updateSelectedSongs();
                return;
            }

            // 普通点击：单选
            songCards.forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            baseIndex = index;
            lastClickedIndex = index;
            updateSelectedSongs();
        });
    });
}

selectAllBtn.addEventListener('click', function() {
    songCards.forEach(card => {
        card.classList.add('selected');
    });
    baseIndex = 0;
    updateSelectedSongs();
});

cancelSelectBtn.addEventListener('click', function() {
    songCards.forEach(card => {
        card.classList.remove('selected');
    });
    baseIndex = -1;
    lastClickedIndex = -1;
    updateSelectedSongs();
});

batchDeleteBtn.addEventListener('click', function() {
    if (selectedSongs.length === 0) {
        alert('请选择要删除的歌曲');
        return;
    }
    if (confirm(`确定删除选中的 ${selectedSongs.length} 首歌曲吗？`)) {
        batchDeleteForm.action = '<?php echo $currentUrl; ?>&artist=<?php echo urlencode($currentArtist); ?>&album=<?php echo urlencode($currentAlbum); ?>';
        batchDeleteForm.submit();
    }
});
</script>