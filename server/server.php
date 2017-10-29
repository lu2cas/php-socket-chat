<?php

class Server
{
    private $__config;
    private $__socket;

    public function __construct()
    {
        $this->__configure();
    }

    private function __configure() {
        try {
            $contents = file_get_contents(realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'config.json');
            $contents = utf8_encode($contents);
            $this->__config = json_decode($contents);
        } catch(Exception $e) {
            printf("Erro ao ler arquivo de configurações: \"%s\".\n", $e->getMessage());
            exit(1);
        }

        error_reporting(E_ALL);
        set_time_limit(0);
        ob_implicit_flush();
    }

    private function __openSocket()
    {
        try {
            if (($this->__socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
                throw new Exception(sprintf("Erro ao criar socket do servidor: \"%s\".\n", socket_strerror(socket_last_error())));
            }

            socket_set_option($this->__socket, SOL_SOCKET, SO_REUSEADDR, 1);
            if (@socket_bind($this->__socket, $this->__config->address->ip, $this->__config->address->port) === false) {
                throw new Exception(sprintf("Erro ao endereçar socket do servidor: \"%s\".\n", socket_strerror(socket_last_error($this->__socket))));
            }

            if (@socket_listen($this->__socket, 5) === false) {
                throw new Exception(sprintf("Erro ao abrir conexões com o socket do servidor: \"%s\".\n", socket_strerror(socket_last_error($this->__socket))));
            }
        } catch(Exception $e) {
            print($e->getMessage());
            exit(1);
        }
    }

    public function __closeSocket()
    {
        socket_close($this->__socket);
    }

    public function run()
    {
        $this->__openSocket();

        $clients = [];
        do {
            // Verifica se há alguma modificação no status de algum cliente
            $read = array_merge([$this->__socket], $clients);
            $write = null;
            $except = null;
            $timeout = 5;
            if (socket_select($read, $write, $except, $timeout) === 0) {
                continue;
            }

            // Configura novas conexões
            if (in_array($this->__socket, $read)) {
                try {
                    if (($client = socket_accept($this->__socket)) === false) {
                        throw new Exception(printf("Falha ao estabelecer conexão com o cliente: \"%s\".\n", socket_strerror(socket_last_error($this->__socket))));
                    }
                } catch(Exception $e) {
                    print($e->getMessage());
                    break;
                }

                $clientAddressIp = null;
                $clientAddressPort = null;
                socket_getpeername($client, $clientAddressIp, $clientAddressPort);
                printf("%s:%s se conectou.\n", $clientAddressIp, $clientAddressPort);

                $clients[] = $client;
                $clientKey = array_search($client, $clients);

                // Envia instruções ao cliente
                $welcomeMessage = "\nBem-vindo ao WhatsLike!\n" .
                "Você é o cliente número: {$clientKey}\n" .
                "Para sair, envie \"quit\". Para encerrar o servidor envie \"shutdown\".\n";
                socket_write($client, $welcomeMessage, strlen($welcomeMessage));
            }

            // Gerencia entradas
            foreach ($clients as $key => $client) {
                if (in_array($client, $read)) {
                    try {
                        if (($buffer = socket_read($client, 2048, PHP_NORMAL_READ)) === false) {
                            throw new Exception(printf("Falha ao receber mensagem do cliente: \"%s\".\n", socket_strerror(socket_last_error($client))));
                        }
                        $buffer = utf8_encode($buffer);
                    } catch(Exception $e) {
                        print($e-getMessage());
                        break 2;
                    }

                    if (!$buffer = trim($buffer)) {
                        continue;
                    }

                    if ($buffer == 'quit') {
                        unset($clients[$key]);
                        socket_close($client);
                        break;
                    }

                    if ($buffer == 'shutdown') {
                        socket_close($client);
                        break 2;
                    }

                    $echoMessage = sprintf("Cliente #%s, você disse \"%s\".\n", $key, $buffer);
                    socket_write($client, $echoMessage, strlen($echoMessage));

                    printf("Cliente #%s enviou: \"%s\".\n", $key, $buffer);
                }
            }
        } while (true);

        $this->closeSocket();
    }
}
