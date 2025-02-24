<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\controllers;

use Ocrend\Kernel\Controllers\Controllers;
use Ocrend\Kernel\Controllers\IControllers;
use Ocrend\Kernel\Router\IRouter;

/**
 * Controlador ref/
 *
 * @author Ocrend Software C.A <bnarvaez@ocrend.com>
 */
class refController extends Controllers implements IControllers
{

    public function __construct(IRouter $router)
    {
        parent::__construct($router);

        global $config, $http;

        // Para Controladores
        if ($router->getController() == 'ref' && is_null($router->getMethod()) && is_null($router->getId())) {
            $this->template->display('error/404');
        }

        // Para metodos y ids
        if (!is_null($router->getMethod()) && !is_null($router->getId())) {
            # Validacion de Ruta de Acceso
            if (
                $router->getMethod() == 'v1'
                && !is_null($router->getId())
            ) {

                $this->name_template = 'busqueda/busqueda';
                $_hc = $router->getId();

                $hc = (int) $_hc;

                switch ($hc) {

                    case ($hc > 1 && $hc <= 30000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '30000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 30000 && $hc <= 60000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '60000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 60000 && $hc <= 90000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '90000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 90000 && $hc <= 120000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '120000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 120000 && $hc <= 150000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '150000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 150000 && $hc <= 180000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '180000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 180000 && $hc <= 210000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '210000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 210000 && $hc <= 240000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '240000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 240000 && $hc <= 270000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '270000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 270000 && $hc <= 300000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '300000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 300000 && $hc <= 330000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '330000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 330000 && $hc <= 360000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '360000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 360000 && $hc <= 390000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '390000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 390000 && $hc <= 420000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '420000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 420000 && $hc <= 450000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '450000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 450000 && $hc <= 480000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '480000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 480000 && $hc <= 510000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '510000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 510000 && $hc <= 540000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '540000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 540000 && $hc <= 570000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '570000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 570000 && $hc <= 600000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '600000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 600000 && $hc <= 630000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '630000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 630000 && $hc <= 660000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '660000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 660000 && $hc <= 690000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '690000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 690000 && $hc <= 720000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '720000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 720000 && $hc <= 750000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '750000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 750000 && $hc <= 780000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '780000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 780000 && $hc <= 810000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '810000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 810000 && $hc <= 840000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '840000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 840000 && $hc <= 870000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '870000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 870000 && $hc <= 900000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '900000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 900000 && $hc <= 930000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '930000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 930000 && $hc <= 960000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '960000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 960000 && $hc <= 990000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '990000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 990000 && $hc <= 1020000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '1020000/' . $hc . '01',
                        ));

                        break;

                    case ($hc > 1020000 && $hc <= 1050000):

                        $this->template->display($this->name_template, array(
                            'appBodyClass' => 'app-contact',
                            'rutaExp' => '1050000/' . $hc . '01',
                        ));

                        break;

                    default:

                        $this->template->display('error/404');

                        break;

                }

            }
        }

    }
}
