<?php
namespace PocketKiller\ShootingRod;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;

use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\protocol\UseItemPacket;

use pocketmine\event\entity\EntityDamageEvent;

use pocketmine\nbt\tag\EnumTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\CompoundTag;

use pocketmine\entity\Snowball;
use pocketmine\entity\Arrow;
use pocketmine\entity\Egg;

use pocketmine\level\sound\BlazeShootSound;
use pocketmine\level\sound\AnvilFallSound;


class Main extends PluginBase implements Listener {

	private $users = [];
	public $disallow = [];

	public function onEnable(){
		$this->saveDefaultConfig();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		if($this->getServer()->getName() == "Genisys"){
			$this->getServer()->getPluginManager()->registerEvents(new GenisysHack(), $this);// a Genisys hack which cancels PlayerFishEvent only for Genisys users.
		}

		if(!$this->checkConfig()){
			$this->getLogger()->error("there was a problem with your config.yml, please delete it and restart the server for a new clean config.");
			$this->getServer()->getPluginManager()->disablePlugin($this);
		}
	}

	public function onPacketRecieve(DataPacketReceiveEvent $event){
		if($event->getPacket() instanceof UseItemPacket){
			$player = $event->getPlayer();
			if($player->getInventory()->getItemInHand()->getId() == 346 && $player->hasPermission('shootingrod.use')){
				if(!isset($this->disallowed[$player->getId()])){
						$namedTag = new CompoundTag("", [
								"Pos" => new EnumTag("Pos", [
									new DoubleTag("", $player->x),
									new DoubleTag("", $player->y + $player->getEyeHeight()),
									new DoubleTag("", $player->z)
								]),
								"Motion" => new EnumTag("Motion", [
									new DoubleTag("", -sin($player->yaw / 180 * M_PI) * cos($player->pitch / 180 * M_PI)),
									new DoubleTag("", -sin($player->pitch / 180 * M_PI)),
									new DoubleTag("", cos($player->yaw / 180 * M_PI) * cos($player->pitch / 180 * M_PI))
								]),
								"Rotation" => new EnumTag("Rotation", [
									new FloatTag("", $player->yaw),
									new FloatTag("", $player->pitch)
								]),
							]);
						
						$type = $this->getConfig()->get("type");

						if(strtolower($type) == "egg") {
							$e = new Egg($player->chunk, $namedTag, $player);
						} elseif(strtolower($type) == "snowball") {
							$e = new Snowball($player->chunk, $namedTag, $player);
						} elseif(strtolower($type) == "arrow") {
							$e = new Arrow($player->chunk, $namedTag, $player);
						}

						$e->setMotion($e->getMotion()->multiply($this->getConfig()->get("speed")));
						$e->spawnToAll();
						$player->getLevel()->addSound(new BlazeShootSound($player), [$player]);

					array_push($this->users, $player->getId());
					if($this->getConfig()->get("cooldown") == 'true' && $player->hasPermission('shootingrod.cooldown')){ //TODO : Enable option for specific players.
						$this->disallowed[$player->getId()] = $player->getId();
						$this->getServer()->getScheduler()->scheduleDelayedTask(new Cooldown($this, $player), $this->getConfig()->get("cooldown-time") * 20);
						return;
					}
					return;
				} elseif(isset($this->disallowed[$player->getId()])) $player->sendTip("§c§lCooldown...");
			}
		}
	}

	public function onDamage(EntityDamageEvent $event){
		if($event instanceof \pocketmine\event\entity\EntityDamageByEntityEvent){
			if(in_array($event->getDamager()->getId(), $this->users)){
				$event->setDamage($this->getConfig()->get("damage")); //TODO : add armor support
				unset($this->users[array_search($event->getDamager()->getId(), $this->users)]);
				$event->getEntity()->getLevel()->addSound(new AnvilFallSound($event->getEntity()));
			}
		}
	}

	public function checkConfig() : bool{
		if($this->getConfig()->get("damage") !== null && $this->getConfig()->get("cooldown") !== null && $this->getConfig()->get("cooldown-time") !== null && $this->getConfig()->get("type") !== null && $this->getConfig()->get("speed") !== null){
			
			$entities = array('snowball', 'arrow', 'egg');
			if(in_array(strtolower($this->getConfig()->get("type")), $entities)){
				if(is_integer($this->getConfig()->get("damage")) && is_integer($this->getConfig()->get("cooldown-time"))){
					return true;
				}
				return false;
			}
			return false;
		}
		return false;
	}
}