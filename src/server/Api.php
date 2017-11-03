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
    public function __construct()
    {
        // Construtor
    }

    /**
     * Ecoa a mensagem de um cliente
     *
     * @param string $message Mensagem enviada
     * @param int $requester_key Chave do socket do cliente que fez a requisição
     * @param array $clients Sockets de clientes conectados ao servidor
     * @throws Exception
     * @return void
     */
    public function echo($message, $requester_key, $clients)
    {
        $client_socket = $clients[$requester_key];

        $echo_message = sprintf("Cliente #%s, você disse \"%s\".\n", $requester_key, $message);
        Socket::writeOnSocket($client_socket, $echo_message);

        Logger::log(sprintf("Cliente #%s enviou: \"%s\".", $requester_key, $message));
    }

    public function quit($requester_key, $clients)
    {
        $client_socket = $clients[$requester_key];
        list($client_ip, $client_port) = Socket::getSocketAddress($client_socket);
        Socket::closeSocket($client_socket);
        Logger::log(sprintf("%s:%s se desconectou.", $client_ip, $client_port));
        unset($clients[$requester_key]);

        return $clients;
    }
}
