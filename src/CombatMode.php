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
    /**
     * Init a new / reset an existed combat session.
     */
    public static function enable(CombatSession $s) : void
    {
        Await::f2c(function () use ($s) : Generator {
            $event = null;
            do {
                if (isset($event) && (!in_array($event->getDamager(), $s->players(), true) || !in_array($event->getEntity(), $s->players(), true))) {
                    $event->cancel();
                } else {
                    $s->resetUntil();
                }

                $awaitEvent = $s->std()->awaitEvent(
                    EntityDamageByEntityEvent::class,
                    fn(EntityDamageByEntityEvent $event) => in_array($event->getDamager(), $s->players(), true) || in_array($event->getEntity(), $s->players(), true),
                    false,
                    EventPriority::NORMAL,
                    false,
                    ...$s->players()
                );
                [, $event] = yield from Await::race([$awaitEvent, $s->until()]);
            } while ($event instanceof EntityDamageByEntityEvent);
            $s->close();
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
                    $opened = $s->open($a, $b);
                    if ($opened !== null) {
                        self::enable($opened);
                    }
                }
            }
        });
    }
}
