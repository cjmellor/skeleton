<?php

/**
 * Helper functions for the build.php script.
 */

/**
 * Run a shell command and return trimmed output.
 */
function run(string $command): string
{
    return trim((string) shell_exec($command));
}

/**
 * Slugify a string (lowercase, dashes, alphanum only).
 */
function slugify(string $text): string
{
    $text = str_replace(' ', '-', $text);
    $text = preg_replace('/[^A-Za-z0-9\-]/', '', $text);
    $text = preg_replace('/-+/', '-', $text);
    $text = trim($text, '-');

    return strtolower($text);
}

/**
 * Convert a string to camelCase.
 */
function camelCase(string $string, bool $ucfirst = false): string
{
    $result = preg_replace_callback('/[-_](.)/', function ($matches) {
        return strtoupper($matches[1]);
    }, $string);

    return $ucfirst ? ucfirst($result) : $result;
}

/**
 * Convert a string to kebab-case.
 */
function kebabCase(string $string): string
{
    return strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-', $string));
}

/**
 * Replace occurrences in a file.
 */
function replaceInFile($search, $replace, $filename): void
{
    file_put_contents(
        $filename,
        str_replace($search, $replace, file_get_contents($filename))
    );
}

/**
 * Merge data into composer.json safely.
 */
function addComposerData(array $data, string $filePath = 'composer.json'): bool
{
    if (! is_readable($filePath) || ! is_writable($filePath)) {
        return false;
    }

    $composerData = json_decode(file_get_contents($filePath), true);

    if ($composerData === null && json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }

    $composerData = array_merge($data, $composerData);

    $result = file_put_contents(
        $filePath,
        json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    return $result !== false;
}
