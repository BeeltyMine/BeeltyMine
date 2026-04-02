@echo off
setlocal EnableExtensions EnableDelayedExpansion
title BeeltyMine server software for Minecraft: Bedrock Edition
cd /d "%~dp0"

rem =========================
rem Config / defaults
rem =========================
set "PHP_BINARY="
set "POWERSHELL_EXE=%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe"
set "POCKETMINE_FILE="
set "DASHBOARD="
set "EXTRA_PM_ARGS="
set /a LOOPS=0

rem =========================
rem Parse optional first arg
rem =========================
if /I "%~1"=="--dashboard" (
	set "DASHBOARD=1"
	shift
)

rem Preserve remaining args after optional shift
set "EXTRA_PM_ARGS=%*"

rem =========================
rem Resolve PHP binary
rem =========================
where /q php.exe
if not errorlevel 1 (
	set "PHP_BINARY=php"
)

if exist "bin\php\php.exe" (
	rem Always prefer local PHP if present
	set "PHPRC="
	set "PHP_BINARY=%CD%\bin\php\php.exe"
)

if not defined PHP_BINARY (
	echo Couldn't find a PHP binary in system PATH or "%CD%\bin\php"
	echo Please refer to the installation instructions at:
	echo https://doc.pmmp.io/en/rtfd/installation.html
	exit /b 1
)

rem =========================
rem Resolve PocketMine entrypoint
rem =========================
if exist "BeeltyMine-MP.phar" (
	set "POCKETMINE_FILE=%CD%\BeeltyMine-MP.phar"
) else if exist "src\PocketMine.php" if exist "vendor\autoload.php" (
	set "POCKETMINE_FILE=%CD%\src\PocketMine.php"
)

if not defined POCKETMINE_FILE (
	echo Neither BeeltyMine-MP.phar nor source bootstrap was found.
	exit /b 1
)

echo Launching "%POCKETMINE_FILE%"

rem =========================
rem Optional dashboard launcher
rem =========================
if defined DASHBOARD (
	set "STATS_FILE=%CD%\diagnostics\dashboard\server-stats.json"

	if not exist "%POWERSHELL_EXE%" (
		set "POWERSHELL_EXE=powershell"
	)

	if not exist "%CD%\diagnostics\dashboard" (
		mkdir "%CD%\diagnostics\dashboard"
	)

	> "%CD%\diagnostics\dashboard\launcher.log" echo [%date% %time%] dashboard launch requested

	start "" "!POWERSHELL_EXE!" ^
		-NoProfile ^
		-STA ^
		-ExecutionPolicy Bypass ^
		-File "%CD%\dashboard.ps1" ^
		-RepoRoot "%CD%" ^
		-PhpBinary "%PHP_BINARY%" ^
		-PocketMineFile "%POCKETMINE_FILE%" ^
		-StatsFile "!STATS_FILE!"

	if defined EXTRA_PM_ARGS (
		set "EXTRA_PM_ARGS=--stats-file=""!STATS_FILE!"" !EXTRA_PM_ARGS!"
	) else (
		set "EXTRA_PM_ARGS=--stats-file=""!STATS_FILE!"""
	)
)

:server_loop
if !LOOPS! GTR 0 echo Restarted !LOOPS! times

if exist "bin\mintty.exe" (
	"bin\mintty.exe" ^
		-o Columns=88 ^
		-o Rows=32 ^
		-o AllowBlinking=0 ^
		-o FontQuality=3 ^
		-o Font="Consolas" ^
		-o FontHeight=10 ^
		-o CursorType=0 ^
		-o CursorBlinks=1 ^
		-h error ^
		-t "BeeltyMine-MP" ^
		-i "bin/pocketmine.ico" ^
		-w max ^
		"%PHP_BINARY%" "%POCKETMINE_FILE%" --enable-ansi %EXTRA_PM_ARGS%
) else (
	"%PHP_BINARY%" "%POCKETMINE_FILE%" %EXTRA_PM_ARGS%
)

set "EXIT_CODE=%ERRORLEVEL%"

if not "%EXIT_CODE%"=="0" (
	if not "%EXIT_CODE%"=="137" if not "%EXIT_CODE%"=="143" (
		echo(
		echo WARNING: Server did not shut down correctly! ^(code %EXIT_CODE%^)
		echo(
	)
	exit /b %EXIT_CODE%
)

echo To stop auto restart, press CTRL+C now. Otherwise, wait 5 seconds for the server to restart.
echo(
timeout /t 5 /nobreak >nul
set /a LOOPS+=1
goto :server_loop
