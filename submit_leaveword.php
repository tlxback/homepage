<?php
function getNextId($dom) {
    $maxId = 0;
    $xpath = new DOMXPath($dom);
    $entries = $xpath->query("/LeaveWords/LeaveWord");
    foreach ($entries as $e) {
        $id = intval($e->getAttribute('id'));
        if ($id > $maxId) $maxId = $id;
    }
    return $maxId + 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post = isset($_POST['post']) ? intval($_POST['post']) : 0;
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $content = trim($_POST['content']);

    // 简单过滤
    if (empty($name) || empty($email) || empty($content)) {
        die('请完整填写表单');
    }

    // 加载或创建XML
    $xmlFile = 'LeaveWord.xml';
    if (!file_exists($xmlFile)) {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        // 添加DTD
        // $dom->appendChild($dom->createDocumentType('LeaveWords', null, 'LeaveWord.dtd'));
        $root = $dom->createElement('LeaveWords');
        $dom->appendChild($root);
    } else {
        $dom = new DOMDocument();
        $dom->formatOutput = true;
        $dom->load($xmlFile);
        $root = $dom->documentElement;
    }

    $newId = getNextId($dom);

    $leaveWord = $dom->createElement('LeaveWord');
    $leaveWord->setAttribute('post', $post);
    $leaveWord->setAttribute('id', $newId);
    $leaveWord->setAttribute('name', $name);
    $leaveWord->setAttribute('email', $email);
    $leaveWord->appendChild($dom->createCDATASection($content)); // 使用CDATA避免转义

    $root->appendChild($leaveWord);
    $dom->save($xmlFile);

    // 重定向回来源页
    $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header("Location: $referer");
    exit;
} else {
    header('Location: index.php');
    exit;
}
?>
