<?php

use Endermanbugzjfc\ZekCau\Plugin\MainClass;
use SOFe\AwaitStd\AwaitStd;
use SOFe\SuiteTester\Await;
use SOFe\SuiteTester\Main;
use muqsit\fakeplayer\Loader;
use muqsit\fakeplayer\behaviour\PvPFakePlayerBehaviour;
use muqsit\fakeplayer\network\listener\ClosureFakePlayerPacketListener;
use pocketmine\Server;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Zombie;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\plugin\PluginEnableEvent;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\ClosureTask;

class PlayerReceiveMessageEvent extends Event {
    public function __construct(
        public Player $player,
        public string $message,
        public int $type,
    ) {}
}

class Context {
    /** @var AwaitStd $std do not type hint directly, because included files are not shaded */
    public $std;
    public Plugin $plugin;
    public Server $server;
    public Loader $fakePlayer;

    public function __construct() {
        $this->std = Main::$std;
        $this->plugin = Main::getInstance();
        $this->server = Server::getInstance();

        foreach ($this->server->getPluginManager()->getPlugins() as $plugin) {
            if ($plugin instanceof Loader) {
                $this->fakePlayer = $plugin;
                break;
            }
        }
        if (!isset($this->fakePlayer)) {
            throw new \RuntimeException("Failed to get fake player loader instance");
        }
    }

    public function awaitMessage(Player $who, string $messageSubstring, ...$args) : Generator {
        $expect = strtolower(sprintf($messageSubstring, ...$args));
        $this->server->getLogger()->debug("Waiting for message to {$who->getName()} " . json_encode($expect));
        return yield from $this->std->awaitEvent(
            event: PlayerReceiveMessageEvent::class,
            eventFilter: fn($event) => $event->player === $who && str_contains(strtolower($event->message), $expect),
            consume: false,
            priority: EventPriority::MONITOR,
            handleCancelled: false,
        );
    }

    public function createPvPFakePlayerBehaviour() : PvPFakePlayerBehaviour {
        return new PvPFakePlayerBehaviour(4, 0);
    }
}

function init_steps(Context $context) : Generator {
    yield "wait for ZekCau to initialize" => function() use($context) {
        yield from $context->std->awaitEvent(PluginEnableEvent::class, fn(PluginEnableEvent $event) : bool => $event->getPlugin() instanceof MainClass, false, EventPriority::MONITOR, false);
    };

    yield "wait for three players to join" => function() use($context) {
        $onlineCount = 0;
        foreach($context->server->getOnlinePlayers() as $player) {
            if($player->isOnline()) {
                $onlineCount += 1;
            }
        }
        if($onlineCount < 3) {
            yield from $context->std->awaitEvent(PlayerJoinEvent::class, fn($_) => count($context->server->getOnlinePlayers()) === 3, false, EventPriority::MONITOR, false);
        }

        yield from $context->std->sleep(10);
    };

    yield "setup chat listeners" => function() use($context) {
        false && yield;
        foreach($context->server->getOnlinePlayers() as $player) {
            $player->getNetworkSession()->registerPacketListener(new ClosureFakePlayerPacketListener(
                function(ClientboundPacket $packet, NetworkSession $session) use($player, $context) : void {
                    if($packet instanceof TextPacket) {
                        $context->server->getLogger()->debug("{$player->getName()} received message: $packet->message");

                        $event = new PlayerReceiveMessageEvent($player, $packet->message, $packet->type);
                        $event->call();
                    }
                }
            ));
        }
    };

    yield "spawn one zombie near one player" => function () use ($context) {
        yield from [];

        $player = array_values($context->server->getOnlinePlayers())[0] ?? throw new \RuntimeException("No player to spawn zombie nearby");
        new Zombie($player->getLocation());
    };

    yield "control one to attack another" => function () use ($context) {
        yield from [];

        $players = array_values($context->server->getOnlinePlayers());
        $a = $players[0] ?? throw new \RuntimeException("Server has 0 players");
        $b = $players[1] ?? throw new \RuntimeException("Server has 1 player only");


        $behaviour = $context->createPvPFakePlayerBehaviour();
        $aFake = $context->fakePlayer->getFakePlayer($a);
        Await::f2c(function () use ($context, $aFake, $behaviour, $b) : \Generator {
            yield from $context->std->sleep(0);
            $aFake->getPlayer()->setTargetEntity($b);
            $aFake->addBehaviour($behaviour);
        });

        $event = yield from $context->std->awaitEvent(
            EntityDamageByEntityEvent::class,
            static fn() => true,
            false,
            EventPriority::MONITOR,
            false,
            $a,
            $b
        );
        $aFake->removeBehaviour($behaviour);
        $damager = $event->getDamager();
        if ($damager !== $a) {
            throw new \RuntimeException("Damager is not \"" . $a->getName() . "\"");
        }
        $entity = $event->getEntity();
        if ($entity !== $b) {
            throw new \RuntimeException("Entity is not \"" . $b->getName() . "\"");
        }
    };
}

function player_attack_test(Context $context, string $a, string $b, string $c) : Generator {
    yield "control the zombie to attack player" => function() use($context, $a, $b, $c) {
        $carrie = $context->server->getPlayerExact($a);
        $player = $context->server->getPlayerExact($b);
        $player2 = $context->server->getPlayerExact($c);
        $behaviour = $context->createPvPFakePlayerBehaviour();
        Await::f2c(function () use ($context, $carrie, $behaviour) : \Generator {
            yield from $context->std->sleep(0);
            $context->fakePlayer->getFakePlayer($carrie)->addBehaviour($behaviour);
        });
        $combatMode = true;
        Await::f2c(function () use ($context, &$combatMode) : \Generator {
            yield from $context->std->sleep(14 * 20);
            $combatMode = false;
        });

        while (true) {
            $event = yield from $context->std->awaitEvent(
                EntityDamageByEntityEvent::class,
                static fn() => true,
                false,
                EventPriority::MONITOR,
                true, // Handle cancelled.
                $player
            );
            $damager = $event->getDamager();
            if ($damager !== $carrie) {
                throw new \RuntimeException("Damager is not \"" . $carrie->getName() . "\"");
            }
            $entity = $event->getEntity();
            switch (true) {
                case $entity === $player:
                    $carrie->setTargetEntity($player2);
                    break;

                case $entity === $player2:
                    $carrie->setTargetEntity($player);
                    break;

                default:
                    throw new \RuntimeException("Entity is not \"" . $player->getName() . "\" or \"" . $player2->getName() . "\"");
            }
            if ($combatMode && !$event->isCancelled()) {
                throw new \RuntimeException("COMBAT mode, NOT cancelled");
            } elseif (!$combatMode && $event->isCancelled()) {
                throw new \RuntimeException("FREE mode, cancelled");
            }
        }
    };
}
