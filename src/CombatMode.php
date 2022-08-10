<?php

declare(strict_types=1);

namespace Endermanbugzjfc\ZekCau;

use Endermanbugzjfc\ZekCau\Plugin\MainClass;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitStd\AwaitStd;
use SOFe\AwaitStd\DisposableListener;
use pocketmine\event\EventPriority;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class CombatMode {
	public static AwaitStd $std;

	public static function autoEnable

	public static function enable(Player $a, Player $b, \Generator $until) : bool {
		if (!isset(self::$std)) {
			throw new \RuntimeException('Put this in your onEnable(): \Endermanbugzjfc\ZekCau\CombatMode::$std = \SOFe\AwaitStd\AwaitStd::init($this);');
		}

		Await::f2c(function () use ($a, $b) : \Generator {
			while (true) {
				$awaitEvent = self::$std->awaitEvent(
					EntityDamageByEntityEvent::class,
					fn(EntityDamageByEntityEvent $event) => !in_array($event->getDamager(), [$a, $b], true) || !in_array($event->getEntity(), [$a, $b], true),
					false,
					EventPriority::NORMAL.
					false,
					$a,
					$b
				);
				[, $event] = yield from Await::race([$awaitEvent, $until]);

				if ($event instanceof EntityDamageByEntityEvent) {
					$event->cancel();
					$notify = new AttackCancelledEvent($event);
					$notify->call();
				}
			}
		}, null, [DisposableListener::class => static fn() => null]);
	}
}
