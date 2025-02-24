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
 * Modelo Odbc GEMA -> Agendas del médico
 */

class Agendas extends Models implements IModels
{
    use DBModel;

    # Variables de clase    
    private $conexion;
    private $codigoHorario;
    
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

        //Código horario
        if ($this->codigoHorario == null){
             throw new ModelsException($config['errors']['codigoHorarioObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoHorario)) {
                    throw new ModelsException($config['errors']['codigoHorarioNumerico']['message'], 1);
            }
        }

    }    

    /**
     * Valida los parámetros de entrada para la eliminación de agenda por rango de fechas
     */
    private function validarParametrosEliminarAgendaPorRangoDeFechas(){
        global $config;

        //Código del médico
        if ($this->codigoMedico == null){
             throw new ModelsException($config['errors']['codigoMedicoObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoMedico)) {
                    throw new ModelsException($config['errors']['codigoMedicoNumerico']['message'], 1);
            }
        }

        //Fecha de inicio
        if ($this->fechaInicio == null){
             throw new ModelsException($config['errors']['fechaInicialObligatorio']['message'], 1);
        } else {
            if ($this->fechaFin != null) {

                $startDate = $this->fechaInicio;
                $endDate   = $this->fechaFin;

                $sd = new DateTime($startDate);
                $ed = new DateTime($endDate);

                if ($sd->getTimestamp() > $ed->getTimestamp()) {
                    throw new ModelsException($config['errors']['fechaInicialIncorrecta']['message'], 1);
                }
            }
        }

        //Fecha final
        if ($this->fechaFin == null){
             throw new ModelsException($config['errors']['fechaFinalObligatorio']['message'], 1);
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
     * Elimianr la agenda del médico
    */
    public function eliminar()
    {
        global $config;
        $codigoRetorno = -1;
        $mensajeRetorno = null;

        //Inicialización de variables
        $stid = null;        
       
        try {         
            //Asignar parámetros de entrada            
            $this->setParameters();

            //Validar parámetros de entrada   
            $this->validarParametros();
            
            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stid);

            $stid = oci_parse($this->conexion->getConexion(), "BEGIN PRO_TEL_ELIM_AGENDA_MEDICO(:pn_cod_horario, :pn_retorno, :pc_mensaje); END;");

            // Bind the input num_entries argument to the $max_entries PHP variable             
            oci_bind_by_name($stid, ":pn_cod_horario", $this->codigoHorario, 32);
            oci_bind_by_name($stid, ":pn_retorno", $codigoRetorno, 32);
            oci_bind_by_name($stid, ":pc_mensaje", $mensajeRetorno, 500);
           
            //Ejecuta el SP
            oci_execute($stid);

           //Valida el código de retorno del SP
            if($codigoRetorno == 0){
                //Agend elimnada exitosamente               
                return array(
                        'status' => true,
                        'data'   => [],
                        'message'   => $mensajeRetorno
                    );
            } elseif ($codigoRetorno == 1) {
                //Mensajes de aplicación
                throw new ModelsException($mensajeRetorno, $codigoRetorno);
            } else {
                //Mensajes de errores técnicos
                throw new Exception($mensajeRetorno, $codigoRetorno);
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
            
            //Cierra la conexión
            $this->conexion->cerrar();
        }
    }

    /**
     * Elimianr la agenda del médico por rango de fechas (eliminar un rango de agendas para un *médico)
    */
    public function eliminarPorRangoDeFechas()
    {
        global $config;
        $codigoRetorno = -1;
        $mensajeRetorno = null;

        //Inicialización de variables
        $stid = null;        
       
        try {         
            //Asignar parámetros de entrada            
            $this->setParameters();

            //Validar parámetros de entrada   
            $this->validarParametrosEliminarAgendaPorRangoDeFechas();
            
            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stid);

            $stid = oci_parse($this->conexion->getConexion(), "BEGIN PRO_TEL_ELIM_AGENDA_RANGO(:pc_cod_medico, :pd_fec_ini, :pd_fec_fin, :pn_retorno, :pc_mensaje); END;");

            // Bind the input num_entries argument to the $max_entries PHP variable             
            oci_bind_by_name($stid, ":pc_cod_medico", $this->codigoMedico, 32);
            oci_bind_by_name($stid, ":pd_fec_ini", $this->fechaInicio, 32);
            oci_bind_by_name($stid, ":pd_fec_fin", $this->fechaFin, 32);
            oci_bind_by_name($stid, ":pn_retorno", $codigoRetorno, 32);
            oci_bind_by_name($stid, ":pc_mensaje", $mensajeRetorno, 500);
           
            //Ejecuta el SP
            oci_execute($stid);

           //Valida el código de retorno del SP
            if($codigoRetorno == 0){
                //Agend elimnada exitosamente               
                return array(
                        'status' => true,
                        'data'   => [],
                        'message'   => $mensajeRetorno
                    );
            } elseif ($codigoRetorno == 1) {
                //Mensajes de aplicación
                throw new ModelsException($mensajeRetorno, $codigoRetorno);
            } else {
                //Mensajes de errores técnicos
                throw new Exception($mensajeRetorno, $codigoRetorno);
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
            
            //Cierra la conexión
            $this->conexion->cerrar();
        }
    }

    /**
     * Consultar agendas del médico
    */
    public function consultarAgendasCreadas()
    {
        global $config;

        //Inicialización de variables
        $stid = null;
        $pc_datos = null;
        $existeDatos = false;
        $agendasCreadasMedico[] = null;

        try {         
            //Asignar parámetros de entrada            
            $this->setParameters();

            //Validar parámetros de entrada            
            //Código del médico
            if ($this->codigoMedico == null){
                 throw new ModelsException($config['errors']['codigoMedicoObligatorio']['message'], 1);
            } else {
                //Validaciones de tipo de datos y rangos permitidos
                if (!is_numeric($this->codigoMedico)) {
                        throw new ModelsException($config['errors']['codigoMedicoNumerico']['message'], 1);
                }
            }

            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stid);

            $pc_datos = oci_new_cursor($this->conexion->getConexion());

            $stid = oci_parse($this->conexion->getConexion(), "BEGIN PRO_TEL_AGENDAS_CREADAS(:pc_cod_medico, :pc_datos); END;");

            // Bind the input num_entries argument to the $max_entries PHP variable             
            oci_bind_by_name($stid,":pc_cod_medico",$this->codigoMedico,32);
            oci_bind_by_name($stid, ":pc_datos", $pc_datos, -1, OCI_B_CURSOR);
           
            //Ejecuta el SP
            oci_execute($stid);

            //Ejecutar el REF CURSOR como un ide de sentencia normal
            oci_execute($pc_datos);  

            //Resultados de la consulta
            $agendasCreadasMedico = array();

            while (($row = oci_fetch_array($pc_datos, OCI_BOTH+OCI_RETURN_NULLS)) != false) {
                $existeDatos = true;

                # RESULTADO OBJETO
                $agendasCreadasMedico[] = array(
                    'tipoHorario' => $row[0] == null ? '' : $row[0],
                    'descripcionTipoHorario' => $row[1] == null ? '' : $row[1],
                    'duracionMinutos' => $row[2] == null ? '' : $row[2],
                    'desde' => $row[3] == null ? '' : $row[3],
                    'hasta' => $row[4] == null ? '' : $row[4],
                    'lunes' => $row[5] == null ? '' : $row[5],
                    'martes' => $row[6] == null ? '' : $row[6],
                    'miercoles' => $row[7] == null ? '' : $row[7],
                    'jueves' => $row[8] == null ? '' : $row[8],
                    'viernes' => $row[9] == null ? '' : $row[9],
                    'sabado' => $row[10] == null ? '' : $row[10],
                    'domingo' => $row[11] == null ? '' : $row[11],
                    'horaInicial' => $row[12] == null ? '' : $row[12],
                    'horaFinal' => $row[13] == null ? '' : $row[13]
                );               
            }

            //Verificar si la consulta devolvió datos
            if ($existeDatos) {
                return array(
                    'status' => true,                    
                    'data'   => $agendasCreadasMedico
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
