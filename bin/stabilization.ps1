param([switch]$SkipDocker)
$ErrorActionPreference='Stop'
$root=Split-Path -Parent $PSScriptRoot
Push-Location $root
try {
  php bin/build-release.php 0.5.0-rc.3
  composer validate --strict
  vendor/bin/phpunit --colors=never
  Get-ChildItem -Recurse -Filter *.php | Where-Object {$_.FullName -notmatch '\\vendor\\'} | ForEach-Object { php -l $_.FullName | Out-Null; if($LASTEXITCODE -ne 0){throw "PHP lint failed: $($_.FullName)"} }
  if(!$SkipDocker){
    if(!(Get-Command docker -ErrorAction SilentlyContinue)){throw 'Docker is required for WordPress integration and E2E gates.'}
    $bash=(Get-Command bash -ErrorAction SilentlyContinue).Source
    if([System.Environment]::OSVersion.Platform -eq [System.PlatformID]::Win32NT){$gitBash=Join-Path $env:ProgramFiles 'Git\bin\bash.exe';if(Test-Path -LiteralPath $gitBash){$bash=$gitBash}}
    if(!$bash){throw 'Bash is required for WordPress integration and E2E gates.'}
    $env:WPNB_ARTIFACTS_DIR=$root;$env:WPNB_WP_IMAGE='wordpress:7.0.4-php8.3-apache';$env:WPNB_DB_ENGINE='InnoDB'; & $bash tests/integration/run-install.sh
    if($LASTEXITCODE -ne 0){throw 'WordPress integration gate failed.'}
  }
} finally { Pop-Location }
