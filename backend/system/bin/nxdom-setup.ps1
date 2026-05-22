# Nxdom CLI - Setup PATH + fungsi nxdom di profil PowerShell
$projectPath = (Get-Item $PSScriptRoot).Parent.Parent.FullName.TrimEnd('\')

# 0. Git\cmd ke PATH User (agar perintah "git" di PowerShell/Cursor dikenali)
$gitCmdCandidates = @(
    (Join-Path ${env:ProgramW6432} "Git\cmd"),
    (Join-Path $env:ProgramFiles "Git\cmd"),
    (Join-Path ${env:ProgramFiles(x86)} "Git\cmd")
) | Where-Object { $_ -and (Test-Path (Join-Path $_ "git.exe")) }
if ($gitCmdCandidates.Count -gt 0) {
    $gitCmd = $gitCmdCandidates[0]
    $userPathGit = [Environment]::GetEnvironmentVariable("Path", "User")
    if ($userPathGit -notlike "*$([regex]::Escape($gitCmd))*") {
        [Environment]::SetEnvironmentVariable('Path', ($userPathGit + ';' + $gitCmd), 'User')
        $env:Path = $env:Path + ';' + $gitCmd
    }
}

# 1. Tambah ke PATH (untuk terminal baru)
$userPath = [Environment]::GetEnvironmentVariable("Path", "User")
if ($userPath -notlike ('*' + $projectPath + '*')) {
    [Environment]::SetEnvironmentVariable('Path', ($userPath + ';' + $projectPath), 'User')
}

# 2. Pasang fungsi nxdom di profil PowerShell (dinamis: cari nxdom.bat di folder saat ini)
$profilePath = $PROFILE
$nxdomFunc = @'
function nxdom {
    $bat = Join-Path (Get-Location) "nxdom.bat"
    if (Test-Path $bat) { & $bat $args }
    else { Write-Host "nxdom: nxdom.bat tidak ditemukan. Pastikan Anda di folder root project." -ForegroundColor Red }
}
'@
$profileDir = Split-Path $profilePath
if (-not (Test-Path $profileDir)) { New-Item -ItemType Directory -Path $profileDir -Force | Out-Null }
$profileContent = if (Test-Path $profilePath) { Get-Content $profilePath -Raw } else { '' }
if ($profileContent -notlike '*function nxdom*') {
    Add-Content -Path $profilePath -Value ([Environment]::NewLine + '# Nxdom CLI' + [Environment]::NewLine + $nxdomFunc)
}
