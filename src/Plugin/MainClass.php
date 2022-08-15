<?php

declare(strict_types=1);

namespace Endermanbugzjfc\ZekCau\Plugin;

use Endermanbugzjfc\ZekCau\CombatMode;
use Endermanbugzjfc\ZekCau\CombatSession;
use Generator;
use pocketmine\plugin\PluginBase;
use SOFe\AwaitStd\AwaitStd;

final class MainClass extends PluginBase
{
    public function onEnable() : void
    {
        $std = AwaitStd::init($this);
        CombatMode::autoEnable($this, new CombatSession(
            $std,
            static fn(CombatSession $s) : Generator => yield from $s->std()->sleep(15 * 20),
        ));
    }
}
