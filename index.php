<?php
session_start();
$studentName = '汤林轩';
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $studentName; ?> 的个人主页</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/styles/default.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/highlight.min.js"></script>

     <!-- <pre><code class="language-html">...</code></pre> -->
     <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/languages/go.min.js"></script>

    <script>hljs.highlightAll();</script>
    <script src="https://kit.fontawesome.com/a2c3d4e5f6.js" crossorigin="anonymous"></script>
</head>
<body>
    <!-- 公告弹窗（不变） -->
    <div id="announcement-modal" class="modal hidden">
        <div class="modal-content">
            <span class="close-btn" onclick="closeAnnouncement()">&times;</span>
            <h2><i class="fas fa-bullhorn"></i> 公告</h2>
            <p>欢迎来到我的个人主页！我是中学生 <?php echo $studentName; ?>，这里记录了我的学习与生活。</p>
            <p>你可以留言，或查看帖子与我互动。</p>
        </div>
    </div>

    <div class="container">
        <!-- 左侧个人信息卡片（不变） -->
        <div class="profile-card">
            <div class="avatar">
                <img src="icon.png" alt="头像" onerror="this.src='https://via.placeholder.com/150?text=Icon'">
            </div>
            <h1><?php echo $studentName; ?></h1>
            <p class="badge"><i class="fas fa-graduation-cap"></i> 中学生 · 热爱学习</p>
            <hr>
            <p><i class="fas fa-phone-alt"></i> 电话: 180-5688-3126</p>
            <p><i class="fas fa-envelope"></i> 邮箱: tlxback@qq.com</p>
            <div class="intro">
                <h3><i class="fas fa-user"></i> 自我介绍</h3>
                <p>大家好，我是<?php echo $studentName; ?>，喜欢书法。我最喜欢的科目是数学。这个个人主页是我的小天地，欢迎留言交流！</p>
            </div>
        </div>

        <!-- 右侧内容区 -->
        <div class="content-panel">
            <!-- 留言表单（不变） -->
            <div class="leave-message">
                <h2><i class="fas fa-comment-dots"></i> 给
                <?php if(isset($_GET['post_id'])):
                    echo '帖子';
                    echo $_GET['post_id'];
                else:
                    echo '我';
                endif
                ?>
                留言</h2>
                <form action="submit_leaveword.php" method="POST">
                    <input type="hidden" name="post" value="0">
                    <input type="text" name="name" placeholder="你的姓名" required>
                    <input type="email" name="email" placeholder="你的邮箱" required>
                    <textarea name="content" rows="3" placeholder="说点什么吧..." required></textarea>
                    <button type="submit" class="btn-yellow"><i class="fas fa-paper-plane"></i> 发布留言</button>
                </form>
            </div>

            <!-- ===== 帖子浏览入口（GET方式确定id） ===== -->
            <div class="post-browser">
                <h2><i class="fas fa-threads"></i> 浏览帖子</h2>
                <?php
                // 读取 posts.xml 生成帖子列表
                if (file_exists('posts.xml')) {
                    $dom = new DOMDocument();
                    $dom->load('posts.xml');
                    $xpath = new DOMXPath($dom);
                    $allPosts = $xpath->query("/posts/post");
                    if ($allPosts->length > 0) {
                        echo '<div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:20px;">';
                        foreach ($allPosts as $p) {
                            $pid = $p->getAttribute('id');
                            $title = htmlspecialchars($p->getAttribute('title'));
                            // 链接到当前页面，带 post_id 参数
                            echo "<a href='?post_id=$pid' class='btn-blue' style='text-decoration:none;'>$title</a>";
                        }
                        echo '</div>';
                    } else {
                        echo '<p>暂无帖子，敬请期待。</p>';
                    }
                } else {
                    echo '<p>帖子库未初始化。</p>';
                }
                ?>

                <!-- 根据GET参数显示具体帖子内容 -->
                <?php if (isset($_GET['post_id'])): 
                    $pid = intval($_GET['post_id']);
                    if (file_exists('posts.xml')) {
                        $dom = new DOMDocument();
                        $dom->load('posts.xml');
                        $xpath = new DOMXPath($dom);
                        $node = $xpath->query("/posts/post[@id='$pid']")->item(0);
                        if ($node) {
                            $title = htmlspecialchars($node->getAttribute('title'));
                            $content = $node->nodeValue; // 包含HTML
                            echo "<div class='post-content-display' style='background:#f9fcff; padding:20px; border-radius:20px; margin-top:10px;'>";
                            echo "<h3>$title</h3><hr>";
                            echo "<div class='post-body'>$content</div>"; // 直接输出HTML
                            echo "</div>";
                ?>
                <!-- 帖子内留言表单 -->
                <div class="leave-message">
                    <h2><i class="fas fa-comment"></i> 对本帖留言</h2>
                    <form action="submit_leaveword.php" method="POST">
                        <input type="hidden" name="post" value="<?php echo $pid; ?>">
                        <input type="text" name="name" placeholder="你的姓名" required>
                        <input type="email" name="email" placeholder="你的邮箱" required>
                        <textarea name="content" rows="3" placeholder="写下你的评论..." required></textarea>
                        <button type="submit" class="btn-yellow"><i class="fas fa-paper-plane"></i> 提交留言</button>
                    </form>
                </div>

                <!-- 显示当前帖子的留言 -->
                <div class="message-list">
                    <h2><i class="fas fa-comments"></i> 本帖留言</h2>
                    <?php
                    if (file_exists('LeaveWord.xml')) {
                        $dom = new DOMDocument();
                        $dom->load('LeaveWord.xml');
                        $xpath = new DOMXPath($dom);
                        $entries = $xpath->query("/LeaveWords/LeaveWord[@post='$pid']");
                        if ($entries->length == 0) echo "<p>还没有留言，成为第一个吧！</p>";
                        foreach ($entries as $entry) {
                            $name = htmlspecialchars($entry->getAttribute('name'));
                            $email = htmlspecialchars($entry->getAttribute('email'));
                            $content = htmlspecialchars($entry->nodeValue);
                            echo "<div class='message-item'>";
                            echo "<div><strong>$name</strong> <span>($email)</span></div>";
                            echo "<p>$content</p>";
                            echo "</div>";
                        }
                    }
                    ?>
                </div>
           </div>
          <?php
                        } else {
                            echo "<p style='color:red;'>帖子不存在</p>";
                        }
                    }
                endif; ?>
            </div>

            <!-- 最近留言（post=0）保持不变 -->
            <div class="message-list">
                <h2><i class="fas fa-history"></i> 最近留言</h2>
                <?php
                if (file_exists('LeaveWord.xml')) {
                    $dom = new DOMDocument();
                    $dom->load('LeaveWord.xml');
                    $xpath = new DOMXPath($dom);
                    $entries = $xpath->query("/LeaveWords/LeaveWord[@post='0']");
                    $count = 0;
                    foreach ($entries as $entry) {
                        if ($count >= 5) break;
                        $name = htmlspecialchars($entry->getAttribute('name'));
                        $email = htmlspecialchars($entry->getAttribute('email'));
                        $content = htmlspecialchars($entry->nodeValue);
                        $time = $entry->getAttribute('id');
                        echo "<div class='message-item'>";
                        echo "<div><strong>$name</strong> <span style='color:#666;'>($email)</span> <span style='float:right;font-size:0.8rem;'>#{$time}</span></div>";
                        echo "<p>$content</p>";
                        echo "</div>";
                        $count++;
                    }
                } else {
                    echo "<p>暂无留言，来抢沙发吧~</p>";
                }
                ?>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
    // 关闭公告函数（假设存在）
    function closeAnnouncement() {
        document.getElementById('announcement-modal').classList.add('hidden');
    }
    </script>
</body>
</html>
