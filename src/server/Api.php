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
    * Cliente que realiza a requisição
    *
    * @var array
    */
    private $client;

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
    public function __construct($client_address, $clients)
    {
        $this->clientAddress = $client_address;
        $this->clients = $clients;

        $client_key = $this->getClientKey($client_address);
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
     * @param string $address Endereço do cliente
     * @return int
     */
    private function getClientKey($address)
    {
        $found_key = null;

        foreach ($this->clients as $key => $client) {
            if ($client['address'] == $address) {
                $found_key = $key;
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
    private function getClient($address)
    {
        $found_client = null;

        foreach ($this->clients as $client) {
            if ($client['address'] == $address) {
                $found_client = $client;
                break;
            }
        }

        return $found_client;
    }

    /**
     * Formata e envia dados para um cliente
     *
     * @param array $client Registro do cliente de destino
     * @param array $message Dados a serem enviados
     * @throws Exception
     * @return void
     */
    private function sendData($client, $data)
    {
        $data = json_encode($data);

        try {
            Socket::writeOnSocket($client['socket'], $data);
        } catch(\Exception $e) {
            Logger::log(sprintf("Falha ao enviar dados para %s: %s", $client['address'], $e->getMessage()), Logger::WARNING);
        }
    }

    /**
     * Associa um nome de usuário ao cliente
     *
     * @return void
     */
    public function bindUsername($username)
    {
        $client_key = $this->getClientKey($this->clientAddress);
        $this->clients[$client_key]['username'] = $username;
    }

    /**
     * Envia uma mensagem de boas-vindas ao cliente
     *
     * @return void
     */
    public function printWelcomeMessage()
    {
        $client = $this->getClient($this->clientAddress);

        $welcome_message = sprintf(
            "Bem-vindo, %s!\nVocê está conectado ao servidor com o endereço %s.\n",
            $client['username'],
            $client['address']
        );

        $data = [
            'message' => $welcome_message
        ];

        $this->sendData($client, $data);
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
        $client = $this->getClient($this->clientAddress);

        $data = [
            'message' => sprintf("%s, você disse \"%s\".", $client['username'], $message),
            'sender' => 'servidor'
        ];

        $this->sendData($client, $data);
    }

    /**
     * Desconecta um cliente do servidor
     *
     * @throws Exception
     * @return void
     */
    public function quit()
    {
        $client_key = $this->getClientKey($this->clientAddress);

        $data = [
            'message' => 'Cliente desconectado.',
            'exit' => 1
        ];
        $this->sendData($this->clients[$client_key], $data);
        Socket::closeSocket($this->clients[$client_key]['socket']);

        Logger::log(sprintf("%s se desconectou.", $this->clients[$client_key]['address']), Logger::INFO);

        unset($this->clients[$client_key]);
    }
}
