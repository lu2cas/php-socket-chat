<?php

try {
    $loader = require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

    $server = new \Server\Server;
    $server->run();

    exit(0);
} catch(\Exception $e) {
    printf("Erro ao executar aplicação: %s\n", $e->getMessage());
    exit(1);
}
