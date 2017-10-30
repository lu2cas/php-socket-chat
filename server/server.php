<?php

class Server
{
    private $config;
    private $socket;

    public function __construct()
    {
        $this->configure();
    }

    private function configure() {
        try {
            $contents = file_get_contents(realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'config.json');
            $this->config = json_decode($contents);
        } catch(Exception $e) {
            printf("Erro ao ler arquivo de configurações: \"%s\".\n", $e->getMessage());
            exit(1);
        }

        error_reporting(E_ALL);
        set_time_limit(0);
        ob_implicit_flush();
    }

    private function openSocket()
    {
        try {
            if (($this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
                $socket_error = socket_strerror(socket_last_error($this->socket));
                $error_message = sprintf("Erro ao criar socket do servidor: \"%s\".\n", $error_message);
                throw new Exception($error_message);
            }

            socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
            if (@socket_bind($this->socket, $this->config->address->ip, $this->config->address->port) === false) {
                $socket_error = socket_strerror(socket_last_error($this->socket));
                $error_message = sprintf("Erro ao endereçar socket do servidor: \"%s\".\n", $socket_error);
                throw new Exception($error_message);
            }

            if (@socket_listen($this->socket, 5) === false) {
                $socket_error = socket_strerror(socket_last_error($this->socket));
                $error_message = sprintf("Erro ao abrir conexões com o socket do servidor: \"%s\".\n", $socket_error);
                throw new Exception($error_message);
            }
        } catch(Exception $e) {
            print($e->getMessage());
            exit(1);
        }
    }

    public function __closeSocket()
    {
        socket_close($this->socket);
    }

    public function run()
    {
        $this->openSocket();

        $clients = [];
        do {
            // Verifica se há alguma modificação no status de algum cliente
            $read = array_merge([$this->socket], $clients);
            $write = null;
            $except = null;
            $timeout = 5;
            if (socket_select($read, $write, $except, $timeout) === 0) {
                continue;
            }

            // Configura novas conexões
            if (in_array($this->socket, $read)) {
                try {
                    if (($client = socket_accept($this->socket)) === false) {
                        $socket_error =  socket_strerror(socket_last_error($this->socket));
                        $error_message =  sprintf("Falha ao estabelecer conexão com o cliente: \"%s\".\n", $error_message);
                        throw new Exception();
                    }
                } catch(Exception $e) {
                    print($e->getMessage());
                    break;
                }

                $client_ip = null;
                $client_port = null;
                socket_getpeername($client, $client_ip, $client_port);
                printf("%s:%s se conectou.\n", $client_ip, $client_port);

                $clients[] = $client;
                $client_key = array_search($client, $clients);

                // Envia instruções ao cliente
                $welcome_message = "\nBem-vindo ao WhatsLike!\n" .
                "Você é o cliente número: {$client_key}\n" .
                "Para sair, envie \"quit\". Para encerrar o servidor envie \"shutdown\".\n";
                socket_write($client, $welcome_message, strlen($welcome_message));
            }

            // Gerencia entradas
            foreach ($clients as $key => $client) {
                if (in_array($client, $read)) {
                    try {
                        if (($buffer = socket_read($client, 2048, PHP_NORMAL_READ)) === false) {
                            $socket_error = socket_strerror(socket_last_error($client));
                            $error_message = sprintf("Falha ao receber mensagem do cliente: \"%s\".\n", $socket_error);
                            throw new Exception($error_message);
                        }
                        //@todo Formatar o $buffer com o charset correto
                        $buffer = trim($buffer);
                    } catch(Exception $e) {
                        print($e-getMessage());
                        break 2;
                    }

                    if (empty($buffer)) {
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

                    $echo_message = sprintf("Cliente #%s, você disse \"%s\".\n", $key, $buffer);
                    socket_write($client, $echo_message, strlen($echo_message));

                    printf("Cliente #%s enviou: \"%s\".\n", $key, $buffer);
                }
            }
        } while (true);

        $this->closeSocket();
    }
}
