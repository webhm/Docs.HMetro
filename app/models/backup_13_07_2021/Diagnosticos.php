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

class Diagnosticos extends Models implements IModels
{
    use DBModel;

    # Variables de clase    
    private $conexion;
    private $start  = 0;
    private $length  = 10;
    private $nombreDiagnostico;
    
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

    /**
     * Valida los parámetros de entrada
     */
    private function validarParametros(){
        global $config;

        //Nombre diagnostico        
        if ($this->nombreDiagnostico == null){
            $this->nombreDiagnostico = "%";
        } else {
            $this->nombreDiagnostico = $this->quitar_tildes(mb_strtoupper($this->sanear_string($this->nombreDiagnostico), 'UTF-8'));

             # Setear valores para busquedas dividadas
            if (stripos($this->nombreDiagnostico, ' ')) {
                $this->nombreDiagnostico = str_replace(' ', '%', $this->nombreDiagnostico);
            }
        }

        //Min row to fetch
        if ($this->start == null){
             throw new ModelsException($config['errors']['startObligatorio']['message'], 1);
        } else {
            if ($this->start < 0) {
                throw new ModelsException($config['errors']['startIncorrecto']['message'], 1);
            }
        }

        //Max row to fetch
        if ($this->length == null){
             throw new ModelsException($config['errors']['lengthObligatorio']['message'], 1);
        } else {
            if ($this->length <= 0) {
                 throw new ModelsException($config['errors']['lengthIncorrecto']['message'], 1);
            }
        }
    }    

    private function setSpanishOracle($stid)
    {

        $sql = "alter session set NLS_LANGUAGE = 'SPANISH'";
        # Execute
        $stid = oci_parse($this->conexion->getConexion(),  $sql);
        oci_execute($stid);

        $sql = "alter session set NLS_TERRITORY = 'SPAIN'";
        # Execute
        $stid = oci_parse($this->conexion->getConexion(),  $sql);
        oci_execute($stid);

        $sql = " alter session set NLS_DATE_FORMAT = 'DD/MM/YYYY HH24:MI'";
        # Execute
        $stid = oci_parse($this->conexion->getConexion(),  $sql);
        oci_execute($stid);

    }

    /**
     * Obtiene los diagnósticos
    */
    public function consultar()
    {
        global $config;

        //Inicialización de variables
        $stid = null;
        $pc_datos = null;
        $existeDatos = false;
        $listaDiagnosticos[] = null;

        try {         
            //Asignar parámetros de entrada            
            $this->setParameters();

            //Validar parámetros de entrada   
            $this->validarParametros();
            
            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stid);

            $pc_datos = oci_new_cursor($this->conexion->getConexion());

            $stid = oci_parse($this->conexion->getConexion(), "BEGIN PRO_TEL_DIAGNOSTICOS(:pc_nombre_diag, :pn_num_reg, :pn_num_pag, :pc_datos); END;");

            // Bind the input num_entries argument to the $max_entries PHP variable             
            oci_bind_by_name($stid,":pc_nombre_diag",$this->nombreDiagnostico,100);
            oci_bind_by_name($stid,":pn_num_reg",$this->length,32);
            oci_bind_by_name($stid,":pn_num_pag",$this->start,32);
            oci_bind_by_name($stid, ":pc_datos", $pc_datos, -1, OCI_B_CURSOR);
           
            //Ejecuta el SP
            oci_execute($stid);

            //Ejecutar el REF CURSOR como un ide de sentencia normal
            oci_execute($pc_datos);  

            //Resultados de la consulta
            $listaDiagnosticos = array();

            while (($row = oci_fetch_array($pc_datos, OCI_BOTH)) != false) {
                $existeDatos = true;

                # RESULTADO OBJETO
                $listaDiagnosticos[] = array(
                    'codigoDiagnostico' => $row[0], 
                    'descripcionDiagnostico' => $row[1], 
                    'codigoGrupoDiagnostico' => $row[2],
                    'descripcionGrupoDiagnostico' => $row[3]
                );
                
            }

            //Verificar si la consulta devolvió datos
            if ($existeDatos) {
                return array(
                    'status' => true,                    
                    'data'   => $listaDiagnosticos
                        );
            }
            else {
                throw new ModelsException($config['errors']['noExistenResultados']['message'], 1);
            }

        } catch (ModelsException $e) {

            return array(
                    'status'    => false,
                    'data'      => [],
                    'message'   => $e->getMessage(),
                    'errorCode' => $e->getCode()
                );

        } catch (Exception $ex) {

            return array(
                    'status'    => false,
                    'data'      => [],
                    'message'   => $ex->getMessage(),
                    'errorCode' => -1
                );

        }
        finally {
            //Libera recursos de conexión
            if ($stid != null){
                oci_free_statement($stid);
            }

            if ($pc_datos != null){
                oci_free_statement($pc_datos);
            }

            //Cierra la conexión
            $this->conexion->cerrar();
        }
    }

    /*
    * Quita las tildes de una cadena
    */
    private function quitar_tildes($cadena)
    {
        $no_permitidas = array("%", "é", "í", "ó", "ú", "É", "Í", "Ó", "Ú", "ñ", "À", "Ã", "Ì", "Ò", "Ù", "Ã™", "Ã ", "Ã¨", "Ã¬", "Ã²", "Ã¹", "ç", "Ç", "Ã¢", "ê", "Ã®", "Ã´", "Ã»", "Ã‚", "ÃŠ", "ÃŽ", "Ã”", "Ã›", "ü", "Ã¶", "Ã–", "Ã¯", "Ã¤", "«", "Ò", "Ã", "Ã„", "Ã‹");
        $permitidas    = array("", "e", "i", "o", "u", "E", "I", "O", "U", "n", "N", "A", "E", "I", "O", "U", "a", "e", "i", "o", "u", "c", "C", "a", "e", "i", "o", "u", "A", "E", "I", "O", "U", "u", "o", "O", "i", "a", "e", "U", "I", "A", "E");
        $texto         = str_replace($no_permitidas, $permitidas, $cadena);
        return $texto;
    }

     private function sanear_string($string)
    {

        $string = trim($string);

        //Esta parte se encarga de eliminar cualquier caracter extraño
        $string = str_replace(
            array(">", "< ", ";", ",", ":", "%", "|"),
            ' ',
            $string
        );

        /*

        if ($this->lang == 'en') {
        $string = str_replace(
        array("CALLE", "TORRE MEDICA", "CONSULTORIO", "CONS."),
        array('STREET', 'MEDICAL TOWER', 'DOCTOR OFFICE', 'DOCTOR OFFICE'),
        $string
        );
        }

         */

        return trim($string);
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
