<?php

namespace Lib;

/**
 * Classe responsável por criar o log de atividades do servidor
 *
 * @author Luccas C. Silveira
 * @license GNU General Public License v3.0
  */
class Logger
{
    public static function log($message) {
        printf("[%s] %s\n", date('d/m/Y H:i:s'), $message);
    }
}
