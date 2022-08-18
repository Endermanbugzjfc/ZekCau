<?php

declare(strict_types=1);

namespace Endermanbugzjfc\ZekCau;

use Closure;
use Generator;
use pocketmine\player\Player;
use RuntimeException;
use SOFe\AwaitGenerator\Loading;
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
        $this->resetUntil();
    }

    /**
     * @var true[]
     * @phpstan-var array<string, true> Key = player 16 bytes unique ID.
     */
    public static array $inSession;

    public function open(Player $a, Player $b, Player ...$players) : ?self
    {
        $players = [$a, $b, ...$players];

        $indexes = [];
        foreach ($players as $player) {
            $indexes[] = $index = $player->getUniqueId()->getBytes();
            if (isset(self::$inSession[$index])) {
                return null;
            }
        }
        foreach ($indexes as $index) {
            self::$inSession[$index] = true;
        }

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
     * @var Loading<null>
     */
    private Loading $loading;

    /**
     * @return Generator<mixed, mixed, mixed, void>
     */
    public function until() : Generator
    {
        /**
         * "Replicate" the same generator because a generator can only run once. While Await::race() yet consumes a generator even though it lose the race.
         *
         * https://github.com/Endermanbugzjfc/ZekCau/runs/7893322801?check_suite_focus=true#step:3:308
         *
         * > yeah Await::race is not a good idea right now because of poorly defined losing behavior
         * - SOFe#4765
         */
        yield from $this->loading->get();
    }

    public function resetUntil() : void
    {
        $this->loading = new Loading(function () : Generator {
            yield from ($this->u)($this);
            return null;
        });
    }

    public function close() : void
    {
        foreach ($this->p as $player) {
            $index = $player->getUniqueId()->getBytes();
            if (!isset(self::$inSession[$index])) {
                $name = $player->getName();
                throw new RuntimeException("CombatSession memory leaked (\"$name\" is not in session)");
            }

            unset(self::$inSession[$index]);
        }
    }
}
