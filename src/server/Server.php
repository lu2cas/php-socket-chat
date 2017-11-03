<?php

namespace Server;

use Lib\Socket;
use Lib\Logger;

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
     * Indicador de execução das atividades do servidor
     * @var \Server\Api
     */
    private $api;

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
                throw new \Exception('Erro ao ler arquivo de configurações.');
            }

            $this->config = json_decode($contents);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Erro no formato do arquivo de configurações.');
            }
        } catch(\Exception $e) {
            printf("%s\n", $e->getMessage());
            exit(1);
        }

        $this->serverSocket = null;
        $this->clients = [];
        $this->api = new Api();

        error_reporting(E_ALL);
        set_time_limit(0);
        ob_implicit_flush();
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

        Logger::log("Servidor iniciado.");

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
     * @throws Exception
     * @return void
     */
    public function halt()
    {
        foreach ($this->clients as $client_socket) {
            Socket::writeOnSocket($client_socket, "Servidor encerrado.\n");
            Socket::closeSocket($client_socket);
        }
        $this->isRunning = false;
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
            Logger::log(sprintf("%s:%s se conectou.", $client_ip, $client_port));

            $this->clients[] = $client_socket;
            $client_key = array_search($client_socket, $this->clients);

            // Envia instruções ao cliente
            $welcome_message = "\nBem-vindo ao WhatsLike!\n" .
            "Você é o cliente #{$client_key}.\n";

            Socket::writeOnSocket($client_socket, $welcome_message);
        }
    }

    /**
     * Manipula as requisições dos clientes conectados ao servidor
     *
     * @throws Exception
     * @return void
     */
    private function handleClientsRequests()
    {
        $waiting_for_reading_sockets = Socket::getSocketsWaitingForReading(array_merge([$this->serverSocket], $this->clients));

        foreach ($this->clients as $client_key => $client_socket) {
            if (in_array($client_socket, $waiting_for_reading_sockets)) {
                try {
                    $json_request = Socket::readFromSocket($client_socket);
                    $request = new Request($json_request);
                    if ($request->isValid()) {
                        $this->execute($request, $client_key);
                    }
                } catch(\Exception $e) {
                    Logger::log(sprintf("Falha na requisição do cliente #%s: %s", $client_key, $e->getMessage()));
                    Socket::writeOnSocket($client_socket, sprintf("%s\n", $e->getMessage()));
                    continue;
                }
            }
        }
    }

    /**
     * Interpreta e valida uma requisição
     *
     * @param \Server\Request $request Objeto de requisição
     * @param int $requester_key Chave do socket do cliente que fez a requisição
     * @throws Exception
     * @return void
     */
    private function execute($request, $requester_key)
    {
        $method = $request->getMethod();

        $parameters = $request->getParameters();
        $parameters['requester_key'] = $requester_key;
        $parameters['clients'] = $this->clients;

        $return = call_user_func_array(
            [
                $this->api,
                $method
            ],
            $parameters
        );

        if ($method == 'quit') {
            $this->clients = $return;
        }
    }

}
