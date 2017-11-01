<?php

namespace Server;

use Lib\Socket;

/**
 * Classe responsável por criar um servidor capaz de aceitar conexões de
 * clientes e gerenciar suas requisições
 *
 * @author Luccas C. Silveira
 * @license GNU General Public License v3.0
  */
class Server
{
    /**
     * Conjunto de configurações internas do servidor
     * @var array
     */
    private $config;

    /**
     * Socket responsável por realizar as funções do servidor
     * @var resource
     */
    private $serverSocket;

    /**
     * Conjunto de clientes conectados ao servidor
     * @var array
     */
    private $clients;

    /**
     * Indicador de execução das atividades do servidor
     * @var boolean
     */
    private $isRunning;

    /**
     * Construtor da classe
     *
     * @return void
     */
    public function __construct()
    {
        $this->configure();
    }

    /**
     * Configura os parâmetros internos do servidor
     *
     * @return void
     */
    private function configure()
    {
        try {
            $contents = @file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'config.json');
            if ($contents === false) {
                throw new \Exception("Erro ao ler arquivo de configurações.\n");
            }

            $this->config = json_decode($contents);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Erro no formato do arquivo de configurações.\n");
            }
        } catch(\Exception $e) {
            printf($e->getMessage());
            exit(1);
        }

        $this->serverSocket = null;
        $this->clients = [];

        error_reporting(E_ALL);
        set_time_limit(0);
        ob_implicit_flush();
    }


    /**
     * Registra os clientes que se conectam ao servidor
     *
     * @return void
     */
    private function acceptClients()
    {
        $waiting_for_reading_sockets = Socket::getSocketsWaitingForReading(array_merge([$this->serverSocket], $this->clients));

        // Verifica se o servidor recebeu uma nova conexão
        if (in_array($this->serverSocket, $waiting_for_reading_sockets)) {
            $client_socket = Socket::acceptSocket($this->serverSocket);

            list($client_ip, $client_port) = Socket::getSocketAddress($client_socket);
            printf("%s:%s se conectou.\n", $client_ip, $client_port);

            $this->clients[] = $client_socket;
            $client_key = array_search($client_socket, $this->clients);

            // Envia instruções ao cliente
            $welcome_message = "\nBem-vindo ao WhatsLike!\n" .
            "Você é o cliente #{$client_key}.\n" .
            "Para sair, envie \"quit\".\n";

            Socket::writeOnSocket($client_socket, $welcome_message);
        }
    }

    /**
     * Manipula as requisições dos clientes conectados ao servidor
     *
     * @return void
     */
    private function handleClientsRequests()
    {
        $waiting_for_reading_sockets = Socket::getSocketsWaitingForReading(array_merge([$this->serverSocket], $this->clients));

        foreach ($this->clients as $key => $client_socket) {
            if (in_array($client_socket, $waiting_for_reading_sockets)) {
                try {
                    $message = Socket::readFromSocket($client_socket);
                } catch(\Exception $e) {
                    print($e->getMessage());
                    continue;
                }

                //@todo Testar remoção de cliente
                if ($message == 'quit') {
                    unset($this->clients[$key]);
                    Socket::closeSocket($client_socket);
                    continue;
                }

                $echo_message = sprintf("Cliente #%s, você disse \"%s\".\n", $key, $message);
                Socket::writeOnSocket($client_socket, $echo_message);

                printf("Cliente #%s enviou: \"%s\".\n", $key, $message);
            }
        }
    }

    /**
     * Inicia as atividades do servidor
     *
     * @return void
     */
    public function run()
    {
        $this->serverSocket = Socket::getServerSocket(
            $this->config->address->ip,
            $this->config->address->port
        );
        if ($this->serverSocket === false) {
            throw new \Exception("Erro ao criar socket do servidor.\n");
        }

        $this->isRunning = true;
        do {
            $this->acceptClients();
            $this->handleClientsRequests();
        } while ($this->isRunning);

        Socket::closeSocket($this->serverSocket);
    }

    /**
     * Encerra as atividades do servidor
     *
     * @return void
     */
    public function stop()
    {
        $this->isRunning = false;
    }
}
