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
     * Nome de usuário associado ao cliente
     * @var string
     */
    private $username;

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
     * Conjunto de respostas retornadas pelo servidor
     * @var array
     */
    private $responsesBuffer;

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
            $this->handleServerResponses();
            $this->handleUserInput();
            usleep(250);
        }
    }

    /**
     * Manipula as respostas enviadas pelo servidor
     *
     * @return void
     */
    private function handleServerResponses()
    {
        $waiting_for_reading_sockets = Socket::getSocketsWaitingForReading([$this->clientSocket], 0);

        if (!empty($waiting_for_reading_sockets)) {
            $data = Socket::readFromSocket($this->clientSocket);
            $data = json_decode($data, true);

            if ($data['type'] == 'message') {
                if (empty($data['sender_username'])) {
                    printf("%s\n", $data['message']);
                } else if ($data['sender_username'] == $this->activeRecipientUsername) {
                    printf("[%s][%s]: %s\n", $data['time'], $data['sender_username'], $data['message']);
                }
            } else if ($data['type'] == 'response') {
                $this->responsesBuffer[] = $data;
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
        $input = Input::nonBlockingRead();

        if (!empty($input)) {
            if (!is_null($this->activeRecipientUsername)) {
                if ($input == '/exit') {
                    $message = sprintf("%s saiu do chat privado com você.\n", $this->username);
                    $this->sendRequest('sendChatNotification', ['username' => $this->activeRecipientUsername, 'notification' => $message]);
                    $this->activeRecipientUsername = null;
                } else {
                    $this->sendRequest('sendChatMessage', ['username' => $this->activeRecipientUsername, 'message' => $input]);
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
     * @param string $token Identificador para requisições síncronas
     * @return void
     */
    private function sendRequest($method, $parameters = [], $token = null)
    {
        $request = [
            'method' => $method,
            'parameters' => $parameters,
            'token' => $token
        ];

        $request = json_encode($request);

        Socket::writeOnSocket($this->clientSocket, $request);
    }

    /**
     * Recupera a resposta de uma requisição síncrona
     *
     * @param string $method Método a ser executado no servidor
     * @param int $timeout Tempo máximo em segundos para aguardar resposta do servidor
     * @return array|null Resposta de requisição síncrona; null, caso não houver resposta
     */
    private function getBufferedResponse($token, $timeout) {
        $response = null;

        $start = time();
        while (time() < $start + $timeout) {
            $this->handleServerResponses();
            if (!empty($this->responsesBuffer)) {
                foreach ($this->responsesBuffer as $buffered_response_key => $buffered_response) {
                    if ($buffered_response['token'] == $token) {
                        $response = $buffered_response;
                        unset($this->responsesBuffer[$buffered_response_key]);
                        break 2;
                    }
                }
            }
        }

        return $response;
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

        $this->username = $username;

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
        printf("Você se conectou com %s em um chat privado.\n\n", $this->activeRecipientUsername);

        $message = sprintf("%s se conectou à você em um chat privado!\n", $this->username);
        $this->sendRequest('sendChatNotification', ['username' => $username, 'notification' => $message]);
    }

    /**
     * Encerra as atividades do cliente
     *
     * @return void
     */
    private function quit() {
        $token = sha1('quit' . microtime());

        $this->sendRequest('quit', ['token' => $token]);
        $response = $this->getBufferedResponse($token, 5);

        printf("%s\n", $response['message']);
        exit(0);
    }
}
