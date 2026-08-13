param([string]$Version = '0.3.0')
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$out = Join-Path $root ("wordpress-news-bot-$Version.zip")
if (Test-Path $out) { Remove-Item -LiteralPath $out -Force }
$stage = Join-Path ([System.IO.Path]::GetTempPath()) ("wpnb-release-" + [guid]::NewGuid().ToString('N'))
$plugin = Join-Path $stage 'wordpress-news-bot'
try {
    New-Item -ItemType Directory -Path $plugin -Force | Out-Null
    $dependencyStage = Join-Path $stage 'dependencies'
    New-Item -ItemType Directory -Path $dependencyStage -Force | Out-Null
    Copy-Item -LiteralPath (Join-Path $root 'composer.json') -Destination $dependencyStage -Force
    Copy-Item -LiteralPath (Join-Path $root 'composer.lock') -Destination $dependencyStage -Force
    composer install --no-dev --no-interaction --prefer-dist --working-dir $dependencyStage | Out-Null

    $files = Get-ChildItem -LiteralPath $root -Recurse -File | Where-Object { $_.FullName -notmatch '\\(vendor|tests|\.git)\\' -and $_.Name -notlike '*.zip' -and $_.Name -notlike '.env*' -and $_.Name -ne '.phpunit.result.cache' -and $_.Name -notin @('.gitignore','composer.json','composer.lock','phpunit.xml.dist') -and $_.FullName -notmatch '\\bin\\' }
    foreach ($file in $files) {
        $relative = $file.FullName.Substring($root.Length).TrimStart('\')
        $target = Join-Path $plugin $relative
        New-Item -ItemType Directory -Path (Split-Path -Parent $target) -Force | Out-Null
        Copy-Item -LiteralPath $file.FullName -Destination $target -Force
    }
    Copy-Item -LiteralPath (Join-Path $dependencyStage 'vendor') -Destination (Join-Path $plugin 'vendor') -Recurse -Force

    php (Join-Path $PSScriptRoot 'package-release.php') $plugin $out
} finally {
    if (Test-Path -LiteralPath $stage) { Remove-Item -LiteralPath $stage -Recurse -Force }
}
Write-Output $out
