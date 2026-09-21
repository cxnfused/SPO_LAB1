<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/HalsteadAnalyzer.php';
require_once __DIR__ . '/../src/InputValidator.php';

function escapeHtml(string|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @param array<string, int> $frequencies */
function sortedFrequencies(array $frequencies): array
{
    arsort($frequencies, SORT_NUMERIC);
    return $frequencies;
}

$samplePath = __DIR__ . '/../sample/analyzed_program.php';
$code = '';
$result = null;
$errorMessage = null;

if (($_GET['sample'] ?? null) === '1') {
    try {
        $code = InputValidator::codeFromRequest(['action' => 'load_sample'], [], $samplePath);
        $result = (new HalsteadAnalyzer())->analyze($code);
    } catch (Throwable $error) {
        $errorMessage = $error->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $code = InputValidator::codeFromRequest($_POST, $_FILES, $samplePath);
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : 'analyze';

        if ($action !== 'clear') {
            $result = (new HalsteadAnalyzer())->analyze($code);
        }
    } catch (Throwable $error) {
        $errorMessage = $error->getMessage();
        $code = is_string($_POST['code'] ?? null) ? $_POST['code'] : '';
    }
}

$operatorRows = $result === null ? [] : sortedFrequencies($result['operators']);
$operandRows = $result === null ? [] : sortedFrequencies($result['operands']);
$operatorNames = array_keys($operatorRows);
$operandNames = array_keys($operandRows);
$rowCount = max(count($operatorRows), count($operandRows));
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Метрики Холстеда для PHP</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="page-header">
    <div class="header-inner">
        <p class="eyebrow">Практическое задание №1</p>
        <h1>Метрики Холстеда для PHP</h1>
        <p class="subtitle">
            Анализ операторов и операндов исходного кода с расчётом шести базовых
            и трёх производных метрик.
        </p>
    </div>
</header>

<main class="page-shell">
    <section class="panel input-panel" aria-labelledby="source-title">
        <div class="section-heading">
            <div>
                <p class="step-label">Шаг 1</p>
                <h2 id="source-title">Исходный PHP-код</h2>
            </div>
            <span class="safe-badge">Код не выполняется</span>
        </div>

        <?php if ($errorMessage !== null): ?>
            <div class="message error" role="alert"><?= escapeHtml($errorMessage) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <label for="code">Вставьте код</label>
            <textarea id="code" name="code" spellcheck="false"
                placeholder="<?php echo escapeHtml('<?php' . PHP_EOL . '$value = 42;'); ?>"
            ><?= escapeHtml($code) ?></textarea>

            <div class="upload-row">
                <div>
                    <label for="source_file">Или загрузите файл</label>
                    <input id="source_file" name="source_file" type="file" accept=".php,text/x-php">
                    <p class="hint">Только .php, не более 1 МиБ</p>
                </div>
                <div class="actions">
                    <button class="button primary" type="submit" name="action" value="analyze">
                        Рассчитать метрики
                    </button>
                    <button class="button secondary" type="submit" name="action" value="load_sample">
                        Загрузить пример
                    </button>
                    <button class="button ghost" type="submit" name="action" value="clear">
                        Очистить
                    </button>
                </div>
            </div>
        </form>
    </section>

    <?php if ($result !== null): ?>
        <section class="results" aria-labelledby="results-title">
            <div class="section-heading result-heading">
                <div>
                    <p class="step-label">Шаг 2</p>
                    <h2 id="results-title">Результаты анализа</h2>
                </div>
                <span class="result-badge">Расчёт выполнен</span>
            </div>

            <div class="metric-grid" aria-label="Базовые метрики">
                <article class="metric-card">
                    <span>η₁</span>
                    <strong><?= (int) $result['eta1'] ?></strong>
                    <p>словарь операторов</p>
                </article>
                <article class="metric-card">
                    <span>η₂</span>
                    <strong><?= (int) $result['eta2'] ?></strong>
                    <p>словарь операндов</p>
                </article>
                <article class="metric-card">
                    <span>N₁</span>
                    <strong><?= (int) $result['N1'] ?></strong>
                    <p>всего операторов</p>
                </article>
                <article class="metric-card">
                    <span>N₂</span>
                    <strong><?= (int) $result['N2'] ?></strong>
                    <p>всего операндов</p>
                </article>
            </div>

            <div class="table-panel">
                <div class="table-title">
                    <h3>Таблица базовых метрик</h3>
                    <p>Частота каждого уникального элемента программы</p>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead>
                        <tr>
                            <th>j</th>
                            <th>Оператор</th>
                            <th>f1j</th>
                            <th>i</th>
                            <th>Операнд</th>
                            <th>f2i</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php for ($row = 0; $row < $rowCount; $row++): ?>
                            <?php
                            $operator = $operatorNames[$row] ?? null;
                            $operand = $operandNames[$row] ?? null;
                            ?>
                            <tr>
                                <td><?= $operator === null ? '' : $row + 1 ?></td>
                                <td class="token"><?= $operator === null ? '' : escapeHtml($operator) ?></td>
                                <td><?= $operator === null ? '' : (int) $operatorRows[$operator] ?></td>
                                <td><?= $operand === null ? '' : $row + 1 ?></td>
                                <td class="token"><?= $operand === null ? '' : escapeHtml($operand) ?></td>
                                <td><?= $operand === null ? '' : (int) $operandRows[$operand] ?></td>
                            </tr>
                        <?php endfor; ?>
                        </tbody>
                        <tfoot>
                        <tr>
                            <td colspan="2">η₁ = <?= (int) $result['eta1'] ?></td>
                            <td>N₁ = <?= (int) $result['N1'] ?></td>
                            <td colspan="2">η₂ = <?= (int) $result['eta2'] ?></td>
                            <td>N₂ = <?= (int) $result['N2'] ?></td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="derived-panel">
                <div class="table-title">
                    <h3>Производные метрики</h3>
                    <p>Рассчитаны по базовым значениям</p>
                </div>
                <div class="derived-grid">
                    <article>
                        <span>Словарь программы</span>
                        <strong>η = <?= (int) $result['eta'] ?></strong>
                        <code>η₁ + η₂</code>
                    </article>
                    <article>
                        <span>Длина программы</span>
                        <strong>N = <?= (int) $result['N'] ?></strong>
                        <code>N₁ + N₂</code>
                    </article>
                    <article>
                        <span>Объём программы</span>
                        <strong>V = <?= number_format((float) $result['V'], 2, ',', ' ') ?></strong>
                        <code>N × log₂(η)</code>
                    </article>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>

<footer>
    Парсер использует лексический анализ PHP и не запускает загруженный исходный код.
</footer>
</body>
</html>
