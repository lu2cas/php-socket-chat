<?php

namespace Server;

use Lib\Socket;
use Lib\Logger;
use Lib\Input;

/**
 * Classe responsável por criar um servidor capaz de aceitar conexões de
 * clientes e gerenciar suas requisições
 *
 * @author Luccas C. Silveira
 * @license GNU General Public License v3.0
  */
class Server
{
    /**
     * Conjunto de configurações internas do servidor
     * @var array
     */
    private $config;

    /**
     * Socket responsável por realizar as funções do servidor
     * @var resource
     */
    private $serverSocket;

    /**
     * Conjunto de clientes conectados ao servidor
     * @var array
     */
    private $clients;

    /**
     * Construtor da classe
     *
     * @return void
     */
    public function __construct()
    {
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
            throw new \Exception('Formato inválido de arquivo de configurações do servidor.');
        }

        $this->serverSocket = null;
        $this->clients = [];

        error_reporting(E_ALL);
        set_time_limit(0);
        ob_implicit_flush();
    }

    /**
     * Inicia as atividades do servidor
     *
     * @return void
     */
    public function run()
    {
        $this->serverSocket = Socket::getServerSocket(
            $this->config->address->ip,
            $this->config->address->port
        );

        Logger::log('Servidor iniciado.', Logger::INFO);

        while (true) {
            $this->acceptClients();
            $this->handleClientsRequests();
            $this->handleUserInput();
        };
    }

    /**
     * Encerra as atividades do servidor
     *
     * @throws Exception
     * @return void
     */
    public function halt()
    {
        foreach ($this->clients as $client) {
            Socket::closeSocket($client['socket']);
        }

        Socket::closeSocket($this->serverSocket);

        Logger::log('Servidor encerrado.', Logger::INFO);

        exit(0);
    }

    /**
     * Registra os clientes que se conectam ao servidor
     *
     * @return void
     */
    private function acceptClients()
    {
        $sockets = [$this->serverSocket];
        foreach ($this->clients as $client) {
            $sockets[] = $client['socket'];
        }
        $waiting_for_reading_sockets = Socket::getSocketsWaitingForReading($sockets);

        // Verifica se o servidor recebeu uma nova conexão
        if (in_array($this->serverSocket, $waiting_for_reading_sockets)) {
            $client_socket = Socket::acceptSocket($this->serverSocket);

            list($client_ip, $client_port) = Socket::getSocketAddress($client_socket);
            $client_address = sprintf('%s:%s', $client_ip, $client_port);

            Logger::log(sprintf('%s conectado.', $client_address), Logger::INFO);

            $client_key = sha1(date('YmdHis') . $client_address);
            $this->clients[$client_key] = [
                'socket' => $client_socket,
                'address' => $client_address,
                'username' => null
            ];
        }
    }

    /**
     * Manipula as requisições dos clientes conectados ao servidor
     *
     * @throws Exception
     * @return void
     */
    private function handleClientsRequests()
    {
        $sockets = [$this->serverSocket];
        foreach ($this->clients as $client) {
            $sockets[] = $client['socket'];
        }
        $waiting_for_reading_sockets = Socket::getSocketsWaitingForReading($sockets);

        foreach ($this->clients as $client_key => $client) {
            if (in_array($client['socket'], $waiting_for_reading_sockets)) {
                try {
                    $json_request = Socket::readFromSocket($client['socket']);

                    Logger::log(sprintf("Cliente %s enviou: %s", $client['address'], $json_request), Logger::INFO);

                    $request = new Request($json_request);
                    if ($request->isValid()) {
                        $this->execute($request, $client_key);
                    }
                } catch(\Exception $e) {
                    // Remove um cliente caso a conexão com o mesmo seja encerrada abruptamente
                    if (socket_last_error($client['socket']) == 104) {
                        Socket::closeSocket($client['socket']);
                        unset($this->clients[$client_key]);
                    }

                    Logger::log(sprintf("Falha ao executar requisição de %s: %s", $client['address'], $e->getMessage()), Logger::WARNING);
                    continue;
                }
            }
        }
    }

    /**
     * Interpreta e valida uma requisição
     *
     * @param \Server\Request $request Objeto de requisição
     * @param int $client_key Chave do cliente que realiza a requisição
     * @throws Exception
     * @return void
     */
    private function execute($request, $client_key)
    {
        $api = new Api($client_key, $this->clients);

        call_user_func_array(
            [
                $api,
                $request->getMethod()
            ],
            $request->getParameters()
        );

        $this->clients = $api->getClients();
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
            switch ($input) {
                case '/halt':
                    $this->halt();
                    break;
                default:
                    print("Comando inválido.\n");
            }
        }
    }
}
