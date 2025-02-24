<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 * @author Brayan Narváez <prinick@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Ocrend\Kernel\Models\ModelsException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Convertir esta api en RESTFULL para recibir JSON
 */
$app->before(function () use ($app) {
    try {
        global $config, $http;

        # Verificar si la api no está activa
        if (!$config['api']['active']) {
            throw new ModelsException('Servicio inactivo', 4070);
        }

        if ($http->getMethod() == 'OPTIONS') {
            throw new ModelsException('OPTIONS', 4090);
        }

    } catch (ModelsException $e) {

        if ($e->getMessage() == 'OPTIONS') {

            return new Response('', 200);

        } else {

            # Capturar error de token caducado
            if ($e->getCode() == 4031) {

                return $app->json(array(
                    'status'    => false,
                    'message'   => $e->getMessage(),
                    'errorCode' => $e->getCode(),
                    # 'data'    => explode('/', $http->getPathInfo())[2],
                ), 401);

            } else {

                return $app->json(array(
                    'status'    => false,
                    'message'   => $e->getMessage(),
                    'errorCode' => $e->getCode(),
                    # 'data'    => explode('/', $http->getPathInfo())[2],
                ));

            }

        }

    }
});

/**
 * Servidores autorizados para consumir la api.
 */

$app->after(function (Request $request, Response $response) {

    $response->headers->set('Access-Control-Allow-Origin', '*');
    $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');

});

$app->options("{anything}", function () {
    return new \Symfony\Component\HttpFoundation\JsonResponse(null, 204);
})->assert("anything", ".*");

$app->error(function (\Exception $e, $code) use ($app) {
    # Capturar errores de la api
    return $app->json(array(
        'status'    => false,
        'message'   => $e->getMessage(),
        'errorCode' => $e->getCode(),
    ));
});
