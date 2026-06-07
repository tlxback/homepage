<?php
// ==================== 配置区域 ====================
define('ADMIN_PWD_HASH', '2c22c6df612295eaaf366631536268695f168b4187f76d37f1c5d8f73e0c84b0'); // "5Gxm1v8i" 的两次 SHA256
define('POSTS_XML', 'posts.xml');
define('LEAVEWORD_XML', 'LeaveWord.xml');
define('UPLOAD_DIR', __DIR__ . '/post_sources/');

if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

// ==================== 辅助函数 ====================
function verifyPassword($pwd) {
    return hash('sha256', hash('sha256', $pwd)) === ADMIN_PWD_HASH;
}

function jsonResponse($code, $result = null) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => $code, 'result' => $result]);
    exit;
}

function textResponse($msg) {
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

function loadPostsDom() {
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    if (!file_exists(POSTS_XML)) {
        $dom->loadXML('<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE posts [
<!ELEMENT posts (post+)>
<!ELEMENT post (#PCDATA)>
<!ATTLIST post id CDATA #REQUIRED>
<!ATTLIST post title CDATA #REQUIRED>
]>
<posts></posts>');
        $dom->save(POSTS_XML);
    } else {
        $dom->load(POSTS_XML);
    }
    return $dom;
}

function loadLeavewordDom() {
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    if (!file_exists(LEAVEWORD_XML)) {
        $dom->loadXML('<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE LeaveWords SYSTEM "LeaveWord.dtd">
<LeaveWords/>');
        $dom->save(LEAVEWORD_XML);
    } else {
        $dom->load(LEAVEWORD_XML);
    }
    return $dom;
}

function getNextPostId($dom) {
    $max = 0;
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('/posts/post') as $node) {
        $id = intval($node->getAttribute('id'));
        if ($id > $max) $max = $id;
    }
    return $max + 1;
}

function getNextLeavewordId($dom) {
    $max = 0;
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('/LeaveWords/LeaveWord') as $node) {
        $id = intval($node->getAttribute('id'));
        if ($id > $max) $max = $id;
    }
    return $max + 1;
}

// ==================== 接口实现 ====================

// 获取所有帖子列表（无需密码）
function handleGetPostList() {
    $dom = loadPostsDom();
    $xpath = new DOMXPath($dom);
    $list = [];
    foreach ($xpath->query('/posts/post') as $post) {
        $list[] = [
            'id'    => intval($post->getAttribute('id')),
            'title' => $post->getAttribute('title'),
            'body'  => $post->nodeValue
        ];
    }
    jsonResponse(0, $list);
}

// 获取单个帖子内容（无需密码） ✨新增
function handleGetPublicPost($pid) {
    $pid = intval($pid);
    $dom = loadPostsDom();
    $xpath = new DOMXPath($dom);
    $node = $xpath->query("/posts/post[@id='$pid']")->item(0);
    if (!$node) textResponse('失败');
    textResponse($node->nodeValue);
}

// 添加留言（无需密码） ✨新增
function handleAddMessage($pid, $name, $email, $content) {
    $pid = intval($pid);
    $name = trim($name);
    $email = trim($email);
    $content = trim($content);
    if (empty($name) || empty($email) || empty($content)) {
        textResponse('失败');
    }
    $dom = loadLeavewordDom();
    $newId = getNextLeavewordId($dom);
    $root = $dom->documentElement;
    $msg = $dom->createElement('LeaveWord');
    $msg->setAttribute('id', $newId);
    $msg->setAttribute('post', $pid);
    $msg->setAttribute('name', $name);
    $msg->setAttribute('email', $email);
    $msg->appendChild($dom->createCDATASection($content));
    $root->appendChild($msg);
    $dom->save(LEAVEWORD_XML);
    textResponse('成功');
}

// ---------- 以下为管理员操作（均需密码）----------
function handleGetPost($pwd, $pid) {
    if (!verifyPassword($pwd)) textResponse('失败');
    $dom = loadPostsDom();
    $xpath = new DOMXPath($dom);
    $node = $xpath->query("/posts/post[@id='$pid']")->item(0);
    textResponse($node ? $node->nodeValue : '失败');
}

function handleDelPost($pwd, $pid) {
    if (!verifyPassword($pwd)) textResponse('失败');
    $dom = loadPostsDom();
    $xpath = new DOMXPath($dom);
    $node = $xpath->query("/posts/post[@id='$pid']")->item(0);
    if ($node) $node->parentNode->removeChild($node);
    $dom->save(POSTS_XML);
    textResponse('成功');
}

function handleWrPost($pwd, $pid, $ptitle, $pval) {
    if (!verifyPassword($pwd)) textResponse('失败');
    $pid = intval($pid);
    $ptitle = trim($ptitle);
    $pval = trim($pval);
    if ($ptitle === '' || $pval === '') textResponse('失败');
    $dom = loadPostsDom();
    $xpath = new DOMXPath($dom);
    $node = $xpath->query("/posts/post[@id='$pid']")->item(0);
    if ($node) {
        $node->setAttribute('title', $ptitle);
        while ($node->hasChildNodes()) $node->removeChild($node->firstChild);
        $node->appendChild($dom->createCDATASection($pval));
    } else {
        $newId = getNextPostId($dom);
        $newPost = $dom->createElement('post');
        $newPost->setAttribute('id', $newId);
        $newPost->setAttribute('title', $ptitle);
        $newPost->appendChild($dom->createCDATASection($pval));
        $dom->documentElement->appendChild($newPost);
    }
    $dom->save(POSTS_XML);
    textResponse('成功');
}

function handleGetLwl($pwd) {
    if (!verifyPassword($pwd)) jsonResponse(1, null);
    $dom = loadLeavewordDom();
    $xpath = new DOMXPath($dom);
    $list = [];
    foreach ($xpath->query('/LeaveWords/LeaveWord') as $e) {
        $list[] = [
            'id'    => intval($e->getAttribute('id')),
            'pid'   => intval($e->getAttribute('post')),
            'name'  => $e->getAttribute('name'),
            'email' => $e->getAttribute('email'),
            'val'   => $e->nodeValue
        ];
    }
    jsonResponse(0, $list);
}

function handleDelLw($pwd, $lwid) {
    if (!verifyPassword($pwd)) textResponse('失败');
    $dom = loadLeavewordDom();
    $xpath = new DOMXPath($dom);
    $node = $xpath->query("/LeaveWords/LeaveWord[@id='$lwid']")->item(0);
    if ($node) $node->parentNode->removeChild($node);
    $dom->save(LEAVEWORD_XML);
    textResponse('成功');
}

function handleUpFile($pwd, $fname) {
    if (!verifyPassword($pwd)) textResponse('失败');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) textResponse('失败');
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) textResponse('失败');
    $targetName = trim($fname);
    if ($targetName === '') $targetName = basename($file['name']);
    else $targetName = basename($targetName);
    if ($targetName === '' || $targetName === '.' || $targetName === '..') textResponse('失败');
    $dest = UPLOAD_DIR . $targetName;
    textResponse(move_uploaded_file($file['tmp_name'], $dest) ? '成功' : '失败');
}

function handleFileList($pwd) {
    if (!verifyPassword($pwd)) jsonResponse(1, null);
    $files = [];
    if (is_dir(UPLOAD_DIR)) {
        foreach (scandir(UPLOAD_DIR) as $item) {
            if ($item !== '.' && $item !== '..' && is_file(UPLOAD_DIR . $item)) $files[] = $item;
        }
        sort($files);
    }
    jsonResponse(0, $files);
}

function handleDelFile($pwd, $fname) {
    if (!verifyPassword($pwd)) textResponse('失败');
    $fname = basename(trim($fname));
    if ($fname === '' || $fname === '.' || $fname === '..') textResponse('失败');
    $path = UPLOAD_DIR . $fname;
    textResponse(is_file($path) && unlink($path) ? '成功' : '失败');
}

// ==================== 路由 ====================
$type = $_GET['type'] ?? '';
switch ($type) {
    case 'getpostlpwd': handleGetPostList(); break;
    case 'getpublicpost': handleGetPublicPost($_GET['pid'] ?? 0); break;  // ✨新增
    case 'addmsg': handleAddMessage($_POST['pid'] ?? 0, $_POST['name'] ?? '', $_POST['email'] ?? '', $_POST['content'] ?? ''); break; // ✨新增
    case 'getpost': handleGetPost($_GET['pwd'] ?? '', $_GET['pid'] ?? 0); break;
    case 'delpost': handleDelPost($_GET['pwd'] ?? '', $_GET['pid'] ?? 0); break;
    case 'wrpost': handleWrPost($_GET['pwd'] ?? '', $_GET['pid'] ?? 0, $_GET['ptitle'] ?? '', $_GET['pval'] ?? ''); break;
    case 'getlwl': handleGetLwl($_GET['pwd'] ?? ''); break;
    case 'dellw': handleDelLw($_GET['pwd'] ?? '', $_GET['lwid'] ?? 0); break;
    case 'upfile': handleUpFile($_GET['pwd'] ?? '', $_GET['fname'] ?? ''); break;
    case 'filel': handleFileList($_GET['pwd'] ?? ''); break;
    case 'delfile': handleDelFile($_GET['pwd'] ?? '', $_GET['fname'] ?? ''); break;
    case 'checkadmin': $pwd = $_GET['pwd'] ?? ''; echo verifyPassword($pwd) ? '成功' : '失败'; break;
    default: textResponse('无效的请求类型');
}
