# ZekCau
ZekCau (Cantonese of 隻抽, means "go it alone") is an example implementation of PocketMine-MP dual-players combat mode using [Await-Std](https://github.com/SOF3/await-std).

But can also be used as an API (virion) or plugin for instant use.

## What is combat mode
Combat modes are enabled in pairs. Two players in the same combat mode pair can only attack / get attacked by each other. Damage cannot be dealed if they try to attack any other entity or vice versa.

> so combat lock of sorts
- [Thunder33345#9999](https://discord.com/channels/373199722573201408/430364566027763744/1006997537716183152)
# API
## Preparation
```php
use Endermanbugzjfc\ZekCau\CombatMode;
use SOFe\AwaitStd\AwaitStd;
```
You must initialize an AwaitStd instance before calling any functions in CombatMode:
```php
CombatMode::$std = $std = AwaitStd::init($this);
```
## Usage
`autoEnable()` will enable combat mode for two players when one attacks another.

The `$until` callback is called every time when they attack each other, means the combat mode timer should be reset. So a new generator is created:
```php
CombatMode::autoEnable(function () use ($std) : \Generator {
	// Each combat mode will least for 15 seconds. The timer resets on damage.
	yield from $std->sleep(15 * 20);
});
```
You are not limited to just `yield from` a sleep-generator, side effects can be made too. Such as sending popups (or updating a boss bar):
```php
CombatMode::autoEnable(function (Player $a, Player $b) use ($std) : \Generator {
	foreach ([$a, $b] as $player) {
		$player->sendPopup("Combat mode timer resets!");
	}

	// Each combat mode will least for 15 seconds. The timer resets on damage.
	yield from $std->sleep(15 * 20);
});		
```
## Count down popup example
```php
$running = 0;
CombatMode::autoEnable(function (Player $a, Player $b) use ($std, &$running) : \Generator {
	// Avoid two generators running at the same time and send overlapping popups.
	$current = ++$running;

	for ($seconds = 15; $seconds > 0; $seconds--) {
		foreach ([$a, $b] as $player) {
			$player->sendPopup("Combat mode count down: $seconds seconds.");
		}

		yield from $std->sleep(20); // Sleep 1 second.
		if ($running !== $current) {
			return; // New generator is created. Stop this one to not send overlapping popups and waste system resources (redundantly yielding from sleep-generator).
		}
	}

	$running = 0;
});
```