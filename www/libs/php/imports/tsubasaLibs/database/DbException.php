<?php
// -------------------------------------------------------------------------------------------------
// DB接続の例外クラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.22.00 2024/05/17 エラーログへ出力する処理を実装。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
use Exception, Throwable;
/**
 * DB接続の例外クラス
 * 
 * @since 0.00.00
 * @version 0.22.00
 */
class DbException extends Exception {
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct(string $message, int $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);

        // 主メッセージ
        error_log(sprintf('PHP DbException: %s in %s on line %s', $message, $this->file, $this->line));

        // スタックトレース(一番元のなったものを参照)
        $ex = $this;
        while ($ex->getPrevious() instanceof Throwable)
            $ex = $ex->getPrevious();
        error_log('PHP Stack trace:');

        // トレースループ
        foreach ($ex->getTrace() as $num => $trace)
            error_log(sprintf('PHP %3d. %s%s%s %s:%s %s',
                $num + 1,
                $trace['class'], $trace['type'], $trace['function'],
                $trace['file'], $trace['line'], json_encode($trace['args'], JSON_UNESCAPED_UNICODE)
            ));
    }
}