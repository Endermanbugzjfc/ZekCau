<?php

declare(strict_types=1);

namespace Endermanbugzjfc\ZekCau;

use Generator;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\EventPriority;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitStd\DisposableListener;
use function in_array;

/**
 * @api
 */
final class CombatMode
{
    public static function enable(CombatSession $s) : void
    {
        Await::f2c(function () use ($s) : Generator {
            $awaitUntil = $s->until();
            while (true) {
                $awaitEvent = $s->std()->awaitEvent(
                    EntityDamageByEntityEvent::class,
                    fn(EntityDamageByEntityEvent $event) => in_array($event->getDamager(), $s->players(), true) || in_array($event->getEntity(), $s->players(), true),
                    false,
                    EventPriority::NORMAL,
                    false,
                    ...$s->players()
                );
                [, $event] = yield from Await::race([$awaitEvent, $awaitUntil]);

                switch (true) {
                    // $until generator is resolved. Combat mode ends.
                    case !$event instanceof EntityDamageByEntityEvent:
                        return;

                        // Attacked / get attacked by other entities when in combat mode.
                    case !in_array($event->getDamager(), $s->players(), true) || !in_array($event->getEntity(), $s->players(), true):
                        $event->cancel();
                        // TODO: knockback.
                        break;

                        // Next until generator (reset combat mode timer).
                    default:
                        $awaitUntil = $s->until();
                        break;
                }
            }
        }, null, [DisposableListener::class => static function () : void {
        }]);
    }

    public static function autoEnable(Plugin $plugin, CombatSession $s) : void
    {
        Await::f2c(function () use ($s, $plugin) : Generator {
            while ($plugin->isEnabled()) {
                $awaitEvent = $s->std()->awaitEvent(
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
                    self::enable($s->open($a, $b));
                }
            }
        });
    }
}
