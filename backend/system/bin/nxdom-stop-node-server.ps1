# Stop Node.js processes running NexaUI server.js only.
# Do NOT use taskkill /IM node.exe (kills IDE helpers) or match 'server\.js' alone (also matches tsserver.js).
$killed = $false
$serverJsPattern = '(?:\\|/)server\.js(?:\s|"|''|$)'
Get-CimInstance Win32_Process -Filter "Name='node.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -and ($_.CommandLine -match $serverJsPattern) } |
    ForEach-Object {
        Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
        $killed = $true
    }
exit $(if ($killed) { 0 } else { 1 })
