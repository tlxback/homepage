<?php
session_start();

// 预设密码 5Gxm1v8i 的两次SHA256哈希
$correctHash = "2c22c6df612295eaaf366631536268695f168b4187f76d37f1c5d8f73e0c84b0";

// 登录验证
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $inputHash = hash('sha256', hash('sha256', $_POST['password']));
    if ($inputHash === $correctHash) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $error = '密码错误';
    }
}

// 登出
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

/**
 * 删除留言记录
 */
function del_word() {
    if (!isset($_GET['delete_id']) || trim($_GET['delete_id']) === '') {
        return false;
    }
    $deleteId = htmlspecialchars($_GET['delete_id'], ENT_QUOTES, 'UTF-8');
    $xmlFile = 'LeaveWord.xml';
    if (!file_exists($xmlFile) || !is_writable($xmlFile)) {
        return false;
    }
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    if (!$dom->load($xmlFile)) {
        return false;
    }
    $root = $dom->documentElement;
    if (!$root) {
        return false;
    }
    $words = $root->getElementsByTagName('LeaveWord');
    $toRemove = [];
    foreach ($words as $word) {
        if ($word->getAttribute('id') == $deleteId) {
            $toRemove[] = $word;
        }
    }
    foreach ($toRemove as $node) {
        $root->removeChild($node);
    }
    return $dom->save($xmlFile) !== false;
}
del_word();

// ========== 管理员专属操作 ==========
if (isset($_SESSION['admin_logged_in'])) {
    // ---------- 帖子管理 ----------
    $postsXmlFile = 'posts.xml';
    $postsDom = new DOMDocument();
    $postsDom->preserveWhiteSpace = false;
    $postsDom->formatOutput = true;
    if (!file_exists($postsXmlFile)) {
        $postsDom->loadXML('<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE posts [
    <!ELEMENT posts (post+)>
    <!ELEMENT post (#PCDATA)>
    <!ATTLIST post id CDATA #REQUIRED>
    <!ATTLIST post title CDATA #REQUIRED>
]>
<posts></posts>');
        $postsDom->save($postsXmlFile);
    } else {
        $postsDom->load($postsXmlFile);
    }
    $xpathPosts = new DOMXPath($postsDom);

    // 删除帖子
    if (isset($_GET['delete_post_id'])) {
        $delId = intval($_GET['delete_post_id']);
        $nodes = $xpathPosts->query("/posts/post[@id='$delId']");
        foreach ($nodes as $node) {
            $node->parentNode->removeChild($node);
        }
        $postsDom->save($postsXmlFile);
        header('Location: admin.php');
        exit;
    }

    // 新增或编辑帖子
    if (isset($_POST['save_post'])) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $editId = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;

        if ($editId > 0) {
            $nodes = $xpathPosts->query("/posts/post[@id='$editId']");
            if ($nodes->length > 0) {
                $node = $nodes->item(0);
                $node->setAttribute('title', $title);
                while ($node->hasChildNodes()) {
                    $node->removeChild($node->firstChild);
                }
                $node->appendChild($postsDom->createCDATASection($content));
            }
        } else {
            $maxId = 0;
            $allPosts = $xpathPosts->query("/posts/post");
            foreach ($allPosts as $p) {
                $pid = intval($p->getAttribute('id'));
                if ($pid > $maxId) $maxId = $pid;
            }
            $newId = $maxId + 1;
            $newPost = $postsDom->createElement('post');
            $newPost->setAttribute('id', $newId);
            $newPost->setAttribute('title', $title);
            $newPost->appendChild($postsDom->createCDATASection($content));
            $postsDom->documentElement->appendChild($newPost);
        }
        $postsDom->save($postsXmlFile);
        header('Location: admin.php');
        exit;
    }

    // ---------- 资源管理（支持 AJAX 上传 + 传统删除）----------
    define('UPLOAD_DIR', __DIR__ . '/post_sources/');
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    // 辅助函数：获取文件列表数组
    function getFileListArray() {
        $files = [];
        if (is_dir(UPLOAD_DIR)) {
            $all = scandir(UPLOAD_DIR);
            foreach ($all as $item) {
                if ($item !== '.' && $item !== '..' && is_file(UPLOAD_DIR . $item)) {
                    $files[] = $item;
                }
            }
            sort($files);
        }
        return $files;
    }

    // 处理 AJAX 请求（上传文件 + 获取文件列表）
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        if ($action === 'upload_file' && isset($_FILES['file'])) {
            $file = $_FILES['file'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                $filename = basename($file['name']);
                // 简单安全检查：只允许字母数字下划线横线点
                //if (preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
                  move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename);
                  echo json_encode(['success' => true, 'files' => getFileListArray()]);
                //} else {
                //    echo json_encode(['success' => false, 'error' => '文件名包含非法字符']);
                //}
            } else {
                echo json_encode(['success' => false, 'error' => '上传错误码：' . $file['error']]);
            }
            exit;
        }

        if ($action === 'get_files') {
            echo json_encode(['success' => true, 'files' => getFileListArray()]);
            exit;
        }

        echo json_encode(['success' => false, 'error' => '未知操作']);
        exit;
    }

    // 传统 POST 处理（用于删除文件等，保持原有方式）
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_action'])) {
        $action = $_POST['file_action'];
        if ($action === 'upload_file' && isset($_FILES['file'])) {
            // 传统上传（如果 JavaScript 被禁用，作为降级方案）
            $file = $_FILES['file'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                $filename = basename($file['name']);
                move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename);
            }
        } elseif ($action === 'delete_file') {
            $filename = basename($_POST['filename'] ?? '');
            if ($filename !== '' && $filename !== '.' && $filename !== '..') {
                $path = UPLOAD_DIR . $filename;
                if (is_file($path)) unlink($path);
            }
        }
        header('Location: admin.php');
        exit;
    }

    // 获取文件列表（用于页面初始渲染）
    $files = getFileListArray();
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <title>管理个人主页</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://kit.fontawesome.com/a2c3d4e5f6.js" crossorigin="anonymous"></script>
    <style>
        /* 进度条增强样式 */
        .upload-progress {
            margin: 15px 0;
            display: none;
        }
        .progress-label {
            font-size: 0.85rem;
            margin-bottom: 5px;
            color: var(--blue);
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1><i class="fas fa-lock"></i> 管理面板</h1>

        <?php if (!isset($_SESSION['admin_logged_in'])): ?>
        <!-- 登录表单 -->
        <div class="login-box">
            <h2>管理员登录</h2>
            <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
            <form method="POST">
                <label for="password">密码</label>
                <input type="password" name="password" id="password" required>
                <button type="submit" class="btn-yellow"><i class="fas fa-sign-in-alt"></i> 登录</button>
            </form>
        </div>

        <?php else: ?>
        <!-- 管理界面 -->
        <div class="admin-toolbar">
            <p>欢迎，管理员！ <a href="?logout=1" class="btn-blue" style="background:#dc3545;"><i class="fas fa-sign-out-alt"></i> 登出</a></p>
        </div>

        <!-- ========= 留言管理 ========= -->
        <h2><i class="fas fa-comments"></i> 所有留言</h2>
        <table class="message-table">
            <tr><th>ID</th><th>帖子</th><th>姓名</th><th>邮箱</th><th>内容</th><th>操作</th></tr>
            <?php
            if (file_exists('LeaveWord.xml')) {
                $dom = new DOMDocument();
                $dom->load('LeaveWord.xml');
                $xpath = new DOMXPath($dom);
                $entries = $xpath->query("/LeaveWords/LeaveWord");
                foreach ($entries as $entry) {
                    $id = $entry->getAttribute('id');
                    $post = $entry->getAttribute('post');
                    $name = htmlspecialchars($entry->getAttribute('name'));
                    $email = htmlspecialchars($entry->getAttribute('email'));
                    $content = htmlspecialchars($entry->nodeValue);
                    echo "<tr>";
                    echo "<td>$id</td><td>$post</td><td>$name</td><td>$email</td><td>$content</td>";
                    echo "<td><a href='?delete_id=$id' onclick='return confirm(\"确认删除？\");' class='delete-btn'><i class='fas fa-trash-alt'></i> 删除</a></td>";
                    echo "</tr>";
                }
            }
            ?>
        </table>

        <!-- ========= 帖子管理 ========= -->
        <h2 style="margin-top:40px;"><i class="fas fa-file-alt"></i> 帖子管理</h2>
        <div id="post-list">
            <?php
            $posts = $xpathPosts->query("/posts/post");
            if ($posts->length == 0) echo "<p>暂无帖子，立即发布一篇吧！</p>";
            foreach ($posts as $p) {
                $pid = $p->getAttribute('id');
                $title = htmlspecialchars($p->getAttribute('title'));
                $contentRaw = $p->nodeValue;
                $contentPreview = mb_substr(strip_tags($contentRaw), 0, 60) . '...';
                echo "<div class='post-card'>";
                echo "<div style='display:flex; justify-content:space-between; align-items:center;'>";
                echo "<span><strong>ID {$pid}</strong> —— {$title}</span>";
                echo "<span>";
                echo "<a href='?edit_post_id=$pid' class='btn-blue' style='padding:6px 16px; margin-right:8px;'><i class='fas fa-edit'></i> 编辑</a>";
                echo "<a href='?delete_post_id=$pid' onclick='return confirm(\"确认删除此帖子？\");' class='delete-btn' style='background:#ffe3e3;'><i class='fas fa-trash-alt'></i> 删除</a>";
                echo "</span>";
                echo "</div>";
                echo "<p style='margin-top:10px; color:#555;'>{$contentPreview}</p>";
                echo "</div>";
            }
            ?>
        </div>

        <!-- 发布/编辑帖子表单 -->
        <div class="post-editor">
            <h3><?php echo isset($_GET['edit_post_id']) ? '编辑帖子' : '发布新帖子'; ?></h3>
            <?php
            $editTitle = '';
            $editContent = '';
            $editId = 0;
            if (isset($_GET['edit_post_id'])) {
                $editId = intval($_GET['edit_post_id']);
                $node = $xpathPosts->query("/posts/post[@id='$editId']")->item(0);
                if ($node) {
                    $editTitle = $node->getAttribute('title');
                    $editContent = $node->nodeValue;
                }
            }
            ?>
            <form method="POST" id="postForm">
                <input type="hidden" name="edit_id" value="<?php echo $editId; ?>">
                <label for="title">帖子标题</label>
                <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($editTitle); ?>" required placeholder="例如：学习笔记">

                <label for="content">内容 (支持HTML)</label>
                <textarea name="content" id="content" rows="10" required placeholder="编写HTML内容..."><?php echo htmlspecialchars($editContent); ?></textarea>

                <div style="display:flex; gap:10px; align-items:center;">
                    <button type="button" class="btn-blue" onclick="previewContent()"><i class="fas fa-eye"></i> 预览</button>
                    <button type="submit" name="save_post" class="btn-yellow"><i class="fas fa-save"></i> 保存帖子</button>
                    <?php if (isset($_GET['edit_post_id'])): ?>
                        <a href="admin.php" class="btn-close" style="background:#aaa;">取消编辑</a>
                    <?php endif; ?>
                </div>
            </form>
            <div id="previewBox" class="preview-area" style="display:none;">
                <h4>预览效果</h4>
                <div id="previewContent"></div>
            </div>
        </div>

        <!-- ========= 资源管理 (post_sources/) 支持 AJAX 上传进度条 ========= -->
        <h2 style="margin-top:40px;"><i class="fas fa-folder-open"></i> 资源管理 (post_sources/)</h2>
        <div style="display: flex; flex-wrap: wrap; gap: 20px;">
            <!-- 上传卡片（AJAX + 进度条） -->
            <div class="post-card" style="flex: 1 1 300px;">
                <h3><i class="fas fa-upload"></i> 上传文件（带进度条）</h3>
                <form id="uploadForm" enctype="multipart/form-data">
                    <input type="file" name="file" id="uploadFile" class="file-input" required>
                    <button type="submit" class="btn-blue" id="uploadBtn"><i class="fas fa-cloud-upload-alt"></i> 上传</button>
                </form>
                <!-- 进度条组件 -->
                <div id="uploadProgress" class="upload-progress">
                    <div class="progress-label">上传进度：<span id="progressPercent">0</span>%</div>
                    <div class="progress">
                        <div id="progressBar" class="progress-bar" style="width:0%"></div>
                    </div>
                </div>
                <p style="font-size:0.85rem; color:#5e6f8c; margin-top:10px;">支持任意文件，同名将覆盖。</p>
            </div>

            <!-- 文件列表卡片（支持传统删除，页面会刷新） -->
            <div class="post-card" style="flex: 1 1 300px;">
                <h3><i class="fas fa-list"></i> 文件列表</h3>
                <div style="margin-bottom:10px;">
                    <a href="admin.php" class="btn-blue" style="padding:6px 16px; display:inline-block;"><i class="fas fa-sync-alt"></i> 刷新页面</a>
                </div>
                <div id="fileListContainer" class="file-list">
                    <?php if (empty($files)): ?>
                        <div style="color:#8a9db0; text-align:center; padding:10px;">📭 暂无文件</div>
                    <?php else: ?>
                        <?php foreach ($files as $file): ?>
                            <div class="file-item" data-filename="<?php echo htmlspecialchars($file); ?>">
                                <span class="file-name">📄 <?php echo htmlspecialchars($file); ?></span>
                                <div>
                                    <!-- 下载链接（修复原错误） -->
                                    <a href="/post_sources/<?php echo urlencode($file); ?>" download class="btn-yellow" style="padding:4px 12px; text-decoration:none; font-size:0.8rem;">下载</a>
                                    <!-- 传统删除表单，提交后会刷新页面 -->
                                    <form method="post" style="display:inline-block; margin-left:8px;">
                                        <input type="hidden" name="file_action" value="delete_file">
                                        <input type="hidden" name="filename" value="<?php echo htmlspecialchars($file); ?>">
                                        <button type="submit" class="delete-btn" onclick="return confirm('确认删除？');"><i class="fas fa-trash-alt"></i> 删除</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 按文件名删除卡片（传统方式） -->
            <div class="post-card" style="flex: 1 1 300px;">
                <h3><i class="fas fa-trash-alt"></i> 按文件名删除</h3>
                <form method="post">
                    <input type="hidden" name="file_action" value="delete_file">
                    <input type="text" name="filename" class="file-input" placeholder="输入要删除的文件名" required>
                    <button type="submit" class="btn-blue"><i class="fas fa-trash"></i> 删除</button>
                </form>
                <p style="font-size:0.85rem; color:#5e6f8c; margin-top:10px;">仅删除 post_sources/ 中的文件</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    // 预览帖子内容（原有功能）
    function previewContent() {
        let content = document.getElementById('content').value;
        let previewDiv = document.getElementById('previewContent');
        let box = document.getElementById('previewBox');
        previewDiv.innerHTML = content;
        box.style.display = 'block';
    }
    window.onload = function() {
        if (document.getElementById('content') && document.getElementById('content').value.trim() !== '') {
            previewContent();
        }
    };

    // ========== AJAX 上传文件 + 进度条 ==========
    (function() {
        const uploadForm = document.getElementById('uploadForm');
        if (!uploadForm) return;

        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const fileInput = document.getElementById('uploadFile');
            const file = fileInput.files[0];
            if (!file) {
                alert('请选择文件');
                return;
            }

            // 显示进度条容器
            const progressContainer = document.getElementById('uploadProgress');
            const progressBar = document.getElementById('progressBar');
            const percentSpan = document.getElementById('progressPercent');
            progressContainer.style.display = 'block';
            progressBar.style.width = '0%';
            percentSpan.innerText = '0';

            const formData = new FormData();
            formData.append('action', 'upload_file');
            formData.append('file', file);

            const xhr = new XMLHttpRequest();
            // 监听上传进度
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percent + '%';
                    percentSpan.innerText = percent;
                }
            });

            xhr.addEventListener('load', function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            // 上传成功，更新文件列表
                            updateFileList(response.files);
                            // 重置表单
                            fileInput.value = '';
                            // 可选：短暂显示成功提示
                            progressBar.style.width = '100%';
                            percentSpan.innerText = '100';
                            setTimeout(() => {
                                progressContainer.style.display = 'none';
                            }, 1000);
                        } else {
                            alert('上传失败：' + (response.error || '未知错误'));
                            progressContainer.style.display = 'none';
                        }
                    } catch (err) {
                        alert('解析响应失败');
                        progressContainer.style.display = 'none';
                    }
                } else {
                    alert('网络错误，状态码：' + xhr.status);
                    progressContainer.style.display = 'none';
                }
            });

            xhr.addEventListener('error', function() {
                alert('上传请求失败');
                progressContainer.style.display = 'none';
            });

            xhr.open('POST', 'admin.php', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(formData);
        });

        // 更新文件列表 DOM（不刷新页面）
        function updateFileList(files) {
            const container = document.getElementById('fileListContainer');
            if (!container) return;

            if (!files || files.length === 0) {
                container.innerHTML = '<div style="color:#8a9db0; text-align:center; padding:10px;">📭 暂无文件</div>';
                return;
            }

            let html = '';
            for (let file of files) {
                const safeFile = escapeHtml(file);
                html += `
                    <div class="file-item">
                        <span class="file-name">📄 ${safeFile}</span>
                        <div>
                            <a href="post_sources/${encodeURIComponent(file)}" download class="btn-yellow" style="padding:4px 12px; text-decoration:none; font-size:0.8rem;">下载</a>
                            <form method="post" style="display:inline-block; margin-left:8px;">
                                <input type="hidden" name="file_action" value="delete_file">
                                <input type="hidden" name="filename" value="${safeFile}">
                                <button type="submit" class="delete-btn" onclick="return confirm('确认删除？');"><i class="fas fa-trash-alt"></i> 删除</button>
                            </form>
                        </div>
                    </div>
                `;
            }
            container.innerHTML = html;
        }

        // 简单的防XSS辅助函数
        function escapeHtml(str) {
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            }).replace(/[\uD800-\uDBFF][\uDC00-\uDFFF]/g, function(c) {
                return c;
            });
        }
    })();
    </script>
</body>
</html>
