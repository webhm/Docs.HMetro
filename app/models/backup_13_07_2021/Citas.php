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
 * Modelo Odbc GEMA -> Citas
 */

class Citas extends Models implements IModels
{
    use DBModel;

    # Variables de clase    
    private $conexion;
    private $codigoHorario;
    private $numeroTurno;
    private $codigoHorarioNuevo;
    private $numeroTurnoNuevo;
    private $codigoInstitucion;
    private $USER        = null;
    private $numeroMes;
    private $codigoMedico;
    private $numeroHistoriaClinica;
    private $numeroAdmision;

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
     * Obtiene el token
     */
    private function obtenerAutorizacion()
    {
        try {
            global $http;

            $token = $http->headers->get("Authorization");

            $auth = new Model\Auth;
            $key  = $auth->GetData($token);

            $this->USER = $key;

        } catch (ModelsException $e) {
            throw $e;
        }
    }

    /**
     * Valida los parámetros de entrada
     */
    private function validarParametros(){
        global $config;

        //Código de horario
        if ($this->codigoHorario == null){
             throw new ModelsException($config['errors']['codigoHorarioObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoHorario)) {
                    throw new ModelsException($config['errors']['codigoHorarioNumerico']['message'],1);
            }
        }

        //Número de turno
        if ($this->numeroTurno == null){
             throw new ModelsException($config['errors']['numeroTurnoObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->numeroTurno)) {
                    throw new ModelsException($config['errors']['numeroTurnoNumerico']['message'], 1);
            }
        }
        
    }

    /**
     * Valida los parámetros de entrada de la consulta del motivo de la cita
     */
    private function validarParametrosConsultarMotivoCita(){
        global $config;

        //Número de historia clínica
        if ($this->numeroHistoriaClinica == null){
             throw new ModelsException($config['errors']['numeroHistoriaClinicaObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->numeroHistoriaClinica)) {
                    throw new ModelsException($config['errors']['numeroHistoriaClinicaNumerico']['message'], 1);
            }
        }
        
        //Número de admisión
        if ($this->numeroAdmision == null){
             throw new ModelsException($config['errors']['numeroAdmisionObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->numeroAdmision)) {
                    throw new ModelsException($config['errors']['numeroAdmisionNumerico']['message'], 1);
            }
        }        
    }

    /**
     * Valida los parámetros de entrada cancelación de cita
     */
    private function validarParametrosCancelacionCita(){
        global $config;

        //Código de horario
        if ($this->codigoHorario == null){
             throw new ModelsException($config['errors']['codigoHorarioObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoHorario)) {
                    throw new ModelsException($config['errors']['codigoHorarioNumerico']['message'], 1);
            }
        }

        //Número de turno
        if ($this->numeroTurno == null){
             throw new ModelsException($config['errors']['numeroTurnoObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->numeroTurno)) {
                    throw new ModelsException($config['errors']['numeroTurnoNumerico']['message'], 1);
            }
        }
        
    }


    /**
     * Valida los parámetros de entrada re-agendamiento de cita
     */
    private function validarParametrosReAgendarCita(){
        global $config;

        //Código de horario
        if ($this->codigoHorario == null){
             throw new ModelsException($config['errors']['codigoHorarioObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoHorario)) {
                    throw new ModelsException($config['errors']['codigoHorarioNumerico']['message'], 1);
            }
        }

        //Código de horario nuevo
        if ($this->codigoHorarioNuevo == null){
             throw new ModelsException($config['errors']['codigoHorarioNuevoObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoHorarioNuevo)) {
                    throw new ModelsException($config['errors']['codigoHorarioNuevoNumerico']['message'], 1);
            }
        }

        //Número de turno
        if ($this->numeroTurno == null){
             throw new ModelsException($config['errors']['numeroTurnoObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->numeroTurno)) {
                    throw new ModelsException($config['errors']['numeroTurnoNumerico']['message'], 1);
            }
        }

        //Número de turno nuevo
        if ($this->numeroTurnoNuevo == null){
             throw new ModelsException($config['errors']['numeroTurnoNuevoObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->numeroTurnoNuevo)) {
                    throw new ModelsException($config['errors']['numeroTurnoNuevoNumerico']['message'], 1);
            }
        }
        
    }

    /**
     * Permite re-agendr una cita
     */
    public function reAgendar()
    {
        global $config;

        //Inicialización de variables
        $stmt = null;
        $codigoRetorno = null;
        $mensajeRetorno = null;

        //Siempre es 1 para el Hospital Metropolitano
        $this->codigoInstitucion  = 1;

        try {

            //Asignar parámetros de entrada            
            $this->setParameters();

            //Validar parámetros de entrada            
            $this->validarParametrosReAgendarCita();

            //Conectar a la BDD
            $this->conexion->conectar();
             
            $stmt = oci_parse($this->conexion->getConexion(),'BEGIN PRO_TEL_REAGENDA_CITA(:pn_institucion, :pn_cod_horario, :pn_num_turno, :pn_cod_horario_nue, :pn_num_turno_nue, :pn_retorno, :pc_mensaje); END;');

            // Bind the input parameter
            oci_bind_by_name($stmt,':pn_institucion',$this->codigoInstitucion,32);
            oci_bind_by_name($stmt,':pn_cod_horario',$this->codigoHorario,32);
            oci_bind_by_name($stmt,':pn_num_turno',$this->numeroTurno,32);
            oci_bind_by_name($stmt,':pn_cod_horario_nue',$this->codigoHorarioNuevo,32);
            oci_bind_by_name($stmt,':pn_num_turno_nue',$this->numeroTurnoNuevo,32);
             
            // Bind the output parameter
            oci_bind_by_name($stmt,':pn_retorno',$codigoRetorno,32);
            oci_bind_by_name($stmt,':pc_mensaje',$mensajeRetorno,500);
                         
            oci_execute($stmt);
            
            //Valida el código de retorno del SP
            if($codigoRetorno == 0){
                //Cita cancelada exitosamente               
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
                    'errorCode' => $ex->getCode()
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
     * Consulta el detalle de la cita.
     */
    public function obtenerDetalleCita()
    {
        global $config;

        //Inicialización de variables
        $stid = null;
        $pc_datos = null;
        $existeDatos = false;        

        try {         
            //Asignar parámetros de entrada            
            $this->setParameters();

            //Validar parámetros de entrada            
            $this->validarParametros();

            //Conectar a la BDD
            $this->conexion->conectar();

            $pc_datos = oci_new_cursor($this->conexion->getConexion());

            $stid = oci_parse($this->conexion->getConexion(), "BEGIN PRO_TEL_CONFIRMA_CITA(:pn_cod_horario, :pn_num_turno, :pc_datos); END;");
           
            // Bind the input num_entries argument to the $max_entries PHP variable             
            oci_bind_by_name($stid,":pn_cod_horario",$this->codigoHorario,32);
            oci_bind_by_name($stid,":pn_num_turno",$this->numeroTurno,32);
            oci_bind_by_name($stid, ":pc_datos", $pc_datos, -1, OCI_B_CURSOR);
           
            //Ejecuta el SP
            oci_execute($stid);

            //Ejecutar el REF CURSOR como un ide de sentencia normal
            oci_execute($pc_datos);  

            //Resultados de la consulta
            $citasDisponibles = array();

            while (($row = oci_fetch_array($pc_datos, OCI_ASSOC+OCI_RETURN_NULLS)) != false) {
                $existeDatos = true;

                # RESULTADO OBJETO
                return array(
                    'status' => true,
                    'data'   => array(
                        'nombresMedico' => $row['NOMBRE_MEDICO'],
                        'codigoEspecialidadMedico' => $row['COD_ESPEC'], 
                        'especialidadMedico' => $row['DESC_ESPECIALIDAD'],
                        'fechaCita' => $row['FECHA_CITA'],
                        'horaCita' => $row['HORA_CITA'],
                        'duracionCita' => $row['DURACION'],
                        'codigoOrganigrama' => $row['COD_ORGANIGRAMA'],
                        'descripcionOrganigrama' => $row['DESC_ORGANIGRAMA'],
                        'direccionOrganigrama' => $row['DIRECCION'],
                        'codigoConsulta' => $row['COD_CONSULTA'],
                        'valorConsulta' => $row['VALOR_CONSULTA'],
                        'codigoLugarAtencion' => $row['CODIGO_LUGAR_ATENCION']
                        )
                );

                //print("Parámetro: " . $row['PARAMETRO']);
            }

            //Verificar si la consulta devolvió datos
            if (!$existeDatos) {

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
     * Consulta el motivo de la cita médica.
     */
    public function consultarMotivoCita()
    {
        global $config;

        //Inicialización de variables
        $stid = null;
        $pc_datos = null;
        $existeDatos = false; 

        try {         
            //Asignar parámetros de entrada            
            $this->setParameters();

            //Validar parámetros de entrada            
            $this->validarParametrosConsultarMotivoCita();

            //Conectar a la BDD
            $this->conexion->conectar();

            $pc_datos = oci_new_cursor($this->conexion->getConexion());

            $stid = oci_parse($this->conexion->getConexion(), "BEGIN PRO_TEL_CEA_NOTA_PTE_LEE(:pn_hc, :pn_adm, :pc_datos); END;");
           
            // Bind the input num_entries argument to the $max_entries PHP variable             
            oci_bind_by_name($stid, ":pn_hc", $this->numeroHistoriaClinica, 32);
            oci_bind_by_name($stid, ":pn_adm", $this->numeroAdmision, 32);
            oci_bind_by_name($stid, ":pc_datos", $pc_datos, -1, OCI_B_CURSOR);
           
            //Ejecuta el SP
            oci_execute($stid);

            //Ejecutar el REF CURSOR como un ide de sentencia normal
            oci_execute($pc_datos);  

            //Resultados de la consulta
            while (($row = oci_fetch_array($pc_datos, OCI_BOTH+OCI_RETURN_NULLS)) != false) {
                $existeDatos = true;

                # RESULTADO OBJETO
                return array(
                    'status' => true,
                    'data'   => array(
                        'motivoCita' => $row[0] == null ? "" : $row[0]
                        )
                );
            }

            //Verificar si la consulta devolvió datos
            if (!$existeDatos) {

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
     * Consultar las citas pagadas para un médico
    */
    public function consultarCitasPagadas()
    {
        global $config;

        //Inicialización de variables
        $stid = null;
        $pc_datos = null;
        $existeDatos = false;
        $citasPagadas[] = null;        

        try {         
            //Asignar parámetros de entrada            
            $this->setParameters();

            //Validar parámetros de entrada    
            //Número de mes
            if ($this->numeroMes == null){
                 throw new ModelsException($config['errors']['numeroMesObligatorio']['message'], 1);
            } else {
                //Validaciones de tipo de datos y rangos permitidos
                if (!is_numeric($this->numeroMes)) {
                        throw new ModelsException($config['errors']['numeroMesNumerico']['message'], 1);
                }
            }

            //Código médico
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

            $stid = oci_parse($this->conexion->getConexion(), "BEGIN PRO_HONORARIOS_MEDICO_WEB(:pn_mes, :pc_medico, :pc_datos); END;");
           
            // Bind the input num_entries argument to the $max_entries PHP variable             
            oci_bind_by_name($stid,":pn_mes",$this->numeroMes,32);
            oci_bind_by_name($stid,":pc_medico",$this->codigoMedico,32);
            oci_bind_by_name($stid, ":pc_datos", $pc_datos, -1, OCI_B_CURSOR);
           
            //Ejecuta el SP
            oci_execute($stid);

            //Ejecutar el REF CURSOR como un ide de sentencia normal
            oci_execute($pc_datos);  

            //Resultados de la consulta
            $citasPagadas = array();

            while (($row = oci_fetch_array($pc_datos, OCI_BOTH)) != false) {
                $existeDatos = true;

                # RESULTADO OBJETO
                $citasPagadas[] = array(
                    'fechaHorario' => $row[0],
                    'codigoHorario'=> $row[1],
                    'numeroTurno' => $row[2],
                    'codigoPaciente' => $row[3], 
                    'nombresPaciente' => $row[4], 
                    'numeroAdmision' => $row[5],
                    'numeroPreFactura' => $row[6],
                    'totalFactura' => $row[7],
                    'numeroFactura' => $row[8],
                    'fechaFactura' => $row[9]
                );
                
            }

            //Verificar si la consulta devolvió datos
            if ($existeDatos) {
                return array(
                    'status' => true,                    
                    'data'   => $citasPagadas
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
                    'errorCode' => 1
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
     * Consultar las citas pasadas del paciente
    */
    public function consultarCitasPasadas()
    {
        global $config;

        //Inicialización de variables
        $stid = null;
        $pc_datos = null;
        $existeDatos = false;
        $citasPasadas[] = null;
        $codigoPersona = null;

        try {         
            //Asignar parámetros de entrada            
            $this->setParameters();

            //Obtiene la autorización 
            $this->obtenerAutorizacion();

            $codigoPersona = $this->USER->COD_PERSONA;
            
            //Validar parámetros de entrada                     
            //Código persona
            if ($codigoPersona == null){
                 throw new ModelsException($config['errors']['codigoPersonaObligatorio']['message'], 1);
            } else {
                //Validaciones de tipo de datos y rangos permitidos
                if (!is_numeric($codigoPersona)) {
                        throw new ModelsException($config['errors']['codigoPersonaNumerico']['message'], 1);
                }
            }

            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stid);

            $pc_datos = oci_new_cursor($this->conexion->getConexion());

            $stid = oci_parse($this->conexion->getConexion(), "BEGIN PRO_TEL_CITAS_PASADAS(:pn_cod_persona, :pc_datos); END;");
           
            // Bind the input num_entries argument to the $max_entries PHP variable             
            oci_bind_by_name($stid,":pn_cod_persona",$codigoPersona,32);
            oci_bind_by_name($stid, ":pc_datos", $pc_datos, -1, OCI_B_CURSOR);
           
            //Ejecuta el SP
            oci_execute($stid);

            //Ejecutar el REF CURSOR como un ide de sentencia normal
            oci_execute($pc_datos);  

            //Resultados de la consulta
            $citasPasadas = array();

            while (($row = oci_fetch_array($pc_datos, OCI_BOTH)) != false) {
                $existeDatos = true;

                # RESULTADO OBJETO
                $citasPasadas[] = array(
                    'codigoHorario' => $row[0],
                    'numeroTurno' => $row[1],
                    'fechaCita' => $row[2],
                    'horaInicioCita'=> $row[3],
                    'horaFinCita' => $row[4],
                    'codigoMedico' => $row[5], 
                    'nombresMedico' => $row[6], 
                    'descripcionEspecialidad' => $row[7]
                );
                
            }

            //Verificar si la consulta devolvió datos
            if ($existeDatos) {
                return array(
                    'status' => true,                    
                    'data'   => $citasPasadas
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
                    'errorCode' => 1
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
     * Consultar las citas pendientes del paciente
    */
    public function consultarCitasPendientes()
    {
        global $config;

        //Inicialización de variables
        $stid = null;
        $pc_datos = null;
        $existeDatos = false;
        $citasPendientes[] = null;
        $codigoPersona = null;

        try {         
            //Asignar parámetros de entrada            
            $this->setParameters();

            //Obtiene la autorización 
            $this->obtenerAutorizacion();

            $codigoPersona = $this->USER->COD_PERSONA;
            
            //Validar parámetros de entrada                     
            //Código persona
            if ($codigoPersona == null){
                 throw new ModelsException($config['errors']['codigoPersonaObligatorio']['message'], 1);
            } else {
                //Validaciones de tipo de datos y rangos permitidos
                if (!is_numeric($codigoPersona)) {
                        throw new ModelsException($config['errors']['codigoPersonaNumerico']['message'], 1);
                }
            }

            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stid);

            $pc_datos = oci_new_cursor($this->conexion->getConexion());

            $stid = oci_parse($this->conexion->getConexion(), "BEGIN PRO_TEL_CITAS_PENDIENTES(:pn_cod_persona, :pc_datos); END;");
           
            // Bind the input num_entries argument to the $max_entries PHP variable             
            oci_bind_by_name($stid,":pn_cod_persona",$codigoPersona,32);
            oci_bind_by_name($stid, ":pc_datos", $pc_datos, -1, OCI_B_CURSOR);
           
            //Ejecuta el SP
            oci_execute($stid);

            //Ejecutar el REF CURSOR como un ide de sentencia normal
            oci_execute($pc_datos);  

            //Resultados de la consulta
            $citasPendientes = array();

            while (($row = oci_fetch_array($pc_datos, OCI_BOTH)) != false) {
                $existeDatos = true;

                # RESULTADO OBJETO
                $citasPendientes[] = array(
                    'codigoHorario' => $row[0],
                    'numeroTurno' => $row[1],
                    'fechaCita' => $row[2],
                    'horaInicioCita'=> $row[3],
                    'horaFinCita' => $row[4],
                    'codigoMedico' => $row[5], 
                    'nombresMedico' => $row[6], 
                    'descripcionEspecialidad' => $row[7]
                );
                
            }

            //Verificar si la consulta devolvió datos
            if ($existeDatos) {
                return array(
                    'status' => true,                    
                    'data'   => $citasPendientes
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
     * Permite cancelar (eliminar) una cita
     */
    public function cancelar()
    {
        global $config;

        //Inicialización de variables
        $stmt = null;
        $codigoRetorno = null;
        $mensajeRetorno = null;

        //Siempre es 1 para el Hospital Metropolitano
        $this->codigoInstitucion  = 1;

        try {

            //Asignar parámetros de entrada            
            $this->setParameters();

            //Validar parámetros de entrada            
            $this->validarParametrosCancelacionCita();

            //Conectar a la BDD
            $this->conexion->conectar();
             
            $stmt = oci_parse($this->conexion->getConexion(),'BEGIN PRO_TEL_CANCELA_CITA(:pn_institucion, :pn_cod_horario, :pn_num_turno, :pn_retorno, :pc_mensaje); END;');

            // Bind the input parameter
            oci_bind_by_name($stmt,':pn_institucion',$this->codigoInstitucion,32);
            oci_bind_by_name($stmt,':pn_cod_horario',$this->codigoHorario,32);
            oci_bind_by_name($stmt,':pn_num_turno',$this->numeroTurno,32);
             
            // Bind the output parameter
            oci_bind_by_name($stmt,':pn_retorno',$codigoRetorno,32);
            oci_bind_by_name($stmt,':pc_mensaje',$mensajeRetorno,500);
                         
            oci_execute($stmt);
            
            //Valida el código de retorno del SP
            if($codigoRetorno == 0){
                //Cita cancelada exitosamente               
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
                    'errorCode' => $ex->getCode()
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
     * __construct()
     */
    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);

        //Instancia la clase conexión a la base de datos
        $this->conexion = new Conexion();

    }
}