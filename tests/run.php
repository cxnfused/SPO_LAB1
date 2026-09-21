<?php

declare(strict_types=1);

$sourceFile = __DIR__ . '/../src/HalsteadAnalyzer.php';

if (!is_file($sourceFile)) {
    fwrite(STDERR, "[FAIL] HalsteadAnalyzer source file does not exist.\n");
    exit(1);
}

require_once $sourceFile;

$validatorFile = __DIR__ . '/../src/InputValidator.php';
if (is_file($validatorFile)) {
    require_once $validatorFile;
}

$testsRun = 0;
$failures = 0;

function test(string $name, callable $callback): void
{
    global $testsRun, $failures;
    $testsRun++;

    try {
        $callback();
        echo "[PASS] {$name}\n";
    } catch (Throwable $error) {
        $failures++;
        fwrite(STDERR, "[FAIL] {$name}: {$error->getMessage()}\n");
    }
}

function assertSameValue(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
        );
    }
}

function assertAlmost(float $expected, float $actual, float $delta = 0.001): void
{
    if (abs($expected - $actual) > $delta) {
        throw new RuntimeException("Expected {$expected}, got {$actual}");
    }
}

function assertThrows(callable $callback, string $expectedClass): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        if (!$error instanceof $expectedClass) {
            throw new RuntimeException(
                "Expected {$expectedClass}, got " . $error::class
            );
        }

        return;
    }

    throw new RuntimeException("Expected exception {$expectedClass}");
}

function assertTrue(bool $condition, string $message = 'Condition is false'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$analyzer = new HalsteadAnalyzer();

test('empty source is rejected', function () use ($analyzer): void {
    assertThrows(fn () => $analyzer->analyze(''), InvalidArgumentException::class);
});

test('source without PHP tag is rejected', function () use ($analyzer): void {
    assertThrows(fn () => $analyzer->analyze('$a = 1;'), InvalidArgumentException::class);
});

test('derived metrics use Halstead formulas', function () use ($analyzer): void {
    $result = $analyzer->analyze('<?php $a = 1;');
    assertSameValue($result['eta1'] + $result['eta2'], $result['eta']);
    assertSameValue($result['N1'] + $result['N2'], $result['N']);
    assertAlmost($result['N'] * log($result['eta'], 2), $result['V']);
});

test('statement terminators are counted as operators', function () use ($analyzer): void {
    $result = $analyzer->analyze('<?php $a = 1; $b = 2;');
    assertSameValue(2, $result['operators'][';'] ?? null);
});

test('PHP tokens are classified as operators and operands', function () use ($analyzer): void {
    $code = <<<'PHP'
<?php
function add(int $a, int $b): int {
    // + = fake tokens
    $text = "+ and = stay inside a string";
    if ($a > $b && $b !== 0) {
        return $a + $b;
    }
    return add(1, 2);
}
PHP;
    $result = $analyzer->analyze($code);
    assertSameValue(1, $result['operators']['function'] ?? null);
    assertSameValue(1, $result['operators']['if'] ?? null);
    assertSameValue(1, $result['operators']['&&'] ?? null);
    assertSameValue(1, $result['operators']['!=='] ?? null);
    assertSameValue(1, $result['operators']['add()'] ?? null);
    assertSameValue(1, $result['operators']['+'] ?? null);
    assertSameValue(3, $result['operands']['$a'] ?? null);
    assertSameValue(4, $result['operands']['$b'] ?? null);
    assertSameValue(1, $result['operands']['"+ and = stay inside a string"'] ?? null);
});

test('comments do not create false tokens', function () use ($analyzer): void {
    $result = $analyzer->analyze('<?php /* $fake = 4; */ $real = 5;');
    assertTrue(!array_key_exists('$fake', $result['operands']));
    assertTrue(!array_key_exists('4', $result['operands']));
    assertSameValue(1, $result['operators']['='] ?? null);
    assertSameValue(1, $result['operands']['$real'] ?? null);
});

test('invalid PHP structure is rejected', function () use ($analyzer): void {
    assertThrows(
        fn () => $analyzer->analyze('<?php if (true { echo 1; }'),
        InvalidArgumentException::class
    );
});

test('sample is a 90 to 100 line console program', function (): void {
    $path = __DIR__ . '/../sample/analyzed_program.php';
    assertTrue(is_file($path), 'Sample program does not exist');
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    assertTrue(is_array($lines));
    assertTrue(count($lines) >= 90 && count($lines) <= 100);
    $code = (string) file_get_contents($path);

    foreach (
        ['function ', 'if (', 'switch (', 'match (', 'for (', 'foreach (', 'while (', 'do {', 'break;', 'continue;']
        as $fragment
    ) {
        assertTrue(str_contains($code, $fragment), "Missing fragment: {$fragment}");
    }
});

test('empty request code is rejected', function (): void {
    assertTrue(class_exists(InputValidator::class), 'InputValidator class does not exist');
    assertThrows(
        fn () => InputValidator::codeFromRequest(['action' => 'analyze', 'code' => '  '], [], ''),
        InvalidArgumentException::class
    );
});

test('sample and clear actions return expected code', function (): void {
    $samplePath = __DIR__ . '/../sample/analyzed_program.php';
    assertSameValue(
        file_get_contents($samplePath),
        InputValidator::codeFromRequest(['action' => 'load_sample'], [], $samplePath)
    );
    assertSameValue('', InputValidator::codeFromRequest(['action' => 'clear'], [], $samplePath));
});

test('non PHP upload is rejected', function (): void {
    $temporary = tempnam(sys_get_temp_dir(), 'halstead_');
    assertTrue($temporary !== false);

    try {
        file_put_contents($temporary, '<?php echo 1;');
        assertThrows(
            fn () => InputValidator::codeFromRequest(
                ['action' => 'analyze'],
                ['source_file' => [
                    'name' => 'source.txt',
                    'tmp_name' => $temporary,
                    'error' => UPLOAD_ERR_OK,
                    'size' => filesize($temporary),
                ]],
                ''
            ),
            InvalidArgumentException::class
        );
    } finally {
        unlink($temporary);
    }
});

test('upload over one MiB is rejected', function (): void {
    $temporary = tempnam(sys_get_temp_dir(), 'halstead_');
    assertTrue($temporary !== false);

    try {
        file_put_contents($temporary, '<?php echo 1;');
        assertThrows(
            fn () => InputValidator::codeFromRequest(
                ['action' => 'analyze'],
                ['source_file' => [
                    'name' => 'source.php',
                    'tmp_name' => $temporary,
                    'error' => UPLOAD_ERR_OK,
                    'size' => 1048577,
                ]],
                ''
            ),
            InvalidArgumentException::class
        );
    } finally {
        unlink($temporary);
    }
});

test('valid PHP upload is read without execution', function (): void {
    $temporary = tempnam(sys_get_temp_dir(), 'halstead_');
    assertTrue($temporary !== false);
    $code = '<?php $value = 42;';

    try {
        file_put_contents($temporary, $code);
        $actual = InputValidator::codeFromRequest(
            ['action' => 'analyze'],
            ['source_file' => [
                'name' => 'source.php',
                'tmp_name' => $temporary,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($temporary),
            ]],
            ''
        );
        assertSameValue($code, $actual);
    } finally {
        unlink($temporary);
    }
});

echo "\nTests: {$testsRun}; Failures: {$failures}\n";
exit($failures === 0 ? 0 : 1);
