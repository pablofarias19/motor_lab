<?php
$base = __DIR__;
$files = glob($base . '/*Test.php');
sort($files);

$passed = 0;
$failed = 0;

foreach ($files as $file) {
    require_once $file;
    $class = basename($file, '.php');
    $test = new $class();

    try {
        $test->run();
        $passed++;
        echo "[PASS] {$class}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "[FAIL] {$class}: {$e->getMessage()}\n";
    }
}

echo "\nPassed: {$passed}\nFailed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
