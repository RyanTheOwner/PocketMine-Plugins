<?php

/**
 * ExplosiveArrow plugin for PocketMine-MP
 * Copyright (C) 2017 JackNoordhuis
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

namespace jacknoordhuis\explosivearrows;

use pocketmine\entity\Arrow;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\Listener;
use pocketmine\level\Explosion;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\Player;

class EventListener implements Listener {

	/** @var ExplosiveArrows */
	private $plugin = null;

	/** @var bool */
	protected $terrainDamage;

	/** @var int */
	protected $defaultSize;

	/** @var bool */
	private $closed = false;

	public function __construct(ExplosiveArrows $plugin) {
		$this->plugin = $plugin;
		$this->terrainDamage = $plugin->getSettings()->get("terrain-damage", false);
		$this->defaultSize = $plugin->getSettings()->get("default-explosion-size", 4);
		$plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
	}

	public function getPlugin() : ExplosiveArrows {
		return $this->plugin;
	}

	/**
	 * Make sure we add the custom bow data to the arrow
	 *
	 * @param EntityShootBowEvent $event
	 *
	 * @ignoreCancelled true
	 *
	 * @priority HIGHEST
	 */
	public function onShoot(EntityShootBowEvent $event) {
		$projectile = $event->getProjectile();
		if($projectile instanceof Arrow) {
			$bow = $event->getBow();
			if($bow->hasCompoundTag()) {
				$tag = $bow->getNamedTag();
				if(isset($tag->TerrainDamage)) {
					$projectile->namedtag->TerrainDamage = new ByteTag("TerrainDamage", $tag["TerrainDamage"]);
				}
				if(isset($tag->ExplosionSize)) {
					$projectile->namedtag->ExplosionSize = new IntTag("ExplosionSize", rtrim($tag["ExplosionSize"], "i"));
				}
			}
		}
	}

	/**
	 * Handle our awesome explosions ^-^
	 *
	 * @param ProjectileHitEvent $event
	 *
	 * @ignoreCancelled true
	 *
	 * @priority HIGHEST
	 */
	public function onHit(ProjectileHitEvent $event) {
		$projectile = $event->getEntity();
		if($projectile->isAlive() and $projectile instanceof Arrow) {
			$shooter = $projectile->getOwningEntity();
			if($shooter instanceof Player) {
				$tag = $projectile->namedtag;
				$terrainDamage = $this->terrainDamage;
				$size = $this->defaultSize;
				if($tag instanceof CompoundTag) {
					if(isset($tag->TerrainDamage)) {
						$terrainDamage = ExplosiveArrows::byteToBool($tag["TerrainDamage"]);
					}
					if(isset($tag->ExplosionSize)) {
						$size = (int)$tag["ExplosionSize"];
					}
				}
				if($size > 0) {
					$explosion = new Explosion($projectile->getPosition(), $size);
					if($terrainDamage) {
						$explosion->explodeA();
					}
					$explosion->explodeB();
					$projectile->kill();
				}
			}
		}
	}

	/**
	 * Dump all the class properties safely
	 */
	public function close() {
		if(!$this->closed) {
			$this->closed = true;
			unset($this->plugin, $this->toHit);
		}
	}

	public function __destruct() {
		$this->close();
	}
}