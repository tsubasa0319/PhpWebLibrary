<?php
// -------------------------------------------------------------------------------------------------
// Web処理の例外クラス
//
// History:
// 0.22.00 2024/05/17 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
use Exception, Throwable;
/**
 * Web処理の例外クラス
 * 
 * @since 0.22.00
 * @version 0.22.00
 */
class WebException extends Exception {
    public function __construct(string $message, int $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);

        // 主メッセージ
        error_log(sprintf('PHP WebException: %s in %s on line %s', $message, $this->file, $this->line));

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