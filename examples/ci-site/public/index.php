<?php
// 最小可验证站点：CI 用它确认「部署完真的能打开、且 sqlite 可写」。
declare(strict_types=1);

$dsn = 'sqlite:' . dirname(__DIR__) . '/database/app.db';
$hits = -1;
try {
    $db = new PDO($dsn, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $hits = (int)$db->query('SELECT COUNT(*) FROM hits')->fetchColumn();
} catch (Throwable $e) {
    echo 'CI_OK php=' . PHP_VERSION . ' db_error=' . $e->getMessage();
    exit;
}

echo 'CI_OK php=' . PHP_VERSION . ' hits=' . $hits;
