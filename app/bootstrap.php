<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('PLATFORM_NAME', 'أثر لتبادل الخبرات والمعرفة');
define('SCHOOL_NAME', 'متوسطة وثانوية البشائر الأولى');

$baseUrl = getenv('APP_BASE_URL');
if ($baseUrl === false || $baseUrl === '') {
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $projectPath = realpath(ROOT_PATH) ?: ROOT_PATH;
    $documentRootPrefix = $documentRoot === false ? '' : rtrim($documentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if ($documentRoot !== false && strcasecmp($projectPath, $documentRoot) === 0) {
        $baseUrl = '';
    } elseif ($documentRootPrefix !== '' && strncasecmp($projectPath, $documentRootPrefix, strlen($documentRootPrefix)) === 0) {
        $relativePath = substr($projectPath, strlen($documentRootPrefix));
        $baseUrl = '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
    } else {
        $baseUrl = '/' . basename(ROOT_PATH);
    }
}
define('BASE_URL', rtrim('/' . ltrim($baseUrl, '/'), '/'));

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('teacher_experiences_session');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

require_once ROOT_PATH . '/app/helpers.php';
require_once ROOT_PATH . '/app/auth.php';

function db(): PDO
{
    static $connection;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $config = require ROOT_PATH . '/config/database.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['database'],
        $config['charset']
    );

    $connection = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connection;
}
