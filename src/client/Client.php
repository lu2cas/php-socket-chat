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
        do {
            Socket::writeOnSocket($this->clientSocket, '{"method":"echo","parameters":{"message":"hello"}}');

            $message = Socket::readFromSocket($this->clientSocket);
            if (!empty($message)) {
                print($message);
            }
        } while ($this->running);

        Socket::closeSocket($this->clientSocket);
    }

    /**
     * Encerra o cliente
     *
     * @throws Exception
     * @return void
     */
    public function halt()
    {
        $this->running = false;
    }
}
