@echo off
setlocal EnableExtensions EnableDelayedExpansion
TITLE PocketMine-MP server software for Minecraft: Bedrock Edition
cd /d %~dp0

set PHP_BINARY=
set DASHBOARD=
set POWERSHELL_EXE=%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe

if /I "%1"=="--dashboard" (
	set DASHBOARD=1
	shift
)

where /q php.exe
if %ERRORLEVEL%==0 (
	set PHP_BINARY=php
)

if exist bin\php\php.exe (
	rem always use the local PHP binary if it exists
	set PHPRC=""
	set PHP_BINARY=bin\php\php.exe
)

if "%PHP_BINARY%"=="" (
	echo Couldn't find a PHP binary in system PATH or "%~dp0bin\php"
	echo Please refer to the installation instructions at https://doc.pmmp.io/en/rtfd/installation.html
	pause
	exit 1
)

if exist src\PocketMine.php (
	if exist vendor\autoload.php (
		set POCKETMINE_FILE=src\PocketMine.php
	) else (
		set POCKETMINE_FILE=
	)
) else (
	set POCKETMINE_FILE=
)

if "%POCKETMINE_FILE%"=="" (
	if exist BeeltyMine-MP.phar (
		set POCKETMINE_FILE=BeeltyMine-MP.phar
	) else (
		echo Neither source bootstrap nor BeeltyMine-MP.phar was found
		pause
		exit 1
	)
)

set EXTRA_PM_ARGS=%*

if defined DASHBOARD (
	set STATS_FILE=%CD%\diagnostics\dashboard\server-stats.json
	if not exist "%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" (
		set POWERSHELL_EXE=powershell
	)
	if not exist "%CD%\diagnostics\dashboard" mkdir "%CD%\diagnostics\dashboard"
	echo [%date% %time%] dashboard launch requested>"%CD%\diagnostics\dashboard\launcher.log"
	start "" "!POWERSHELL_EXE!" -NoProfile -STA -ExecutionPolicy Bypass -File "%CD%\dashboard.ps1" -RepoRoot "%CD%" -PhpBinary "%PHP_BINARY%" -PocketMineFile "%POCKETMINE_FILE%" -StatsFile "!STATS_FILE!"
	set EXTRA_PM_ARGS=--stats-file="!STATS_FILE!"
)

if exist bin\mintty.exe (
	start "" bin\mintty.exe -o Columns=88 -o Rows=32 -o AllowBlinking=0 -o FontQuality=3 -o Font="Consolas" -o FontHeight=10 -o CursorType=0 -o CursorBlinks=1 -h error -t "BeeltyMine-MP" -i bin/pocketmine.ico -w max %PHP_BINARY% %POCKETMINE_FILE% --enable-ansi %EXTRA_PM_ARGS%
) else (
	REM pause on exitcode != 0 so the user can see what went wrong
	%PHP_BINARY% %POCKETMINE_FILE% %EXTRA_PM_ARGS% || pause
)