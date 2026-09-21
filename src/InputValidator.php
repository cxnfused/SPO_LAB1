<?php

declare(strict_types=1);

final class InputValidator
{
    private const MAX_UPLOAD_BYTES = 1048576;

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     */
    public static function codeFromRequest(array $post, array $files, string $samplePath): string
    {
        $action = is_string($post['action'] ?? null) ? $post['action'] : 'analyze';

        if ($action === 'clear') {
            return '';
        }

        if ($action === 'load_sample') {
            return self::readSample($samplePath);
        }

        $upload = $files['source_file'] ?? null;
        if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            return self::readUpload($upload);
        }

        $code = is_string($post['code'] ?? null) ? $post['code'] : '';
        if (trim($code) === '') {
            throw new InvalidArgumentException('Введите PHP-код или загрузите файл с расширением .php.');
        }

        return $code;
    }

    private static function readSample(string $samplePath): string
    {
        if ($samplePath === '' || !is_file($samplePath) || !is_readable($samplePath)) {
            throw new InvalidArgumentException('Файл примера недоступен.');
        }

        $code = file_get_contents($samplePath);
        if ($code === false) {
            throw new InvalidArgumentException('Не удалось прочитать файл примера.');
        }

        return $code;
    }

    /** @param array<string, mixed> $upload */
    private static function readUpload(array $upload): string
    {
        $error = filter_var($upload['error'] ?? null, FILTER_VALIDATE_INT);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Не удалось загрузить PHP-файл. Код ошибки: ' . (string) $error);
        }

        $name = is_string($upload['name'] ?? null) ? $upload['name'] : '';
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'php') {
            throw new InvalidArgumentException('Допускаются только файлы с расширением .php.');
        }

        $size = filter_var($upload['size'] ?? null, FILTER_VALIDATE_INT);
        if ($size === false || $size < 0 || $size > self::MAX_UPLOAD_BYTES) {
            throw new InvalidArgumentException('Размер PHP-файла не должен превышать 1 МиБ.');
        }

        $temporaryPath = is_string($upload['tmp_name'] ?? null) ? $upload['tmp_name'] : '';
        if ($temporaryPath === '' || !is_file($temporaryPath) || !is_readable($temporaryPath)) {
            throw new InvalidArgumentException('Загруженный PHP-файл нельзя прочитать.');
        }

        $code = file_get_contents($temporaryPath);
        if ($code === false || trim($code) === '') {
            throw new InvalidArgumentException('Загруженный PHP-файл пуст или недоступен.');
        }

        return $code;
    }
}
