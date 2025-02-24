<?php

/*
 *
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace app\controllers;

use app\models as Model;
use Ocrend\Kernel\Controllers\Controllers;
use Ocrend\Kernel\Controllers\IControllers;
use Ocrend\Kernel\Router\IRouter;

/**
 * Controlador facturasController/
 *
 */

class fotoController extends Controllers implements IControllers
{

    public function __construct(IRouter $router)
    {
        parent::__construct($router);

        $m = new Model\Medicos;
        var_dump($m->getFotos());

        # var_dump($m->insertFoto_Medico());

        # var_dump($m->getFoto_Medico());

        # echo '<img src="data:image/jpg;base64,' . $m->insertFoto_Medico() . '"/>';

    }
}
