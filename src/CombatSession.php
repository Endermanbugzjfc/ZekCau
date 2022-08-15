<?php

declare(strict_types=1);

namespace Endermanbugzjfc\ZekCau;

use Closure;
use Generator;
use pocketmine\player\Player;
use SOFe\AwaitStd\AwaitStd;

/**
 * @api
 */
class CombatSession
{
    /**
     * @param \Closure(self $s): \Generator<mixed, mixed, mixed, void> $u
     * @param Player[] $p
     */
    public function __construct(private AwaitStd $s, private Closure $u, private array $p = [])
    {
    }

    public function open(Player $a, Player $b, Player ...$players) : self
    {
        $players = [$a, $b, ...$players];
        return new self($this->s, $this->u, $players);
    }

    /**
     * @return Player[]
     */
    public function players() : array
    {
        return $this->p;
    }

    public function std() : AwaitStd
    {
        return $this->s;
    }

    /**
     * @return Generator<mixed, mixed, mixed, void>
     */
    public function until() : Generator
    {
        yield from ($this->u)($this);
    }
}
