<?php

namespace Client;

use Lib\Socket;
use Lib\Logger;
use Lib\Input;

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
     * Username do cliente selecionado para conversa privada
     * @var string
     */
    private $activeRecipientUsername;

    /**
     * Nome do grupo selecionado para conversa em grupo
     * @var string
     */
    private $activeGroupName;

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
        $this->activeRecipientUsername = null;
        $this->activeGroup = null;

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

        $this->bindUsername();

        $this->sendRequest('printWelcomeMessage');

        while (true) {
            $this->handleServerMessages();
            $this->handleUserInput();
        }
    }

    /**
     * Manipula as respostas enviadas pelo servidor
     *
     * @return void
     */
    private function handleServerMessages()
    {
        $waiting_for_reading_sockets = Socket::getSocketsWaitingForReading([$this->clientSocket]);

        if (!empty($waiting_for_reading_sockets)) {
            $data = Socket::readFromSocket($this->clientSocket);
            $data = json_decode($data, true);

            if ($data['type'] == 'message') {
                if (empty($data['sender_username'])) {
                    printf("%s\n", $data['message']);
                } else if ($data['sender_username'] == $this->activeRecipientUsername) {
                    printf("[%s][%s]: %s\n", $data['datetime'], $data['sender_username'], $data['message']);
                }
            }
        }
    }

    /**
     * Manipula as entradas do usuário
     *
     * @return void
     */
    private function handleUserInput()
    {
        $input = Input::nonBlockRead();

        if (!empty($input)) {
            if (!is_null($this->activeRecipientUsername)) {
                if ($input == '/exit') {
                    $this->activeRecipientUsername = null;
                } else {
                    $this->sendRequest('sendChatMessage', ['recipient_username' => $this->activeRecipientUsername, 'message' => $input]);
                }
            } else {
                list($command, $parameters) = $this->parseInput($input);
                switch ($command) {
                    case '/echo':
                        $this->sendRequest('echo', ['message' => current($parameters)]);
                        break;
                    case '/quit':
                        $this->quit();
                        break;
                    case '/privatechat':
                        $this->startPrivateChat(current($parameters));
                        break;
                    default:
                        print("Comando inválido.\n");
                        break;
                }
            }
        }
    }

    /**
     * Analisa e formata a entrada do usuário
     *
     * @return array Comando e parâmetros
     */
    public function parseInput($input)
    {
        $parsed_input = [
            'command' => null,
            'parameters' => []
        ];

        if (substr($input, 0, 1) == '/') {
            $space = strpos($input, ' ');

            if ($space !== false) {
                $command = substr($input, 0, $space);
                $parameters = trim(substr($input, $space));

                if (in_array($command, ['/echo', '/privatechat'])) {
                    $parameters = [$parameters];
                } else {
                    $parameters = explode($parameters, ' ');
                }

                $parsed_input['command'] = $command;
                $parsed_input['parameters'] = $parameters;
            } else {
                $parsed_input['command'] = $input;
            }
        }

        return array_values($parsed_input);
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
        $username = Input::read('Por favor, digite o seu nome de usuário');

        //@todo Validar nome de usuário
        $username = trim($username);

        if (!empty($username)) {
            $this->sendRequest('bindUsername', ['username' => $username]);
        }

        print("\n");
    }

    /**
     * Inicia um chat privado
     *
     * @param string $username Username do usuário de destino do chat privado
     * @return void
     */
    private function startPrivateChat($username)
    {
        $this->activeRecipientUsername = trim($username);

        // Limpa a tela
        print(chr(27) . chr(91) . 'H' . chr(27) . chr(91) . 'J');
        printf("Você está em um chat privado com %s.\n\n", $this->activeRecipientUsername);
    }

    private function quit() {
        $this->sendRequest('quit');

        while (true) {
            $waiting_for_reading_sockets = Socket::getSocketsWaitingForReading([$this->clientSocket]);

            if (!empty($waiting_for_reading_sockets)) {
                $data = Socket::readFromSocket($this->clientSocket);
                $data = json_decode($data, true);
                if ($data['type'] == 'response' && $data['method'] == 'quit') {
                    printf("%s\n", $data['message']);
                    exit(0);
                }
            }
        }
    }
}
