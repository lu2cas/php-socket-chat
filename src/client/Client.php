<?php

namespace Client;

use Lib\Socket;
use Lib\Logger;

/**
 * Classe responsável por criar um cliente capaz de se conectar a um servidor
 *
 * @author Luccas C. Silveira
 * @license GNU General Public License v3.0
  */
class Client
{
    /**
     * Conjunto de configurações internas do cliente
     * @var array
     */
    private $config;

    /**
     * Socket responsável por realizar as funções de cliente
     * @var resource
     */
    private $clientSocket;

    /**
     * Indicador de execução das atividades do client
     * @var boolean
     */
    private $running;

    /**
     * Stream da entrada de dados padrão
     * @var resource
     */
    private $stdin;

    /**
     * Construtor da classe
     *
     * @return void
     */
    public function __construct() {
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
            throw new \Exception('Formato inválido de arquivo de configurações do cliente.');
        }
        $this->clientSocket = null;
        $this->running = false;
        $this->stdin = fopen('php://stdin','r');

        error_reporting(E_ALL);
        set_time_limit(0);
        ob_implicit_flush();
    }

    /**
     * Inicia o cliente
     *
     * @return void
     */
    public function run()
    {
        $this->clientSocket = Socket::getClientSocket(
            $this->config->server->address->ip,
            $this->config->server->address->port
        );

        $this->running = true;

        $this->joinServer();

        do {
            $this->handleServerResponse();
            $this->handleUserInput();
        } while ($this->running);

        Socket::closeSocket($this->clientSocket);
    }

    /**
     * Encerra o cliente
     *
     * @return void
     */
    public function halt()
    {
        $this->running = false;
    }

    private function handleServerResponse()
    {
        $waiting_for_reading_sockets = Socket::getSocketsWaitingForReading([$this->clientSocket]);

        if (!empty($waiting_for_reading_sockets)) {
            $message = Socket::readFromSocket($this->clientSocket);
            if (!empty(trim($message))) {
                printf("%s\n", $message);
            }
        }
    }

    public function nonBlockRead() {
        $read = [$this->stdin];
        $null = null;
        $result = stream_select($read, $null, $null, 0);

        if ($result === 0) {
            return false;
        }

        $input = fgets($this->stdin);

        return trim($input);
    }

    private function handleUserInput()
    {
        $input = $this->nonBlockRead();

        if (!empty($input)) {
            $input = trim($input);
            $input = explode(' ', $input);

            $method = array_shift($input);
            $parameters = $input;

            switch ($method) {
                case '/m':
                    $request = $this->makeRequest('sendMessage', $parameters);
                    Socket::writeOnSocket($this->clientSocket, $request);
                    break;

                default:
                    print("Comando inválido.\n");
                    break;
            }
        }
    }

    private function makeRequest($method, $parameters = [])
    {
        $request = [
            'method' => $method,
            'parameters' => $parameters
        ];

        $request = json_encode($request);

        return $request;
    }

    private function joinServer()
    {
        print('Por favor, digite o seu nome de usuário: ');
        $username = fgets($this->stdin);

        //@todo Validar nome de usuário
        if (!empty($username)) {
            $request = $this->makeRequest('join', ['usename' => $username]);
            Socket::writeOnSocket($this->clientSocket, $request);
        }
    }
}
