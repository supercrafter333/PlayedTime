<?php

namespace supercrafter333\PlayedTime;

use DateInterval;
use DateTime;
use Exception;
use JsonException;
use pocketmine\player\Player;
use pocketmine\utils\{AssumptionFailedError, Config, SingletonTrait, TextFormat};
use function array_keys;
use function print_r;
use function strtolower;

/**
 * This class is the manager for all the time stuff. It's the most important file for other developers.
 */
class PlayedTimeManager
{
    use SingletonTrait;

    /**
     * Constructor
     */
    public function __construct()
    {
        self::setInstance($this);
    }

    /**
     * Returns the configuration file of the played times.
     *
     * @return Config
     */
    public function getConfig(): Config
    {
        return new Config(PlayedTimeLoader::getInstance()->getDataFolder() . "playedTimes.json", Config::JSON);
    }

    /**
     * Adds a player to the cache-array.
     *
     * @param Player|string $player
     * @return bool
     */
    public function addPlayerToCache(Player|string $player): bool
    {
        $name = $this->cleanName($player);

        if (isset(self::$cachedPlayers[$name])) return false;

        self::$cachedPlayers[$name] = new DateTime('now');
        return true;
    }

    /**
     * Removes a player from the cache-array.
     *
     * @param Player|string $player
     * @return bool
     * @throws JsonException
     */
    public function removePlayerFromCache(Player|string $player): bool
    {
        if (!$this->saveFor($player)) return false;

        $name = $this->cleanName($player);

        if (!isset(self::$cachedPlayers[$name])) return false;

        unset(self::$cachedPlayers[$name]);
        return true;
    }

    /**
     * Checks if a player exists in the cache-array.
     *
     * @param Player|string $player
     * @return bool
     */
    public function isPlayerCached(Player|string $player): bool
    {
        return isset(self::$cachedPlayers[$this->cleanName($player)]);
    }

    /**
     * Returns the cache-array for each player.
     *
     * @return array
     */
    public function getFullCache(): array
    {
        return self::$cachedPlayers;
    }

    /**
     * Returns the played time of a player-session.
     *
     * @param Player $player
     * @return DateInterval|null
     */
    public function getSessionTime(Player $player): DateInterval|null
    {
        return $this->isPlayerCached($player) ? $this->calculateSessionTime($player) : null;
    }

    /**
     * Returns the total played time of a player.
     *
     * @param Player|string $player
     * @return DateInterval|null
     * @throws Exception
     */
    public function getTotalTime(Player|string $player): DateInterval|null
    {
        return $this->calculateTotalTime($player);
    }

    /**
     * Saves the time for a player in the time configuration file.
     *
     * @param Player|string $player
     * @return bool
     * @throws JsonException
     */
    public function saveFor(Player|string $player): bool
    {
        $name = $this->cleanName($player);

        if (($tt = $this->getTotalTime($player)) === null) return false;

        $cfg = $this->getConfig();
        try {
            $cfg->set($name, $this->generateDateIntervalString($tt));
        } catch (AssumptionFailedError $error) {
            return false;
        }
        $cfg->save();
        return true;
    }

    /**
     * Saves the time of all cached players.
     *
     * @return void
     * @throws JsonException
     */
    public function saveAll(): void
    {
        foreach (array_keys(self::$cachedPlayers) as $cachedPlayer)
            $this->saveFor($cachedPlayer);
    }



    ######################################
    ########### Internal Stuff ###########
         #                          #
         #                          #
      #  #  #                    #  #  #
       # # #                      # # #
         #                          #

    /**
     * @var array
     */
    private static array $cachedPlayers = [];

    /**
     * @param Player|string $player
     * @return string
     */
    private function cleanName(Player|string $player): string
    {
        if ($player instanceof Player) $player = $player->getName();

        return strtolower(TextFormat::clean($player));
    }

    /**
     * @param Player|string $player
     * @return DateInterval|null
     * @throws Exception
     */
    private function getTimeFromConfig(Player|string $player): DateInterval|null
    {
        $val = $this->getConfig()->get($this->cleanName($player), null);

        try {
            $dateIntv = new DateInterval($val);
            return $dateIntv;
        } catch (Exception $exception) {
            print_r("Got ERROR: " . $exception->getMessage() . PHP_EOL . " (" . $exception->getCode() . ")");
            return null;
        }
    }

    /**
     * @param Player $player
     * @return DateInterval|null
     */
    private function calculateSessionTime(Player $player): DateInterval|null
    {
        if (!$this->isPlayerCached($player)) return null;

        $name = $this->cleanName($player);
        /**@var $joined DateTime*/
        $joined = self::$cachedPlayers[$name];
        $now = new DateTime('now');

        return $now->diff($joined);
    }

    /**
     * @param Player|string $player
     * @return DateInterval|null
     * @throws Exception
     */
    private function calculateTotalTime(Player|string $player): DateInterval|null
    {
        $name = $this->cleanName($player);
        $total = $this->getTimeFromConfig($player);

        if (!$player instanceof Player || $this->getSessionTime($player) === null) return $total;

        $session = $this->getSessionTime($player);
        if ($total === null) return $session;

        $dtNow = (new DateTime('now'))->add($total);
        $dtNow2 = new DateTime('now');
        return $dtNow->diff($dtNow2->add($session), true);
    }

    /**
     * @param DateInterval $dateInterval
     * @return string
     * @throws AssumptionFailedError
     */
    private function generateDateIntervalString(DateInterval $dateInterval): string
    {
        $str = "P";
        if ($dateInterval->y > 0) $str .= $dateInterval->y . "Y";
        if ($dateInterval->m > 0) $str .= $dateInterval->m . "M";
        if ($dateInterval->d > 0) $str .= $dateInterval->d . "D";
        if ($dateInterval->s > 0 || $dateInterval->i > 0 || $dateInterval->h > 0) $str .= "T";
        if ($dateInterval->h > 0) $str .= $dateInterval->h . "H";
        if ($dateInterval->i > 0) $str .= $dateInterval->i . "M";
        if ($dateInterval->s > 0) $str .= $dateInterval->s . "S";

        if ($str === "P") throw new AssumptionFailedError("DateInterval cannot be empty!");

        return $str;
    }
}