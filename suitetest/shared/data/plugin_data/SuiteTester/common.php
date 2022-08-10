<?php

use SOFe\AwaitStd\AwaitStd;
use SOFe\SuiteTester\Await;
use SOFe\SuiteTester\Main;
use keopiwauyu\HyundaiCommando\MainClass;
use muqsit\fakeplayer\network\listener\ClosureFakePlayerPacketListener;
use pocketmine\Server;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;
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
}

function init_steps(Context $context) : Generator {
    yield "register server crasher command" => function() use ($context) {
        false && yield;
        $context->server->getCommandMap()->register("fbp", new class("crasher", "Crash server", "/crasher", ["crash"]) extends Command {
            /**
             * @param mixed[] $args
             */
            public function execute(CommandSender $sender, string $aliasUsed, array $args) : void {
                throw new \RuntimeException("Crasher command executed");
            }
        });
    };

    yield "wait for HyundaiCommando to initialize" => function() use($context) {
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
}


function crash_protector_test(Context $context, string $adminName) : Generator {
    $value = "false";

    yield "execute /crash with value" => function() use($context, $adminName, $value) {
        false && yield;

        Await::f2c(function() use ($context, $adminName, $value) : \Generator {
            yield from $context->std->sleep(0);
        $admin = $context->server->getPlayerExact($adminName);
        $admin->chat("/crash $value");
        });
    };
    yield "wait error message" => function() use($context, $adminName, $value) {
        $admin = $context->server->getPlayerExact($adminName);

        yield from Await::all([
            $context->awaitMessage($admin, "Invalid value '$value' for argument #1"),
        ]);
    };
}
