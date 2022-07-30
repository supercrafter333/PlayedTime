<?php

namespace supercrafter333\PlayedTime;

use DateInterval;
use DateTime;
use Exception;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\lang\Translatable;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use function array_keys;
use function array_shift;
use function count;
use function explode;
use function implode;
use function is_numeric;
use function round;
use function serialize;
use function sort;
use function str_contains;
use function str_replace;
use function unserialize;
use function var_dump;

/**
 *
 */
class PlayedTimeCommand extends Command implements PluginOwned
{

    /**
     * @param string $name
     * @param Translatable|string $description
     * @param Translatable|string|null $usageMessage
     * @param array $aliases
     */
    public function __construct(string $name, Translatable|string $description = "", Translatable|string|null $usageMessage = null, array $aliases = [])
    {
        $this->setPermission("playedtime.cmd");
        parent::__construct($name, $description, $usageMessage, $aliases);
    }

    /**
     * @return PlayedTimeLoader
     */
    public function getOwningPlugin(): Plugin
    {
        return PlayedTimeLoader::getInstance();
    }

    /**
     * @param CommandSender|Player $s
     * @param string $permission
     * @return bool
     */
    private function checkPermission(CommandSender|Player $s, string $permission): bool
    {
        if (!$s->hasPermission($permission)) {
            $s->sendMessage(KnownTranslationFactory::pocketmine_command_error_permission($this->getName())->prefix(TextFormat::RED));
            return false;
        }
        return true;
    }

    /**
     * @param string $msgPrefix
     * @param array|null $replace
     * @param bool $allowNewLines
     * @return string
     */
    public function getMsg(string $msgPrefix, array|null $replace = null, bool $allowNewLines = true): string
    {
        $msgCfg = new Config($this->getOwningPlugin()->getDataFolder() . "messages.yml", Config::YAML);

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

    /**
     * @param CommandSender $s
     * @param string $commandLabel
     * @param array $args
     * @return void
     * @throws Exception
     */
    public function execute(CommandSender $s, string $commandLabel, array $args): void
    {
        if (!$this->checkPermission($s, $this->getPermission())) return;

        if (!isset($args[0])) {
            $cmdArr = ["mytime", "time", "top"];
            $cmds = "";
            foreach ($cmdArr as $cmd) {
                $cmds .= "\n§6/playedtime " . $cmd . " " . ($this->getMsg($cmd . "-command:args") !== "Message not found!" ? $this->getMsg($cmd . "-command:args") : "") . " §r - §b" . $this->getMsg($cmd . "-command:description");
            }
            $s->sendMessage($this->getMsg("help-command:onSuccess", ["{commands}" => $cmds]));
            return;
        }

        $notAPlayerMsg = fn() => $s->sendMessage($this->getMsg("only-in-game"));
        $timeMsg = fn(DateInterval $time): string => $this->getMsg("time-format", [
            "{y}" => $time->y,
            "{m}" => $time->m,
            "{d}" => $time->d,
            "{h}" => $time->h,
            "{i}" => $time->i,
            "{s}" => $time->s
        ], false);

        $subCmd = array_shift($args);
        switch ($subCmd) {
            case "help":
                $cmdArr = ["mytime", "time", "top"];
                $cmds = "";
                foreach ($cmdArr as $cmd) {
                    $cmds .= "\n§6/playedtime " . $cmd . " " . ($this->getMsg($cmd . "-command:args") !== "Message not found!" ? $this->getMsg($cmd . "-command:args") : "") . " §r - §b" . $this->getMsg($cmd . "-command:description");
                }
                $s->sendMessage($this->getMsg("help-command:onSuccess", ["{commands}" => $cmds]));

                break;

            case "mytime":
            case "mine":
                if (!$s instanceof Player) {
                    $notAPlayerMsg();
                    return;
                }

                if (!$this->checkPermission($s, "playedtime.cmd.mytime")) return;

                if (($mt = $this->getOwningPlugin()->getPlayedTimeManager()->getTotalTime($s)) === null) return;
                if (($mst = $this->getOwningPlugin()->getPlayedTimeManager()->getSessionTime($s)) === null) return;
                $s->sendMessage($this->getMsg("mytime-command:onSuccess", ["{total_time}" => $timeMsg($mt), "{session_time}" => $timeMsg($mst)]));

                break;

            case "time":
                if (!$this->checkPermission($s, "playedtime.cmd.time")) return;

                if (!isset($args[0])) {
                    if (!$s instanceof Player) {
                        $notAPlayerMsg();
                        return;
                    }

                    if (!$this->checkPermission($s, "playedtime.cmd.mytime")) return;

                    if (($mt = $this->getOwningPlugin()->getPlayedTimeManager()->getTotalTime($s)) === null) return;
                    if (($mst = $this->getOwningPlugin()->getPlayedTimeManager()->getSessionTime($s)) === null) return;
                    $s->sendMessage($this->getMsg("mytime-command:onSuccess", ["{total_time}" => $timeMsg($mt), "{session_time}" => $timeMsg($mst)]));
                    return;
                }

                $name = implode(" ", $args);
                if (($tPlayer = $this->getOwningPlugin()->getServer()->getPlayerByPrefix($name))) $name = $tPlayer->getName();

                if (!($totalTime = $this->getOwningPlugin()->getPlayedTimeManager()->getTotalTime($name)) instanceof DateInterval) {
                    $s->sendMessage($this->getMsg("time-command:onFail", ["{player}" => $name]));
                    return;
                }

                if ($tPlayer instanceof Player) $s->sendMessage($this->getMsg("time-command:onSuccess2",
                    ["{player}" => $name, "{time}" => $timeMsg($this->getOwningPlugin()->getPlayedTimeManager()->getSessionTime($tPlayer))]));

                $s->sendMessage($this->getMsg("time-command:onSuccess",
                    ["{player}" => $name, "{time}" => $timeMsg($this->getOwningPlugin()->getPlayedTimeManager()->getTotalTime($name))]));

                break;

            case "top":
                if (!$this->checkPermission($s, "playedtime.cmd.top")) return;

                $top = null;
                $all = $this->getOwningPlugin()->getPlayedTimeManager()->getConfig()->getAll();
                $allTimes = [];
                foreach ($all as $playerName => $intervalStr)
                    if (($tt = PlayedTimeManager::getInstance()->getTotalTime($playerName)) instanceof DateInterval)
                        $allTimes[$playerName] = (new DateTime('now'))->add($tt)->getTimestamp();

                PlayedTimeLoader::getInstance()->getServer()->getAsyncPool()->submitTask(
                    new TopSortAsyncTask($s->getName(), $allTimes, (isset($args[1]) && is_numeric($args[1]) && $args[1] > 0 ? $args[1] : 1))
                );
                break;

            default:
                $cmdArr = ["mytime", "time", "top"];
                $cmds = "";
                foreach ($cmdArr as $cmd) {
                    $cmds .= "\n§6/playedtime " . $cmd . " " . ($this->getMsg($cmd . "-command:args") !== "Message not found!" ? $this->getMsg($cmd . "-command:args") : "") . " §r - §b" . $this->getMsg($cmd . "-command:description");
                }
                $s->sendMessage($this->getMsg("help-command:onSuccess", ["{commands}" => $cmds]));

                break;
        }
    }
}