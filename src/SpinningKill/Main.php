<?php

declare(strict_types=1);

namespace SpinningKill;

use pocketmine\world\World;
use pocketmine\entity\Skin;
use pocketmine\entity\Human;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\entity\EntityFactory;
use pocketmine\command\CommandSender;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\PluginBase;
use pocketmine\entity\EntityDataHelper;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

class Main extends PluginBase implements Listener {

    private Config $killRecord;

    protected function onEnable(): void {
        $this->saveDefaultConfig();
        $this->killRecord = new Config($this->getDataFolder() . "kills.yml", Config::YAML);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        EntityFactory::getInstance()->register(KillEntity::class, function (World $world, CompoundTag $nbt): KillEntity {
            return new KillEntity(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt);
        }, ['KillEntity']);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() === "spinningkill") {
            if ($sender instanceof Player) {
                if ($sender->getServer()->isOp($sender->getName())) {
                    if (!isset($args[0])) {
                        $sender->sendMessage("§aUsage: /spinningkill spawn|remove");
                        return false;
                    }
                    if ($args[0] === "spawn") {
                        $this->spawnKillEntity($sender);
                        $sender->sendMessage("§bSpinning kill entity spawned.");
                    } elseif ($args[0] === "remove") {
                        $killEntity = $this->getNearSpinningKill($sender);

                        if ($killEntity !== null) {
                            $killEntity->flagForDespawn();
                            $sender->sendMessage("§bSpinning kill entity removed.");
                            return true;
                        }
                        $sender->sendMessage("§cNo spinning kill entity found.");
                    }
                }
            }
        }
        return true;
    }

    public function getNearSpinningKill(Player $player): ?KillEntity {
        $level = $player->getWorld();

        foreach ($level->getEntities() as $entity) {
            if ($entity instanceof KillEntity) {
                if ($player->getPosition()->distance($entity->getPosition()) <= 5 && $entity->getPosition()->distance($player->getPosition()) > 0) {
                    return $entity;
                }
            }
        }
        return null;
    }

    public function spawnKillEntity(Player $sender): void {
        $path = $this->getFile() . "resources/texture.png";
        $img = @imagecreatefrompng($path);
        $skinBytes = "";
        $s = (int)@getimagesize($path)[1];

        for ($y = 0; $y < $s; $y++) {
            for ($x = 0; $x < 64; $x++) {
                $color = @imagecolorat($img, $x, $y);
                $a = ((~($color >> 24)) << 1) & 0xff;
                $r = ($color >> 16) & 0xff;
                $g = ($color >> 8) & 0xff;
                $b = $color & 0xff;
                $skinBytes .= chr($r) . chr($g) . chr($b) . chr($a);
            }
        }
        @imagedestroy($img);

        $skin = new Skin($sender->getSkin()->getSkinId(), $skinBytes, '', 'geometry.gorilla', file_get_contents($this->getFile() . 'resources/gorilla.json'));
        $entity = new KillEntity($sender->getLocation(), $skin);
        $nameTag = $this->getConfig()->get("nametag", "&cHey &0{player} Estas Jugando\n &4Sexo&0Craft  &4Factions &c Tu Total de Kills es\n &4{kills} Kills");
        $entity->setNameTag($nameTag);
        $entity->setNameTagAlwaysVisible();
        $entity->setNameTagVisible();
        $entity->spawnToAll();
    }

    public function onJoin(PlayerJoinEvent $ev): void {
        $player = $ev->getPlayer();
        if (!$this->killRecord->get($player->getName())) {
            $this->killRecord->set($player->getName(), 0);
            $this->killRecord->save();
        }
    }

    public function onPlayerDeath(PlayerDeathEvent $ev): void {
        $player = $ev->getPlayer();
        $cause = $player->getLastDamageCause();
        if ($cause instanceof EntityDamageByEntityEvent) {
            $killer = $cause->getDamager();
            if ($killer instanceof Player) {
                $this->addKill($killer);
            }
        }
    }

    public function addKill(Player $player): void {
        $this->killRecord->set($player->getName(), $this->killRecord->get($player->getName()) + 1);
        $this->killRecord->save();
    }

    public function getKillCount(Player $player): int {
        return $this->killRecord->get($player->getName(), 0);
    }
}
