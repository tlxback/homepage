<#
.SYNOPSIS
    持续交互式 TCP 客户端
.DESCRIPTION
    连接指定 IP 和端口的 TCP 服务器，循环发送用户输入的消息并显示响应。
    输入 quit/exit 退出，输入空行不发送。
#>

# 清屏并显示标题
Clear-Host
Write-Host "===== PowerShell TCP 持续交互客户端 =====" -ForegroundColor Cyan

# 1. 获取服务器地址和端口
do {
    $server = Read-Host "请输入服务器 IP 地址 (例如 127.0.0.1)"
    if ([string]::IsNullOrWhiteSpace($server)) { Write-Host "IP 地址不能为空！" -ForegroundColor Red }
} while ([string]::IsNullOrWhiteSpace($server))

do {
    $portStr = Read-Host "请输入端口号 (1-65535)"
    if (-not [int]::TryParse($portStr, [ref]$null)) { Write-Host "端口号必须是整数！" -ForegroundColor Red; continue }
    $port = [int]$portStr
    if ($port -lt 1 -or $port -gt 65535) { Write-Host "端口范围 1-65535！" -ForegroundColor Red }
} while ($port -lt 1 -or $port -gt 65535)

# 2. 连接服务器
$client = $null
$stream = $null
try {
    Write-Host "正在连接 $($server):$($port) ..." -ForegroundColor Yellow
    $client = New-Object System.Net.Sockets.TcpClient
    $client.Connect($server, $port)
    $stream = $client.GetStream()
    Write-Host "连接成功！输入消息后按回车发送，输入 quit/exit 断开连接。`n" -ForegroundColor Green
}
catch {
    Write-Host "连接失败: $_" -ForegroundColor Red
    pause
    exit 1
}

# 3. 持续交互循环
try {
    while ($true) {
        # 获取用户输入
        $userInput = Read-Host "`n[发送]"
        if ($userInput -eq "" ) { continue }
        if ($userInput -eq "quit" -or $userInput -eq "exit") {
            Write-Host "用户主动断开连接。" -ForegroundColor Yellow
            break
        }

        # 发送消息（UTF-8 编码）
        $sendBytes = [System.Text.Encoding]::UTF8.GetBytes($userInput)
        $stream.Write($sendBytes, 0, $sendBytes.Length)
        $stream.Flush()

        # 接收服务器响应（此处假定服务器会回复数据，且不会自动断开）
        # 注意：如果服务器不回复或回复不完整，下面代码可能阻塞。可根据实际情况调整。
        if ($stream.DataAvailable) {
            $buffer = New-Object byte[] 4096
            $received = $stream.Read($buffer, 0, $buffer.Length)
            if ($received -gt 0) {
                $response = [System.Text.Encoding]::UTF8.GetString($buffer, 0, $received)
                Write-Host "[接收] $response" -ForegroundColor Green
            }
        }
        else {
            # 没有数据可读时，可以选择等待一小段时间或直接跳过
            # 为防止死等，添加一个简单的延时（可选）
            Start-Sleep -Milliseconds 100
        }
    }
}
catch {
    Write-Host "通信异常: $_" -ForegroundColor Red
}
finally {
    # 4. 清理资源
    if ($stream) { $stream.Close() }
    if ($client) { $client.Close() }
    Write-Host "连接已关闭。按任意键退出..." -ForegroundColor DarkGray
    [void] $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
}