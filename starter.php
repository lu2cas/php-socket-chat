<?php

try {
    $loader = require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

    if (isset($argv[1]) && ($argv[1] == 'client' || $argv[1] == 'server')) {
        $agent = $argv[1];
    } else {
        throw new Exception('Argumentos inválidos para inicialização do agente.');
    }

    if ($agent == 'server') {
        $server = new \Server\Server;
        $server->run();
    } elseif ($agent == 'client') {
        $client = new \Client\Client;
        $client->run();
    }

    exit(0);
} catch(\Exception $e) {
    printf("Aplicação encerrada: %s\n", $e->getMessage());
    exit(1);
}
