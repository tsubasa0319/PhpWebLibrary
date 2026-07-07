<?php
// -------------------------------------------------------------------------------------------------
// 共通の例外クラス
//
// History:
// 0.28.02 2024/06/27 作成。
// 0.37.00 2024/09/11 エラーログに、エラーコード/メッセージを追加。
// 0.48.00 2024/10/24 スタックトレースの取得を、クラス/タイプ/関数が無い場合に対応。
// 0.87.04 2025/04/24 スタックトレースの取得を、ファイルパス/行番号が無い場合に対応。
// 1.01.02 2025/10/01 スタックトレースの取得を、パラメータが無い場合に対応。
//                    スタックトレースのパラメータ値の取得を参照型へ対応。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\common;
use Exception as BaseException, Throwable;
use SensitiveParameterValue;

/**
 * 共通の例外クラス
 * 
 * @since 0.28.02
 * @version 1.01.02
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
            error_log(sprintf('PHP %3d. %s(%s): %s%s%s(%s)',
                $num + 1,
                $trace['file'] ?? '', $trace['line'] ?? '',
                $trace['class'] ?? '', $trace['type'] ?? '', $trace['function'] ?? '',
                (function ($args) {
                    $_args = [];
                    if (is_array($args))
                        foreach ($args as $arg)
                            $_args[] = $this->convValueArg($arg);
                    return implode(', ', $_args);
                })($trace['args'] ?? [])
            ));
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * パラメータ値をログ用に変換
     * 
     * @since 1.02.00
     * @param mixed $arg パラメータ値
     * @param bool $isInner 再帰的かどうか
     * @return string ログ用の値
     */
    protected function convValueArg(mixed $arg, bool $isInner = false): string {
        return match (true) {
            is_string($arg) =>  $this->convStringArg($arg, $isInner),
            is_object($arg) =>  $this->convObjectArg($arg, $isInner),
            is_array($arg)  =>  (array_values($arg) === $arg ?
                $this->convArrayArg($arg) :
                $this->convHashArg($arg)
            ),
            default =>  $this->convOtherArg($arg, $isInner)
        };
    }

    /**
     * パラメータ値をログ用に変換(文字列型)
     * 
     * @since 1.02.00
     * @param mixed $arg パラメータ値
     * @param bool $isInner 再帰的かどうか
     * @return string ログ用の値
     */
    protected function convStringArg(string $arg, bool $isInner = false): string {
        $maxLength = $isInner ? 30 : 100;

        $data = substr(json_encode($arg, JSON_UNESCAPED_UNICODE), 1, -1);
        $data = str_replace(['\\', '\''], ['\\\\', '\\\''], $data);
        if (strlen($data) > $maxLength - 5)
            $data = sprintf('%s...', substr($data, 0, $maxLength - 5));

        return sprintf('\'%s\'', $data);
    }

    /**
     * パラメータ値をログ用に変換(オブジェクト型)
     * 
     * @since 1.02.00
     * @param mixed $arg パラメータ値
     * @param bool $isInner 再帰的かどうか
     * @return string ログ用の値
     */
    protected function convObjectArg(object $arg, bool $isInner = false): string {
        // 機密情報を隠蔽
        if ($arg instanceof SensitiveParameterValue) return '\'[Sensitive Parameter]\'';

        $maxLength = $isInner ? 30 : 100;

        $nameLength = strlen($arg::class);
        $hasProperty = json_encode($arg, JSON_UNESCAPED_UNICODE) !== '{}';

        // クラス名が長い
        if ($nameLength > $maxLength - 2 and !$hasProperty)
            return sprintf('%s...{}', substr($arg::class, 0, $maxLength - 5));
        if ($nameLength > $maxLength - 5 and $hasProperty)
            return sprintf('%s...{...}', substr($arg::class, 0, $maxLength - 8));

        $data = sprintf('%s{%s}', $arg::class, substr($this->convHashArg((array)$arg), 1, -1));

        // プロパティが長い
        if (strlen($data) > $maxLength) $data = sprintf('%s...}', substr($data, 0, $maxLength - 4));

        return $data;
    }

    /**
     * パラメータ値をログ用に変換(配列型)
     * 
     * @since 1.02.00
     * @param mixed $arg パラメータ値
     * @return string ログ用の値
     */
    protected function convArrayArg(array $arg): string {
        $datas = [];

        foreach ($arg as $value)
            $datas[] = $this->convValueArg($value, true);

        return sprintf('[%s]', implode(',', $datas));
    }

    /**
     * パラメータ値をログ用に変換(連想配列型)
     * 
     * @since 1.02.00
     * @param mixed $arg パラメータ値
     * @return string ログ用の値
     */
    protected function convHashArg(array $arg): string {
        $datas = [];

        foreach ($arg as $name => $value)
            $datas[] = sprintf('%s:%s', $name, $this->convValueArg($value, true));

        return sprintf('[%s]', implode(',', $datas));
    }

    /**
     * パラメータ値をログ用に変換(その他のデータ型)
     * 
     * @since 1.02.00
     * @param mixed $arg パラメータ値
     * @param bool $isInner 再帰的かどうか
     * @return string ログ用の値
     */
    protected function convOtherArg(mixed $arg, bool $isInner = false): string {
        $maxLength = $isInner ? 30 : 100;

        $data = substr(json_encode($arg, JSON_UNESCAPED_UNICODE), 1, -1);
        if (strlen($data) > $maxLength - 3)
            $data = sprintf('%s...', substr($data, 0, $maxLength - 3));

        return $data;
    }
}