<?php
// 目标目录（确保存在且可写）
define('UPLOAD_DIR', __DIR__ . '/post_sources/');
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// ---------- 处理表单提交（无任何界面提示）----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 上传文件
    if ($action === 'upload' && isset($_FILES['file'])) {
        $file = $_FILES['file'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $filename = basename($file['name']);
            $dest = UPLOAD_DIR . $filename;
            move_uploaded_file($file['tmp_name'], $dest);
        }
    }

    // 删除文件（通过隐藏表单或手动输入）
    if ($action === 'delete') {
        $filename = basename($_POST['filename'] ?? '');
        if ($filename !== '' && $filename !== '.' && $filename !== '..') {
            $path = UPLOAD_DIR . $filename;
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    // 重定向到当前页面（避免刷新重复提交）
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ---------- 获取文件列表 ----------
$files = [];
if (is_dir(UPLOAD_DIR)) {
    $all = scandir(UPLOAD_DIR);
    foreach ($all as $item) {
        if ($item !== '.' && $item !== '..' && is_file(UPLOAD_DIR . $item)) {
            $files[] = $item;
        }
    }
}
sort($files, SORT_STRING);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>post_sources 可视化管理</title>
    <link rel="stylesheet" href="style2.css">
</head>
<body>
<div class="container">
    <h1>📁 post_sources 管理器</h1>

    <!-- 三卡片布局 -->
    <div class="card-grid">
        <!-- 上传卡片 -->
        <div class="card">
            <h2>⬆️ 上传文件</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <input type="file" name="file" class="file-input" required>
                <button type="submit" class="btn">📤 上传到 post_sources/</button>
            </form>
            <div style="margin-top:12px; font-size:0.85rem; color:#5e6f8c;">无大小限制，同名文件自动覆盖</div>
        </div>

        <!-- 文件列表卡片 -->
        <div class="card">
            <h2>📋 文件列表</h2>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline">🔄 刷新列表</a>
            <div class="file-list">
                <?php if (empty($files)): ?>
                    <div style="color:#8a9db0; text-align:center; padding:20px;">📭 目录为空</div>
                <?php else: ?>
                    <?php foreach ($files as $file): ?>
                        <div class="file-item">
                            <span class="file-name">📄 <?php echo htmlspecialchars($file); ?></span>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($file); ?>">
                                <button type="submit" class="delete-btn" title="删除">🗑️</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- 删除卡片（按文件名删除） -->
        <div class="card">
            <h2>🗑️ 删除文件</h2>
            <form method="post">
                <input type="hidden" name="action" value="delete">
                <input type="text" name="filename" class="file-input" placeholder="输入要删除的文件名" required>
                <button type="submit" class="btn">❌ 删除文件</button>
            </form>
            <div style="margin-top:12px; font-size:0.85rem; color:#5e6f8c;">仅删除 post_sources/ 中的文件</div>
        </div>
    </div>
</div>
</body>
</html>
