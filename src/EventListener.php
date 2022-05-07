<?php

namespace supercrafter333\PlayedTime;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;

class EventListener implements Listener
{

    public function onJoin(PlayerJoinEvent $ev): void
    {
        PlayedTimeLoader::getInstance()->getPlayedTimeManager()->addPlayerToCache($ev->getPlayer());
    }

    public function onQuit(PlayerQuitEvent $ev): void
    {
        PlayedTimeLoader::getInstance()->getPlayedTimeManager()->saveFor($ev->getPlayer());
        PlayedTimeLoader::getInstance()->getPlayedTimeManager()->removePlayerFromCache($ev->getPlayer()->getName());
    }
}