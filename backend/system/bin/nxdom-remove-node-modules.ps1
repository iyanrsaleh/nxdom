# Robust node_modules removal on Windows (rmdir often fails: access denied, long paths, read-only)
param(
    [string]$ProjectRoot = (Get-Location).Path
)

$nodeModules = Join-Path $ProjectRoot 'node_modules'
if (-not (Test-Path -LiteralPath $nodeModules)) {
    exit 0
}

# Clear read-only / system attributes (npm often sets these)
cmd /c "attrib -R /S /D `"$nodeModules\*`"" 2>$null | Out-Null

function Remove-TreeForce([string]$Path) {
    if (-not (Test-Path -LiteralPath $Path)) { return }
    $longPath = if ($Path.StartsWith('\\?\')) { $Path } else { "\\?\$((Resolve-Path -LiteralPath $Path).Path)" }
    Remove-Item -LiteralPath $longPath -Recurse -Force -ErrorAction SilentlyContinue
}

Remove-TreeForce $nodeModules

if (Test-Path -LiteralPath $nodeModules) {
    # Robocopy /MIR from empty dir — reliable way to delete locked/deep node_modules on Windows
    $empty = Join-Path $env:TEMP ("nxdom-empty-" + [guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path $empty -Force | Out-Null
    try {
        & robocopy $empty $nodeModules /mir /r:2 /w:1 /nfl /ndl /njh /njs /nc /ns /np | Out-Null
        $robocode = $LASTEXITCODE
        if ($robocode -ge 8) {
            exit 1
        }
        Remove-TreeForce $nodeModules
    } finally {
        Remove-Item -LiteralPath $empty -Recurse -Force -ErrorAction SilentlyContinue
    }
}

if (Test-Path -LiteralPath $nodeModules) {
    exit 1
}
exit 0
