<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\models;

use app\models as Model;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Bmi
 */

class Bmi extends Models implements IModels
{

    private $cedula  = null;
    private $poliza  = null;
    private $nombres = null;

    private $url_afiliado = 'http://181.39.1.118:8090/RsProveedores/api/getAfiliados?cedula=+cedula+&poliza=+poliza+&nombres=+nombres+';

    final private function setParameters()
    {
        global $http;

        $this->cedula  = ($http->request->get('type') == 'cedula') ? $http->request->get('param') : 'NULL';
        $this->poliza  = ($http->request->get('type') == 'poliza') ? $http->request->get('param') : 'NULL';
        $this->nombres = ($http->request->get('type') == 'nombres') ? $http->request->get('param') : 'NULL';

        # SETEAR LINK FINAL PARA LLAMDA
        $this->url_afiliado = str_replace('+cedula+', $this->cedula, $this->url_afiliado);
        $this->url_afiliado = str_replace('+poliza+', $this->poliza, $this->url_afiliado);
        $this->url_afiliado = str_replace('+nombres+', $this->nombres, $this->url_afiliado);

    }

    # Solicitar Facturas e-billing
    final public function getAfiliado()
    {

        # setear parametrospara llamada
        $this->setParameters();

        $client = new \GuzzleHttp\Client();

        $response = $client->request('GET', $this->url_afiliado, [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'auth'    => ['admin', 'admin'],
        ]);

        # Si servidor de BMI no respodne adecuadamente
        if ($response->getStatusCode() != 200) {

            return array(
                'status'  => false,
                'message' => 'Servidor BMI no responde.',
            );

        }

        $res = json_decode($response->getBody()->getContents(), true);

        # Si esta vacio el array
        if (empty($res)) {

            return array(
                'status'  => true,
                'data'    => false,
                'message' => 'No existen resultados. ',

            );

        }

        # SETEAR DATA
        $res[0]['fecnacimiento'] = date('d-m-Y', strtotime($res[0]['fecnacimiento']));

        # Si existen datos en lallamada
        return array(
            'status' => true,
            'data'   => $res,
        );

    }

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);
    }
}
