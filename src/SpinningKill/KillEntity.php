<?php

declare(strict_types=1);

namespace SpinningKill;

use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\utils\TextFormat;
use pocketmine\Server;

class KillEntity extends Human {

    public function onUpdate(int $currentTick): bool {
        $this->location->yaw += 5.5;
        $this->move($this->motion->x, $this->motion->y, $this->motion->z);
        $this->updateMovement();

        foreach ($this->getViewers() as $viewer) {
            $loader = Server::getInstance()->getPluginManager()->getPlugin("SpinningKill");
            if ($loader instanceof Main) {
                $kills = $loader->getKillCount($viewer);
                $nameTag = $loader->getConfig()->get("nametag", "&cHey &0{player} Estas Jugando\n &4Sexo&0Craft  &4Factions &c Tu Total de Kills es\n &4{kills} Kills");
                $formattedNameTag = TextFormat::colorize(str_replace(["{kills}", "{player}"], [$kills, $viewer->getName()], $nameTag));
                $this->setNameTag($formattedNameTag);
            }
        }
        return parent::onUpdate($currentTick);
    }

    public function attack(EntityDamageEvent $source): void {
        $source->cancel();
    }
}
