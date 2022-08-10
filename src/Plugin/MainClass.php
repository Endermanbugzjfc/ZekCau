<?php

declare(strict_types=1);

namespace Endermanbugzjfc\ZekCau\Plugin;

use SOFe\AwaitStd\AwaitStd;
use pocketmine\plugin\PluginBase;

final class MainClass extends PluginBase {
	public function onEnable() : void{
		CombatMode::$std = AwaitStd::init($this);
	}

	public function onDisable() : void{
		unset(CombatMode::$std);
	}
}
