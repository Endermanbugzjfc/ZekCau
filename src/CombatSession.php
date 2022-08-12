<?php

declare(strict_types=1);

namespace Endermanbugzjfc\ZekCau;

use pocketmine\player\Player;

/**
 * @api
 */
class CombatSession {
	/**
	 * @param Player[] $p
	 * @param Closure(self $s): \Generator<mixed, mixed, mixed, void>
	 */
	public function __construct(private AwaitStd $s, private Closure $u, private array $p = []) {

	}

	public function open(Player $a, Player $b, Player ...$players) : self {
		$players = [$a, $b, ...$players];
		return new self($this->s, $this->until, $players);
	}

	/**
	 * @return []Player
	 */
	public function players() : array {
		return $this->p;
	}

	public function std() : AwaitStd {
		return $this->s;
	}

	/**
	 * @return \Generator<mixed, mixed, mixed, void>
	 */
	public function until() : \Generator {
		return yield from $this->u($this);
	}
}
