<?php

namespace Lib;

/**
 * Classe responsável por realizar a abstração da biblioteca de sockets do PHP
 *
 * @author Luccas C. Silveira
 * @license GNU General Public License v3.0
 * @see http://php.net/manual/en/book.sockets.php
 */
class Socket {

    /**
     * Cria o socket do servidor e o abre para conexões com clientes
     *
     * @param string $ip Endereço IP do servidor
     * @param string $port Porta do servidor
     * @throws \Exception
     * @return resource Socket do servidor
     */
    public static function getServerSocket($ip, $port)
    {
        $server_socket = false;

        if (($server_socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
            $socket_error = socket_strerror(socket_last_error());
            $error_message = sprintf("Erro ao criar socket do servidor: \"%s\".\n", $error_message);
            throw new \Exception($error_message);
        }

        socket_set_option($server_socket, SOL_SOCKET, SO_REUSEADDR, 1);
        if (@socket_bind($server_socket, $ip, $port) === false) {
            $socket_error = socket_strerror(socket_last_error($server_socket));
            $error_message = sprintf("Erro ao endereçar socket do servidor: \"%s\".\n", $socket_error);
            throw new \Exception($error_message);
        }

        if (@socket_listen($server_socket, 5) === false) {
            $socket_error = socket_strerror(socket_last_error($server_socket));
            $error_message = sprintf("Erro ao abrir conexões com o socket do servidor: \"%s\".\n", $socket_error);
            throw new \Exception($error_message);
        }

        return $server_socket;
    }

    /**
     * Fecha a conexão do socket do servidor
     *
     * @throws \Exception
     * @return void
     */
    public static function closeSocket($socket)
    {
        $socket_closed = false;

        if (($socket_closed = @socket_close($socket)) === false) {
            $socket_error = socket_strerror(socket_last_error($socket));
            $error_message = sprintf("Erro ao fechar conexão com socket: \"%s\".\n", $socket_error);
            throw new \Exception($error_message);
        }
    }

    /**
     * Escreve mensagem em um socket
     *
     * @param resource $socket Socket no qual uma mensagem será escrita
     * @param resource $message Mensgem a ser escrita no socket
     * @throws \Exception
     * @return int Número de bytes escritos com sucesso no socket
     */
    public static function writeOnSocket($socket, $message)
    {
        $written_bytes = false;

        if (($written_bytes = @socket_write($socket, $message, strlen($message))) === false) {
            $socket_error = socket_strerror(socket_last_error($socket));
            $error_message = sprintf("Erro ao escrever mensagem em socket: \"%s\".\n", $socket_error);
            throw new \Exception($error_message);
        }

        return $written_bytes;
    }

    /**
     * Lê mensagem de um socket
     *
     * @param resource $socket Socket no qual uma mensagem será lida
     * @throws \Exception
     * @return string Mensagem lida
     */
    public static function readFromSocket($socket)
    {
        $message = '';

        do {
            $buffer = @socket_read($socket, 1024, PHP_NORMAL_READ);
            if ($buffer === false) {
                $socket_error = socket_strerror(socket_last_error($socket));
                $error_message = sprintf("Falha ao ler buffer do socket: \"%s\".\n", $socket_error);
                throw new \Exception($error_message);
            }
            $message .= $buffer;
        } while (!empty(trim($buffer)));

        $message = trim($message);

        return $message;
    }

    /**
     * Retorna o conjunto de sockets que contém dados a serem consumidos
     *
     * @param array $sockets Conjunto de sockets a serem inspecionados
     * @return array Conjunto de sockets que contém dados a serem consumidos
     */
    public static function getSocketsWaitingForReading($sockets)
    {
        $sockets_waiting_for_reading = [];

        $null = null;
        $selected_sockets = @socket_select($sockets, $null, $null, 5);

        if ($selected_sockets === false) {
            throw new \Exception("Erro ao inspecionar sockets para leitura.\n");
        }

        if ($selected_sockets > 0) {
            $sockets_waiting_for_reading = $sockets;
        }

        return $sockets_waiting_for_reading;
    }

    /**
     * Aceita a conexão de um socket com outro socket ouvinte
     *
     * @param resource $listener_socket Socket ouvinte
     * @return resource Socket com o qual se estabeleceu conexão
     */
    public static function acceptSocket($listener_socket)
    {
        $socket = false;

        if (($socket = @socket_accept($listener_socket)) === false) {
            $socket_error = socket_strerror(socket_last_error($listener_socket));
            $error_message = sprintf("Falha ao estabelecer conexão com o cliente: \"%s\".\n", $error_message);
            throw new \Exception($error_message);
        }

        return $socket;
    }

    /**
     * Retorna o endereço de rede de um socket
     *
     * @param resource $socket Socket a ser inspecionado
     * @return array Ip e porta do socket inspecionado
     */
    public static function getSocketAddress($socket)
    {
        $address = [];

        $ip = $port = null;
        if (@socket_getpeername($socket, $ip, $port) === false) {
            $socket_error = socket_strerror(socket_last_error($socket));
            $error_message = sprintf("Falha ao obter endereço do socket: \"%s\".\n", $error_message);
            throw new \Exception($error_message);
        }

        $address = [$ip, $port];

        return $address;
    }
}
