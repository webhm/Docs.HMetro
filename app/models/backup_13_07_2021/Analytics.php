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
use Doctrine\DBAL\DriverManager;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;

/**
 * Modelo Odbc GEMA -> Analytics -> Analytics
 */

class Analytics extends Models implements IModels
{

    # Variables de conexion
    private $_conexion = null;
    private $USER      = null;

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();

        //..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);

    }

    private function getAuthorizationn()
    {

        try {

            global $http;

            $token = $http->headers->get("Authorization");

            $auth = new Model\Auth;
            $key  = $auth->GetData($token);

            $this->USER = $key;

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    public function insertLogUser()
    {

        global $http;

        if ($http->getMethod() == 'OPTIONS') {

            $this->getAuthorizationn();

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            # Query USERues
            $queryBuilder
                ->insert('ANALITICAS_USUARIOS_WEB')
                ->values(
                    array(
                        'codigo_persona' => '?',
                        'perfil'         => '?',
                    )
                )
                ->setParameter(0, '79964401')
                ->setParameter(1, '2')

            ;

            # Execute
            $result = $queryBuilder->execute();

            $this->_conexion->close();

        }

    }

/**
 * __construct()
 */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);

    }
}
