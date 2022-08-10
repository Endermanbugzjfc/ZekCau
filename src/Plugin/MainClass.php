<?php

declare(strict_types=1);

namespace Endermanbugzjfc\ZekCau\Plugin;

use SOFe\AwaitStd\AwaitStd;
use pocketmine\plugin\PluginBase;

final class MainClass extends PluginBase {
	public function onEnable() : void{
		CombatMode::$std = $std = AwaitStd::init($this);
		CombatMode::autoEnable(function () use ($std) : \Generator {
			// Each combat mode will least for 15 seconds. The timer resets on damage.
			yield from $std->sleep(15 * 20);
		});
	}

	public function onDisable() : void{
		unset(CombatMode::$std);
	}
}
