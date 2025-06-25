@echo off
rem -------------------------------------------------------------------------------------
rem プログラムソースの変更差分を抽出
rem
rem History:
rem 0.00.00 2024/01/18 作成。
rem 0.27.00 2024/06/22 比較先のブランチも指定するように変更。
rem -------------------------------------------------------------------------------------

rem -------------------------------------------------------------------------------------
rem 定数定義

rem 文字セット(Shift_JIS)
set CHARSET_SJIS=932

rem 文字セット(UTF-8)
set CHARSET_UTF8=65001

rem 既定の比較元のブランチ
set DEFAULT_BRANCH_BASE=backlog/master

rem 既定の比較先のブランチ
set DEFAULT_BRANCH_CURRENT=master

rem -------------------------------------------------------------------------------------
rem 環境設定

rem 出力先ディレクトリ
set output_dir=www\temp

rem 作成ファイル名
set target_name=diff

rem -------------------------------------------------------------------------------------
rem プロンプト
set /p branch_base=比較元のブランチを入力してください。(既定値:%DEFAULT_BRANCH_BASE%)
if "%branch_base%"=="" set branch_base=%DEFAULT_BRANCH_BASE%

set /p branch_current=比較先のブランチを入力してください。(既定値:%DEFAULT_BRANCH_CURRENT%)
if "%branch_current%"=="" set branch_current=%DEFAULT_BRANCH_CURRENT%

rem -------------------------------------------------------------------------------------
rem 主処理

rem ソース管理のホームディレクトリを取得
cd /d %~dp0
cd ..
set home_dir=%CD%

rem Gitへパスを通す
set Path=%Path%;%ProgramFiles%\Git\bin

rem 出力先ディレクトリを生成
set target_dir=%home_dir%\%output_dir%\%target_name%
if exist "%target_dir%" rmdir "%target_dir%" /s /q
mkdir "%target_dir%"

rem 文字セットをUTF-8へ変更
chcp %CHARSET_UTF8%

rem Gitコマンドより差分抽出、出力先ディレクトリへコピー
echo Export Start   To %target_dir%
cd /d %home_dir%
git config --global core.quotepath false
for /f %%z in ('git diff %branch_base% %branch_current% --name-only') do call :f01_start "%%z"
goto :f01_end
:f01_start
echo F | xcopy "%~dpf1" "%target_dir%\%~1"> nul
goto :EOF
:f01_end
echo Export End
pause

rem 文字セットを元に戻す
chcp %CHARSET_SJIS%

rem zip圧縮
echo Archive Start   To %home_dir%\%output_dir%
cd /d %home_dir%\%output_dir%
if exist "%target_name%.zip" del "%target_name%.zip"
powershell compress-archive "%target_name%/* "%target_name%"
echo Archive End
pause

start explorer %home_dir%\%output_dir%

exit /b