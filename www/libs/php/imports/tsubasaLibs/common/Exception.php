<?php
// -------------------------------------------------------------------------------------------------
// 共通の例外クラス
//
// History:
// 0.28.02 2024/06/27 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\common;
use Exception as BaseClass, Throwable;
/**
 * 共通の例外クラス
 * 
 * @since 0.28.02
 * @version 0.28.02
 */
class Exception extends BaseClass {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** 例外名 */
    const EXCEPTION_NAME = 'LibException';
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct(string $message, int $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);

        // 主メッセージ
        error_log(sprintf('PHP %s: %s in %s on line %s',
            static::EXCEPTION_NAME, $message, $this->file, $this->line));

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