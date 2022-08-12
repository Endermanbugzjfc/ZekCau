# ZekCau
ZekCau (Cantonese of 隻抽, means "go it alone") is an example implementation of PocketMine-MP dual-players combat mode using [Await-Std](https://github.com/SOF3/await-std).

But can also be used as an API (virion) or plugin for instant use.

## What is combat mode
Combat modes are enabled in pairs. Two players in the same combat mode pair can only attack / get attacked by each other. Damage cannot be dealed if they try to attack any other entity or vice versa.

> so combat lock of sorts
>
> -- <cite>[Thunder33345#9999](https://discord.com/channels/373199722573201408/430364566027763744/1006997537716183152)</cite>
# API
## Preparation
```php
use Endermanbugzjfc\ZekCau\CombatMode;
use Endermanbugzjfc\ZekCau\CombatSession;
use SOFe\AwaitStd\AwaitStd;
```
Initialize an AwaitStd instance.
```php
$std = AwaitStd::init($this);
```
## Usage
`autoEnable()` will enable combat mode for two players when one attacks another.

The `$until` callback is called every time when they attack each other, means the combat mode timer should be reset by creating a new generator. The following code returns a sleep-generator (generator version of delayed task) of 15 seconds:
```php
CombatMode::autoEnable($this, new CombatSession(
	$std,
	static fn(CombatSession $s) : \Generator => yield from $s->std()->sleep(15 * 20),
));
```
You are not limited to just `yield from` a sleep-generator, side effects can be made too. Such as sending popups (or updating a boss bar):
```php
CombatMode::autoEnable($std, static function (CombatSession $s) : \Generator {
	foreach ($s->players() as $player) {
		$player->sendPopup("Combat mode timer resets!");
	}

	yield from $std->sleep(15 * 20);
});		
```
## Count down popup example
```php
CombatMode::autoEnable($this, new CombatSession($std, static function (CombatSession $s) : \Generator {
	// Avoid two generators running at the same time and send overlapping popups.
	static $running = 0; // $running will not reset after this (closure) function ends because of static.
	$current = ++$running;

	for ($seconds = 15; $seconds > 0; $seconds--) {
		foreach ($s->players() as $player) {
			$player->sendPopup("Combat mode count down: $seconds seconds.");
		}

		yield from $std->sleep(20); // Sleep 1 second.
		if ($running !== $current) {
			return; // New generator is created. Stop this one to not send overlapping popups and waste system resources (redundantly creating sleep-generators).
		}
	}

	$running = 0;
}));
```