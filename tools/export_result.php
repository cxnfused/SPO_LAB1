<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/HalsteadAnalyzer.php';

$samplePath = __DIR__ . '/../sample/analyzed_program.php';
$code = file_get_contents($samplePath);

if ($code === false) {
    fwrite(STDERR, "Не удалось прочитать анализируемую программу.\n");
    exit(1);
}

$result = (new HalsteadAnalyzer())->analyze($code);
$json = json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
);

echo $json . PHP_EOL;
