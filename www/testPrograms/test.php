<?php
// PhpWebLibrary 動作確認用テストページ
header('Content-Type: text/html; charset=UTF-8');
$exts = ['curl', 'mbstring', 'pdo_mysql', 'sqlsrv', 'pdo_sqlsrv', 'xdebug'];
$loader = __DIR__ . '/../libs/php/imports/loader.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head><meta charset="UTF-8"><title>PhpWebLibrary 動作確認</title></head>
<body>
<h1>PhpWebLibrary テストページ</h1>
<p>このページが表示されれば Apache + PHP は動作しています。</p>
<h2>PHP 情報</h2>
<ul>
  <li>PHP バージョン: <?= htmlspecialchars(phpversion()) ?></li>
  <li>SAPI: <?= htmlspecialchars(php_sapi_name()) ?></li>
  <li>サーバ: <?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? '-') ?></li>
  <li>ホスト: <?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? '-') ?></li>
  <li>現在時刻: <?= date('Y-m-d H:i:s') ?> (<?= htmlspecialchars(date_default_timezone_get()) ?>)</li>
</ul>
<h2>拡張モジュール</h2>
<ul>
<?php foreach ($exts as $ext): ?>
  <li><?= htmlspecialchars($ext) ?>: <?= extension_loaded($ext) ? 'OK' : '—' ?></li>
<?php endforeach; ?>
</ul>
<h2>tsubasaLibs ローダー</h2>
<p>loader.php: <?= is_file($loader) ? '存在' : '見つからない' ?></p>
</body>
</html>
