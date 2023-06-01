<?php

namespace supercrafter333\PlayedTime;

use pocketmine\permission\PermissionManager;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;

class PlayedTimeLoader extends PluginBase
{
    use SingletonTrait;

    protected function onLoad(): void
    { self::setInstance($this); }

    protected function onEnable(): void
    {
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
        $this->saveResource("messages.yml");
        $bypassPerm = PermissionManager::getInstance()->getPermission("playedtime.cmd.*");
        $bypassPerm->addChild("playedtime.cmd", true);
        $bypassPerm->addChild("playedtime.cmd.mytime", true);
        $bypassPerm->addChild("playedtime.cmd.time", true);
        $bypassPerm->addChild("playedtime.cmd.top", true);
        $this->getServer()->getCommandMap()->register("PlayedTime", new PlayedTimeCommand("playedtime", "PlayedTime commands.", null, ["pt"]));
    }

    protected function onDisable(): void
    {
        $this->getPlayedTimeManager()->saveAll();
    }

    public function getPlayedTimeManager(): PlayedTimeManager
    {
        return new PlayedTimeManager;
    }
}