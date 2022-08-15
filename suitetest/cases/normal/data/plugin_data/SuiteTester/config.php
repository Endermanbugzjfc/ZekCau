<?php

require __DIR__ . "/common.php";

return function() {
    $context = new Context;

    yield from init_steps($context);
    yield from zombie_attack_test($context, "alice");
};
