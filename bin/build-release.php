<?php
declare(strict_types=1);

$source=realpath($argv[2]??dirname(__DIR__));
if($source===false||!is_file($source.DIRECTORY_SEPARATOR.'wordpress-news-bot.php'))throw new RuntimeException('Release source is invalid.');
$header=file_get_contents($source.DIRECTORY_SEPARATOR.'wordpress-news-bot.php');
if(!preg_match('/^ \* Version:\s*(\S+)/m',(string)$header,$match))throw new RuntimeException('Plugin version header was not found.');
$version=$argv[1]??$match[1];
if($version!==$match[1])throw new RuntimeException('Requested version does not match plugin header.');
$output=$argv[3]??($source.DIRECTORY_SEPARATOR.'wordpress-news-bot-'.$version.'.zip');
$stage=sys_get_temp_dir().DIRECTORY_SEPARATOR.'wpnb-release-'.bin2hex(random_bytes(8));
$plugin=$stage.DIRECTORY_SEPARATOR.'wordpress-news-bot';

try{
    if(!mkdir($plugin,0700,true))throw new RuntimeException('Release staging directory could not be created.');
    $excluded='~(?:^|/)(?:\.git|\.github|\.tmp-history-build|tests|test-results|bin|dist|vendor|node_modules|coverage|cache|src)(?:/|$)|(?:^|/)(?:\.env(?:\..*)?|composer\.(?:json|lock)|package(?:-lock)?\.json|playwright\.config\.js|phpunit\.xml\.dist|\.gitignore|\.phpunit\.result\.cache)$|\.zip$~i';
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
    foreach($iterator as$item){$relative=str_replace('\\','/',substr($item->getPathname(),strlen($source)+1));if(preg_match($excluded,$relative))continue;$target=$plugin.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);if($item->isDir()){if(!is_dir($target)&&!mkdir($target,0700,true))throw new RuntimeException('Staging directory could not be created.');}else{if(!is_dir(dirname($target))&&!mkdir(dirname($target),0700,true))throw new RuntimeException('Staging directory could not be created.');if(!copy($item->getPathname(),$target))throw new RuntimeException('Production file could not be staged.');}}
    $dependency=$stage.DIRECTORY_SEPARATOR.'dependencies';mkdir($dependency,0700,true);$composerJson=$source.DIRECTORY_SEPARATOR.'composer.json';$composerLock=$source.DIRECTORY_SEPARATOR.'composer.lock';if(is_file($composerJson)){copy($composerJson,$dependency.DIRECTORY_SEPARATOR.'composer.json');if(is_file($composerLock))copy($composerLock,$dependency.DIRECTORY_SEPARATOR.'composer.lock');passthru('composer install --no-dev --no-interaction --prefer-dist --working-dir='.escapeshellarg($dependency),$composerCode);if($composerCode!==0)throw new RuntimeException('Production Composer dependencies could not be staged.');copyTree($dependency.DIRECTORY_SEPARATOR.'vendor',$plugin.DIRECTORY_SEPARATOR.'vendor');}else{mkdir($plugin.DIRECTORY_SEPARATOR.'vendor',0700,true);file_put_contents($plugin.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php',"<?php\n// No production Composer dependencies.\n");}
    passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.DIRECTORY_SEPARATOR.'package-release.php').' '.escapeshellarg($plugin).' '.escapeshellarg($output).' '.escapeshellarg($version),$code);if($code!==0)throw new RuntimeException('Release package validation failed.');
}finally{if(is_dir($stage))removeTree($stage);}
fwrite(STDOUT,realpath($output).PHP_EOL);

function copyTree(string$source,string$target):void{if(!is_dir($target)&&!mkdir($target,0700,true))throw new RuntimeException('Vendor staging failed.');foreach(new DirectoryIterator($source)as$item){if($item->isDot())continue;$to=$target.DIRECTORY_SEPARATOR.$item->getFilename();$item->isDir()?copyTree($item->getPathname(),$to):copy($item->getPathname(),$to);}}
function removeTree(string$directory):void{$items=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($items as$item)$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());rmdir($directory);}
