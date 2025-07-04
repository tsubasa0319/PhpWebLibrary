<?php
// -------------------------------------------------------------------------------------------------
// 予約語(Microsoft SQL Server)
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;

/**
 * 予約語(Microsoft SQL Server)
 * 
 * @since 0.00.00
 * @version 0.00.00
 */
class ReservedWordsMssql {
    // ---------------------------------------------------------------------------------------------
    // メソッド(静的)
    /**
     * 予約語を取得
     * 
     * @return string[]
     */
    static public function getWords() {
        return [
            'add',
            'all',
            'alter',
            'and',
            'any',
            'as',
            'asc',
            'authorization',

            'backup',
            'begin',
            'between',
            'break',
            'browse',
            'bulk',
            'by',

            'cascade',
            'case',
            'check',
            'checkpoint',
            'close',
            'clustered',
            'coalesce',
            'collate',
            'column',
            'commit',
            'compute',
            'constraint',
            'contains',
            'containstable',
            'continue',
            'convert',
            'create',
            'cross',
            'current',
            'current_date',
            'current_time',
            'current_timestamp',
            'current_user',
            'cursor',

            'database',
            'dbcc',
            'deallocate',
            'declare',
            'default',
            'delete',
            'deny',
            'desc',
            'disk',
            'distinct',
            'distributed',
            'double',
            'drop',
            'dump',

            'else',
            'end',
            'errlvl',
            'escape',
            'except',
            'exec',
            'execute',
            'exists',
            'exit',
            'external',

            'fetch',
            'file',
            'fillfactor',
            'for',
            'foreign',
            'freetext',
            'freetexttable',
            'from',
            'full',
            'function',

            'goto',
            'grant',
            'group',

            'having',
            'holdlock',

            'identity',
            'identity_insert',
            'identitycol',
            'if',
            'in',
            'index',
            'inner',
            'insert',
            'intersect',
            'into',
            'is',

            'join',

            'key',
            'kill',

            'left',
            'like',
            'lineno',
            'load',

            'merge',

            'national',
            'nocheck',
            'nonclustered',
            'not',
            'null',
            'nullif',

            'of',
            'off',
            'offsets',
            'on',
            'open',
            'opendatasource',
            'openquery',
            'openrowset',
            'openxml',
            'option',
            'or',
            'order',
            'outer',
            'over',
            
            'percent',
            'pivot',
            'plan',
            'precision',
            'primary',
            'print',
            'proc',
            'procedure',
            'public',
            
            'raiserror',
            'read',
            'readtext',
            'reconfigure',
            'references',
            'replication',
            'restore',
            'restrict',
            'return',
            'revert',
            'revoke',
            'right',
            'rollback',
            'rowcount',
            'rowguidcol',
            'rule',

            'save',
            'schema',
            'securityaudit',
            'select',
            'semantickeyphrasetable',
            'semanticsimilaritydetailstable',
            'semanticsimilaritytable',
            'session_user',
            'set',
            'setuser',
            'shutdown',
            'some',
            'statistics',
            'system_user',

            'table',
            'tablesample',
            'textsize',
            'then',
            'to',
            'top',
            'tran',
            'transaction',
            'trigger',
            'truncate',
            'try_convert',
            'tsequal',

            'union',
            'unique',
            'unpivot',
            'update',
            'updatetext',
            'use',
            'user',

            'values',
            'varying',
            'view',

            'waitfor',
            'when',
            'where',
            'while',
            'with',
            'within group',
            'writetext'
       ];
    }
}