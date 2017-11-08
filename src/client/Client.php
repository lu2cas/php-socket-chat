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
        $this->stdin = fopen('php://stdin', 'r');

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
        print("Iniciando cliente do php-socket-chat...\n\n");

        $this->clientSocket = Socket::getClientSocket(
            $this->config->server->address->ip,
            $this->config->server->address->port
        );

        $this->running = true;

        $this->bindUsername();

        $this->sendRequest('printWelcomeMessage');

        while (true) {
            $this->handleServerResponse();
            $this->handleUserInput();
        }
    }

    /**
     * Manipula as respostas enviadas pelo servidor
     *
     * @return void
     */
    private function handleServerResponse()
    {
        $waiting_for_reading_sockets = Socket::getSocketsWaitingForReading([$this->clientSocket]);

        if (!empty($waiting_for_reading_sockets)) {
            $response = Socket::readFromSocket($this->clientSocket);
            $response = json_decode($response, true);

            $output = '';
            if (array_key_exists('datetime', $response) && !empty($response['datetime'])) {
                $output .= sprintf("[%s]", $response['datetime']);
            }

            if (array_key_exists('sender', $response) && !empty($response['sender'])) {
                $output .= sprintf("[%s]: ", $response['sender']);
            }

            if (array_key_exists('message', $response) && !empty($response['message'])) {
                $output .= sprintf("%s", $response['message']);
            }

            if (!empty($output)) {
                printf("%s\n", $output);
            }

            if (array_key_exists('exit', $response) && $response['exit'] == 1) {
                Socket::closeSocket($this->clientSocket);
                exit(0);
            }
        }
    }

    /**
     * Realiza uma leitura na entrada padrão sem bloquear o processo principal
     *
     * @return string|boolean Texto informado, ou false caso não houver entradas
     */
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

    /**
     * Manipula as entradas do usuário
     *
     * @return void
     */
    private function handleUserInput()
    {
        $input = $this->nonBlockRead();

        if (!empty($input)) {
            $input = trim($input);
            $input = explode(' ', $input);

            $method = array_shift($input);
            $parameters = $input;

            switch ($method) {
                case '/e':
                    $this->sendRequest('echo', ['message' => $parameters[0]]);
                    break;
                case '/q':
                    $this->sendRequest('quit');
                    break;
                default:
                    print("Comando inválido.\n");
                    break;
            }
        }
    }

    /**
     * Cria uma requisição para o servidor
     *
     * @param string $method Método a ser executado no servidor
     * @param array $parameters Parâmetros do método a ser executado
     * @return void
     */
    private function sendRequest($method, $parameters = [])
    {
        $request = [
            'method' => $method,
            'parameters' => $parameters
        ];

        $request = json_encode($request);

        Socket::writeOnSocket($this->clientSocket, $request);
    }

    /**
     * Atribui um username informado pelo usuário ao cliente conectado ao servidor
     *
     * @return void
     */
    private function bindUsername()
    {
        print('Por favor, digite o seu nome de usuário: ');
        $username = fgets($this->stdin);

        //@todo Validar nome de usuário
        $username = trim($username);

        if (!empty($username)) {
            $this->sendRequest('bindUsername', ['username' => $username]);
        }

        print("\n");
    }
}
