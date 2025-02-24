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
use DateTime;
use Doctrine\DBAL\DriverManager;
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Models\Traits\DBModel;
use Ocrend\Kernel\Router\IRouter;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Exception;

/**
 * Modelo Odbc GEMA -> Historia clínica
 */

class PruebaTransaccion extends Models implements IModels
{
    use DBModel;

    # Variables de clase    
    private $conexion;
    private $idTabla1;
    private $idTabla2;
    
    /**
     * Asigna los parámetros de entrada
     */
    private function setParameters()
    {
        global $http;

        foreach ($http->request->all() as $key => $value) {
            $this->$key = strtoupper($value);
        }

    }

    private function insertarTabla2($idTabla2, $descripcionTabla2, $stmt, $conexion1)
    {    
        $r = FALSE;
        $codigoError = -1;
        $mensajeError;

        try {
            $stmt = oci_parse($conexion1,'BEGIN PRO_PRUEBA_TABLA2_INS(:pn_id, :pc_descripcion); END;');

            // Bind the input parameter
            oci_bind_by_name($stmt,':pn_id', $idTabla2,32); 
            oci_bind_by_name($stmt,':pc_descripcion',$descripcionTabla2,200);
            
            $r = oci_execute($stmt, OCI_DEFAULT);

        } catch (Exception $ex) {
        
            if (!$r) {
                $e = oci_error($stmt);
                
                $mensajeError = "Error al insertar los datos en la tabla 2, consulte con el Administrador del Sistema. " . $e['message'];

                //Verifica los mensajes de error del Oracle
                //Llave primaria duplicada
                if ($e['code'] == 1) {
                    $mensajeError = "Registro de la tabla 2 ya existe.";
                }

                oci_rollback($conexion1);
                throw new Exception($mensajeError, $codigoError);                
            }

        }    
    }

    /**
     * Permite registrar un nuevo paciente
     */
    public function ejecutar()
    {
        global $config;

        //Inicialización de variables
        $stmt = null;     
        $descripcionTabla1 = "descripción 1";               
        $descripcionTabla2 = "descripción 2";  
        $r = FALSE;  
        $rCommit = FALSE;
        $codigoError = -1;
        $mensajeError;           

        try {             
            //Asigna y valida los parámetro de entrada
            $this->setParameters();    

            //Conectar a la BDD
            $this->conexion->conectar();
            
            $stmt = oci_parse($this->conexion->getConexion(),'BEGIN PRO_PRUEBA_TABLA1_INS(:pn_id, :pc_descripcion); END;');

            // Bind the input parameter
            oci_bind_by_name($stmt,':pn_id', $this->idTabla1,32); 
            oci_bind_by_name($stmt,':pc_descripcion',$descripcionTabla1,200);
                                               
            $r = oci_execute($stmt, OCI_DEFAULT);
                       
            //insertar tabla2
            $this->insertarTabla2($this->idTabla2, $descripcionTabla2, $stmt, $this->conexion->getConexion());            
               
            // Consignar los cambios de ambas tablas
            $rCommit = oci_commit($this->conexion->getConexion());            

            return array(
                    'status'    => true,
                    'data'      => [],
                    'message'   => "Transacción OK"
                );

        } catch (Exception $ex) {
            //
            $mensajeError = $ex->getMessage();

            //Error en la tabla 1
            if (!$r) {
                $e = oci_error($stmt);                

                $mensajeError = "Error al insertar los datos en la tabla 1, consulte con el Administrador del Sistema. " . $e['message'];

                //Verifica los mensajes de error del Oracle
                //Llave primaria duplicada
                if ($e['code'] == 1) {
                    $mensajeError = "Registro de la tabla 1 ya existe";
                }                               
            }

            return array(
                    'status'    => false,
                    'data'      => [],
                    'message'   => $mensajeError,
                    'errorCode' => $codigoError
                );

        }       
        finally {
            //Libera recursos de conexión
            if ($stmt != null){
                oci_free_statement($stmt);
            }

            //Cierra la conexión
            $this->conexion->cerrar();
        }

    }
   
    /**
     * __construct()
     */
    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);

        //Instancia la clase conexión a la base de datos
        $this->conexion = new Conexion();

    }
}
