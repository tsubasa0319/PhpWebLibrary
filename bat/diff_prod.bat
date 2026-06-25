@echo off
rem -------------------------------------------------------------------------------------
rem プログラムソースの変更差分を抽出(本番環境用)
rem
rem History:
rem 0.77.00 2025/02/28 作成。
rem -------------------------------------------------------------------------------------

rem -------------------------------------------------------------------------------------
rem 定数定義

rem 文字セット(Shift_JIS)
set CHARSET_SJIS=932

rem 文字セット(UTF-8)
set CHARSET_UTF8=65001

rem 既定の比較元のブランチ
set DEFAULT_BRANCH_BASE=backlog/main

rem 既定の比較先のブランチ
set DEFAULT_BRANCH_CURRENT=main

rem 既定の比較元のブランチ(旧)
set DEFAULT_BRANCH_BASE_OLD=backlog/master

rem 既定の比較先のブランチ(旧)
set DEFAULT_BRANCH_CURRENT_OLD=master

rem -------------------------------------------------------------------------------------
rem 環境設定

rem 出力先ディレクトリ
set output_dir=www\temp

rem 作成ファイル名
set target_name=diff

rem -------------------------------------------------------------------------------------
rem 事前処理

rem ソース管理のホームディレクトリを取得
cd /d %~dp0
cd ..
set home_dir=%CD%

rem Gitへパスを通す
set Path=%Path%;%ProgramFiles%\Git\bin

rem -------------------------------------------------------------------------------------
rem 比較元/比較先を設定
set branch_base=%DEFAULT_BRANCH_BASE%
set branch_current=%DEFAULT_BRANCH_CURRENT%

rem 旧既定ブランチ
set exist_branch=0
for /f %%z in ('git rev-parse --verify --quiet %branch_base%') do set exist_branch=1
if %exist_branch% equ 0 set branch_base=%DEFAULT_BRANCH_BASE_OLD%
if %exist_branch% equ 0 set branch_current=%DEFAULT_BRANCH_CURRENT_OLD%

set exist_branch=0
for /f %%z in ('git rev-parse --verify --quiet %branch_base%') do set exist_branch=1
if %exist_branch% equ 0 echo Not exist remote branch %branch_base%
if %exist_branch% equ 0 pause
if %exist_branch% equ 0 exit

set exist_branch=0
for /f %%z in ('git rev-parse --verify --quiet %branch_current%') do set exist_branch=1
if %exist_branch% equ 0 echo Not exist local branch %branch_current%
if %exist_branch% equ 0 pause
if %exist_branch% equ 0 exit

rem -------------------------------------------------------------------------------------
rem 主処理

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

rem 文字セットを元に戻す
chcp %CHARSET_SJIS%

rem zip圧縮
echo Archive Start   To %home_dir%\%output_dir%
cd /d %home_dir%\%output_dir%
if exist "%target_name%.zip" del "%target_name%.zip"
powershell compress-archive "%target_name%/* "%target_name%"
echo Archive End

start explorer %home_dir%\%output_dir%