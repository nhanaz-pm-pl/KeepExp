<?php

declare(strict_types=1);

namespace NhanAZ\KeepExp;

use pocketmine\utils\Config;
use pocketmine\player\Player;
use pocketmine\event\Listener;
use pocketmine\utils\TextFormat;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\network\mcpe\protocol\ToastRequestPacket;

class Main extends PluginBase implements Listener {

	protected Config $config;
	private $playerExp = [];

	protected function onEnable(): void {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->saveDefaultConfig();
		$this->config = $this->getConfig();
	}

	private function sendToast(Player $player, string $title, string $body): void {
		$player->getNetworkSession()->sendDataPacket(
			ToastRequestPacket::create(
				TextFormat::colorize($title),
				TextFormat::colorize($body)
			)
		);
	}

	private function msgAfterRespawn(Player $player): void {
		$msgAfterRespawn = $this->config->get("msgAfterRespawn", "You died, but your experience is safe!");
		match ($this->config->get("msgType", "none")) {
			"message" => $player->sendMessage(TextFormat::colorize($msgAfterRespawn)),
			"title" => $player->sendTitle(TextFormat::colorize($msgAfterRespawn)),
			"popup" => $player->sendPopup(TextFormat::colorize($msgAfterRespawn)),
			"tip" => $player->sendTip(TextFormat::colorize($msgAfterRespawn)),
			"actionbar" => $player->sendActionBarMessage(TextFormat::colorize($msgAfterRespawn)),
			"toast" => $this->sendToast($player, $this->config->get("toastTitle", "[KeepExp]"), $msgAfterRespawn),
			default => "None"
		};
	}

	private function keepExp($event): void {
		$player = $event->getPlayer();
		if ($this->config->get("keepExp", true)) {
			match ($this->config->get("typeExp", "droppedExp")) {
				"droppedExp" => $this->playerExp[$player->getName()] = $event->getXpDropAmount(),
				"realExp" => $this->playerExp[$player->getName()] = $player->getXpManager()->getCurrentTotalXp(),
				default => "None"
			};
			$event->setXpDropAmount(0);
		}
	}

	public function onPlayerDeath(PlayerDeathEvent $event): void {
		if ($this->config->get("keepExp", true)) {
			$worldName = $event->getPlayer()->getWorld()->getDisplayName();
			$worlds = $this->config->get("worlds", []);
			switch ($this->config->get("mode", "all")) {
				case "all":
					$this->keepExp($event);
					break;
				case "whitelist":
					if (in_array($worldName, $worlds, true)) {
						$this->keepExp($event);
					}
					break;
				case "blacklist":
					if (!in_array($worldName, $worlds, true)) {
						$this->keepExp($event);
					}
					break;
			}
		}
	}

	public function onPlayerRespawn(PlayerRespawnEvent $event): void {
		$player = $event->getPlayer();
		if ($this->config->get("keepExp", true)) {
			$this->getScheduler()->scheduleDelayedTask(new ClosureTask(
				function () use ($player): void {
					if (isset($this->playerExp[$player->getName()])) {
						if ($player->isOnline()) {
							$player->getXpManager()->addXp($this->playerExp[$player->getName()]);
							$this->msgAfterRespawn($player);
						}
						unset($this->playerExp[$player->getName()]);
					}
				}
			), 20);
		}
	}
}
