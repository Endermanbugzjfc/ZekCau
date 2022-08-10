<?php

declare(strict_types=1);

namespace Endermanbugzjfc\ZekCau;

use Endermanbugzjfc\ZekCau\Plugin\MainClass;
use pocketmine\event\Event;

final class AttackCancelledEvent extends Event {
	public function __construct(protected EntityDamageByEntityEvent $event) {
	}

	public function getDamageEvent() : EntityDamageByEntityEvent {
		return $this->event;
	}
}
