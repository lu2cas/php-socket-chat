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
    *
    * @var int
    */
    private $client_key;

    /**
     * Conjunto de clientes conectados ao servidor
     *
     * @var array
     */
    private $clients;

    /**
     * Construtor da classe
     *
     * @param string $client_address Endereço do cliente que realiza a requisição
     * @param array $clients Conjunto de clientes conectados ao servidor
     * @return void
     */
    public function __construct($client_key, &$clients)
    {
        $this->clientKey = $client_key;
        $this->clients = $clients;
    }

    /**
     * Retorna o conjunto atualizado de clientes conectados ao servidor
     *
     * @return array
     */
    public function getClients()
    {
        return $this->clients;
    }

    /**
     * Retorna o registro de um cliente
     *
     * @param string $address Endereço do cliente
     * @return array
     */
    private function getClient($identifier, $target = 'username')
    {
        $found_client = null;

        foreach ($this->clients as $client_key => $client) {
            if ($client[$target] == $identifier) {
                $found_client = $client;
                break;
            }
        }

        return $found_client;
    }

    /**
     * Formata e envia dados para um cliente
     *
     * @param array $recipient Registro do cliente de destino
     * @param string $message Mensagem a ser enviada
     * @param string $sender_username Username do remetente; null, se o remetente for o servidor
     * @throws Exception
     * @return void
     */
    private function sendMessage($recipient, $message, $sender_username = null)
    {
        $data = [
            'type' => 'message',
            'message' => $message,
            'sender_username' => is_null($sender_username) ? '' : $sender_username,
            'datetime' => date('d/m/Y H:i:s')
        ];

        $data = json_encode($data);

        try {
            Socket::writeOnSocket($recipient['socket'], $data);
        } catch(\Exception $e) {
            Logger::log(sprintf("Falha ao enviar dados para %s: %s", $recipient['address'], $e->getMessage()), Logger::WARNING);
        }
    }

    /**
     * Formata e envia a resposta de um método para um cliente
     *
     * @param string $method Método solicitado
     * @param boolean $success Sucesso da execução do método
     * @param string $message Mensagem gerada pelo método
     * @param array $data Dados retornados pelo método
     * @throws Exception
     * @return void
     */
    private function sendResponse($method, $success, $message = null, $data = null)
    {
        $recipient = $this->clients[$this->clientKey];

        $response = [
            'type' => 'response',
            'method' => $method,
            'success' => $success ? 1 : 0,
            'message' => is_null($message) ? '' : $message,
            'data' => empty($data) ? '' : $data,
            'datetime' => date('d/m/Y H:i:s')
        ];

        $response = json_encode($response);

        try {
            Socket::writeOnSocket($recipient['socket'], $response);
        } catch(\Exception $e) {
            Logger::log(sprintf("Falha ao enviar dados para %s: %s", $recipient['address'], $e->getMessage()), Logger::WARNING);
        }
    }

    /**
     * Associa um nome de usuário ao cliente
     *
     * @return void
     */
    public function bindUsername($username)
    {
        $this->clients[$this->clientKey]['username'] = $username;
    }

    /**
     * Envia uma mensagem de boas-vindas ao cliente
     *
     * @return void
     */
    public function printWelcomeMessage()
    {
        $recipient = $this->clients[$this->clientKey];

        $message = sprintf(
            "Bem-vindo, %s!\nVocê está conectado ao servidor com o endereço %s.\n",
            $recipient['username'],
            $recipient['address']
        );

        $this->sendMessage($recipient, $message);
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
        $recipient = $this->clients[$this->clientKey];

        $message = sprintf("%s, você disse \"%s\".", $recipient['username'], $message);

        $this->sendMessage($recipient, $message);
    }

    /**
     * Desconecta um cliente do servidor
     *
     * @throws Exception
     * @return void
     */
    public function quit()
    {
        $recipient = $this->clients[$this->clientKey];

        $this->sendResponse('quit', true, 'Desconectando cliente...');

        Socket::closeSocket($recipient['socket']);

        Logger::log(sprintf("%s desconectado.", $recipient['address']), Logger::INFO);

        unset($this->clients[$this->clientKey]);
    }

    /**
     * Envia uma mensagem para um cliente
     *
     * @throws Exception
     * @return void
     */
    public function sendChatMessage($recipient_username, $message)
    {
        $recipient = $this->getClient($recipient_username);

        if (!is_null($recipient)) {
            $this->sendMessage($recipient, $message, $this->clients[$this->clientKey]['username']);
        }
    }
}
