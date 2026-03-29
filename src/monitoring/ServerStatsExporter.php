<?php

declare(strict_types=1);

namespace pocketmine\monitoring;

use pocketmine\Server;
use pocketmine\utils\Filesystem;
use pocketmine\utils\Process;
use function array_map;
use function count;
use function dirname;
use function is_dir;
use function json_encode;
use function microtime;
use function mkdir;
use function round;

final class ServerStatsExporter{
	private bool $writeFailureLogged = false;

	public function __construct(
		private string $filePath,
		private \Logger $logger
	){}

	public function write(Server $server, bool $running = true) : void{
		try{
			$this->writeInternal($server, $running);
			$this->writeFailureLogged = false;
		}catch(\Throwable $e){
			if(!$this->writeFailureLogged){
				$this->logger->error("Failed to write stats file \"$this->filePath\": " . $e->getMessage());
				$this->writeFailureLogged = true;
			}
		}
	}

	private function writeInternal(Server $server, bool $running) : void{
		$dir = dirname($this->filePath);
		if(!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)){
			throw new \RuntimeException("Unable to create stats directory \"$dir\"");
		}

		$memory = Process::getAdvancedMemoryUsage();
		$onlinePlayers = $server->getOnlinePlayers();
		$bandwidth = $server->getNetwork()->getBandwidthTracker();
		$query = $server->getQueryInformation();

		$worlds = [];
		foreach($server->getWorldManager()->getWorlds() as $world){
			$worlds[] = [
				"id" => $world->getId(),
				"name" => $world->getDisplayName(),
				"folder" => $world->getFolderName(),
				"players" => count($world->getPlayers()),
				"loaded_chunks" => count($world->getLoadedChunks()),
				"ticking_chunks" => count($world->getTickingChunks()),
				"tick_time_ms" => round($world->getTickRateTime(), 3),
			];
		}

		$payload = [
			"running" => $running,
			"generated_at_unix" => microtime(true),
			"uptime_seconds" => round(microtime(true) - $server->getStartTime(), 2),
			"server" => [
				"name" => $server->getName(),
				"version" => $server->getPocketMineVersion(),
				"minecraft_version" => $server->getVersion(),
				"tick" => $server->getTick(),
				"threads" => Process::getThreadCount(),
			],
			"tps" => [
				"current" => $server->getTicksPerSecond(),
				"average" => $server->getTicksPerSecondAverage(),
			],
			"load" => [
				"current_percent" => $server->getTickUsage(),
				"average_percent" => $server->getTickUsageAverage(),
			],
			"memory" => [
				"main_mb" => round($memory[0] / 1024 / 1024, 2),
				"global_mb" => round($memory[1] / 1024 / 1024, 2),
				"real_mb" => round($memory[2] / 1024 / 1024, 2),
				"low_memory" => $server->getMemoryManager()->isLowMemory(),
			],
			"players" => [
				"online" => count($onlinePlayers),
				"max" => $server->getMaxPlayers(),
				"connecting" => $server->getNetwork()->getConnectionCount() - count($onlinePlayers),
				"names" => array_map(static fn($player) => $player->getName(), $onlinePlayers),
			],
			"network" => [
				"upload_kb_s" => round($bandwidth->getSend()->getAverageBytes() / 1024, 2),
				"download_kb_s" => round($bandwidth->getReceive()->getAverageBytes() / 1024, 2),
				"connections" => $server->getNetwork()->getConnectionCount(),
				"valid_connections" => $server->getNetwork()->getValidConnectionCount(),
			],
			"query" => [
				"motd" => $query->getServerName(),
				"world" => $query->getWorld(),
				"player_count" => $query->getPlayerCount(),
				"max_players" => $query->getMaxPlayerCount(),
				"player_list" => $query->getPlayerList(),
			],
			"worlds" => $worlds,
		];

		Filesystem::safeFilePutContents(
			$this->filePath,
			json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
		);
	}
}
