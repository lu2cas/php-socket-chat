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
    const INFO = 'informe';
    const WARNING = 'aviso';
    const ERROR = 'erro';

    public static function log($message, $type) {
        printf("[%s][%s] %s\n", date('d/m/Y H:i:s'), $type, $message);
    }
}
