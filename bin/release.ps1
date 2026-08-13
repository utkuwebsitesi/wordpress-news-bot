param([string]$Version = '0.1.0')
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$out = Join-Path $root ("neyelazim-newsbot-$Version.zip")
if (Test-Path $out) { Remove-Item -LiteralPath $out -Force }
$stage = Join-Path ([System.IO.Path]::GetTempPath()) ("nyb-release-" + [guid]::NewGuid().ToString('N'))
$plugin = Join-Path $stage 'neyelazim-newsbot'
New-Item -ItemType Directory -Path $plugin -Force | Out-Null
$files = Get-ChildItem -LiteralPath $root -Recurse -File | Where-Object { $_.FullName -notmatch '\\(vendor|tests|\.git)\\' -and $_.Name -notlike '*.zip' -and $_.Name -notlike '.env*' -and $_.Name -ne '.phpunit.result.cache' -and $_.Name -notin @('.gitignore','composer.json','composer.lock','phpunit.xml.dist') -and $_.FullName -notmatch '\\bin\\' }
foreach ($file in $files) {
    $relative = $file.FullName.Substring($root.Length).TrimStart('\')
    $target = Join-Path $plugin $relative
    New-Item -ItemType Directory -Path (Split-Path -Parent $target) -Force | Out-Null
    Copy-Item -LiteralPath $file.FullName -Destination $target -Force
}
Compress-Archive -Path $plugin -DestinationPath $out -Force
Remove-Item -LiteralPath $stage -Recurse -Force
Write-Output $out
