<?php
// -------------------------------------------------------------------------------------------------
// 共通の例外クラス
//
// History:
// 0.28.02 2024/06/27 作成。
// 0.37.00 2024/09/11 エラーログに、エラーコード/メッセージを追加。
// 0.48.00 2024/10/24 スタックトレースの取得を、クラス/タイプ/関数が無い場合に対応。
// 0.87.04 2025/04/24 スタックトレースの取得を、ファイルパス/行番号が無い場合に対応。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\common;
use Exception as BaseException, Throwable;

/**
 * 共通の例外クラス
 * 
 * @since 0.28.02
 * @version 0.87.04
 */
class Exception extends BaseException {
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

        // 詳細(一番元になったものを参照)
        $ex = $this;
        while ($ex->getPrevious() instanceof Throwable)
            $ex = $ex->getPrevious();

        // エラーコード、メッセージ
        error_log(sprintf('PHP Detail: [%s:%s]%s',
            $ex::class, $ex->getCode(), $ex->getMessage()
        ));

        // スタックトレース
        error_log('PHP Stack trace:');
        foreach ($ex->getTrace() as $num => $trace)
            error_log(sprintf('PHP %3d. %s%s%s %s:%s %s',
                $num + 1,
                $trace['class'] ?? '', $trace['type'] ?? '', $trace['function'] ?? '',
                $trace['file'] ?? '', $trace['line'] ?? '', json_encode($trace['args'], JSON_UNESCAPED_UNICODE)
            ));
    }
}