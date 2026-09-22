<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/HalsteadAnalyzer.php';
require_once __DIR__ . '/../src/InputValidator.php';

$passed = 0;
$analyzer = new HalsteadAnalyzer();

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function runTest(string $name, callable $test): void
{
    global $passed;

    try {
        $test();
        $passed++;
        echo "[OK] {$name}\n";
    } catch (Throwable $error) {
        echo "[ERROR] {$name}: {$error->getMessage()}\n";
        exit(1);
    }
}

runTest('Расчёт метрик', function () use ($analyzer): void {
    $result = $analyzer->analyze('<?php $value = 1 + 2;');

    check($result['operators']['+'] === 1, 'Не найден оператор +');
    check($result['operands']['$value'] === 1, 'Не найдена переменная $value');
    check($result['eta'] === $result['eta1'] + $result['eta2'], 'Неверно рассчитан словарь');
    check($result['N'] === $result['N1'] + $result['N2'], 'Неверно рассчитана длина');
});

runTest('Операторы и операнды', function () use ($analyzer): void {
    $code = <<<'PHP'
<?php
if ($left > $right) {
    return $left;
}
return $right;
PHP;
    $result = $analyzer->analyze($code);

    check($result['operators']['if'] === 1, 'Не найден оператор if');
    check($result['operators']['return'] === 2, 'Неверное число операторов return');
    check($result['operands']['$left'] === 2, 'Неверное число операндов $left');
});

runTest('Круглые скобки', function () use ($analyzer): void {
    $result = $analyzer->analyze('<?php if (count($items) > 0) { return $items; }');

    check($result['operators']['count()'] === 1, 'Не найден вызов count()');
    check($result['operators']['()'] === 1, 'Скобки if должны учитываться отдельно');
});

runTest('Интерполяция строки', function () use ($analyzer): void {
    $code = <<<'PHP'
<?php echo "Привет, {$name}!";
PHP;
    $result = $analyzer->analyze($code);

    check($result['operands']['$name'] === 1, 'Не найдена переменная внутри строки');
});

runTest('Проверка синтаксиса', function () use ($analyzer): void {
    try {
        $analyzer->analyze('<?php if (true { echo 1; }');
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException('Ошибочный PHP-код был принят');
});

runTest('Загрузка PHP-файла', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'halstead_');
    check($path !== false, 'Не удалось создать временный файл');

    $code = '<?php $number = 42;';
    file_put_contents($path, $code);

    try {
        $loaded = InputValidator::codeFromRequest(
            ['action' => 'analyze'],
            ['source_file' => [
                'name' => 'example.php',
                'tmp_name' => $path,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($path),
            ]],
            ''
        );

        check($loaded === $code, 'Содержимое файла изменилось');
    } finally {
        unlink($path);
    }
});

echo "\nВсе тесты пройдены: {$passed}\n";
