<?php

require_once realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'server.php';

$server = new Server();
$server->run();
exit(0);
