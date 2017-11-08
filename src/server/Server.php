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
    private $running;

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
        $contents = @file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'config.json');
        if ($contents === false) {
            throw new \Exception('Arquivo de configurações inexistente.');
        }

        $this->config = json_decode($contents);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Formato inválido de arquivo de configurações do servidor.');
        }

        $this->serverSocket = null;
        $this->clients = [];
        $this->running = false;

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

        Logger::log('Servidor iniciado.', Logger::INFO);

        $this->running = true;
        do {
            $this->acceptClients();
            $this->handleClientsRequests();
        } while ($this->running);

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
            Socket::writeOnSocket($client_socket, "Servidor encerrado.");
            Socket::closeSocket($client_socket);
        }
        $this->running = false;
    }

    /**
     * Registra os clientes que se conectam ao servidor
     *
     * @return void
     */
    private function acceptClients()
    {
        $sockets = [$this->serverSocket];
        foreach ($this->clients as $client) {
            $sockets[] = $client['socket'];
        }
        $waiting_for_reading_sockets = Socket::getSocketsWaitingForReading($sockets);

        // Verifica se o servidor recebeu uma nova conexão
        if (in_array($this->serverSocket, $waiting_for_reading_sockets)) {
            $client_socket = Socket::acceptSocket($this->serverSocket);

            list($client_ip, $client_port) = Socket::getSocketAddress($client_socket);
            $client_address = sprintf('%s:%s', $client_ip, $client_port);
            Logger::log(sprintf("%s se conectou.", $client_address), Logger::INFO);

            $this->clients[] = [
                'socket' => $client_socket,
                'address' => $client_address,
                'username' => null
            ];

            // Envia instruções ao cliente
            $welcome_message = "Bem-vindo ao WhatsLike!\n" .
            "O seu endereço é {$client_address}.";

            try {
                Socket::writeOnSocket($client_socket, $welcome_message);
            } catch(\Exception $e) {
                Logger::log(sprintf("Falha ao enviar mensagem para %s: %s", $client_address, $e->getMessage()), Logger::WARNING);
            }
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

        foreach ($this->clients as $client) {
            if (in_array($client['socket'], $waiting_for_reading_sockets)) {
                try {
                    $json_request = Socket::readFromSocket($client['socket']);
                    $request = new Request($json_request);
                    if ($request->isValid()) {
                        $this->execute($request, $client['username']);
                    }
                } catch(\Exception $e) {
                    Logger::log(sprintf("Falha ao executar requisição do cliente #%s: %s", $client['username'], $e->getMessage()), Logger::WARNING);
                    continue;
                }
            }
        }
    }

    /**
     * Interpreta e valida uma requisição
     *
     * @param \Server\Request $request Objeto de requisição
     * @param int $client_key Chave do cliente que realiza a requisição
     * @throws Exception
     * @return void
     */
    private function execute($request, $username)
    {
        $api = new Api($client_key, $this->clients);

        call_user_func_array(
            [
                $api,
                $request->getMethod()
            ],
            $request->getParameters()
        );

        $this->clients = $api->getClients();
    }

}
