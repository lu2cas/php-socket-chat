<?php

namespace Server;

class Server
{
    private $config;
    private $socket;
    private $clients;

    public function __construct()
    {
        $this->configure();
    }

    private function configure(): void
    {
        try {
            $contents = file_get_contents(realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'config.json');
            $this->config = json_decode($contents);
        } catch(Exception $e) {
            printf("Erro ao ler arquivo de configurações: \"%s\".\n", $e->getMessage());
            exit(1);
        }

        $this->clients = [];

        error_reporting(E_ALL);
        set_time_limit(0);
        ob_implicit_flush();
    }

    private function openSocket(): void
    {
        try {
            if (($this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
                $socket_error = socket_strerror(socket_last_error());
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

    private function closeSocket(): void
    {
        socket_close($this->socket);
    }

    private function getWatingForReadingSockets(): array
    {
        $waiting_for_reading_sockets = [];

        $read = array_merge([$this->socket], $this->clients);
        $write = $except = null;
        if (socket_select($read, $write, $except, 5) > 0) {
            $waiting_for_reading_sockets = $read;
        }

        return $waiting_for_reading_sockets;
    }

    public function run(): void
    {
        $this->openSocket();

        do {
            $waiting_for_reading_sockets = $this->getWatingForReadingSockets();

            // Verifica se o servidor recebeu uma nova conexão
            if (in_array($this->socket, $waiting_for_reading_sockets)) {
                try {
                    if (($client_socket = socket_accept($this->socket)) === false) {
                        $socket_error = socket_strerror(socket_last_error($this->socket));
                        $error_message = sprintf("Falha ao estabelecer conexão com o cliente: \"%s\".\n", $error_message);
                        throw new Exception($error_message);
                    }
                } catch(Exception $e) {
                    print($e->getMessage());
                    break;
                }

                $client_ip = $client_port = null;
                socket_getpeername($client_socket, $client_ip, $client_port);
                printf("%s:%s se conectou.\n", $client_ip, $client_port);

                $this->clients[] = $client_socket;
                $client_key = array_search($client_socket, $this->clients);

                // Envia instruções ao cliente
                $welcome_message = "\nBem-vindo ao WhatsLike!\n" .
                "Você é o cliente #{$client_key}.\n" .
                "Para sair, envie \"quit\". Para encerrar o servidor envie \"shutdown\".\n";
                socket_write($client_socket, $welcome_message, strlen($welcome_message));
            }

            // Gerencia entradas de clientes
            foreach ($this->clients as $key => $client_socket) {
                if (in_array($client_socket, $waiting_for_reading_sockets)) {
                    try {
                        $message = '';
                        do {
                            $buffer = @socket_read($client_socket, 5, PHP_NORMAL_READ);
                            if ($buffer === false) {
                                $socket_error = socket_strerror(socket_last_error($client_socket));
                                $error_message = sprintf("Falha ao capturar mensagem do cliente: \"%s\".\n", $socket_error);
                                throw new Exception($error_message);
                            }
                            $message .= $buffer;
                        } while (!empty(trim($buffer)));

                        //@todo Formatar a mensagem com o charset correto
                        $message = trim($message);
                    } catch(Exception $e) {
                        print($e-getMessage());
                        break 2;
                    }

                    if (empty($message)) {
                        continue;
                    }

                    if ($message == 'quit') {
                        unset($this->clients[$key]);
                        socket_close($client_socket);
                        break;
                    }

                    if ($message == 'shutdown') {
                        socket_close($client_socket);
                        break 2;
                    }

                    $echo_message = sprintf("Cliente #%s, você disse \"%s\".\n", $key, $message);
                    socket_write($client_socket, $echo_message, strlen($echo_message));

                    printf("Cliente #%s enviou: \"%s\".\n", $key, $message);
                }
            }
        } while (true);

        $this->closeSocket();
    }
}
