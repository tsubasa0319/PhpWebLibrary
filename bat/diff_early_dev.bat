@echo off
rem -------------------------------------------------------------------------------------
rem �v���O�����\�[�X�̕ύX�����𒊏o(�����J���p)
rem
rem History:
rem 0.10.00 2024/03/06 �쐬�B
rem 0.27.00 2024/06/22 ��r��̃u�����`���w�肷��悤�ɕύX�B
rem -------------------------------------------------------------------------------------

rem -------------------------------------------------------------------------------------
rem �萔��`

rem �����Z�b�g(Shift_JIS)
set CHARSET_SJIS=932

rem �����Z�b�g(UTF-8)
set CHARSET_UTF8=65001

rem ����̔�r���̃u�����`
set DEFAULT_BRANCH_BASE=backlog/test

rem ����̔�r��̃u�����`
set DEFAULT_BRANCH_CURRENT=test

rem -------------------------------------------------------------------------------------
rem ���ݒ�

rem �o�͐�f�B���N�g��
set output_dir=www\temp

rem �쐬�t�@�C����
set target_name=diff

rem -------------------------------------------------------------------------------------
rem ��r��/��r���ݒ�
set branch_base=%DEFAULT_BRANCH_BASE%
set branch_current=%DEFAULT_BRANCH_CURRENT%

rem -------------------------------------------------------------------------------------
rem �又��

rem �\�[�X�Ǘ��̃z�[���f�B���N�g�����擾
cd /d %~dp0
cd ..
set home_dir=%CD%

rem Git�փp�X��ʂ�
set Path=%Path%;%ProgramFiles%\Git\bin

rem �o�͐�f�B���N�g���𐶐�
set target_dir=%home_dir%\%output_dir%\%target_name%
if exist "%target_dir%" rmdir "%target_dir%" /s /q
mkdir "%target_dir%"

rem �����Z�b�g��UTF-8�֕ύX
chcp %CHARSET_UTF8%

rem Git�R�}���h��荷�����o�A�o�͐�f�B���N�g���փR�s�[
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

rem �����Z�b�g�����ɖ߂�
chcp %CHARSET_SJIS%

rem zip���k
echo Archive Start   To %home_dir%\%output_dir%
cd /d %home_dir%\%output_dir%
if exist "%target_name%.zip" del "%target_name%.zip"
powershell compress-archive "%target_name%/* "%target_name%"
echo Archive End

start explorer %home_dir%\%output_dir%