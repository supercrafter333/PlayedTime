<?php

namespace supercrafter333\PlayedTime;

################################################
################ COPYRIGHT NOTE ################
################################################
#
# Some code-parts of this class are copied from:
# https://github.com/poggit-orphanage/EconomyS/blob/master/EconomyAPI/src/onebone/economyapi/task/SortTask.php
#
################################################
################################################

use DateInterval;
use pocketmine\player\Player;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Config;

class TopSortAsyncTask extends AsyncTask
{

    /**
     * @param string $player
     * @param array $times
     * @param int $page
     */
    public function __construct(private string $player, private array $times, private int $page) {}

    public function onRun(): void
    {
        $times = (array)$this->times;
        arsort($times);

        $ret = [];

        $n = 1;
        $this->max = ceil(count($times) / 10);
        $this->page = (int)min($this->max, max(1, $this->page));

        foreach ($times as $player => $time) {
            $player = strtolower($player);
            $current = (int)ceil($n / 10);
            if ($current === $this->page) {
                $ret[$n] = $player;
            } elseif ($current > $this->page) {
                break;
            }
            ++$n;
        }
        $this->setResult($ret);
    }

    public function onCompletion(): void
    {
        $top = $this->getResult();
        $server = Server::getInstance();

        $timeMsg = fn(DateInterval $time): string => $this->getMsg("time-format", [
            "{y}" => $time->y,
            "{m}" => $time->m,
            "{d}" => $time->d,
            "{h}" => $time->h,
            "{i}" => $time->i,
            "{s}" => $time->s
        ], false);

        if (($s = $server->getPlayerExact($this->player)) instanceof Player) {
                $page = (string)$this->page;
                $pages = (string)$this->max;

                $s->sendMessage($this->getMsg("top-command:onSuccess",
                    ["{page}" => $page, "{pages}" => $pages]));
                foreach ($top as $number => $name) {
                    $s->sendMessage($this->getMsg("top-command:onSuccessTemplate",
                        ["{number}" => $number, "{name}" => $name, "{time}" => $timeMsg(PlayedTimeManager::getInstance()->getTotalTime($name))]));
                }
        }
    }

    /**
     * @param string $msgPrefix
     * @param array|null $replace
     * @param bool $allowNewLines
     * @return string
     */
    public function getMsg(string $msgPrefix, array|null $replace = null, bool $allowNewLines = true): string
    {
        $msgCfg = new Config(PlayedTimeLoader::getInstance()->getDataFolder() . "messages.yml", Config::YAML);

        if (!str_contains($msgPrefix, ':')) $message = $msgCfg->get($msgPrefix);
        else {
            $newPref = explode(':', $msgPrefix);
            $message = $msgCfg->getNested($newPref[0] . "." . $newPref[1]);
        }
        if (!$message || $message === null) return "Message not found!";
        if ($replace !== null)
            foreach (array_keys($replace) as $key) {
                $message = str_replace($key, $replace[$key], $message);
            }

        return $allowNewLines ? str_replace("{line}", "\n", $message) : $message;
    }
}