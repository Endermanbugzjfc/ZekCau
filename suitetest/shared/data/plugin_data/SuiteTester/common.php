<?php

use Endermanbugzjfc\ZekCau\Plugin\MainClass;
use SOFe\AwaitStd\AwaitStd;
use SOFe\SuiteTester\Await;
use SOFe\SuiteTester\Main;
use muqsit\fakeplayer\network\listener\ClosureFakePlayerPacketListener;
use pocketmine\Server;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
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

    public function __construct() {
        $this->std = Main::$std;
        $this->plugin = Main::getInstance();
        $this->server = Server::getInstance();
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

    public function findZombie(Player $player) : Zombie {
        $zombie = null;
        foreach ($admin->getWorld()->getEntites() as $entity) {
            if ($entity instanceof Zombie) {
                return $entity;
            }
        }

        throw new \RuntimeException("The zombie is not in the same world as player");
    }
}

function init_steps(Context $context) : Generator {
    yield "wait for ZekCau to initialize" => function() use($context) {
        yield from $context->std->awaitEvent(PluginEnableEvent::class, fn(PluginEnableEvent $event) : bool => $event->getPlugin() instanceof MainClass, false, EventPriority::MONITOR, false);
    };

    yield "wait for two players to join" => function() use($context) {
        $onlineCount = 0;
        foreach($context->server->getOnlinePlayers() as $player) {
            if($player->isOnline()) {
                $onlineCount += 1;
            }
        }
        if($onlineCount < 2) {
            yield from $context->std->awaitEvent(PlayerJoinEvent::class, fn($_) => count($context->server->getOnlinePlayers()) === 2, false, EventPriority::MONITOR, false);
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

    yield "control one player to attack another" => function () use ($context) {
        yield from [];

        $a = $context->server->getOnlinePlayers()[0] ?? throw new \RuntimeException("Server has 0 players");
        $b = $context->server->getOnlinePlayers()[0] ?? throw new \RuntimeException("Server has 1 player only");
        Await::f2c(function () use ($context, $a, $b) : \Generator {
            yield from $context->std->sleep(0);
            $a->attackEntity($b);
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

function zombie_attack_test(Context $context, string $playerName) : Generator {
    yield "control the zombie to attack player" => function() use($context, $playerName) {
        $player = $context->server->getPlayerExact($playerName);
        $zombie = $context->findZombie($player);
        Await::f2c(function () use ($context, $player, $zombie) : \Generator {
            yield from $context->std->sleep(0);
            $zombie->attackEntity($player);
        });

        $event = yield from $context->awaitEvent(
            EntityDamageByEntityEvent::class,
            static fn() => true,
            false,
            EventPriority::MONITOR,
            true, // Handle cancelled.
            $player
        );
        $damager = $event->getDamager();
        if ($damager !== $a) {
            throw new \RuntimeException("Damager is not \"" . $a->getName() . "\"");
        }
        $entity = $event->getEntity();
        if ($entity !== $b) {
            throw new \RuntimeException("Entity is not \"" . $b->getName() . "\"");
        }
        if (!$event->isCancelled()) {
            throw new \RuntimeException("Event is not cancelled");
        }
    };
}
