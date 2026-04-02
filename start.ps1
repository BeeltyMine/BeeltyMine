[CmdletBinding(PositionalBinding=$false)]
param (
	[string]$php = "",
	[switch]$Loop = $false,
	[switch]$Dashboard = $false,
	[string]$file = "",
	[string][Parameter(ValueFromRemainingArguments)]$extraPocketMineArgs
)

if($php -ne ""){
	$binary = $php
}elseif(Test-Path "bin\php\php.exe"){
	$env:PHPRC = ""
	$binary = "bin\php\php.exe"
}elseif((Get-Command php -ErrorAction SilentlyContinue)){
	$binary = "php"
}else{
	echo "Couldn't find a PHP binary in system PATH or $pwd\bin\php"
	echo "Please refer to the installation instructions at https://doc.pmmp.io/en/rtfd/installation.html"
	pause
	exit 1
}

if($file -eq ""){
	if(Test-Path "BeeltyMine-MP.phar"){
		$file = "BeeltyMine-MP.phar"
	}elseif((Test-Path "src\PocketMine.php") -and (Test-Path "vendor\autoload.php")){
		$file = "src\PocketMine.php"
	}else{
		echo "Neither BeeltyMine-MP.phar nor source bootstrap was found"
		pause
		exit 1
	}
}

echo "Launching $file"

$dashboardLaunched = $false
$powerShellExe = Join-Path $env:SystemRoot "System32\WindowsPowerShell\v1.0\powershell.exe"
if(-not (Test-Path $powerShellExe)){
	$powerShellExe = "powershell"
}

function StartServer{
	$serverArgs = @($extraPocketMineArgs)

	if($Dashboard){
		$statsFile = Join-Path $PSScriptRoot "diagnostics\dashboard\server-stats.json"
		if(-not $dashboardLaunched){
			$dashboardScript = Join-Path $PSScriptRoot "dashboard.ps1"
			New-Item -ItemType Directory -Force -Path (Join-Path $PSScriptRoot "diagnostics\dashboard") | Out-Null
			Set-Content -Path (Join-Path $PSScriptRoot "diagnostics\dashboard\launcher.log") -Encoding UTF8 -Value ("[{0}] dashboard launch requested" -f (Get-Date -Format "yyyy-MM-dd HH:mm:ss"))
			Start-Process $powerShellExe -ArgumentList @(
				"-NoProfile",
				"-STA",
				"-ExecutionPolicy", "Bypass",
				"-File", $dashboardScript,
				"-RepoRoot", $PSScriptRoot,
				"-PhpBinary", $binary,
				"-PocketMineFile", $file,
				"-StatsFile", $statsFile
			) | Out-Null
			$script:dashboardLaunched = $true
		}

		$serverArgs = @("--stats-file=$statsFile") + $serverArgs
	}

	& $binary $file @serverArgs
}

$loops = 0

StartServer

while($Loop){
	if($loops -ne 0){
		echo ("Restarted " + $loops + " times")
	}
	$loops++
	echo "To escape the loop, press CTRL+C now. Otherwise, wait 5 seconds for the server to restart."
	echo ""
	Start-Sleep 5
	StartServer
}
