<?php

namespace Lib;

/**
 * Classe responsável por manipular entradas de usuário
 *
 * @author Luccas C. Silveira
 * @license GNU General Public License v3.0
  */
class Input
{
    /**
     * Realiza uma leitura na entrada padrão sem bloquear o processo principal
     *
     * @return string Texto informado, ou null caso não houver entrada
     */
    public static function nonBlockRead() {
        $stdin = fopen('php://stdin', 'r');

        $read = [$stdin];
        $null = null;
        $result = stream_select($read, $null, $null, 0);

        $input = null;

        if ($result !== 0) {
            $input = fgets($stdin);
            $input = trim($input);
        }

        fclose($stdin);

        return $input;
    }

    /**
     * Realiza uma leitura na entrada padrão
     *
     * @return string Texto informado
     */
    public static function read($label = false)
    {
        $stdin = fopen('php://stdin', 'r');

        if ($label) {
            printf("%s: ", $label);
        }

        $input = fgets($stdin);

        fclose($stdin);

        return $input;
    }
}
