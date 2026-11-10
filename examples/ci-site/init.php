<?php
// 初始化脚本由 deploy.sh 的 --db-init 在部署后以 www-data 身份执行一次。
declare(strict_types=1);

$dir = __DIR__ . '/database';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
$db = new PDO('sqlite:' . $dir . '/app.db', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE IF NOT EXISTS hits (id INTEGER PRIMARY KEY AUTOINCREMENT)');
$db->exec('INSERT INTO hits DEFAULT VALUES');
echo "init ok: " . $db->query('SELECT COUNT(*) FROM hits')->fetchColumn() . " hits\n";