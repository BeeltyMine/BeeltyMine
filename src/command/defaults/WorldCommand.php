<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\math\Vector3;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Filesystem;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;
use pocketmine\world\WorldCreationOptions;
use pocketmine\world\WorldManager;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\generator\InvalidGeneratorOptionsException;
use pocketmine\world\WorldException;
use Symfony\Component\Filesystem\Path;
use function array_map;
use function array_shift;
use function array_slice;
use function count;
use function explode;
use function implode;
use function is_dir;
use function scandir;
use function sort;
use function sprintf;
use function strtolower;
use function trim;
use const SCANDIR_SORT_NONE;
use const SORT_STRING;

class WorldCommand extends VanillaCommand{

    public function __construct(){
        parent::__construct("world", "World management command", "/world <list|tp|load|unload|create|delete|info|setdefault> ...", ["worlds"]);
        $this->setPermission(DefaultPermissionNames::COMMAND_WORLD_MANAGE);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args){
        if(!$this->testPermission($sender)){
            return true;
        }
        if(($sub = array_shift($args)) === null){
            throw new InvalidCommandSyntaxException();
        }

        $worldManager = $sender->getServer()->getWorldManager();
        switch(strtolower($sub)){
            case "list":
            case "ls":
                $this->handleList($sender, $worldManager);
                return true;
            case "tp":
            case "teleport":
                $this->handleTeleport($sender, $worldManager, $args);
                return true;
            case "load":
                $this->handleLoad($sender, $worldManager, $args);
                return true;
            case "unload":
                $this->handleUnload($sender, $worldManager, $args);
                return true;
            case "create":
                $this->handleCreate($sender, $worldManager, $args);
                return true;
            case "delete":
            case "remove":
                $this->handleDelete($sender, $worldManager, $args);
                return true;
            case "info":
                $this->handleInfo($sender, $worldManager, $args);
                return true;
            case "setdefault":
            case "default":
                $this->handleSetDefault($sender, $worldManager, $args);
                return true;
        }

        throw new InvalidCommandSyntaxException();
    }

    private function handleList(CommandSender $sender, WorldManager $worldManager) : void{
        $worlds = $worldManager->getWorlds();
        if($worlds === []){
            $sender->sendMessage(TextFormat::YELLOW . "No worlds are currently loaded.");
        }else{
            $lines = [];
            foreach($worlds as $world){
                $lines[] = $this->formatWorldSummary($world, $world === $worldManager->getDefaultWorld());
            }
            $sender->sendMessage(TextFormat::GREEN . "Loaded worlds:\n" . implode("\n", $lines));
        }

        $unloaded = $this->getGeneratedButUnloadedWorlds($sender->getServer(), $worlds);
        if($unloaded !== []){
            $sender->sendMessage(TextFormat::YELLOW . "Unloaded: " . TextFormat::GRAY . implode(", ", $unloaded));
        }
    }

    private function handleTeleport(CommandSender $sender, WorldManager $worldManager, array $args) : void{
        if(!($sender instanceof Player)){
            $sender->sendMessage(TextFormat::RED . "You can only perform this command as a player");
            return;
        }
        if(!isset($args[0])){
            throw new InvalidCommandSyntaxException();
        }
        $worldName = $args[0];
        $world = $worldManager->getWorldByName($worldName);
        if($world === null){
            $sender->sendMessage(TextFormat::RED . "World not found: " . $worldName);
            return;
        }
        $sender->teleport($world->getSafeSpawn());
        Command::broadcastCommandMessage($sender, "Teleported to world " . $worldName);
    }

    private function handleLoad(CommandSender $sender, WorldManager $worldManager, array $args) : void{
        if(!isset($args[0])){
            throw new InvalidCommandSyntaxException();
        }
        $worldName = $args[0];
        if($worldManager->isWorldLoaded($worldName)){
            $sender->sendMessage(TextFormat::YELLOW . "World already loaded: " . $worldName);
            return;
        }
        if(!$worldManager->isWorldGenerated($worldName)){
            $sender->sendMessage(TextFormat::RED . "World not found on disk: " . $worldName);
            return;
        }
        try{
            $success = $worldManager->loadWorld($worldName, true);
        }catch(WorldException $e){
            $sender->sendMessage(TextFormat::RED . "Failed to load world: " . $e->getMessage());
            return;
        }
        $sender->sendMessage($success ? TextFormat::GREEN . "Loaded world " . $worldName : TextFormat::RED . "Failed to load world " . $worldName);
    }

    private function handleUnload(CommandSender $sender, WorldManager $worldManager, array $args) : void{
        if(!isset($args[0])){
            throw new InvalidCommandSyntaxException();
        }
        $worldName = $args[0];
        $force = isset($args[1]) && strtolower($args[1]) === "force";
        $world = $worldManager->getWorldByName($worldName);
        if($world === null){
            $sender->sendMessage(TextFormat::RED . "World is not loaded: " . $worldName);
            return;
        }
        if(!$force && $world === $worldManager->getDefaultWorld()){
            $sender->sendMessage(TextFormat::RED . "Set another default world before unloading this one (use force to override).");
            return;
        }
        try{
            $result = $worldManager->unloadWorld($world, $force);
        }catch(\InvalidArgumentException $e){
            $sender->sendMessage(TextFormat::RED . $e->getMessage());
            return;
        }
        $sender->sendMessage($result ? TextFormat::GREEN . "Unloaded world " . $worldName : TextFormat::RED . "World unload cancelled: " . $worldName);
    }

    private function handleSetDefault(CommandSender $sender, WorldManager $worldManager, array $args) : void{
        if(!isset($args[0])){
            throw new InvalidCommandSyntaxException();
        }
        $worldName = $args[0];
        $world = $worldManager->getWorldByName($worldName);
        if($world === null){
            $sender->sendMessage(TextFormat::RED . "Load the world before setting it as default: " . $worldName);
            return;
        }
        $worldManager->setDefaultWorld($world);
        $sender->sendMessage(TextFormat::GREEN . "Default world set to " . $worldName);
    }

    private function handleCreate(CommandSender $sender, WorldManager $worldManager, array $args) : void{
        if(!isset($args[0])){
            throw new InvalidCommandSyntaxException();
        }
        $name = trim($args[0]);
        if($name === ""){
            throw new InvalidCommandSyntaxException();
        }
        if($worldManager->isWorldGenerated($name)){
            $sender->sendMessage(TextFormat::RED . "World already exists: " . $name);
            return;
        }
        try{
            $creationOptions = $this->buildCreationOptions(array_slice($args, 1));
        }catch(\InvalidArgumentException|InvalidGeneratorOptionsException $e){
            $sender->sendMessage(TextFormat::RED . $e->getMessage());
            return;
        }
        try{
            $success = $worldManager->generateWorld($name, $creationOptions);
        }catch(\InvalidArgumentException $e){
            $sender->sendMessage(TextFormat::RED . $e->getMessage());
            return;
        }
        $sender->sendMessage($success ? TextFormat::GREEN . "Created world " . $name : TextFormat::RED . "Failed to create world " . $name);
    }

    private function handleDelete(CommandSender $sender, WorldManager $worldManager, array $args) : void{
        if(!isset($args[0])){
            throw new InvalidCommandSyntaxException();
        }
        $name = $args[0];
        $force = isset($args[1]) && strtolower($args[1]) === "force";
        $world = $worldManager->getWorldByName($name);
        if($world !== null){
            if($world === $worldManager->getDefaultWorld()){
                $sender->sendMessage(TextFormat::RED . "Change the default world before deleting it.");
                return;
            }
            if(!$force){
                $sender->sendMessage(TextFormat::RED . "World is loaded. Use /world delete " . $name . " force if you are sure.");
                return;
            }
            try{
                $worldManager->unloadWorld($world, true);
            }catch(\Throwable $e){
                $sender->sendMessage(TextFormat::RED . "Failed to unload world: " . $e->getMessage());
                return;
            }
        }
        $worldDir = Path::join($sender->getServer()->getDataPath(), "worlds", $name);
        if(!is_dir($worldDir)){
            $sender->sendMessage(TextFormat::RED . "World folder not found: " . $name);
            return;
        }
        Filesystem::recursiveUnlink($worldDir);
        $sender->sendMessage(TextFormat::GREEN . "Deleted world " . $name);
    }

    private function handleInfo(CommandSender $sender, WorldManager $worldManager, array $args) : void{
        if(!isset($args[0])){
            throw new InvalidCommandSyntaxException();
        }
        $worldName = $args[0];
        $world = $worldManager->getWorldByName($worldName);
        if($world === null){
            $sender->sendMessage(TextFormat::RED . "World is not loaded: " . $worldName . " (load it first).");
            return;
        }
        $data = $world->getProvider()->getWorldData();
        $spawn = $data->getSpawn();
        $sender->sendMessage(TextFormat::GREEN . "World info for " . $worldName);
        $sender->sendMessage(TextFormat::GRAY . " Seed: " . $data->getSeed());
        $generatorOptions = $data->getGeneratorOptions();
        $generatorLine = $data->getGenerator();
        if($generatorOptions !== ""){
            $generatorLine .= " (" . $generatorOptions . ")";
        }
        $sender->sendMessage(TextFormat::GRAY . " Generator: " . $generatorLine);
        $sender->sendMessage(TextFormat::GRAY . " Difficulty: " . $this->formatDifficulty($world->getDifficulty()));
        $sender->sendMessage(TextFormat::GRAY . " Time: " . (int) $world->getTime());
        $sender->sendMessage(TextFormat::GRAY . sprintf(" Spawn: %.1f %.1f %.1f", $spawn->getX(), $spawn->getY(), $spawn->getZ()));
        $sender->sendMessage(TextFormat::GRAY . " Players: " . count($world->getPlayers()));
    }

    /**
     * @param World[] $loaded
     * @return string[]
     */
    private function getGeneratedButUnloadedWorlds(Server $server, array $loaded) : array{
        $worldsDir = Path::join($server->getDataPath(), "worlds");
        if(!is_dir($worldsDir)){
            return [];
        }
        $entries = scandir($worldsDir, SCANDIR_SORT_NONE);
        if($entries === false){
            return [];
        }
        $loadedNames = [];
        foreach($loaded as $world){
            $loadedNames[$world->getFolderName()] = true;
        }
        $result = [];
        foreach($entries as $entry){
            if($entry === "." || $entry === ".."){
                continue;
            }
            if(isset($loadedNames[$entry])){
                continue;
            }
            if(is_dir(Path::join($worldsDir, $entry))){
                $result[] = $entry;
            }
        }
        sort($result, SORT_STRING);
        return $result;
    }

    private function formatWorldSummary(World $world, bool $isDefault) : string{
        $data = $world->getProvider()->getWorldData();
        return sprintf(
            "%s%s%s%s seed:%d time:%d players:%d",
            $isDefault ? TextFormat::GOLD . "*" : TextFormat::DARK_GRAY . "-",
            TextFormat::GREEN,
            $world->getFolderName(),
            TextFormat::WHITE,
            $data->getSeed(),
            (int) $world->getTime(),
            count($world->getPlayers())
        );
    }

    /**
     * @param string[] $tokens
     */
    private function buildCreationOptions(array $tokens) : WorldCreationOptions{
        $options = WorldCreationOptions::create();
        // Support positional generator token: e.g. `/world create name nether seed=123`
        $positionalGenerator = null;
        if(count($tokens) > 0 && strpos($tokens[0], '=') === false){
            $positionalGenerator = array_shift($tokens);
        }

        $parsed = $this->parseOptionTokens($tokens);
        if($positionalGenerator !== null && !isset($parsed['generator'])){
            $parsed['generator'] = $positionalGenerator;
        }
        $generatorName = strtolower($parsed['generator'] ?? 'default');
        $generatorEntry = GeneratorManager::getInstance()->getGenerator($generatorName);
        if($generatorEntry === null){
            throw new \InvalidArgumentException("Unknown generator \"$generatorName\"");
        }
        $options->setGeneratorClass($generatorEntry->getGeneratorClass());
        $preset = $parsed['preset'] ?? '';
        if($preset !== ''){
            $generatorEntry->validateGeneratorOptions($preset);
            $options->setGeneratorOptions($preset);
        }
        if(isset($parsed['seed'])){
            $seed = Generator::convertSeed($parsed['seed']);
            if($seed === null){
                throw new \InvalidArgumentException("Invalid seed value \"" . $parsed['seed'] . "\"");
            }
            $options->setSeed($seed);
        }
        if(isset($parsed['difficulty'])){
            $options->setDifficulty(World::getDifficultyFromString($parsed['difficulty']));
        }
        if(isset($parsed['dimension'])){
            $options->setDimension(strtolower($parsed['dimension']));
        }
        // If using the Nether generator and no explicit dimension provided, default to 'nether'
        if(($parsed['generator'] ?? '') !== '' && strtolower($parsed['generator']) === 'nether' && ($parsed['dimension'] ?? '') === ''){
            $options->setDimension('nether');
        }
        if(isset($parsed['spawn'])){
            $options->setSpawnPosition($this->parseSpawnVector($parsed['spawn']));
        }
        return $options;
    }

    /**
     * @param string[] $tokens
     * @return array<string, string>
     */
    private function parseOptionTokens(array $tokens) : array{
        $result = [];
        foreach($tokens as $token){
            [$key, $value] = explode('=', $token, 2) + [null, null];
            if($key === null || $value === null){
                throw new \InvalidArgumentException("Invalid option \"$token\". Use key=value format.");
            }
            $key = strtolower(trim($key));
            $value = trim($value);
            if($key === '' || $value === ''){
                throw new \InvalidArgumentException("Invalid option \"$token\". Use key=value format.");
            }
            $result[$key] = $value;
        }
        return $result;
    }

    private function parseSpawnVector(string $value) : Vector3{
        $parts = array_map('trim', explode(',', $value));
        if(count($parts) !== 3){
            throw new \InvalidArgumentException('Spawn must be formatted as x,y,z');
        }
        return new Vector3((float) $parts[0], (float) $parts[1], (float) $parts[2]);
    }

    private function formatDifficulty(int $difficulty) : string{
        return match($difficulty){
            World::DIFFICULTY_PEACEFUL => 'peaceful',
            World::DIFFICULTY_EASY => 'easy',
            World::DIFFICULTY_NORMAL => 'normal',
            World::DIFFICULTY_HARD => 'hard',
            default => (string) $difficulty
        };
    }
}
