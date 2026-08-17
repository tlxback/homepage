# 设置服务器参数
$root = "C:\www\"          # 网站根目录
$port = 8080               # 监听端口（避免使用 80 等需要管理员权限的端口）
$prefix = "http://*:$port/"  # 监听所有网络接口，若只需本机改为 "http://localhost:$port/"

# 创建 HttpListener 并添加前缀
$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add($prefix)
$listener.Start()
Write-Host "HTTP 服务器已启动，根目录: $root ，监听地址: $prefix" -ForegroundColor Green

# 处理请求
while ($listener.IsListening) {
    # 等待传入请求（异步）
    $context = $listener.GetContext()
    $request = $context.Request
    $response = $context.Response

    # 获取请求的文件路径（URL 解码，防止中文乱码）
    $rawUrl = [System.Uri]::UnescapeDataString($request.Url.LocalPath)
    if ($rawUrl -eq "/") { $rawUrl = "/index.html" }   # 默认首页

    $filePath = Join-Path $root $rawUrl.TrimStart('/')
    Write-Host "请求: $rawUrl -> $filePath"

    # 检查文件是否存在
    if (Test-Path $filePath -PathType Leaf) {
        # 读取文件内容并返回
        $fileBytes = [System.IO.File]::ReadAllBytes($filePath)
        $response.ContentLength64 = $fileBytes.Length
        $response.OutputStream.Write($fileBytes, 0, $fileBytes.Length)

        # 简单设置 Content-Type（可根据扩展名扩展）
        $ext = [System.IO.Path]::GetExtension($filePath)
        $mime = @{
            ".html" = "text/html"; ".htm" = "text/html"
            ".css"  = "text/css"
            ".js"   = "application/javascript"
            ".png"  = "image/png"
            ".jpg"  = "image/jpeg"; ".jpeg" = "image/jpeg"
            ".gif"  = "image/gif"
            ".txt"  = "text/plain"
        }[$ext]
        if ($mime) { $response.ContentType = $mime }
    }
    else {
        # 返回 404
        $response.StatusCode = 404
        $errMsg = "404 Not Found: $rawUrl"
        $errBytes = [System.Text.Encoding]::UTF8.GetBytes($errMsg)
        $response.OutputStream.Write($errBytes, 0, $errBytes.Length)
        $response.ContentType = "text/plain; charset=utf-8"
    }

    $response.OutputStream.Close()
}

# 停止服务器（按 Ctrl+C 后执行）
$listener.Stop()