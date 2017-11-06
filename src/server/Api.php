<?php

namespace Server;

use Lib\Socket;
use Lib\Logger;

/**
 * Classe responsável por disponibilizar os métodos de serviço do servidor
 *
 * @author Luccas C. Silveira
 * @license GNU General Public License v3.0
  */
class Api
{
    /**
    * Chave do cliente que realiza a requisição
    * @var string
    */
    private $clientKey;

    /**
     * Conjunto de clientes conectados ao servidor
     * @var array
     */
    private $clients;

    /**
     * Construtor da classe
     * @param int $client_key Chave do cliente que realiza a requisição
     * @param array $clients Conjunto de clientes conectados ao servidor
     *
     * @return void
     */
    public function __construct($client_key, $clients)
    {
        $this->clientKey = $client_key;
        $this->clients = $clients;
    }

    public function getClients()
    {
        return $this->clients;
    }

    /**
     * Ecoa a mensagem de um cliente
     *
     * @param string $message Mensagem enviada
     * @throws Exception
     * @return void
     */
    public function echo($message)
    {
        $client_socket = $this->clients[$this->clientKey];

        $echo_message = sprintf("Cliente #%s, você disse \"%s\".\n", $this->clientKey, $message);
        Socket::writeOnSocket($client_socket, $echo_message);

        Logger::log(sprintf("Cliente #%s enviou: \"%s\".", $this->clientKey, $message));
    }

    /**
     * Desconecta um cliente do servidor
     *
     * @throws Exception
     * @return void
     */
    public function quit()
    {
        $client_socket = $this->clients[$this->clientKey];

        list($client_ip, $client_port) = Socket::getSocketAddress($client_socket);
        Socket::closeSocket($client_socket);
        unset($this->clients[$this->clientKey]);

        Logger::log(sprintf("%s:%s se desconectou.", $client_ip, $client_port));
    }
}
