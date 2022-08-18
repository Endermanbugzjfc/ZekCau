<?php

require __DIR__ . "/common.php";

return function() {
    $context = new Context;

    yield from init_steps($context);
    yield from player_attack_test($context, "林月娥", "alice", "bob");
};
