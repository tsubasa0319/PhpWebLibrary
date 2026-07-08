<?php
// -------------------------------------------------------------------------------------------------
// 外部設定クラス
//
// History:
// 1.06.00 2026/06/05 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\system;

/**
 * 外部設定クラス
 * 
 * @since 1.06.00
 * @version 1.06.00
 */
abstract class ExternalConfig {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** 機密ディレクトリ名 */
    protected const CONFIDENTIAL_DIR = '.confidential';

    // ---------------------------------------------------------------------------------------------
    // プロパティ(静的)
    /** @var array<string, array<string, string>> パス別キャッシュ */
    protected static array $cache = [];

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    // .confidential ディレクトリを置く親ディレクトリのパスを返す
    abstract protected function getConfidentialBaseDir(): string;

    // システム名を返す(.confidential 配下のディレクトリ名と一致)
    abstract protected function getSystemName(): string;

    /**
     * 設定値を取得
     * 
     * @param string $key キー名
     * @return string|null 設定値(キーが存在しない場合は null)
     */
    protected function getValue(string $key): ?string {
        $path = $this->resolveConfigPath();
        if (!array_key_exists($path, self::$cache))
            self::$cache[$path] = $this->parseEnvFile($path);

        return self::$cache[$path][$key] ?? null;
    }

    /**
     * .env ファイルのパスを組み立て
     * 
     * @return string .env ファイルの絶対パス
     */
    protected function resolveConfigPath(): string {
        return $this->getConfidentialBaseDir()
            . '/' . self::CONFIDENTIAL_DIR
            . '/' . $this->getSystemName() . '/.env';
    }

    /**
     * .env ファイルを解析
     * 
     * @param string $path .env ファイルの絶対パス
     * @return array<string, string> キーと値のマップ
     */
    protected function parseEnvFile(string $path): array {
        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            [$key, $value] = explode('=', $line, 2);
            $value = trim($value);
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $m)) $value = $m[2];
            $values[trim($key)] = $value;
        }

        return $values;
    }
}