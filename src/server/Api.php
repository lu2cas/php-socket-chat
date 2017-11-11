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
     * Retorna a chave de um cliente no conjunto de clientes conectados
     *
     * @param string $identifier Valor do identificador de cliente
     * @param string $target Nome do identificador de cliente
     * @return int
     */
    private function getClientKey($identifier, $target = 'address')
    {
        $found_key = null;

        foreach ($this->clients as $client_key => $client) {
            if ($client[$target] == $identifier) {
                $found_key = $client_key;
                break;
            }
        }

        return $found_key;
    }

    /**
     * Retorna o registro de um cliente
     *
     * @param string $address Endereço do cliente
     * @return array
     */
    private function getClient($identifier, $target = 'address')
    {
        $found_client = null;

        $client_key = $this->getClientKey($identifier, $target);

        if (!is_null($client_key)) {
            $found_client = $this->clients[$client_key];
        }

        return $found_client;
    }

    /**
     * Formata e envia dados para um cliente
     *
     * @param array $recipient Registro do cliente de destino
     * @param string $message Dados a serem enviados
     * @param string $sender Username do cliente que envia os dados
     * @param boolean $datetime Anexar data e hora de envio à mensage?
     * @param boolean $exit Desconetar cliente de destino?
     * @throws Exception
     * @return void
     */
    private function sendData($recipient, $message, $sender = null, $datetime = false, $exit = false)
    {
        $data = [
            'message' => $message,
            'sender' => is_null($sender) ? '' : $sender,
            'datetime' => $datetime ? date('d/m/Y H:i:s') : '',
            'exit' => $exit ? 1 : 0
        ];

        $data = json_encode($data);

        try {
            Socket::writeOnSocket($recipient['socket'], $data);
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
        $client = $this->clients[$this->clientKey];

        $message = sprintf(
            "Bem-vindo, %s!\nVocê está conectado ao servidor com o endereço %s.\n",
            $client['username'],
            $client['address']
        );

        $this->sendData($client, $message);
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
        $client = $this->clients[$this->clientKey];

        $message = sprintf("%s, você disse \"%s\".", $client['username'], $message);
        $sender = 'servidor';

        $this->sendData($client, $message, $sender);
    }

    /**
     * Desconecta um cliente do servidor
     *
     * @throws Exception
     * @return void
     */
    public function quit()
    {
        $client = $this->clients[$this->clientKey];

        $message = 'Cliente desconectado.';
        $exit = true;

        $this->sendData($client, $message, false, false, $exit);
        Socket::closeSocket($client['socket']);

        Logger::log(sprintf("%s desconectado.", $client['address']), Logger::INFO);

        unset($this->clients[$this->clientKey]);
    }

    /**
     * Envia uma mensagem para um cliente
     *
     * @throws Exception
     * @return void
     */
    public function sendMessage($message, $username)
    {
        $sender = $this->clients[$this->clientKey];
        $recipient = $this->getClient($username, 'username');

        if (!is_null($recipient)) {
            $this->sendData($recipient, $message, $sender['username'], true);
        }
    }
}
