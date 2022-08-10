<?php

require __DIR__ . "/common.php";

return function() {
    $context = new Context;

    yield from init_steps($context);
    yield from crash_protector_test($context, "alice");
};
