<?php
declare(strict_types=1);

if ($argc < 3 || $argc > 4) {
    fwrite(STDERR, "Usage: php package-release.php <staged-plugin-dir> <output.zip> [expected-version]\n");
    exit(2);
}

$source = realpath($argv[1]);
$output = $argv[2];
$root = 'wordpress-news-bot';
$required = $root . '/wordpress-news-bot.php';
$expectedVersion=$argv[3]??'';

if ($source === false || !is_dir($source) || basename($source) !== $root) {
    throw new RuntimeException('Staging plugin directory is invalid.');
}
if (!class_exists(ZipArchive::class)) {
    throw new RuntimeException('PHP ZipArchive extension is required.');
}

$zip = new ZipArchive();
if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Release ZIP could not be created.');
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    $relative = substr($item->getPathname(), strlen($source) + 1);
    $entryName = $root . '/' . str_replace('\\', '/', $relative);
    if (str_contains($entryName, '\\')) {
        throw new RuntimeException('Backslash remained in ZIP entry: ' . $entryName);
    }
    if ($item->isDir()) {
        $zip->addEmptyDir(rtrim($entryName, '/') . '/');
    } elseif (!$zip->addFile($item->getPathname(), $entryName)) {
        throw new RuntimeException('File could not be added to ZIP: ' . $entryName);
    }
}

if (!$zip->close()) {
    throw new RuntimeException('Release ZIP could not be finalized.');
}

$report = validateRelease($output, $root, $required,$expectedVersion);
fwrite(STDOUT, 'ZIP entries: ' . $report['entry_count'] . PHP_EOL);
fwrite(STDOUT, 'ZIP entries containing backslash: ' . $report['backslash_count'] . PHP_EOL);
fwrite(STDOUT, "ZIP first two levels:\n" . implode(PHP_EOL, $report['first_two_levels']) . PHP_EOL);

/** @return array{entry_count:int,backslash_count:int,first_two_levels:list<string>} */
function validateRelease(string $archive, string $root, string $required,string$expectedVersion=''): array
{
    $zip = new ZipArchive();
    if ($zip->open($archive) !== true) {
        throw new RuntimeException('Release ZIP could not be opened for validation.');
    }

    $roots = [];
    $foundRequired = false;
    $backslashCount = 0;
    $firstTwoLevels = [];
    $entryCount = $zip->numFiles;
    for ($index = 0; $index < $entryCount; $index++) {
        $entry = $zip->getNameIndex($index);
        if ($entry !== false && str_contains($entry, '\\')) {
            $backslashCount++;
        }
        if ($entry === false || $backslashCount > 0) {
            throw new RuntimeException('ZIP entry contains a backslash.');
        }
        if (!str_starts_with($entry, $root . '/')) {
            throw new RuntimeException('ZIP entry is outside the plugin root: ' . $entry);
        }
        if (preg_match('~(^|/)(?:\.git|\.github|\.tmp-history-build|tests|test-results|dist|node_modules|coverage|cache|src|\.env(?:\..*)?)(?:/|$)~i', $entry) || preg_match('~/(?:composer\.(?:json|lock)|package(?:-lock)?\.json|playwright\.config\.js|phpunit\.xml\.dist)$~i',$entry) || str_ends_with(strtolower($entry), '.zip')) {
            throw new RuntimeException('Development or generated file found in release ZIP: ' . $entry);
        }
        $parts = explode('/', $entry);
        $roots[$parts[0]] = true;
        $firstTwoLevels[implode('/', array_slice($parts, 0, min(2, count($parts))))] = true;
        if (($parts[1] ?? '') === $root || preg_match('/^' . preg_quote($root, '/') . '-\d/', $parts[1] ?? '')) {
            throw new RuntimeException('Nested release directory detected: ' . $entry);
        }
        $foundRequired = $foundRequired || $entry === $required;
    }
    $zip->close();

    if (count($roots) !== 1 || !isset($roots[$root])) {
        throw new RuntimeException('Release ZIP must contain exactly one root directory.');
    }
    if (!$foundRequired) {
        throw new RuntimeException('Main plugin file is missing from the release ZIP.');
    }

    $extractRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wpnb-zip-check-' . bin2hex(random_bytes(8));
    if (!mkdir($extractRoot, 0700, true) && !is_dir($extractRoot)) {
        throw new RuntimeException('Temporary extraction directory could not be created.');
    }
    try {
        $extract = new ZipArchive();
        if ($extract->open($archive) !== true || !$extract->extractTo($extractRoot)) {
            throw new RuntimeException('Release ZIP could not be extracted.');
        }
        $extract->close();
        $mainFile = $extractRoot . DIRECTORY_SEPARATOR . $root . DIRECTORY_SEPARATOR . 'wordpress-news-bot.php';
        if (!is_file($mainFile)) {
            throw new RuntimeException('Extracted main plugin file was not found.');
        }
        $header=(string)file_get_contents($mainFile);$requiredHeaders=['Plugin Name'=>'WordPress News Bot','Text Domain'=>'wordpress-news-bot'];if($expectedVersion!==''){$requiredHeaders['Version']=$expectedVersion;if(version_compare($expectedVersion,'0.3.3','>='))$requiredHeaders['Author']='Utkuweb';}foreach($requiredHeaders as$name=>$expected){if(!preg_match('/^ \* '.preg_quote($name,'/').':\s*(.+)$/m',$header,$match)||trim($match[1])!==$expected)throw new RuntimeException('Plugin header mismatch: '.$name);}
    } finally {
        removeTree($extractRoot);
    }

    $levels = array_keys($firstTwoLevels);
    sort($levels);
    return ['entry_count' => $entryCount, 'backslash_count' => $backslashCount, 'first_two_levels' => $levels];
}

function removeTree(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
}
