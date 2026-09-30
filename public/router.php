<?php
/**
 * PHP Built-in Server Router
 * Leitet alle Requests durch index.php AUSSER adminer-direct.php
 */
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Statische Dateien direkt ausliefern
$file = __DIR__ . $path;
if ($path !== '/' && file_exists($file) && !is_dir($file) && $path !== '/adminer-direct.php') {
    return false; // PHP built-in server liefert die Datei direkt aus
}

// Alle anderen Requests → Router
require __DIR__ . '/index.php';
