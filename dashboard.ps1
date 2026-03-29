[CmdletBinding()]
param(
	[string]$RepoRoot = "",
	[string]$StatsFile = "",
	[string]$PhpBinary = "",
	[string]$PocketMineFile = ""
)

function ConvertTo-QuotedArg {
	param([string]$Value)

	if($Value -eq $null){
		return '""'
	}

	return '"' + ($Value -replace '"', '\"') + '"'
}

if([Threading.Thread]::CurrentThread.ApartmentState -ne [Threading.ApartmentState]::STA){
	$selfPath = $MyInvocation.MyCommand.Path
	$relaunchArgs = @(
		"-NoProfile",
		"-STA",
		"-ExecutionPolicy", "Bypass",
		"-File", (ConvertTo-QuotedArg $selfPath),
		"-RepoRoot", (ConvertTo-QuotedArg $RepoRoot),
		"-StatsFile", (ConvertTo-QuotedArg $StatsFile),
		"-PhpBinary", (ConvertTo-QuotedArg $PhpBinary),
		"-PocketMineFile", (ConvertTo-QuotedArg $PocketMineFile)
	) -join " "
	Start-Process powershell -ArgumentList $relaunchArgs | Out-Null
	exit 0
}

Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
Add-Type -AssemblyName System.Windows.Forms.DataVisualization

if($RepoRoot -eq ""){
	$RepoRoot = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
}

if($StatsFile -eq ""){
	$StatsFile = Join-Path $RepoRoot "diagnostics\dashboard\server-stats.json"
}

if($PhpBinary -eq ""){
	$PhpBinary = "php"
}

$DiagnosticsRoot = Split-Path -Parent $StatsFile
$TodoPath = Join-Path $RepoRoot "TODO.md"
$AnalysisPath = Join-Path $DiagnosticsRoot "latest-analysis.md"
$TestResultsPath = Join-Path $DiagnosticsRoot "test-results.json"
$DashboardErrorLog = Join-Path $DiagnosticsRoot "dashboard-error.log"
$ServerLogPath = Join-Path $RepoRoot "server.log"

New-Item -ItemType Directory -Force -Path $DiagnosticsRoot | Out-Null

$script:StatsData = $null
$script:StatsLastWrite = $null
$script:TodoLastWrite = $null
$script:AnalysisLastWrite = $null
$script:TestResults = @()
$script:ActiveTest = $null
$script:TestOutputQueue = [System.Collections.Concurrent.ConcurrentQueue[string]]::new()
$script:QueryConfig = $null
$script:QueryLastAttempt = $null
$script:LastQueryStats = $null
$script:CurrentTelemetryMode = "waiting"

function Get-FileText {
	param([string]$Path)

	if(Test-Path $Path){
		return [System.IO.File]::ReadAllText($Path)
	}

	return ""
}

function Get-JsonFile {
	param([string]$Path)

	if(!(Test-Path $Path)){
		return $null
	}

	try{
		return Get-Content -Raw -Path $Path | ConvertFrom-Json
	}catch{
		return $null
	}
}

function Get-RecentServerLogLines {
	param([int]$Tail = 250)

	if(!(Test-Path $ServerLogPath)){
		return @()
	}

	try{
		return @(Get-Content -Path $ServerLogPath -Tail $Tail -ErrorAction Stop)
	}catch{
		return @()
	}
}

function New-LiveFinding {
	param(
		[string]$Severity,
		[string]$Area,
		[string]$Title,
		[string]$Detail
	)

	return [pscustomobject]@{
		Severity = $Severity
		Area = $Area
		Title = $Title
		Detail = $Detail
	}
}

function Get-SeverityRank {
	param([string]$Severity)

	switch($Severity){
		"Critical" { return 3 }
		"Warning" { return 2 }
		"Info" { return 1 }
		default { return 0 }
	}
}

function Get-HealthPalette {
	param([string]$State)

	switch($State){
		"good" {
			return @{
				Back = [System.Drawing.Color]::FromArgb(18, 55, 42)
				Border = [System.Drawing.Color]::FromArgb(47, 196, 125)
				Fore = [System.Drawing.Color]::FromArgb(229, 255, 241)
			}
		}
		"warn" {
			return @{
				Back = [System.Drawing.Color]::FromArgb(64, 49, 19)
				Border = [System.Drawing.Color]::FromArgb(245, 179, 65)
				Fore = [System.Drawing.Color]::FromArgb(255, 245, 219)
			}
		}
		"bad" {
			return @{
				Back = [System.Drawing.Color]::FromArgb(78, 25, 31)
				Border = [System.Drawing.Color]::FromArgb(255, 95, 109)
				Fore = [System.Drawing.Color]::FromArgb(255, 230, 233)
			}
		}
		default {
			return @{
				Back = [System.Drawing.Color]::FromArgb(28, 31, 38)
				Border = [System.Drawing.Color]::FromArgb(92, 101, 117)
				Fore = [System.Drawing.Color]::FromArgb(238, 242, 248)
			}
		}
	}
}

function Set-ValueCardState {
	param(
		$CardRef,
		[string]$State
	)

	$palette = Get-HealthPalette -State $State
	$CardRef.Panel.BackColor = $palette.Back
	$CardRef.Panel.BorderStyle = [System.Windows.Forms.BorderStyle]::FixedSingle
	$CardRef.ValueLabel.ForeColor = $palette.Fore
}

function Set-BadgeState {
	param(
		[System.Windows.Forms.Label]$Badge,
		[string]$Text,
		[string]$State
	)

	$palette = Get-HealthPalette -State $State
	$Badge.Text = $Text
	$Badge.BackColor = $palette.Back
	$Badge.ForeColor = $palette.Fore
}

function Get-HealthStateFromFindings {
	param([object[]]$Findings)

	if(@($Findings | Where-Object Severity -eq "Critical").Count -gt 0){
		return "bad"
	}
	if(@($Findings | Where-Object Severity -eq "Warning").Count -gt 0){
		return "warn"
	}
	return "good"
}

function Get-LiveInsights {
	param(
		$Snapshot,
		[string]$TelemetryMode
	)

	$findings = New-Object System.Collections.Generic.List[object]
	$recentLogLines = Get-RecentServerLogLines

	if($TelemetryMode -eq "json"){
		# Full stats available.
	}elseif($TelemetryMode -eq "title+query"){
		$findings.Add((New-LiveFinding -Severity "Info" -Area "Telemetry" -Title "PHAR fallback mode" -Detail "Canli veri title + query ile geliyor. Deep timings verisi sinirli.")) | Out-Null
	}elseif($TelemetryMode -eq "query"){
		$findings.Add((New-LiveFinding -Severity "Warning" -Area "Telemetry" -Title "Sinirli telemetry" -Detail "Sunucu query ile goruluyor. TPS, load ve memory tam toplanamiyor.")) | Out-Null
	}elseif($TelemetryMode -eq "offline"){
		$findings.Add((New-LiveFinding -Severity "Critical" -Area "Server" -Title "Sunucuya erisilemiyor" -Detail "Ne stats dosyasi ne de query yaniti var.")) | Out-Null
	}

	if($null -ne $Snapshot){
		$tpsCurrent = $Snapshot.tps.current
		$loadCurrent = $Snapshot.load.current_percent
		$realMemory = $Snapshot.memory.real_mb

		if($null -ne $tpsCurrent){
			if([double]$tpsCurrent -lt 16){
				$findings.Add((New-LiveFinding -Severity "Critical" -Area "TPS" -Title "TPS cok dusuk" -Detail ("Anlik TPS {0}" -f $tpsCurrent))) | Out-Null
			}elseif([double]$tpsCurrent -lt 18.5){
				$findings.Add((New-LiveFinding -Severity "Warning" -Area "TPS" -Title "TPS dusuyor" -Detail ("Anlik TPS {0}" -f $tpsCurrent))) | Out-Null
			}
		}

		if($null -ne $loadCurrent){
			if([double]$loadCurrent -ge 85){
				$findings.Add((New-LiveFinding -Severity "Critical" -Area "Load" -Title "Load cok yuksek" -Detail ("Sunucu load % {0}" -f $loadCurrent))) | Out-Null
			}elseif([double]$loadCurrent -ge 60){
				$findings.Add((New-LiveFinding -Severity "Warning" -Area "Load" -Title "Load yukseliyor" -Detail ("Sunucu load % {0}" -f $loadCurrent))) | Out-Null
			}
		}

		if($null -ne $realMemory){
			if([double]$realMemory -ge 1024){
				$findings.Add((New-LiveFinding -Severity "Critical" -Area "Memory" -Title "Memory cok yuksek" -Detail ("Real memory {0} MB" -f $realMemory))) | Out-Null
			}elseif([double]$realMemory -ge 650){
				$findings.Add((New-LiveFinding -Severity "Warning" -Area "Memory" -Title "Memory yukseliyor" -Detail ("Real memory {0} MB" -f $realMemory))) | Out-Null
			}
		}

		foreach($world in @($Snapshot.worlds)){
			if($null -ne $world.tick_time_ms -and $world.tick_time_ms -ne "N/A"){
				$worldTick = [double]$world.tick_time_ms
				if($worldTick -ge 40){
					$findings.Add((New-LiveFinding -Severity "Critical" -Area "World" -Title ("World spike: {0}" -f $world.name) -Detail ("Tick time {0} ms" -f $worldTick))) | Out-Null
				}elseif($worldTick -ge 20){
					$findings.Add((New-LiveFinding -Severity "Warning" -Area "World" -Title ("World yavasliyor: {0}" -f $world.name) -Detail ("Tick time {0} ms" -f $worldTick))) | Out-Null
				}
			}
		}
	}

	$gcDurations = @()
	foreach($line in $recentLogLines){
		if($line -match 'Cyclic Garbage Collector\] Run #\d+ took ([0-9.]+) ms'){
			$gcDurations += [double]$matches[1]
		}
	}
	if($gcDurations.Count -gt 0){
		$gcMax = ($gcDurations | Measure-Object -Maximum).Maximum
		if($gcMax -ge 45){
			$findings.Add((New-LiveFinding -Severity "Critical" -Area "GC" -Title "Garbage collector spike" -Detail ("Son loglarda GC en fazla {0} ms" -f [math]::Round($gcMax, 2)))) | Out-Null
		}elseif($gcMax -ge 25){
			$findings.Add((New-LiveFinding -Severity "Warning" -Area "GC" -Title "GC maliyeti yuksek" -Detail ("Son loglarda GC en fazla {0} ms" -f [math]::Round($gcMax, 2)))) | Out-Null
		}
	}

	foreach($line in $recentLogLines | Where-Object { $_ -match '\[WARNING\]|\[ERROR\]' } | Select-Object -Last 4){
		if($line -match 'MySQL baglantisi kurulamadi'){
			$findings.Add((New-LiveFinding -Severity "Warning" -Area "Plugin" -Title "MySQL baglantisi yok" -Detail ($line.Trim()))) | Out-Null
		}elseif($line -match 'tick overload'){
			$findings.Add((New-LiveFinding -Severity "Critical" -Area "Tick" -Title "Tick overload" -Detail ($line.Trim()))) | Out-Null
		}else{
			$findings.Add((New-LiveFinding -Severity "Info" -Area "Log" -Title "Recent warning" -Detail ($line.Trim()))) | Out-Null
		}
	}

	foreach($result in @($script:TestResults | Sort-Object finished_at -Descending | Select-Object -First 3)){
		if($result.status -eq "Failed"){
			$findings.Add((New-LiveFinding -Severity "Warning" -Area "Tests" -Title ("{0} failed" -f $result.name) -Detail ("Exit {0} | {1}s" -f $result.exit_code, $result.duration_seconds))) | Out-Null
		}
	}

	$activePlayers = @()
	if($null -ne $Snapshot -and $null -ne $Snapshot.players -and $null -ne $Snapshot.players.names){
		$activePlayers = @($Snapshot.players.names | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
	}

	$orderedFindings = @($findings | Sort-Object @{Expression = { Get-SeverityRank $_.Severity }; Descending = $true}, Area, Title)
	$healthState = Get-HealthStateFromFindings -Findings $orderedFindings

	return [pscustomobject]@{
		HealthState = $healthState
		Findings = $orderedFindings
		ActivePlayers = $activePlayers
		LogLines = $recentLogLines
	}
}

function New-MergedSnapshot {
	param(
		$titleStats,
		$queryStats
	)

	if($null -eq $titleStats -and $null -eq $queryStats){
		return $null
	}

	$playerNames = @()
	$onlinePlayers = $null
	$maxPlayers = $null
	$worldName = "world"
	$minecraftVersion = $null

	if($null -ne $queryStats){
		$playerNames = @($queryStats.players.names | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
		$onlinePlayers = $queryStats.players.online
		$maxPlayers = $queryStats.players.max
		$worldName = $queryStats.query.world
		$minecraftVersion = $queryStats.server.minecraft_version
	}

	if($null -eq $onlinePlayers -and $null -ne $titleStats){
		$onlinePlayers = $titleStats.Online
		$maxPlayers = $titleStats.MaxPlayers
	}

	return [pscustomobject]@{
		running = $true
		uptime_seconds = $null
		server = [pscustomobject]@{
			name = "BeeltyMine"
			version = if($null -ne $titleStats){ $titleStats.Version }else{ $queryStats.server.version }
			minecraft_version = $minecraftVersion
			tick = $null
			threads = if($null -ne $titleStats){ $titleStats.Threads }else{ $null }
		}
		tps = [pscustomobject]@{
			current = if($null -ne $titleStats){ $titleStats.Tps }else{ $null }
			average = if($null -ne $titleStats){ $titleStats.Tps }else{ $null }
		}
		load = [pscustomobject]@{
			current_percent = if($null -ne $titleStats){ $titleStats.LoadPercent }else{ $null }
			average_percent = if($null -ne $titleStats){ $titleStats.LoadPercent }else{ $null }
		}
		memory = [pscustomobject]@{
			main_mb = if($null -ne $titleStats){ $titleStats.MainMemoryMb }else{ $null }
			global_mb = $null
			real_mb = if($null -ne $titleStats){ $titleStats.RealMemoryMb }else{ $null }
			low_memory = $null
		}
		players = [pscustomobject]@{
			online = $onlinePlayers
			max = $maxPlayers
			connecting = $null
			names = $playerNames
		}
		network = [pscustomobject]@{
			upload_kb_s = if($null -ne $titleStats){ $titleStats.UploadKb }else{ $null }
			download_kb_s = if($null -ne $titleStats){ $titleStats.DownloadKb }else{ $null }
			connections = $null
			valid_connections = $null
		}
		query = if($null -ne $queryStats){ $queryStats.query }else{ $null }
		worlds = @(
			[pscustomobject]@{
				name = $worldName
				folder = $worldName
				players = $onlinePlayers
				loaded_chunks = "N/A"
				ticking_chunks = "N/A"
				tick_time_ms = "N/A"
			}
		)
	}
}

function Get-ServerPropertyMap {
	param([string]$Path)

	$properties = @{}
	if(!(Test-Path $Path)){
		return $properties
	}

	foreach($line in [System.IO.File]::ReadLines($Path)){
		if([string]::IsNullOrWhiteSpace($line) -or $line.StartsWith("#")){
			continue
		}

		$separatorIndex = $line.IndexOf("=")
		if($separatorIndex -lt 1){
			continue
		}

		$key = $line.Substring(0, $separatorIndex).Trim()
		$value = $line.Substring($separatorIndex + 1).Trim()
		$properties[$key] = $value
	}

	return $properties
}

function Get-QueryConfig {
	$serverPropertiesPath = Join-Path $RepoRoot "server.properties"
	$properties = Get-ServerPropertyMap -Path $serverPropertiesPath

	$queryEnabled = $true
	if($properties.ContainsKey("enable-query")){
		$queryEnabled = $properties["enable-query"] -match '^(on|true|1|yes)$'
	}

	$port = 19132
	if($properties.ContainsKey("server-port")){
		[void][int]::TryParse($properties["server-port"], [ref]$port)
	}

	$queryHost = "127.0.0.1"
	if($properties.ContainsKey("server-ip")){
		$serverIp = $properties["server-ip"]
		if(-not [string]::IsNullOrWhiteSpace($serverIp) -and $serverIp -ne "0.0.0.0" -and $serverIp -ne "::"){
			$queryHost = $serverIp
		}
	}

	return [pscustomobject]@{
		Enabled = $queryEnabled
		Host = $queryHost
		Port = $port
		PropertiesPath = $serverPropertiesPath
	}
}

function Get-ConsoleTitleStats {
	if(-not ("BeeltyMineDashboardNative" -as [type])){
		Add-Type @'
using System;
using System.Text;
using System.Runtime.InteropServices;
public static class BeeltyMineDashboardNative{
	public delegate bool EnumWindowsProc(IntPtr hWnd, IntPtr lParam);
	[DllImport("user32.dll")] public static extern bool EnumWindows(EnumWindowsProc callback, IntPtr lParam);
	[DllImport("user32.dll")] public static extern bool IsWindowVisible(IntPtr hWnd);
	[DllImport("user32.dll")] public static extern int GetWindowTextLength(IntPtr hWnd);
	[DllImport("user32.dll")] public static extern int GetWindowText(IntPtr hWnd, StringBuilder text, int count);
}
'@
	}

	$titles = New-Object System.Collections.Generic.List[string]
	[BeeltyMineDashboardNative]::EnumWindows({
		param($windowHandle, $lParam)

		if(-not [BeeltyMineDashboardNative]::IsWindowVisible($windowHandle)){
			return $true
		}

		$titleLength = [BeeltyMineDashboardNative]::GetWindowTextLength($windowHandle)
		if($titleLength -le 0){
			return $true
		}

		$titleBuilder = New-Object System.Text.StringBuilder ($titleLength + 1)
		[void][BeeltyMineDashboardNative]::GetWindowText($windowHandle, $titleBuilder, $titleBuilder.Capacity)
		$titleText = $titleBuilder.ToString()
		if($titleText -match '(?i)(BeeltyMine|PocketMine).*\|\s*Online\s+\d+/\d+'){
			[void]$titles.Add($titleText)
		}

		return $true
	}, [IntPtr]::Zero) | Out-Null

	$bestTitle = $titles |
		Where-Object { $_ -match '(?i)\|\s*TPS\s+[0-9.]+' } |
		Select-Object -First 1

	if([string]::IsNullOrWhiteSpace($bestTitle)){
		$bestTitle = $titles | Select-Object -First 1
	}

	if([string]::IsNullOrWhiteSpace($bestTitle)){
		return $null
	}

	$version = "-"
	$online = $null
	$maxPlayers = $null
	$mainMemory = $null
	$realMemory = $null
	$virtualMemory = $null
	$threads = $null
	$upload = $null
	$download = $null
	$tps = $null
	$load = $null

	if($bestTitle -match '(?i)^[^\d]*\s+([0-9][0-9A-Za-z\.\-]*)\s+\|\s+Online'){
		$version = $matches[1]
	}
	if($bestTitle -match '(?i)\|\s*Online\s+(\d+)\/(\d+)'){
		$online = [int]$matches[1]
		$maxPlayers = [int]$matches[2]
	}
	if($bestTitle -match '(?i)\|\s*Memory\s+([0-9.]+)\/([0-9.]+)\/([0-9.]+)\s+MB\s+@\s+(\d+)\s+threads'){
		$mainMemory = [double]$matches[1]
		$realMemory = [double]$matches[2]
		$virtualMemory = [double]$matches[3]
		$threads = [int]$matches[4]
	}
	if($bestTitle -match '(?i)\|\s*U\s+([0-9.]+)\s+D\s+([0-9.]+)\s+kB\/s'){
		$upload = [double]$matches[1]
		$download = [double]$matches[2]
	}
	if($bestTitle -match '(?i)\|\s*TPS\s+([0-9.]+)'){
		$tps = [double]$matches[1]
	}
	if($bestTitle -match '(?i)\|\s*Load\s+([0-9.]+)%'){
		$load = [double]$matches[1]
	}

	return [pscustomobject]@{
		Title = $bestTitle
		Version = $version
		Online = $online
		MaxPlayers = $maxPlayers
		MainMemoryMb = $mainMemory
		RealMemoryMb = $realMemory
		VirtualMemoryMb = $virtualMemory
		Threads = $threads
		UploadKb = $upload
		DownloadKb = $download
		Tps = $tps
		LoadPercent = $load
	}
}

function ConvertTo-BigEndianInt32Bytes {
	param([int]$Value)

	$bytes = [System.BitConverter]::GetBytes($Value)
	if([System.BitConverter]::IsLittleEndian){
		[Array]::Reverse($bytes)
	}
	return $bytes
}

function Invoke-ServerQuery {
	param(
		[string]$QueryHost,
		[int]$QueryPort,
		[int]$TimeoutMs = 800
	)

		$udpClient = New-Object System.Net.Sockets.UdpClient
	try{
		$udpClient.Client.ReceiveTimeout = $TimeoutMs
		$udpClient.Client.SendTimeout = $TimeoutMs
		$udpClient.Connect($QueryHost, $QueryPort)

		$sessionId = Get-Random -Minimum 1 -Maximum 2147483647
		$sessionBytes = ConvertTo-BigEndianInt32Bytes -Value $sessionId

		$handshakePacket = New-Object byte[] 7
		$handshakePacket[0] = 0xFE
		$handshakePacket[1] = 0xFD
		$handshakePacket[2] = 0x09
		[Array]::Copy($sessionBytes, 0, $handshakePacket, 3, 4)
		[void]$udpClient.Send($handshakePacket, $handshakePacket.Length)

		$remoteEndPoint = New-Object System.Net.IPEndPoint([System.Net.IPAddress]::Any, 0)
		[byte[]]$handshakeResponse = $udpClient.Receive([ref]$remoteEndPoint)
		if($handshakeResponse.Length -lt 6 -or $handshakeResponse[0] -ne 0x09){
			return $null
		}

		$tokenString = [System.Text.Encoding]::ASCII.GetString($handshakeResponse, 5, $handshakeResponse.Length - 5).Trim([char]0)
		$token = 0
		if(-not [int]::TryParse($tokenString, [ref]$token)){
			return $null
		}

		$tokenBytes = ConvertTo-BigEndianInt32Bytes -Value $token
		$statisticsPacket = New-Object byte[] 15
		$statisticsPacket[0] = 0xFE
		$statisticsPacket[1] = 0xFD
		$statisticsPacket[2] = 0x00
		[Array]::Copy($sessionBytes, 0, $statisticsPacket, 3, 4)
		[Array]::Copy($tokenBytes, 0, $statisticsPacket, 7, 4)
		$statisticsPacket[11] = 0xFF
		$statisticsPacket[12] = 0xFF
		$statisticsPacket[13] = 0xFF
		$statisticsPacket[14] = 0x01
		[void]$udpClient.Send($statisticsPacket, $statisticsPacket.Length)

		[byte[]]$statisticsResponse = $udpClient.Receive([ref]$remoteEndPoint)
		if($statisticsResponse.Length -lt 6 -or $statisticsResponse[0] -ne 0x00){
			return $null
		}

		$payloadText = [System.Text.Encoding]::UTF8.GetString($statisticsResponse, 5, $statisticsResponse.Length - 5)
		$playerMarker = "`0" + [char]1 + "player_`0`0"
		$markerIndex = $payloadText.IndexOf($playerMarker)
		if($markerIndex -lt 0){
			return $null
		}

		$keyValueText = $payloadText.Substring(0, $markerIndex)
		$playerText = $payloadText.Substring($markerIndex + $playerMarker.Length)

		$metadata = @{}
		$keyValueParts = $keyValueText -split "`0"
		for($i = 0; $i + 1 -lt $keyValueParts.Length; $i += 2){
			$key = $keyValueParts[$i]
			if([string]::IsNullOrWhiteSpace($key)){
				continue
			}
			$metadata[$key] = $keyValueParts[$i + 1]
		}

		$players = @()
		foreach($playerName in ($playerText.Trim([char]0) -split "`0")){
			if(-not [string]::IsNullOrWhiteSpace($playerName)){
				$players += $playerName
			}
		}

		$onlinePlayers = 0
		$maxPlayers = 0
		$hostPort = $QueryPort
		if($metadata.ContainsKey("numplayers")){
			[void][int]::TryParse($metadata["numplayers"], [ref]$onlinePlayers)
		}
		if($metadata.ContainsKey("maxplayers")){
			[void][int]::TryParse($metadata["maxplayers"], [ref]$maxPlayers)
		}
		if($metadata.ContainsKey("hostport")){
			[void][int]::TryParse($metadata["hostport"], [ref]$hostPort)
		}

		$mapName = if($metadata.ContainsKey("map")){ $metadata["map"] }else{ "-" }
		$motd = if($metadata.ContainsKey("hostname")){ $metadata["hostname"] }else{ "-" }
		$minecraftVersion = if($metadata.ContainsKey("version")){ $metadata["version"] }else{ "-" }
		$serverEngine = if($metadata.ContainsKey("server_engine")){ $metadata["server_engine"] }else{ "PocketMine-MP" }

		return [pscustomobject]@{
			source = "query"
			running = $true
			generated_at_unix = [DateTimeOffset]::Now.ToUnixTimeMilliseconds() / 1000
			uptime_seconds = $null
			server = [pscustomobject]@{
				name = $motd
				version = $serverEngine
				minecraft_version = $minecraftVersion
				tick = $null
				threads = $null
			}
			tps = [pscustomobject]@{
				current = $null
				average = $null
			}
			load = [pscustomobject]@{
				current_percent = $null
				average_percent = $null
			}
			memory = [pscustomobject]@{
				main_mb = $null
				global_mb = $null
				real_mb = $null
				low_memory = $null
			}
			players = [pscustomobject]@{
				online = $onlinePlayers
				max = $maxPlayers
				connecting = $null
				names = $players
			}
			network = [pscustomobject]@{
				upload_kb_s = $null
				download_kb_s = $null
				connections = $null
				valid_connections = $null
			}
			query = [pscustomobject]@{
				motd = $motd
				world = $mapName
				player_count = $onlinePlayers
				max_players = $maxPlayers
				player_list = $players
				host = $QueryHost
				port = $hostPort
			}
			worlds = @(
				[pscustomobject]@{
					name = $mapName
					folder = $mapName
					players = $onlinePlayers
					loaded_chunks = $null
					ticking_chunks = $null
					tick_time_ms = $null
				}
			)
		}
	}catch{
		return $null
	}finally{
		$udpClient.Dispose()
	}
}

function Save-TestResults {
	$script:TestResults |
		ConvertTo-Json -Depth 8 |
		Set-Content -Path $TestResultsPath -Encoding UTF8
}

function Load-TestResults {
	$loaded = Get-JsonFile -Path $TestResultsPath
	if($null -eq $loaded){
		$script:TestResults = @()
		return
	}

	if($loaded -is [System.Array]){
		$script:TestResults = @($loaded)
	}else{
		$script:TestResults = @($loaded)
	}
}

function ConvertTo-ProcessArgument {
	param([string]$Value)

	if($null -eq $Value){
		return '""'
	}

	if($Value -notmatch '[\s"]'){
		return $Value
	}

	$escaped = $Value -replace '(\\*)"', '$1$1\"'
	$escaped = $escaped -replace '(\\+)$', '$1$1'
	return '"' + $escaped + '"'
}

function Join-ProcessArguments {
	param([string[]]$Arguments)

	return (($Arguments | ForEach-Object { ConvertTo-ProcessArgument $_ }) -join " ")
}

function New-ValueCard {
	param(
		[string]$Title,
		[string]$InitialValue = "-"
	)

	$panel = New-Object System.Windows.Forms.Panel
	$panel.Dock = [System.Windows.Forms.DockStyle]::Fill
	$panel.Margin = New-Object System.Windows.Forms.Padding(8)
	$panel.Padding = New-Object System.Windows.Forms.Padding(12)
	$panel.BackColor = [System.Drawing.Color]::FromArgb(28, 31, 38)

	$titleLabel = New-Object System.Windows.Forms.Label
	$titleLabel.Text = $Title
	$titleLabel.Dock = [System.Windows.Forms.DockStyle]::Top
	$titleLabel.Font = New-Object System.Drawing.Font("Segoe UI Semibold", 9)
	$titleLabel.Height = 24
	$titleLabel.ForeColor = [System.Drawing.Color]::FromArgb(153, 164, 180)

	$valueLabel = New-Object System.Windows.Forms.Label
	$valueLabel.Text = $InitialValue
	$valueLabel.Dock = [System.Windows.Forms.DockStyle]::Fill
	$valueLabel.Font = New-Object System.Drawing.Font("Segoe UI", 15, [System.Drawing.FontStyle]::Bold)
	$valueLabel.ForeColor = [System.Drawing.Color]::FromArgb(238, 242, 248)

	[void]$panel.Controls.Add($valueLabel)
	[void]$panel.Controls.Add($titleLabel)

	return @{
		Panel = $panel
		ValueLabel = $valueLabel
	}
}

function New-LineChart {
	param(
		[string]$Title,
		[string]$SeriesName,
		[System.Drawing.Color]$LineColor
	)

	$chart = New-Object System.Windows.Forms.DataVisualization.Charting.Chart
	$chart.Dock = [System.Windows.Forms.DockStyle]::Fill
	$chart.BackColor = [System.Drawing.Color]::FromArgb(17, 19, 24)
	$chart.Palette = [System.Windows.Forms.DataVisualization.Charting.ChartColorPalette]::None
	$chart.PaletteCustomColors = @($LineColor)

	$chartArea = New-Object System.Windows.Forms.DataVisualization.Charting.ChartArea
	$chartArea.Name = "MainArea"
	$chartArea.AxisX.MajorGrid.Enabled = $false
	$chartArea.AxisX.LabelStyle.Enabled = $false
	$chartArea.AxisY.MajorGrid.LineColor = [System.Drawing.Color]::FromArgb(54, 61, 74)
	$chartArea.AxisY.MajorGrid.LineDashStyle = [System.Windows.Forms.DataVisualization.Charting.ChartDashStyle]::Dash
	$chartArea.AxisX.LineColor = [System.Drawing.Color]::FromArgb(76, 84, 99)
	$chartArea.AxisY.LineColor = [System.Drawing.Color]::FromArgb(76, 84, 99)
	$chartArea.AxisY.LabelStyle.ForeColor = [System.Drawing.Color]::FromArgb(191, 201, 214)
	$chartArea.BackColor = [System.Drawing.Color]::FromArgb(17, 19, 24)
	[void]$chart.ChartAreas.Add($chartArea)

	$legend = New-Object System.Windows.Forms.DataVisualization.Charting.Legend
	$legend.Docking = [System.Windows.Forms.DataVisualization.Charting.Docking]::Top
	$legend.Font = New-Object System.Drawing.Font("Segoe UI", 8)
	$legend.BackColor = [System.Drawing.Color]::FromArgb(17, 19, 24)
	$legend.ForeColor = [System.Drawing.Color]::FromArgb(225, 230, 237)
	[void]$chart.Legends.Add($legend)

	$series = New-Object System.Windows.Forms.DataVisualization.Charting.Series
	$series.Name = $SeriesName
	$series.ChartType = [System.Windows.Forms.DataVisualization.Charting.SeriesChartType]::Line
	$series.BorderWidth = 3
	$series.Color = $LineColor
	$series.ChartArea = "MainArea"
	[void]$chart.Series.Add($series)

	$titleObject = New-Object System.Windows.Forms.DataVisualization.Charting.Title
	$titleObject.Text = $Title
	$titleObject.Font = New-Object System.Drawing.Font("Segoe UI Semibold", 10)
	$titleObject.ForeColor = [System.Drawing.Color]::FromArgb(225, 230, 237)
	[void]$chart.Titles.Add($titleObject)

	return @{
		Chart = $chart
		Series = $series
	}
}

function Add-ChartPoint {
	param(
		[System.Windows.Forms.DataVisualization.Charting.Series]$Series,
		[double]$Value,
		[int]$MaxPoints = 120
	)

	[void]$Series.Points.AddY($Value)
	while($Series.Points.Count -gt $MaxPoints){
		$Series.Points.RemoveAt(0)
	}
}

function Set-RichText {
	param(
		[System.Windows.Forms.TextBoxBase]$Control,
		[string]$Text
	)

	if($Control.Text -ne $Text){
		$Control.Text = $Text
	}
}

function Append-OutputLine {
	param(
		[System.Windows.Forms.TextBoxBase]$Control,
		[string]$Text
	)

	$Control.AppendText($Text + [Environment]::NewLine)
	$Control.SelectionStart = $Control.TextLength
	$Control.ScrollToCaret()
}

function Write-AnalysisReport {
	$stats = Get-JsonFile -Path $StatsFile
	$reportTitleStats = Get-ConsoleTitleStats
	$reportQueryStats = if($script:QueryConfig.Enabled){ Invoke-ServerQuery -QueryHost $script:QueryConfig.Host -QueryPort $script:QueryConfig.Port -TimeoutMs 400 }else{ $null }
	$reportSnapshot = if($null -ne $stats){ $stats }else{ New-MergedSnapshot -titleStats $reportTitleStats -queryStats $reportQueryStats }
	$telemetryMode = if($null -ne $stats){ "json" }elseif($null -ne $reportTitleStats -and $null -ne $reportQueryStats){ "title+query" }elseif($null -ne $reportTitleStats){ "title" }elseif($null -ne $reportQueryStats){ "query" }else{ $script:CurrentTelemetryMode }
	$insights = Get-LiveInsights -Snapshot $reportSnapshot -TelemetryMode $telemetryMode
	$todoText = Get-FileText -Path $TodoPath
	$latestResults = @($script:TestResults | Sort-Object finished_at -Descending | Select-Object -First 8)

	$lines = New-Object System.Collections.Generic.List[string]
	$lines.Add("# BeeltyMine Dashboard Analysis")
	$lines.Add("")
	$lines.Add("Generated: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')")
	$lines.Add("")
	$lines.Add("## Executive Summary")
	$lines.Add("")
	$lines.Add("- Health State: $($insights.HealthState)")
	$lines.Add("- Telemetry Mode: $telemetryMode")
	$lines.Add("- Active Players: $(if($insights.ActivePlayers.Count -gt 0){ $insights.ActivePlayers -join ', ' }else{ 'none' })")
	$lines.Add("")

	if($null -ne $reportSnapshot){
		$lines.Add("## Live Snapshot")
		$lines.Add("")
		$lines.Add("- Running: $($reportSnapshot.running)")
		$lines.Add("- Uptime: $($reportSnapshot.uptime_seconds)s")
		$lines.Add("- Tick: $($reportSnapshot.server.tick)")
		$lines.Add("- TPS: current $($reportSnapshot.tps.current) / average $($reportSnapshot.tps.average)")
		$lines.Add("- Load: current $($reportSnapshot.load.current_percent)% / average $($reportSnapshot.load.average_percent)%")
		$lines.Add("- Memory: main $($reportSnapshot.memory.main_mb) MB / real $($reportSnapshot.memory.real_mb) MB")
		$lines.Add("- Players: $($reportSnapshot.players.online)/$($reportSnapshot.players.max)")
		$lines.Add("- Network: up $($reportSnapshot.network.upload_kb_s) kB/s / down $($reportSnapshot.network.download_kb_s) kB/s")
		$lines.Add("")
		$lines.Add("### Worlds")
		$lines.Add("")
		foreach($world in $reportSnapshot.worlds){
			$lines.Add("- $($world.name): players=$($world.players), loaded_chunks=$($world.loaded_chunks), ticking_chunks=$($world.ticking_chunks), tick_time_ms=$($world.tick_time_ms)")
		}
		$lines.Add("")
	}

	$lines.Add("## Performance Findings")
	$lines.Add("")
	if($insights.Findings.Count -eq 0){
		$lines.Add("- No obvious performance or health issue detected from current telemetry.")
	}else{
		foreach($finding in $insights.Findings | Select-Object -First 12){
			$lines.Add("- [$($finding.Severity)] $($finding.Area): $($finding.Title) | $($finding.Detail)")
		}
	}
	$lines.Add("")

	$lines.Add("## Recent Warning Signals")
	$lines.Add("")
	$warningLines = @($insights.LogLines | Where-Object { $_ -match '\[WARNING\]|\[ERROR\]' } | Select-Object -Last 8)
	if($warningLines.Count -eq 0){
		$lines.Add("- No recent warning/error line found in server.log.")
	}else{
		foreach($warningLine in $warningLines){
			$lines.Add("- $warningLine")
		}
	}
	$lines.Add("")

	$lines.Add("## Last Test Results")
	$lines.Add("")
	if($latestResults.Count -eq 0){
		$lines.Add("- No test run recorded yet.")
	}else{
		foreach($result in $latestResults){
			$lines.Add("- $($result.name): $($result.status) (exit=$($result.exit_code), duration=$($result.duration_seconds)s)")
		}
	}


	$report = ($lines -join [Environment]::NewLine)
	Set-Content -Path $AnalysisPath -Value $report -Encoding UTF8
}

function Add-TestResultToListView {
	param(
		[System.Windows.Forms.ListView]$ListView,
		$Result
	)

	$item = New-Object System.Windows.Forms.ListViewItem($Result.name)
	[void]$item.SubItems.Add($Result.status)
	[void]$item.SubItems.Add([string]$Result.exit_code)
	[void]$item.SubItems.Add([string]$Result.duration_seconds)
	[void]$item.SubItems.Add([string]$Result.finished_at)
	[void]$item.SubItems.Add([string]$Result.command)
	[void]$ListView.Items.Add($item)
}

function Start-TestCommand {
	param(
		[string]$Name,
		[string]$Command,
		[System.Windows.Forms.TextBoxBase]$OutputControl
	)

	if($script:ActiveTest -and -not $script:ActiveTest.Process.HasExited){
		[System.Windows.Forms.MessageBox]::Show(
			"Once mevcut test bitmeden yeni bir test baslatilamaz.",
			"BeeltyMine Dashboard",
			[System.Windows.Forms.MessageBoxButtons]::OK,
			[System.Windows.Forms.MessageBoxIcon]::Information
		) | Out-Null
		return
	}

	$psi = New-Object System.Diagnostics.ProcessStartInfo
	$psi.FileName = "powershell"
	$psi.Arguments = Join-ProcessArguments @("-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", $Command)
	$psi.WorkingDirectory = $RepoRoot
	$psi.UseShellExecute = $false
	$psi.RedirectStandardOutput = $true
	$psi.RedirectStandardError = $true
	$psi.CreateNoWindow = $true

	$process = New-Object System.Diagnostics.Process
	$process.StartInfo = $psi
	$process.EnableRaisingEvents = $true

	$outputSource = "BeeltyMineTestOutput_" + [Guid]::NewGuid().ToString("N")
	$errorSource = "BeeltyMineTestError_" + [Guid]::NewGuid().ToString("N")

	$outEvent = Register-ObjectEvent -InputObject $process -EventName OutputDataReceived -SourceIdentifier $outputSource -MessageData $script:TestOutputQueue -Action {
		if($EventArgs.Data){
			[void]$Event.MessageData.Enqueue($EventArgs.Data)
		}
	}
	$errEvent = Register-ObjectEvent -InputObject $process -EventName ErrorDataReceived -SourceIdentifier $errorSource -MessageData $script:TestOutputQueue -Action {
		if($EventArgs.Data){
			[void]$Event.MessageData.Enqueue($EventArgs.Data)
		}
	}

	[void]$process.Start()
	$process.BeginOutputReadLine()
	$process.BeginErrorReadLine()

	Append-OutputLine -Control $OutputControl -Text ("[{0}] Started: {1}" -f $Name, $Command)

	$script:ActiveTest = [pscustomobject]@{
		Name = $Name
		Command = $Command
		Process = $process
		StartedAt = Get-Date
		OutputEvent = $outEvent
		ErrorEvent = $errEvent
		OutputSource = $outputSource
		ErrorSource = $errorSource
	}
}

Load-TestResults
$script:QueryConfig = Get-QueryConfig

$ErrorActionPreference = "Stop"
try{
$form = New-Object System.Windows.Forms.Form
$form.Text = "BeeltyMine Dashboard"
$form.Width = 1460
$form.Height = 920
$form.StartPosition = [System.Windows.Forms.FormStartPosition]::CenterScreen
$form.BackColor = [System.Drawing.Color]::FromArgb(11, 13, 18)

$mainLayout = New-Object System.Windows.Forms.TableLayoutPanel
$mainLayout.Dock = [System.Windows.Forms.DockStyle]::Fill
$mainLayout.RowCount = 2
$mainLayout.ColumnCount = 1
[void]$mainLayout.RowStyles.Add((New-Object System.Windows.Forms.RowStyle([System.Windows.Forms.SizeType]::Absolute, 92)))
[void]$mainLayout.RowStyles.Add((New-Object System.Windows.Forms.RowStyle([System.Windows.Forms.SizeType]::Percent, 100)))

$headerPanel = New-Object System.Windows.Forms.Panel
$headerPanel.Dock = [System.Windows.Forms.DockStyle]::Fill
$headerPanel.BackColor = [System.Drawing.Color]::FromArgb(17, 19, 24)
$headerPanel.Padding = New-Object System.Windows.Forms.Padding(16, 10, 16, 10)

$titleLabel = New-Object System.Windows.Forms.Label
$titleLabel.Text = "BeeltyMine Live Dashboard"
$titleLabel.Font = New-Object System.Drawing.Font("Segoe UI Semibold", 16)
$titleLabel.AutoSize = $true
$titleLabel.ForeColor = [System.Drawing.Color]::FromArgb(237, 201, 122)
$titleLabel.Location = New-Object System.Drawing.Point(14, 14)

$subTitleLabel = New-Object System.Windows.Forms.Label
$subTitleLabel.Text = "Live stats bekleniyor | file: $StatsFile"
$subTitleLabel.Font = New-Object System.Drawing.Font("Consolas", 9)
$subTitleLabel.AutoSize = $false
$subTitleLabel.AutoEllipsis = $true
$subTitleLabel.Width = 980
$subTitleLabel.Height = 22
$subTitleLabel.ForeColor = [System.Drawing.Color]::FromArgb(145, 156, 171)
$subTitleLabel.Location = New-Object System.Drawing.Point(18, 48)
$subTitleLabel.Anchor = [System.Windows.Forms.AnchorStyles]::Top -bor [System.Windows.Forms.AnchorStyles]::Left -bor [System.Windows.Forms.AnchorStyles]::Right

$healthBadge = New-Object System.Windows.Forms.Label
$healthBadge.AutoSize = $false
$healthBadge.Width = 180
$healthBadge.Height = 30
$healthBadge.TextAlign = [System.Drawing.ContentAlignment]::MiddleCenter
$healthBadge.Font = New-Object System.Drawing.Font("Segoe UI Semibold", 10)
$healthBadge.Location = New-Object System.Drawing.Point(1040, 14)
$healthBadge.Anchor = [System.Windows.Forms.AnchorStyles]::Top -bor [System.Windows.Forms.AnchorStyles]::Right

$telemetryBadge = New-Object System.Windows.Forms.Label
$telemetryBadge.AutoSize = $false
$telemetryBadge.Width = 180
$telemetryBadge.Height = 30
$telemetryBadge.TextAlign = [System.Drawing.ContentAlignment]::MiddleCenter
$telemetryBadge.Font = New-Object System.Drawing.Font("Segoe UI Semibold", 10)
$telemetryBadge.Location = New-Object System.Drawing.Point(1230, 14)
$telemetryBadge.Anchor = [System.Windows.Forms.AnchorStyles]::Top -bor [System.Windows.Forms.AnchorStyles]::Right

[void]$headerPanel.Controls.Add($titleLabel)
[void]$headerPanel.Controls.Add($subTitleLabel)
[void]$headerPanel.Controls.Add($healthBadge)
[void]$headerPanel.Controls.Add($telemetryBadge)

$tabs = New-Object System.Windows.Forms.TabControl
$tabs.Dock = [System.Windows.Forms.DockStyle]::Fill
$tabs.Font = New-Object System.Drawing.Font("Segoe UI", 9)
$tabs.Appearance = [System.Windows.Forms.TabAppearance]::Normal

$liveTab = New-Object System.Windows.Forms.TabPage
$liveTab.Text = "Live Stats"
$liveTab.BackColor = [System.Drawing.Color]::FromArgb(11, 13, 18)

$diagTab = New-Object System.Windows.Forms.TabPage
$diagTab.Text = "Tests and Analysis"
$diagTab.BackColor = [System.Drawing.Color]::FromArgb(11, 13, 18)

$improvementsTab = New-Object System.Windows.Forms.TabPage
$improvementsTab.Text = "Improvements"
$improvementsTab.BackColor = [System.Drawing.Color]::FromArgb(11, 13, 18)

$liveLayout = New-Object System.Windows.Forms.TableLayoutPanel
$liveLayout.Dock = [System.Windows.Forms.DockStyle]::Fill
$liveLayout.RowCount = 3
$liveLayout.ColumnCount = 1
[void]$liveLayout.RowStyles.Add((New-Object System.Windows.Forms.RowStyle([System.Windows.Forms.SizeType]::Absolute, 220)))
[void]$liveLayout.RowStyles.Add((New-Object System.Windows.Forms.RowStyle([System.Windows.Forms.SizeType]::Percent, 44)))
[void]$liveLayout.RowStyles.Add((New-Object System.Windows.Forms.RowStyle([System.Windows.Forms.SizeType]::Percent, 56)))
$liveLayout.Padding = New-Object System.Windows.Forms.Padding(10)

$cardsGrid = New-Object System.Windows.Forms.TableLayoutPanel
$cardsGrid.Dock = [System.Windows.Forms.DockStyle]::Fill
$cardsGrid.RowCount = 2
$cardsGrid.ColumnCount = 4
for($i = 0; $i -lt 4; $i++){
	[void]$cardsGrid.ColumnStyles.Add((New-Object System.Windows.Forms.ColumnStyle([System.Windows.Forms.SizeType]::Percent, 25)))
}
for($i = 0; $i -lt 2; $i++){
	[void]$cardsGrid.RowStyles.Add((New-Object System.Windows.Forms.RowStyle([System.Windows.Forms.SizeType]::Percent, 50)))
}

$cardRefs = @{
	Status = (New-ValueCard -Title "Server Status")
	Uptime = (New-ValueCard -Title "Uptime")
	Tps = (New-ValueCard -Title "TPS")
	Load = (New-ValueCard -Title "Load")
	Players = (New-ValueCard -Title "Players")
	Memory = (New-ValueCard -Title "Memory")
	Network = (New-ValueCard -Title "Network")
	Version = (New-ValueCard -Title "Version")
}

$cards = @(
	$cardRefs.Status.Panel,
	$cardRefs.Uptime.Panel,
	$cardRefs.Tps.Panel,
	$cardRefs.Load.Panel,
	$cardRefs.Players.Panel,
	$cardRefs.Memory.Panel,
	$cardRefs.Network.Panel,
	$cardRefs.Version.Panel
)

for($i = 0; $i -lt $cards.Count; $i++){
	[void]$cardsGrid.Controls.Add($cards[$i], ($i % 4), [math]::Floor($i / 4))
}

$chartSplit = New-Object System.Windows.Forms.SplitContainer
$chartSplit.Dock = [System.Windows.Forms.DockStyle]::Fill
$chartSplit.Orientation = [System.Windows.Forms.Orientation]::Vertical
$chartSplit.SplitterDistance = 640

$tpsChartRef = New-LineChart -Title "TPS / Load Trend" -SeriesName "TPS" -LineColor ([System.Drawing.Color]::FromArgb(41, 128, 185))
$loadSeries = New-Object System.Windows.Forms.DataVisualization.Charting.Series
$loadSeries.Name = "Load %"
$loadSeries.ChartType = [System.Windows.Forms.DataVisualization.Charting.SeriesChartType]::Line
$loadSeries.BorderWidth = 2
$loadSeries.Color = [System.Drawing.Color]::FromArgb(192, 57, 43)
$loadSeries.ChartArea = "MainArea"
[void]$tpsChartRef.Chart.Series.Add($loadSeries)

$memoryChartRef = New-LineChart -Title "Memory Trend" -SeriesName "Main MB" -LineColor ([System.Drawing.Color]::FromArgb(39, 174, 96))
$realMemorySeries = New-Object System.Windows.Forms.DataVisualization.Charting.Series
$realMemorySeries.Name = "Real MB"
$realMemorySeries.ChartType = [System.Windows.Forms.DataVisualization.Charting.SeriesChartType]::Line
$realMemorySeries.BorderWidth = 2
$realMemorySeries.Color = [System.Drawing.Color]::FromArgb(142, 68, 173)
$realMemorySeries.ChartArea = "MainArea"
[void]$memoryChartRef.Chart.Series.Add($realMemorySeries)

[void]$chartSplit.Panel1.Controls.Add($tpsChartRef.Chart)
[void]$chartSplit.Panel2.Controls.Add($memoryChartRef.Chart)

$worldGrid = New-Object System.Windows.Forms.DataGridView
$worldGrid.Dock = [System.Windows.Forms.DockStyle]::Fill
$worldGrid.BackgroundColor = [System.Drawing.Color]::FromArgb(17, 19, 24)
$worldGrid.AutoSizeColumnsMode = [System.Windows.Forms.DataGridViewAutoSizeColumnsMode]::Fill
$worldGrid.ReadOnly = $true
$worldGrid.AllowUserToAddRows = $false
$worldGrid.AllowUserToDeleteRows = $false
$worldGrid.RowHeadersVisible = $false
$worldGrid.EnableHeadersVisualStyles = $false
$worldGrid.GridColor = [System.Drawing.Color]::FromArgb(54, 61, 74)
$worldGrid.ColumnHeadersDefaultCellStyle.BackColor = [System.Drawing.Color]::FromArgb(28, 31, 38)
$worldGrid.ColumnHeadersDefaultCellStyle.ForeColor = [System.Drawing.Color]::FromArgb(225, 230, 237)
$worldGrid.DefaultCellStyle.BackColor = [System.Drawing.Color]::FromArgb(17, 19, 24)
$worldGrid.DefaultCellStyle.ForeColor = [System.Drawing.Color]::FromArgb(225, 230, 237)
$worldGrid.DefaultCellStyle.SelectionBackColor = [System.Drawing.Color]::FromArgb(43, 49, 61)
$worldGrid.DefaultCellStyle.SelectionForeColor = [System.Drawing.Color]::FromArgb(245, 247, 250)

$bottomSplit = New-Object System.Windows.Forms.SplitContainer
$bottomSplit.Dock = [System.Windows.Forms.DockStyle]::Fill
$bottomSplit.Orientation = [System.Windows.Forms.Orientation]::Vertical
$bottomSplit.SplitterDistance = 900

$rightPanelLayout = New-Object System.Windows.Forms.TableLayoutPanel
$rightPanelLayout.Dock = [System.Windows.Forms.DockStyle]::Fill
$rightPanelLayout.RowCount = 3
$rightPanelLayout.ColumnCount = 1
[void]$rightPanelLayout.RowStyles.Add((New-Object System.Windows.Forms.RowStyle([System.Windows.Forms.SizeType]::Absolute, 110)))
[void]$rightPanelLayout.RowStyles.Add((New-Object System.Windows.Forms.RowStyle([System.Windows.Forms.SizeType]::Percent, 35)))
[void]$rightPanelLayout.RowStyles.Add((New-Object System.Windows.Forms.RowStyle([System.Windows.Forms.SizeType]::Percent, 65)))

$summaryBox = New-Object System.Windows.Forms.RichTextBox
$summaryBox.Dock = [System.Windows.Forms.DockStyle]::Fill
$summaryBox.Font = New-Object System.Drawing.Font("Segoe UI", 10)
$summaryBox.ReadOnly = $true
$summaryBox.BackColor = [System.Drawing.Color]::FromArgb(17, 19, 24)
$summaryBox.ForeColor = [System.Drawing.Color]::FromArgb(233, 238, 245)
$summaryBox.BorderStyle = [System.Windows.Forms.BorderStyle]::FixedSingle

$activePlayersList = New-Object System.Windows.Forms.ListBox
$activePlayersList.Dock = [System.Windows.Forms.DockStyle]::Fill
$activePlayersList.Font = New-Object System.Drawing.Font("Segoe UI Semibold", 10)
$activePlayersList.BackColor = [System.Drawing.Color]::FromArgb(17, 19, 24)
$activePlayersList.ForeColor = [System.Drawing.Color]::FromArgb(233, 238, 245)
$activePlayersList.BorderStyle = [System.Windows.Forms.BorderStyle]::FixedSingle

$issuesList = New-Object System.Windows.Forms.ListView
$issuesList.Dock = [System.Windows.Forms.DockStyle]::Fill
$issuesList.View = [System.Windows.Forms.View]::Details
$issuesList.FullRowSelect = $true
$issuesList.GridLines = $true
$issuesList.BackColor = [System.Drawing.Color]::FromArgb(17, 19, 24)
$issuesList.ForeColor = [System.Drawing.Color]::FromArgb(233, 238, 245)
[void]$issuesList.Columns.Add("Severity", 82)
[void]$issuesList.Columns.Add("Area", 90)
[void]$issuesList.Columns.Add("Finding", 280)

[void]$rightPanelLayout.Controls.Add($summaryBox, 0, 0)
[void]$rightPanelLayout.Controls.Add($activePlayersList, 0, 1)
[void]$rightPanelLayout.Controls.Add($issuesList, 0, 2)
[void]$bottomSplit.Panel1.Controls.Add($worldGrid)
[void]$bottomSplit.Panel2.Controls.Add($rightPanelLayout)

[void]$liveLayout.Controls.Add($cardsGrid, 0, 0)
[void]$liveLayout.Controls.Add($chartSplit, 0, 1)
[void]$liveLayout.Controls.Add($bottomSplit, 0, 2)
[void]$liveTab.Controls.Add($liveLayout)

$diagLayout = New-Object System.Windows.Forms.TableLayoutPanel
$diagLayout.Dock = [System.Windows.Forms.DockStyle]::Fill
$diagLayout.RowCount = 3
$diagLayout.ColumnCount = 1
[void]$diagLayout.RowStyles.Add((New-Object System.Windows.Forms.RowStyle([System.Windows.Forms.SizeType]::Absolute, 88)))
[void]$diagLayout.RowStyles.Add((New-Object System.Windows.Forms.RowStyle([System.Windows.Forms.SizeType]::Absolute, 210)))
[void]$diagLayout.RowStyles.Add((New-Object System.Windows.Forms.RowStyle([System.Windows.Forms.SizeType]::Percent, 100)))
$diagLayout.Padding = New-Object System.Windows.Forms.Padding(10)

$buttonPanel = New-Object System.Windows.Forms.FlowLayoutPanel
$buttonPanel.Dock = [System.Windows.Forms.DockStyle]::Fill
$buttonPanel.WrapContents = $true
$buttonPanel.Padding = New-Object System.Windows.Forms.Padding(0, 4, 0, 0)

$phpunitButton = New-Object System.Windows.Forms.Button
$phpunitButton.Text = "Run PHPUnit"
$phpunitButton.Width = 130
$phpunitButton.BackColor = [System.Drawing.Color]::FromArgb(33, 37, 46)
$phpunitButton.ForeColor = [System.Drawing.Color]::FromArgb(225, 230, 237)
$phpunitButton.FlatStyle = [System.Windows.Forms.FlatStyle]::Flat

$phpstanButton = New-Object System.Windows.Forms.Button
$phpstanButton.Text = "Run PHPStan"
$phpstanButton.Width = 130
$phpstanButton.BackColor = [System.Drawing.Color]::FromArgb(33, 37, 46)
$phpstanButton.ForeColor = [System.Drawing.Color]::FromArgb(225, 230, 237)
$phpstanButton.FlatStyle = [System.Windows.Forms.FlatStyle]::Flat

$analysisButton = New-Object System.Windows.Forms.Button
$analysisButton.Text = "Export Analysis"
$analysisButton.Width = 130
$analysisButton.BackColor = [System.Drawing.Color]::FromArgb(33, 37, 46)
$analysisButton.ForeColor = [System.Drawing.Color]::FromArgb(225, 230, 237)
$analysisButton.FlatStyle = [System.Windows.Forms.FlatStyle]::Flat

$openReportsButton = New-Object System.Windows.Forms.Button
$openReportsButton.Text = "Open Reports"
$openReportsButton.Width = 130
$openReportsButton.BackColor = [System.Drawing.Color]::FromArgb(33, 37, 46)
$openReportsButton.ForeColor = [System.Drawing.Color]::FromArgb(225, 230, 237)
$openReportsButton.FlatStyle = [System.Windows.Forms.FlatStyle]::Flat

$customCommandBox = New-Object System.Windows.Forms.TextBox
$customCommandBox.Width = 480
$customCommandBox.Text = 'php vendor/bin/phpunit --filter ExampleTest'
$customCommandBox.BackColor = [System.Drawing.Color]::FromArgb(17, 19, 24)
$customCommandBox.ForeColor = [System.Drawing.Color]::FromArgb(225, 230, 237)
$customCommandBox.BorderStyle = [System.Windows.Forms.BorderStyle]::FixedSingle

$customRunButton = New-Object System.Windows.Forms.Button
$customRunButton.Text = "Run Custom"
$customRunButton.Width = 120
$customRunButton.BackColor = [System.Drawing.Color]::FromArgb(55, 89, 138)
$customRunButton.ForeColor = [System.Drawing.Color]::FromArgb(245, 247, 250)
$customRunButton.FlatStyle = [System.Windows.Forms.FlatStyle]::Flat

$buttonPanel.Controls.AddRange(@(
	$phpunitButton,
	$phpstanButton,
	$analysisButton,
	$openReportsButton,
	$customCommandBox,
	$customRunButton
))

$resultsList = New-Object System.Windows.Forms.ListView
$resultsList.Dock = [System.Windows.Forms.DockStyle]::Fill
$resultsList.View = [System.Windows.Forms.View]::Details
$resultsList.FullRowSelect = $true
$resultsList.GridLines = $true
$resultsList.BackColor = [System.Drawing.Color]::FromArgb(17, 19, 24)
$resultsList.ForeColor = [System.Drawing.Color]::FromArgb(225, 230, 237)
[void]$resultsList.Columns.Add("Name", 140)
[void]$resultsList.Columns.Add("Status", 110)
[void]$resultsList.Columns.Add("Exit", 60)
[void]$resultsList.Columns.Add("Duration", 80)
[void]$resultsList.Columns.Add("Finished", 150)
[void]$resultsList.Columns.Add("Command", 620)

$diagOutput = New-Object System.Windows.Forms.RichTextBox
$diagOutput.Dock = [System.Windows.Forms.DockStyle]::Fill
$diagOutput.Font = New-Object System.Drawing.Font("Consolas", 9)
$diagOutput.ReadOnly = $true
$diagOutput.BackColor = [System.Drawing.Color]::FromArgb(17, 19, 24)
$diagOutput.ForeColor = [System.Drawing.Color]::FromArgb(214, 221, 230)

[void]$diagLayout.Controls.Add($buttonPanel, 0, 0)
[void]$diagLayout.Controls.Add($resultsList, 0, 1)
[void]$diagLayout.Controls.Add($diagOutput, 0, 2)
[void]$diagTab.Controls.Add($diagLayout)

$improvementsSplit = New-Object System.Windows.Forms.SplitContainer
$improvementsSplit.Dock = [System.Windows.Forms.DockStyle]::Fill
$improvementsSplit.Orientation = [System.Windows.Forms.Orientation]::Vertical
$improvementsSplit.SplitterDistance = 620

$todoBox = New-Object System.Windows.Forms.RichTextBox
$todoBox.Dock = [System.Windows.Forms.DockStyle]::Fill
$todoBox.Font = New-Object System.Drawing.Font("Consolas", 10)
$todoBox.ReadOnly = $true
$todoBox.BackColor = [System.Drawing.Color]::FromArgb(17, 19, 24)
$todoBox.ForeColor = [System.Drawing.Color]::FromArgb(214, 221, 230)

$analysisBox = New-Object System.Windows.Forms.RichTextBox
$analysisBox.Dock = [System.Windows.Forms.DockStyle]::Fill
$analysisBox.Font = New-Object System.Drawing.Font("Consolas", 10)
$analysisBox.ReadOnly = $true
$analysisBox.BackColor = [System.Drawing.Color]::FromArgb(17, 19, 24)
$analysisBox.ForeColor = [System.Drawing.Color]::FromArgb(214, 221, 230)

[void]$improvementsSplit.Panel1.Controls.Add($todoBox)
[void]$improvementsSplit.Panel2.Controls.Add($analysisBox)
[void]$improvementsTab.Controls.Add($improvementsSplit)

[void]$tabs.Controls.AddRange(@($liveTab, $diagTab, $improvementsTab))

[void]$mainLayout.Controls.Add($headerPanel, 0, 0)
[void]$mainLayout.Controls.Add($tabs, 0, 1)
[void]$form.Controls.Add($mainLayout)

foreach($result in ($script:TestResults | Sort-Object finished_at)){
	Add-TestResultToListView -ListView $resultsList -Result $result
}

Write-AnalysisReport
Set-RichText -Control $todoBox -Text (Get-FileText -Path $TodoPath)
Set-RichText -Control $analysisBox -Text (Get-FileText -Path $AnalysisPath)
$cardRefs.Status.ValueLabel.Text = "WAITING"
Set-BadgeState -Badge $healthBadge -Text "HEALTH: WAITING" -State "neutral"
Set-BadgeState -Badge $telemetryBadge -Text "MODE: WAITING" -State "neutral"
foreach($cardRef in $cardRefs.Values){
	Set-ValueCardState -CardRef $cardRef -State "neutral"
}
$summaryBox.Text = "Live health summary`r`n`r`nServer basliyor, telemetry bekleniyor."
if($script:QueryConfig.Enabled){
	$subTitleLabel.Text = "Live stats bekleniyor | JSON: $StatsFile | Query: $($script:QueryConfig.Host):$($script:QueryConfig.Port)"
}else{
	$subTitleLabel.Text = "Live stats bekleniyor | file: $StatsFile"
}

$phpUnitCommand = "& `"$PhpBinary`" `"vendor/bin/phpunit`" --colors=never"
$phpStanCommand = "& `"$PhpBinary`" `"vendor/bin/phpstan`" analyse -c `"phpstan.neon.dist`" --no-progress"

$phpunitButton.Add_Click({
	Start-TestCommand -Name "PHPUnit" -Command $phpUnitCommand -OutputControl $diagOutput
})

$phpstanButton.Add_Click({
	Start-TestCommand -Name "PHPStan" -Command $phpStanCommand -OutputControl $diagOutput
})

$analysisButton.Add_Click({
	Write-AnalysisReport
	Set-RichText -Control $analysisBox -Text (Get-FileText -Path $AnalysisPath)
	Append-OutputLine -Control $diagOutput -Text "Analysis snapshot exported."
})

$openReportsButton.Add_Click({
	Start-Process explorer.exe -ArgumentList $DiagnosticsRoot
})

$customRunButton.Add_Click({
	if([string]::IsNullOrWhiteSpace($customCommandBox.Text)){
		return
	}
	Start-TestCommand -Name "Custom" -Command $customCommandBox.Text -OutputControl $diagOutput
})

$refreshTimer = New-Object System.Windows.Forms.Timer
$refreshTimer.Interval = 1000
$refreshTimer.Add_Tick({
	try{
		$statsInfo = Get-Item -Path $StatsFile -ErrorAction SilentlyContinue
		if($statsInfo -and ($null -eq $script:StatsLastWrite -or $script:StatsLastWrite -ne $statsInfo.LastWriteTimeUtc)){
			$stats = Get-JsonFile -Path $StatsFile
			if($null -ne $stats){
				$script:StatsData = $stats
				$script:StatsLastWrite = $statsInfo.LastWriteTimeUtc

				$cardRefs.Status.ValueLabel.Text = if($stats.running){ "RUNNING" }else{ "STOPPED" }
				$cardRefs.Uptime.ValueLabel.Text = "{0:n0}s" -f [double]$stats.uptime_seconds
				$cardRefs.Tps.ValueLabel.Text = "{0} / {1}" -f $stats.tps.current, $stats.tps.average
				$cardRefs.Load.ValueLabel.Text = "{0}% / {1}%" -f $stats.load.current_percent, $stats.load.average_percent
				$cardRefs.Players.ValueLabel.Text = "{0}/{1}" -f $stats.players.online, $stats.players.max
				$cardRefs.Memory.ValueLabel.Text = "{0} MB / {1} MB" -f $stats.memory.main_mb, $stats.memory.real_mb
				$cardRefs.Network.ValueLabel.Text = "U {0} / D {1} kB/s" -f $stats.network.upload_kb_s, $stats.network.download_kb_s
				$cardRefs.Version.ValueLabel.Text = "{0} / MC {1}" -f $stats.server.version, $stats.server.minecraft_version
				$subTitleLabel.Text = "Live stats aktif | file: $StatsFile"

				Add-ChartPoint -Series $tpsChartRef.Series -Value ([double]$stats.tps.average)
				Add-ChartPoint -Series $loadSeries -Value ([double]$stats.load.average_percent)
				Add-ChartPoint -Series $memoryChartRef.Series -Value ([double]$stats.memory.main_mb)
				Add-ChartPoint -Series $realMemorySeries -Value ([double]$stats.memory.real_mb)

				$worldRows = foreach($world in $stats.worlds){
					[pscustomobject]@{
						Name = $world.name
						Folder = $world.folder
						Players = $world.players
						LoadedChunks = $world.loaded_chunks
						TickingChunks = $world.ticking_chunks
						TickMs = $world.tick_time_ms
					}
				}
				$worldGrid.DataSource = $worldRows
			}
		}else{
			$titleStats = Get-ConsoleTitleStats
			if($null -ne $titleStats){
				$cardRefs.Status.ValueLabel.Text = "RUNNING (TITLE)"
				$cardRefs.Uptime.ValueLabel.Text = "N/A"
				$cardRefs.Tps.ValueLabel.Text = if($null -ne $titleStats.Tps){ "{0}" -f $titleStats.Tps }else{ "N/A" }
				$cardRefs.Load.ValueLabel.Text = if($null -ne $titleStats.LoadPercent){ "{0}%" -f $titleStats.LoadPercent }else{ "N/A" }
				$cardRefs.Players.ValueLabel.Text = if($null -ne $titleStats.Online -and $null -ne $titleStats.MaxPlayers){ "{0}/{1}" -f $titleStats.Online, $titleStats.MaxPlayers }else{ "N/A" }
				$cardRefs.Memory.ValueLabel.Text = if($null -ne $titleStats.MainMemoryMb -and $null -ne $titleStats.VirtualMemoryMb){ "{0} MB / {1} MB" -f $titleStats.MainMemoryMb, $titleStats.VirtualMemoryMb }else{ "N/A" }
				$cardRefs.Network.ValueLabel.Text = if($null -ne $titleStats.UploadKb -and $null -ne $titleStats.DownloadKb){ "U {0} / D {1} kB/s" -f $titleStats.UploadKb, $titleStats.DownloadKb }else{ "{0}:{1}" -f $script:QueryConfig.Host, $script:QueryConfig.Port }
				$cardRefs.Version.ValueLabel.Text = $titleStats.Version
				$subTitleLabel.Text = "PHAR title stats aktif | Query destek: $($script:QueryConfig.Host):$($script:QueryConfig.Port)"

				if($null -ne $titleStats.Tps){
					Add-ChartPoint -Series $tpsChartRef.Series -Value ([double]$titleStats.Tps)
				}
				if($null -ne $titleStats.LoadPercent){
					Add-ChartPoint -Series $loadSeries -Value ([double]$titleStats.LoadPercent)
				}
				if($null -ne $titleStats.MainMemoryMb){
					Add-ChartPoint -Series $memoryChartRef.Series -Value ([double]$titleStats.MainMemoryMb)
				}
				if($null -ne $titleStats.RealMemoryMb){
					Add-ChartPoint -Series $realMemorySeries -Value ([double]$titleStats.RealMemoryMb)
				}
			}

			$queryDue = $null -eq $script:QueryLastAttempt -or ((Get-Date) - $script:QueryLastAttempt).TotalSeconds -ge 3
			if($script:QueryConfig.Enabled -and $queryDue){
				$script:QueryLastAttempt = Get-Date
				$queryStats = Invoke-ServerQuery -QueryHost $script:QueryConfig.Host -QueryPort $script:QueryConfig.Port
				if($null -ne $queryStats){
					$script:StatsData = $queryStats
					if($null -eq $titleStats){
						$cardRefs.Status.ValueLabel.Text = "RUNNING (QUERY)"
						$cardRefs.Uptime.ValueLabel.Text = "N/A"
						$cardRefs.Tps.ValueLabel.Text = "N/A"
						$cardRefs.Load.ValueLabel.Text = "N/A"
						$cardRefs.Players.ValueLabel.Text = "{0}/{1}" -f $queryStats.players.online, $queryStats.players.max
						$cardRefs.Memory.ValueLabel.Text = "N/A"
						$cardRefs.Network.ValueLabel.Text = "{0}:{1}" -f $script:QueryConfig.Host, $script:QueryConfig.Port
						$cardRefs.Version.ValueLabel.Text = "{0} / MC {1}" -f $queryStats.server.version, $queryStats.server.minecraft_version
					}else{
						$cardRefs.Version.ValueLabel.Text = "{0} / MC {1}" -f $titleStats.Version, $queryStats.server.minecraft_version
					}

					if(Test-Path (Join-Path $RepoRoot "src\\PocketMine.php")){
						$subTitleLabel.Text = "Query fallback aktif | JSON stats bekleniyor | $($script:QueryConfig.Host):$($script:QueryConfig.Port)"
					}elseif($null -ne $titleStats){
						$subTitleLabel.Text = "PHAR title stats + query aktif | $($script:QueryConfig.Host):$($script:QueryConfig.Port)"
					}else{
						$subTitleLabel.Text = "PHAR mode | Query fallback aktif | deep metrikler bu kurulumda yok"
					}

					$worldGrid.DataSource = @(
						[pscustomobject]@{
							Name = $queryStats.query.world
							Folder = $queryStats.query.world
							Players = $queryStats.players.online
							LoadedChunks = "N/A"
							TickingChunks = "N/A"
							TickMs = "N/A"
						}
					)
				}else{
					$script:StatsData = $null
					$cardRefs.Status.ValueLabel.Text = "OFFLINE"
					$cardRefs.Uptime.ValueLabel.Text = "-"
					$cardRefs.Tps.ValueLabel.Text = "-"
					$cardRefs.Load.ValueLabel.Text = "-"
					$cardRefs.Players.ValueLabel.Text = "-"
					$cardRefs.Memory.ValueLabel.Text = "-"
					$cardRefs.Network.ValueLabel.Text = "-"
					$cardRefs.Version.ValueLabel.Text = "-"
					if(Test-Path (Join-Path $RepoRoot "src\\PocketMine.php")){
						$subTitleLabel.Text = "Stats dosyasi yok, query yanit vermiyor | file: $StatsFile"
					}else{
						$subTitleLabel.Text = "PHAR mode | Query yanit vermiyor, bu yüzden live veri yok"
					}
				}
			}elseif($null -eq $script:StatsData){
				$cardRefs.Status.ValueLabel.Text = "WAITING"
				if($script:QueryConfig.Enabled){
					$subTitleLabel.Text = "Stats dosyasi henuz gelmedi | Query hazir: $($script:QueryConfig.Host):$($script:QueryConfig.Port)"
				}else{
					$subTitleLabel.Text = "Stats dosyasi henuz gelmedi | Query disabled"
				}
			}
		}

		$analysisStats = $null
		$currentTitleStats = Get-ConsoleTitleStats
		if($statsInfo){
			$analysisStats = Get-JsonFile -Path $StatsFile
		}
		if($script:QueryConfig.Enabled -and ($null -eq $script:QueryLastAttempt -or ((Get-Date) - $script:QueryLastAttempt).TotalSeconds -ge 3)){
			$script:QueryLastAttempt = Get-Date
			$script:LastQueryStats = Invoke-ServerQuery -QueryHost $script:QueryConfig.Host -QueryPort $script:QueryConfig.Port
		}
		$currentQueryStats = $script:LastQueryStats

		if($null -ne $analysisStats){
			$script:CurrentTelemetryMode = "json"
		}elseif($null -ne $currentTitleStats -and $null -ne $currentQueryStats){
			$analysisStats = New-MergedSnapshot -titleStats $currentTitleStats -queryStats $currentQueryStats
			$script:CurrentTelemetryMode = "title+query"
		}elseif($null -ne $currentTitleStats){
			$analysisStats = New-MergedSnapshot -titleStats $currentTitleStats -queryStats $null
			$script:CurrentTelemetryMode = "title"
		}elseif($null -ne $currentQueryStats){
			$analysisStats = New-MergedSnapshot -titleStats $null -queryStats $currentQueryStats
			$script:CurrentTelemetryMode = "query"
		}else{
			$script:CurrentTelemetryMode = "offline"
		}

		$liveInsights = Get-LiveInsights -Snapshot $analysisStats -TelemetryMode $script:CurrentTelemetryMode
		$healthState = $liveInsights.HealthState
		$modeState = switch($script:CurrentTelemetryMode){
			"json" { "good" }
			"title+query" { "warn" }
			"title" { "warn" }
			"query" { "warn" }
			"offline" { "bad" }
			default { "neutral" }
		}

		Set-BadgeState -Badge $healthBadge -Text ("HEALTH: {0}" -f $healthState.ToUpperInvariant()) -State $healthState
		Set-BadgeState -Badge $telemetryBadge -Text ("MODE: {0}" -f $script:CurrentTelemetryMode.ToUpperInvariant()) -State $modeState

		$activePlayersList.Items.Clear()
		if($liveInsights.ActivePlayers.Count -gt 0){
			foreach($playerName in $liveInsights.ActivePlayers){
				[void]$activePlayersList.Items.Add($playerName)
			}
		}else{
			[void]$activePlayersList.Items.Add("No active players")
		}

		$issuesList.Items.Clear()
		if($liveInsights.Findings.Count -gt 0){
			foreach($finding in $liveInsights.Findings | Select-Object -First 8){
				$item = New-Object System.Windows.Forms.ListViewItem($finding.Severity)
				[void]$item.SubItems.Add($finding.Area)
				[void]$item.SubItems.Add(("{0} | {1}" -f $finding.Title, $finding.Detail))
				switch($finding.Severity){
					"Critical" { $item.ForeColor = [System.Drawing.Color]::FromArgb(255, 130, 145) }
					"Warning" { $item.ForeColor = [System.Drawing.Color]::FromArgb(245, 201, 105) }
					default { $item.ForeColor = [System.Drawing.Color]::FromArgb(196, 214, 235) }
				}
				[void]$issuesList.Items.Add($item)
			}
		}else{
			$item = New-Object System.Windows.Forms.ListViewItem("Info")
			[void]$item.SubItems.Add("Health")
			[void]$item.SubItems.Add("No immediate issue detected from current telemetry.")
			$item.ForeColor = [System.Drawing.Color]::FromArgb(196, 214, 235)
			[void]$issuesList.Items.Add($item)
		}

		$summaryLines = @(
			"Server Health Summary",
			"",
			"State: $healthState",
			"Mode: $($script:CurrentTelemetryMode)",
			"Players: $(if($null -ne $analysisStats -and $null -ne $analysisStats.players){ '{0}/{1}' -f $analysisStats.players.online, $analysisStats.players.max }else{ 'N/A' })",
			"Findings: $($liveInsights.Findings.Count)"
		)
		if($liveInsights.Findings.Count -gt 0){
			$topFinding = $liveInsights.Findings[0]
			$summaryLines += ""
			$summaryLines += "Top issue:"
			$summaryLines += "[$($topFinding.Severity)] $($topFinding.Title)"
		}
		Set-RichText -Control $summaryBox -Text ($summaryLines -join [Environment]::NewLine)

		Set-ValueCardState -CardRef $cardRefs.Status -State $(if($script:CurrentTelemetryMode -eq "offline"){ "bad" }elseif($script:CurrentTelemetryMode -eq "json"){ "good" }elseif($script:CurrentTelemetryMode -eq "waiting"){ "neutral" }else{ "warn" })
		if($null -ne $analysisStats -and $null -ne $analysisStats.tps.current){
			Set-ValueCardState -CardRef $cardRefs.Tps -State $(if([double]$analysisStats.tps.current -lt 16){ "bad" }elseif([double]$analysisStats.tps.current -lt 18.5){ "warn" }else{ "good" })
		}else{
			Set-ValueCardState -CardRef $cardRefs.Tps -State "neutral"
		}
		if($null -ne $analysisStats -and $null -ne $analysisStats.load.current_percent){
			Set-ValueCardState -CardRef $cardRefs.Load -State $(if([double]$analysisStats.load.current_percent -ge 85){ "bad" }elseif([double]$analysisStats.load.current_percent -ge 60){ "warn" }else{ "good" })
		}else{
			Set-ValueCardState -CardRef $cardRefs.Load -State "neutral"
		}
		if($null -ne $analysisStats -and $null -ne $analysisStats.memory.real_mb){
			Set-ValueCardState -CardRef $cardRefs.Memory -State $(if([double]$analysisStats.memory.real_mb -ge 1024){ "bad" }elseif([double]$analysisStats.memory.real_mb -ge 650){ "warn" }else{ "good" })
		}else{
			Set-ValueCardState -CardRef $cardRefs.Memory -State "neutral"
		}
		Set-ValueCardState -CardRef $cardRefs.Uptime -State $(if($script:CurrentTelemetryMode -eq "json"){ "good" }else{ "neutral" })
		Set-ValueCardState -CardRef $cardRefs.Players -State $(if($null -ne $analysisStats -and $null -ne $analysisStats.players.online -and [int]$analysisStats.players.online -gt 0){ "good" }else{ "neutral" })
		Set-ValueCardState -CardRef $cardRefs.Network -State $(if($script:CurrentTelemetryMode -eq "offline"){ "bad" }else{ "neutral" })
		Set-ValueCardState -CardRef $cardRefs.Version -State "neutral"

		$line = $null
		while($script:TestOutputQueue.TryDequeue([ref]$line)){
			Append-OutputLine -Control $diagOutput -Text $line
			$line = $null
		}

		if($script:ActiveTest -and $script:ActiveTest.Process.HasExited){
			$finishedAt = Get-Date
			$duration = [math]::Round(($finishedAt - $script:ActiveTest.StartedAt).TotalSeconds, 2)
			$result = [pscustomobject]@{
				name = $script:ActiveTest.Name
				status = if($script:ActiveTest.Process.ExitCode -eq 0){ "Passed" }else{ "Failed" }
				exit_code = $script:ActiveTest.Process.ExitCode
				duration_seconds = $duration
				finished_at = $finishedAt.ToString("yyyy-MM-dd HH:mm:ss")
				command = $script:ActiveTest.Command
			}

			$script:TestResults = @($script:TestResults + $result | Sort-Object finished_at -Descending | Select-Object -First 30)
			Save-TestResults
			Add-TestResultToListView -ListView $resultsList -Result $result
			Append-OutputLine -Control $diagOutput -Text ("[{0}] Finished with exit code {1}" -f $result.name, $result.exit_code)

			Unregister-Event -SourceIdentifier $script:ActiveTest.OutputSource -ErrorAction SilentlyContinue
			Unregister-Event -SourceIdentifier $script:ActiveTest.ErrorSource -ErrorAction SilentlyContinue
			$script:ActiveTest.Process.Dispose()
			$script:ActiveTest = $null

			Write-AnalysisReport
			Set-RichText -Control $analysisBox -Text (Get-FileText -Path $AnalysisPath)
		}

		$todoInfo = Get-Item -Path $TodoPath -ErrorAction SilentlyContinue
		if($todoInfo -and ($null -eq $script:TodoLastWrite -or $script:TodoLastWrite -ne $todoInfo.LastWriteTimeUtc)){
			$script:TodoLastWrite = $todoInfo.LastWriteTimeUtc
			Set-RichText -Control $todoBox -Text (Get-FileText -Path $TodoPath)
		}

		$analysisInfo = Get-Item -Path $AnalysisPath -ErrorAction SilentlyContinue
		if($analysisInfo -and ($null -eq $script:AnalysisLastWrite -or $script:AnalysisLastWrite -ne $analysisInfo.LastWriteTimeUtc)){
			$script:AnalysisLastWrite = $analysisInfo.LastWriteTimeUtc
			Set-RichText -Control $analysisBox -Text (Get-FileText -Path $AnalysisPath)
		}
	}catch{
		$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
		$body = @(
			"[$timestamp] Dashboard timer error",
			($_ | Out-String),
			""
		) -join [Environment]::NewLine
		Add-Content -Path $DashboardErrorLog -Value $body -Encoding UTF8
		$subTitleLabel.Text = "Dashboard runtime hatasi | file: $StatsFile"
	}
})

$form.Add_Shown({
	try{
		$bottomSplit.Panel1MinSize = 700
		$bottomSplit.Panel2MinSize = 260
		$preferredRightWidth = 320
		$minLeftWidth = 700
		$maxSplitter = [Math]::Max($minLeftWidth, $bottomSplit.Width - $preferredRightWidth)
		$bottomSplit.SplitterDistance = [Math]::Min($maxSplitter, [Math]::Max($minLeftWidth, $bottomSplit.SplitterDistance))
	}catch{
	}
	$refreshTimer.Start()
})

$form.Add_FormClosing({
	$refreshTimer.Stop()
	if($script:ActiveTest -and -not $script:ActiveTest.Process.HasExited){
		try{
			$script:ActiveTest.Process.Kill()
		}catch{
		}
	}
})

[void]$form.ShowDialog()
}catch{
	$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
	$body = @(
		"[$timestamp] Dashboard startup/runtime error",
		($_ | Out-String),
		""
	) -join [Environment]::NewLine
	Add-Content -Path $DashboardErrorLog -Value $body -Encoding UTF8
	throw
}

