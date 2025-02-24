<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use app\models as Model;

$app->get('/home', function () use ($app) {

    global $http;
    return $app->json(array());
});

/**
 * Send Email Metrored => Gracias Por preferirnos => Encuesta Online
 *
 * @return json
 */

$app->get('/metrored/task/emails/polls', function () use ($app) {
    $m = new Model\Metrored;
    return $app->json($m->postPolls());
});

$app->get('/metrored/paciente/atencion/{token}', function ($token) use ($app) {
    $u = new Model\Metrored;
    return $app->json($u->getAtencion($token));
});

$app->get('/metrored/paciente/respuesta/{cal}/{token}', function ($cal, $token) use ($app) {
    $u = new Model\Metrored;
    return $app->json($u->setRespuesta($cal, $token));
});
