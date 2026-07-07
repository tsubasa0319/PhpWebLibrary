<?php
// tsubasaLibs ライブラリを使った画面プログラム（動作確認用）
// 型クラス(Date/Decimal)のみ使うため、type ローダだけを読み込む（外部依存を避ける）
require_once __DIR__ . '/../libs/php/imports/tsubasaLibs/type/loader.php';

use tsubasaLibs\type\Date;
use tsubasaLibs\type\Decimal;

header('Content-Type: text/html; charset=UTF-8');

$weekNames = ['日', '月', '火', '水', '木', '金', '土'];

// --- Date 型の処理 ---
$today     = new Date();
$todayStr  = (string)$today;
$todayWeek = $weekNames[$today->getWeek()];
$after30   = (string)(new Date())->addDays(30);
$endOfMon  = (string)(new Date())->setLastDayOfMonth();

// --- Decimal 型の処理（誤差の出ない十進計算） ---
$decSum   = (string)(new Decimal('0.1'))->add('0.2');            // 0.3
$floatSum = sprintf('%.17g', 0.1 + 0.2);                          // 0.30000000000000004
$price    = 1980;
$taxIncl  = (string)(new Decimal((string)$price))->mult('1.1');   // 2178
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>tsubasaLibs ライブラリ動作確認</title>
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <style>
    body { font-family: sans-serif; margin: 2em; color: #222; }
    h1 { font-size: 1.4em; }
    table { border-collapse: collapse; margin: 0.5em 0 1.5em; }
    caption { font-weight: bold; text-align: left; margin-bottom: 0.3em; }
    th, td { border: 1px solid #bbb; padding: 5px 12px; text-align: left; }
    th { background: #f0f0f0; }
    .ok { color: #167c2b; font-weight: bold; }
  </style>
</head>
<body>
  <h1>tsubasaLibs ライブラリ動作確認</h1>
  <p>PHP <?= htmlspecialchars(phpversion()) ?> ／ ホスト <?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? '-') ?><br>
     tsubasaLibs の型クラスを使った処理結果です。</p>

  <table>
    <caption>Date 型（tsubasaLibs\type\Date）</caption>
    <tr><th>今日</th><td><?= htmlspecialchars($todayStr) ?>（<?= htmlspecialchars($todayWeek) ?>）</td></tr>
    <tr><th>30 日後</th><td><?= htmlspecialchars($after30) ?></td></tr>
    <tr><th>今月末</th><td><?= htmlspecialchars($endOfMon) ?></td></tr>
  </table>

  <table>
    <caption>Decimal 型（tsubasaLibs\type\Decimal・誤差なし十進計算）</caption>
    <tr><th>0.1 + 0.2（Decimal）</th><td><?= htmlspecialchars($decSum) ?></td></tr>
    <tr><th>0.1 + 0.2（PHP の float）</th><td><?= htmlspecialchars($floatSum) ?></td></tr>
    <tr><th>税込 <?= (int)$price ?> × 1.1（Decimal）</th><td><?= htmlspecialchars($taxIncl) ?> 円</td></tr>
  </table>

  <p class="ok">&#10003; ライブラリの読み込みと処理に成功しました。</p>
</body>
</html>
