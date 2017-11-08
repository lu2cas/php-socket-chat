<?php

namespace Server;

/**
 * Classe responsável por servir como invólucro de requisições ao servidor
 *
 * @author Luccas C. Silveira
 * @license GNU General Public License v3.0
  */
class Request
{
    /**
     * Requisição ao servidor no formato JSON
     * @var string
     */
    private $jsonRequest;

    /**
     * Método requisitado
     * @var string
     */
    private $method;

    /**
     * Parâmetros do método requisitado
     * @var array
     */
    private $parameters;

    /**
     * Construtor
     *
     * @param string $json_request Requisição ao servidor no formato JSON
     * @return void
     */
    public function __construct(string $json_request)
    {
        $this->jsonRequest = $json_request;
    }

    /**
     * Valida a requisição ao servidor
     *
     * @throws Exception
     * @return boolean
     */
    public function isValid()
    {
        $request = json_decode($this->jsonRequest, true);
        if (
            json_last_error() !== JSON_ERROR_NONE ||
            !array_key_exists('method', $request) ||
            empty($request['method']) ||
            !is_string($request['method']) ||
            !array_key_exists('parameters', $request) ||
            !is_array($request['parameters'])
        ) {
            throw new \Exception('Formato de requisição inválido.');
        }

        $api_class = '\\Server\\Api';
        $method_reflection = new \ReflectionMethod($api_class, $request['method']);

        if (
            !method_exists($api_class, $request['method']) ||
            !is_callable($api_class, $request['method']) ||
            !$method_reflection->isPublic()
        ) {
            throw new \Exception('Método requisitado indisponível.');
        }

        foreach (array_keys($request['parameters']) as $key) {
            if (!is_string($key)) {
                throw new \Exception('Formato de parâmetros inválido para o método requisitado.');
            }
        }

        if (count($request['parameters']) != $method_reflection->getNumberOfRequiredParameters()) {
            throw new \Exception('Número de parâmetros inválido para o método requisitado.');
        }

        $request['parameters'] = $this->sortParameters($request['parameters'], $method_reflection->getParameters());
        if ($request['parameters'] === false) {
            throw new \Exception('Nomes de parâmetros inválidos para o método requisitado.');
        }

        $this->method = $request['method'];
        $this->parameters = $request['parameters'];

        return true;
    }

    /**
     * Ordena todos os parâmetros passados de acordo com os parâmetros necessários ao
     * método requisitado
     *
     * @param array $sent_parameters Parâmetros enviados
     * @param array $reflected_parameters Parâmetros necessários ao método requisitado
     * @return array|boolean Parâmetros ordenados de acordo com a assinatura do método requisitado;
     * false caso algum parâmetro esteja com o nome incorreto
     */
    private function sortParameters($sent_parameters, $reflected_parameters)
    {
        $parameters = [];
        $valid_parameters = [];
        foreach ($reflected_parameters as $reflected_parameter) {
            $valid_parameters[] = $reflected_parameter->getName();
            foreach ($sent_parameters as $sent_parameter_key => $sent_parameter_value) {
                if ($sent_parameter_key == $reflected_parameter->getName()) {
                    $parameters[$sent_parameter_key] = $sent_parameter_value;
                }
            }
        }

        return array_keys($parameters) == $valid_parameters ? $parameters : false;
    }

    /**
     * Retorna o método requisitado
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Retorna os parâmetros do método requisitado
     *
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }
}
