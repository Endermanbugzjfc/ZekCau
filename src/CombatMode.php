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

/**
 * @api
 */
final class CombatMode {
	public static AwaitStd $std;

	/**
	 * @param \Closure(Player $a, Player $b): \Generator<mixed, mixed, mixed, void> $until Creates a new generator which with can end the combat mode by resolving.
	 */
	public static function enable(Player $a, Player $b, \Closure $until) : void {
		Await::f2c(function () use ($a, $b, $until) : \Generator {
			if (!isset(self::$std)) {
				throw new \RuntimeException('Put this in your onEnable(): \Endermanbugzjfc\ZekCau\CombatMode::$std = \SOFe\AwaitStd\AwaitStd::init($this);');
			}

			$until = fn() => $until($a, $b);
			$awaitUntil = $until();
			while (true) {
				$awaitEvent = self::$std->awaitEvent(
					EntityDamageByEntityEvent::class,
					fn(EntityDamageByEntityEvent $event) => in_array($event->getDamager(), [$a, $b], true) || in_array($event->getEntity(), [$a, $b], true),
					false,
					EventPriority::NORMAL.
					false,
					$a,
					$b
				);
				[, $event] = yield from Await::race([$awaitEvent, $awaitUntil]);

				switch (true) {
					// $until generator is resolved. Combat mode ends.
					case !$event instanceof EntityDamageByEntityEvent:
						return;

					// Attacked / get attacked by other entities when in combat mode.
					case !in_array($event->getDamager(), [$a, $b], true) || !in_array($event->getEntity(), [$a, $b], true):
						$event->cancel();
						// TODO: knockback.
						break;

					// Next until generator (reset combat mode timer).
					default:
						$awaitUntil = $until();
						break;
				}
			}
		}, null, [DisposableListener::class => static fn() => null]);
	}

	/**
	 * @param \Closure(Player $a, Player $b): \Generator<mixed, mixed, mixed, void> $until Creates a new generator which with can end the combat mode by resolving.
	 */
	public static function autoEnable(callable $until) : void {
		Await::f2c(function () use ($player) : \Generator {
			while (true) {
				$awaitEvent = self::$std->awaitEvent(
					EntityDamageByEntityEvent::class,
					static fn(EntityDamageByEntityEvent $event) => true,
					false,
					EventPriority::MONITOR,
					false
				);
				$event = yield from $awaitEvent;

				$a = $event->getDamager();
				$b = $event->getEntity();
				if ($a instanceof Player && $b instanceof Player) {
					self::enable($a, $b, $until);
				}
			}
		});
	}
}
