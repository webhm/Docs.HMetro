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
use Exception;
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Pacientes
 */

class Pacientes extends Models implements IModels
{
    # Variables de clase
    private $USER = null;
    private $sortField = 'ROWNUM_';
    private $sortType = 'desc'; # desc
    private $offset = 1;
    private $limit = 25;
    private $searchField = null;
    private $startDate = null;
    private $endDate = null;
    private $_conexion = null;

    #Paciente
    private $codigoEspecialidadMedico;
    private $codigoHorario;
    private $fechaNacimiento;
    private $codigoConsulta;
    private $indentificacionPaciente;
    private $valorConsulta;
    private $numeroTurno;
    private $primerApellidoPaciente;
    private $primerNombrePaciente;
    private $codigoLugarAtencion;
    private $genero;
    private $tipoIdentificacionPaciente;
    private $valorCobertura;
    private $codigoPersona;

    private $codigoInstitucion;
    private $tipoIdentificacion;
    private $identificacion;
    private $primerApellido;
    private $segundoApellido;
    private $primerNombre;
    private $segundoNombre;
    private $estadoCivil;
    private $calle;
    private $numero;
    private $celular;
    private $email;
    private $pais;
    private $provincia;
    private $ciudad;
    private $distrito;

    #Datos de Factura
    private $apellidosFactura;
    private $correoFactura;
    private $direccionFactura;
    private $identificacionFactura;
    private $nombresFactura;
    private $tipoIdentificacionFactura;

    #Datos de TC
    private $identificacionTitular;
    private $nombreTitular;
    private $numeroAutorizacion;
    private $numeroVoucher;
    private $tipoTarjetaCredito;
    private $telefono;

    #Tipo de la cita
    private $tipoCita;

    # Variables de clase
    private $conexion;

/**
 * Obtiene el token
 */
    private function obtenerAutorizacion()
    {
        try {
            global $http;

            $token = $http->headers->get("Authorization");

            $auth = new Model\Auth;
            $key = $auth->GetData($token);

            $this->USER = $key;

        } catch (ModelsException $e) {
            throw $e;
        }
    }

    /**
     * Obtiene los datos del paciente con parámetro codigoPersona
     */
    public function obtenerDatosPaciente1()
    {
        global $config;

        try {
            //Asignar parámetros de entrada
            $this->setParameters();

            //Validar parámetros de entrada
            //Código de la persona
            if ($this->codigoPersona == null) {
                throw new ModelsException($config['errors']['codigoPersonaObligatorio']['message'], 1);
            } else {
                //Validaciones de tipo de datos y rangos permitidos
                if (!is_numeric($this->codigoPersona)) {
                    throw new ModelsException($config['errors']['codigoPersonaNumerico']['message'], 1);
                }
            }

            return $this->obtenerDatosPaciente($this->codigoPersona);

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
                'errorCode' => $e->getCode()
            );

        } catch (Exception $ex) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $ex->getMessage(),
                'errorCode' => -1
            );

        }
    }

    /**
     * Obtiene los datos del paciente tomando el codigoPersona desde el Token
     */
    public function obtenerDatosPaciente2()
    {
        global $config;
        $codigoPersona = null;

        try {
            //Asignar parámetros de entrada
            $this->setParameters();

            //Obtiene la autorización
            $this->obtenerAutorizacion();

            $codigoPersona = $this->USER->COD_PERSONA;

            //Validar parámetros de entrada
            //Código de la persona
            if ($codigoPersona == null) {
                throw new ModelsException($config['errors']['codigoPersonaObligatorio']['message'], 1);
            } else {
                //Validaciones de tipo de datos y rangos permitidos
                if (!is_numeric($codigoPersona)) {
                    throw new ModelsException($config['errors']['codigoPersonaNumerico']['message'], 1);
                }
            }

            return $this->obtenerDatosPaciente($codigoPersona);

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
                'errorCode' => $e->getCode()
            );

        } catch (Exception $ex) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $ex->getMessage(),
                'errorCode' => -1
            );

        }
    }

    /**
     * Obtiene los datos del paciente
     */
    public function obtenerDatosPaciente($codigoPersona)
    {
        global $config;

        //Inicialización de variables
        $stid = null;
        $pc_datos = null;
        $existeDatos = false;
        $datosPaciente[] = null;

        try {
            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stid);

            $pc_datos = oci_new_cursor($this->conexion->getConexion());

            $stid = oci_parse($this->conexion->getConexion(), "BEGIN PRO_TEL_DATOS_PACIENTE(:pn_cod_persona, :pc_datos); END;");

            // Bind the input num_entries argument to the $max_entries PHP variable
            oci_bind_by_name($stid, ":pn_cod_persona", $codigoPersona, 32);
            oci_bind_by_name($stid, ":pc_datos", $pc_datos, -1, OCI_B_CURSOR);

            //Ejecuta el SP
            oci_execute($stid);

            //Ejecutar el REF CURSOR como un ide de sentencia normal
            oci_execute($pc_datos);

            //Resultados de la consulta
            $datosPaciente = array();

            while (($row = oci_fetch_array($pc_datos, OCI_BOTH + OCI_RETURN_NULLS)) != false) {
                $existeDatos = true;

                # RESULTADO OBJETO
                $datosPaciente[] = array(
                    'primerApellido' => $row[0] == null ? '' : $row[0],
                    'segundoApellido' => $row[1] == null ? '' : $row[1],
                    'primerNombre' => $row[2] == null ? '' : $row[2],
                    'segundoNombre' => $row[3] == null ? '' : $row[3],
                    'genero' => $row[4] == null ? '' : $row[4],
                    'estadoCivil' => $row[5] == null ? '' : $row[5],
                    'fechaNacimiento' => $row[6] == null ? '' : $row[6],
                    'cedula' => $row[7] == null ? '' : $row[7],
                    'pasaporte' => $row[8] == null ? '' : $row[8],
                    'ruc' => $row[9] == null ? '' : $row[9],
                    'direcciones' => $this->obtenerDirecciones($codigoPersona, $stid),
                    'mediosContacto' => $this->obtenerMediosContacto($codigoPersona, $stid),
                );
            }

            //Verificar si la consulta devolvió datos
            if ($existeDatos) {
                return array(
                    'status' => true,
                    'data' => $datosPaciente,
                );
            } else {
                throw new ModelsException($config['errors']['noExistenResultados']['message'], 1);
            }

        } catch (ModelsException $e) {
            throw $e;
        } catch (Exception $ex) {
            throw $ex;
        } finally {
            //Libera recursos de conexión
            if ($stid != null) {
                oci_free_statement($stid);
            }

            if ($pc_datos != null) {
                oci_free_statement($pc_datos);
            }

            //Cierra la conexión
            $this->conexion->cerrar();
        }
    }

    /**
     * Obtiene las direcciones asociadas al paciente
     */
    public function obtenerDirecciones($codigoPersona, $stid)
    {
        global $config;

        //Inicialización de variables
        $pc_datos = null;
        $existeDatos = false;
        $direcciones[] = null;

        try {
            $pc_datos = oci_new_cursor($this->conexion->getConexion());

            $stid = oci_parse($this->conexion->getConexion(), "BEGIN PRO_TEL_DIRECCION_PACIENTE(:pn_cod_persona, :pc_datos); END;");

            // Bind the input num_entries argument to the $max_entries PHP variable
            oci_bind_by_name($stid, ":pn_cod_persona", $codigoPersona, 32);
            oci_bind_by_name($stid, ":pc_datos", $pc_datos, -1, OCI_B_CURSOR);

            //Ejecuta el SP
            oci_execute($stid);

            //Ejecutar el REF CURSOR como un ide de sentencia normal
            oci_execute($pc_datos);

            //Resultados de la consulta
            $direcciones = array();

            while (($row = oci_fetch_array($pc_datos, OCI_BOTH + OCI_RETURN_NULLS)) != false) {
                $existeDatos = true;

                # RESULTADO OBJETO
                $direcciones[] = array(
                    'codigoDireccion' => $row[0] == null ? '' : $row[0],
                    'tipoDireccion' => $row[1] == null ? '' : $row[1],
                    'calle' => $row[2] == null ? '' : $row[2],
                    'numero' => $row[3] == null ? '' : $row[3],
                    'interseccion' => $row[4] == null ? '' : $row[4],
                    'referencia' => $row[5] == null ? '' : $row[5],
                    'pais' => $row[6] == null ? '' : $row[6],
                    'provincia' => $row[7] == null ? '' : $row[7],
                    'canton' => $row[8] == null ? '' : $row[8],
                );

            }

            //Verificar si la consulta devolvió datos
            if ($existeDatos) {
                return $direcciones;
            } else {
                return [];
            }

        } finally {
            //Libera recursos de conexión
            if ($pc_datos != null) {
                oci_free_statement($pc_datos);
            }

        }
    }

    /**
     * Obtiene los medios de contacto del paciente
     */
    public function obtenerMediosContacto($codigoPersona, $stid)
    {
        global $config;

        //Inicialización de variables
        $pc_datos = null;
        $existeDatos = false;
        $mediosContacto[] = null;

        try {
            $pc_datos = oci_new_cursor($this->conexion->getConexion());

            $stid = oci_parse($this->conexion->getConexion(), "BEGIN PRO_TEL_CONTACTOS_PACIENTE(:pn_cod_persona, :pc_datos); END;");

            // Bind the input num_entries argument to the $max_entries PHP variable
            oci_bind_by_name($stid, ":pn_cod_persona", $codigoPersona, 32);
            oci_bind_by_name($stid, ":pc_datos", $pc_datos, -1, OCI_B_CURSOR);

            //Ejecuta el SP
            oci_execute($stid);

            //Ejecutar el REF CURSOR como un ide de sentencia normal
            oci_execute($pc_datos);

            //Resultados de la consulta
            $mediosContacto = array();

            while (($row = oci_fetch_array($pc_datos, OCI_BOTH + OCI_RETURN_NULLS)) != false) {
                $existeDatos = true;

                # RESULTADO OBJETO
                $mediosContacto[] = array(
                    'valor' => $row[0] == null ? '' : $row[0],
                    'tipo' => $row[1] == null ? '' : $row[1],
                );

            }

            //Verificar si la consulta devolvió datos
            if ($existeDatos) {
                return $mediosContacto;
            } else {
                return [];
            }

        } finally {
            //Libera recursos de conexión
            if ($pc_datos != null) {
                oci_free_statement($pc_datos);
            }

        }
    }

    private function setSpanishOracle($stmt)
    {

        $sql = "alter session set NLS_LANGUAGE = 'SPANISH'";
        # Execute
        $stmt = oci_parse($this->conexion->getConexion(), $sql);
        oci_execute($stmt);

        $sql = "alter session set NLS_TERRITORY = 'SPAIN'";
        # Execute
        $stmt = oci_parse($this->conexion->getConexion(), $sql);
        oci_execute($stmt);

        $sql = " alter session set NLS_DATE_FORMAT = 'DD/MM/YYYY HH24:MI'";
        # Execute
        $stmt = oci_parse($this->conexion->getConexion(), $sql);
        oci_execute($stmt);

    }

    /**
     * Permite registrar un nuevo paciente
     */
    public function crear($codigoInstitucion, $tipoIdentificacion, $identificacion, $primerApellido, $segundoApellido, $primerNombre, $segundoNombre, $fechaNacimiento, $genero, $clave, $claveAnterior, $calle, $celular, $email)
    {
        global $config;

        //Inicialización de variables
        $stmt = null;
        $codigoRetorno = null;
        $mensajeRetorno = null;
        $numeroHistoriaClinica = null;
        $codigoPersona = null;

        //Valores por defecto
        $numero = "";
        $telefono = "";
        $estadoCivil = "";
        $pais = "313"; //Ecuador
        $provincia = "17"; //Pichincha
        $ciudad = "01"; // Quito
        $distrito = 66; //Belisario quevedo

        try {
            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stmt);

            $stmt = oci_parse($this->conexion->getConexion(), 'BEGIN PRO_TEL_REGISTRA_PACIENTE(:pn_institucion, :pc_tipo_doc, :pc_id, :pc_primer_apellido, :pc_segundo_apellido, :pc_primer_nombre, :pc_segundo_nombre, :pd_fecha_nac, :pc_estado_civil, :pc_sexo, :pc_clave, :pc_clave_ant, :pc_calle, :pc_numero, :pc_telefono, :pc_celular, :pc_email, :pc_pais, :pc_provincia, :pc_ciudad, :pc_distrito, :pn_hc, :pn_cod_persona, :pn_retorno, :pc_mensaje); END;');

            // Bind the input parameter
            oci_bind_by_name($stmt, ':pn_institucion', $codigoInstitucion, 32);
            oci_bind_by_name($stmt, ':pc_tipo_doc', $tipoIdentificacion, 32);
            oci_bind_by_name($stmt, ':pc_id', $identificacion, 32);
            oci_bind_by_name($stmt, ':pc_primer_apellido', $primerApellido, 32);
            oci_bind_by_name($stmt, ':pc_segundo_apellido', $segundoApellido, 32);
            oci_bind_by_name($stmt, ':pc_primer_nombre', $primerNombre, 32);
            oci_bind_by_name($stmt, ':pc_segundo_nombre', $segundoNombre, 32);
            oci_bind_by_name($stmt, ':pd_fecha_nac', $fechaNacimiento, 32);
            oci_bind_by_name($stmt, ':pc_estado_civil', $estadoCivil, 32);
            oci_bind_by_name($stmt, ':pc_sexo', $genero, 32);
            oci_bind_by_name($stmt, ':pc_clave', $clave, 100);
            oci_bind_by_name($stmt, ':pc_clave_ant', $claveAnterior, 100);
            oci_bind_by_name($stmt, ':pc_calle', $calle, 60);
            oci_bind_by_name($stmt, ':pc_numero', $numero, 32);
            oci_bind_by_name($stmt, ':pc_telefono', $telefono, 60);
            oci_bind_by_name($stmt, ':pc_celular', $celular, 60);
            oci_bind_by_name($stmt, ':pc_email', $email, 60);
            oci_bind_by_name($stmt, ':pc_pais', $pais, 32);
            oci_bind_by_name($stmt, ':pc_provincia', $provincia, 32);
            oci_bind_by_name($stmt, ':pc_ciudad', $ciudad, 32);
            oci_bind_by_name($stmt, ':pc_distrito', $distrito, 32);

            // Bind the output parameter
            oci_bind_by_name($stmt, ':pn_hc', $numeroHistoriaClinica, 32);
            oci_bind_by_name($stmt, ':pn_cod_persona', $codigoPersona, 32);
            oci_bind_by_name($stmt, ':pn_retorno', $codigoRetorno, 32);
            oci_bind_by_name($stmt, ':pc_mensaje', $mensajeRetorno, 500);

            oci_execute($stmt);

            //Valida el código de retorno del SP
            if ($codigoRetorno == 0) {
                //Cita cancelada exitosamente
                /* return array(
            'status' => true,
            'data'   => [],
            'message'   => $mensajeRetorno
            );*/
            } elseif ($codigoRetorno == 1) {
                //Mensajes de aplicación
                throw new ModelsException($mensajeRetorno, $codigoRetorno);
            } else {
                //Mensajes de errores técnicos
                throw new Exception($mensajeRetorno, -1);
            }
        } finally {
            //Libera recursos de conexión
            if ($stmt != null) {
                oci_free_statement($stmt);
            }

            //Cierra la conexión
            $this->conexion->cerrar();
        }

    }

    /**
     * Permite realizar el pago de una consulta
     */
    public function realizarPagoConsulta()
    {
        global $config;

        //Inicialización de variables
        $stmt = null;
        $codigoRetorno = null;
        $mensajeRetorno = null;

        //Siempre es 1 para el Hospital Metropolitano
        //$this->codigoInstitucion  = 1;

        try {

            //Asignar parámetros de entrada
            $this->setParameters();

            //Validar parámetros de entrada
            $this->validarParametrosPagoCita();

            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stmt);

            $stmt = oci_parse($this->conexion->getConexion(), 'BEGIN PRO_REGISTRA_DATOS_WEB(:pc_codigo_espec_medico, :pc_codigo_horario, :pc_fk_arinda_no_arti, :pc_identificacion_paciente, :pc_monto, :pc_numero_turno, :pc_servicio,  :pc_valor_cobertura, :pc_apellidos_factura, :pc_correo_factura, :pc_direccion_factura, :pc_identificacion_factura, :pc_nombres_factura, :pc_tipo_id_factura, :pc_identificacion_titular, :pc_nombre_titular, :pc_numero_autorizacion, :pc_numero_voucher, :pc_tipo_tarjeta_credito, :pc_telefono, :pc_tipo_cita, :pc_error, :pc_mensaje_error); END;');

            // Bind the input parameter
            oci_bind_by_name($stmt, ':pc_codigo_espec_medico', $this->codigoEspecialidadMedico, 32);
            oci_bind_by_name($stmt, ':pc_codigo_horario', $this->codigoHorario, 32);
            //oci_bind_by_name($stmt,':pd_fecha_nacimiento',$this->fechaNacimiento,32);
            oci_bind_by_name($stmt, ':pc_fk_arinda_no_arti', $this->codigoConsulta, 32);
            oci_bind_by_name($stmt, ':pc_identificacion_paciente', $this->indentificacionPaciente, 32);
            oci_bind_by_name($stmt, ':pc_monto', $this->valorConsulta, 32);
            oci_bind_by_name($stmt, ':pc_numero_turno', $this->numeroTurno, 32);
            //oci_bind_by_name($stmt,':pc_primer_apellido',$this->primerApellidoPaciente,80);
            //oci_bind_by_name($stmt,':pc_primer_nombre',$this->primerNombrePaciente,80);
            oci_bind_by_name($stmt, ':pc_servicio', $this->codigoLugarAtencion, 32);
            //oci_bind_by_name($stmt,':pc_sexo',$this->genero,32);
            //oci_bind_by_name($stmt,':pc_tipo_id_paciente',$this->tipoIdentificacionPaciente,32);
            oci_bind_by_name($stmt, ':pc_valor_cobertura', $this->valorCobertura, 32);
            oci_bind_by_name($stmt, ':pc_apellidos_factura', $this->apellidosFactura, 120);
            oci_bind_by_name($stmt, ':pc_correo_factura', $this->correoFactura, 120);
            oci_bind_by_name($stmt, ':pc_direccion_factura', $this->direccionFactura, 250);
            oci_bind_by_name($stmt, ':pc_identificacion_factura', $this->identificacionFactura, 32);
            oci_bind_by_name($stmt, ':pc_nombres_factura', $this->nombresFactura, 120);
            oci_bind_by_name($stmt, ':pc_tipo_id_factura', $this->tipoIdentificacionFactura, 32);
            oci_bind_by_name($stmt, ':pc_identificacion_titular', $this->identificacionTitular, 32);
            oci_bind_by_name($stmt, ':pc_nombre_titular', $this->nombreTitular, 120);
            oci_bind_by_name($stmt, ':pc_numero_autorizacion', $this->numeroAutorizacion, 32);
            oci_bind_by_name($stmt, ':pc_numero_voucher', $this->numeroVoucher, 32);
            oci_bind_by_name($stmt, ':pc_tipo_tarjeta_credito', $this->tipoTarjetaCredito, 32);
            oci_bind_by_name($stmt, ':pc_telefono', $this->telefono, 32);
            oci_bind_by_name($stmt, ':pc_tipo_cita', $this->tipoCita, 1);

            // Bind the output parameter
            oci_bind_by_name($stmt, ':pc_error', $codigoRetorno, 32);
            oci_bind_by_name($stmt, ':pc_mensaje_error', $mensajeRetorno, 500);

            oci_execute($stmt);

            //Valida el código de retorno del SP
            if ($codigoRetorno == 0) {
                //Cita cancelada exitosamente
                return array(
                    'status' => true,
                    'data' => [],
                    'message' => $mensajeRetorno
                );
            } elseif ($codigoRetorno == 1) {
                //Mensajes de aplicación
                throw new ModelsException($mensajeRetorno, $codigoRetorno);
            } else {
                //Mensajes de errores técnicos
                throw new Exception($mensajeRetorno, -1);
            }

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
                'errorCode' => $e->getCode()
            );

        } catch (Exception $ex) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $ex->getMessage(),
                'errorCode' => $ex->getCode()
            );

        } finally {
            //Libera recursos de conexión
            if ($stmt != null) {
                oci_free_statement($stmt);
            }

            //Cierra la conexión
            $this->conexion->cerrar();
        }

    }

    /**
     * Valida los parámetros de entrada pago de cita
     */
    private function validarParametrosPagoCita()
    {
        global $config;

        //Correo de la factura
        if ($this->correoFactura == null) {
            throw new ModelsException($config['errors']['correoFacturaObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!Helper\Strings::is_email($this->correoFactura)) {
                throw new ModelsException($config['errors']['correoFacturaIsEmail']['message'], 1);
            }
        }

        //Código del lugar de atención
        if ($this->codigoLugarAtencion == null) {
            throw new ModelsException($config['errors']['codigoLugarAtencionObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoLugarAtencion)) {
                throw new ModelsException($config['errors']['codigoLugarAtencionNumerico']['message'], 1);
            }
        }

        //Código de consulta
        if ($this->codigoConsulta == null) {
            throw new ModelsException($config['errors']['codigoConsultaObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoConsulta)) {
                throw new ModelsException($config['errors']['codigoConsultaNumerico']['message'], 1);
            }
        }

        //Código de la especialidad del médico
        if ($this->codigoEspecialidadMedico == null) {
            throw new ModelsException($config['errors']['codigoEspecialidadMedicoObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoEspecialidadMedico)) {
                throw new ModelsException($config['errors']['codigoEspecialidadMedicoNumerico']['message'], 1);
            }
        }

        //Código de horario
        if ($this->codigoHorario == null) {
            throw new ModelsException($config['errors']['codigoHorarioObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoHorario)) {
                throw new ModelsException($config['errors']['codigoHorarioNumerico']['message'], 1);
            }
        }

        //Número de turno
        if ($this->numeroTurno == null) {
            throw new ModelsException($config['errors']['numeroTurnoObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->numeroTurno)) {
                throw new ModelsException($config['errors']['numeroTurnoNumerico']['message'], 1);
            }
        }

        //Tipo de consulta
        if ($this->tipoCita == null) {
            throw new ModelsException($config['errors']['tipoCitaObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            //S = Subsecuente / P = Primera vez
            if (!Helper\Strings::contain($this->tipoCita, 'SP')) {
                throw new ModelsException($config['errors']['tipoCitaNoPermitido']['message'], 1);
            }
        }

    }

    /**
     * Valida los parámetros de entrada creación del paciente
     */
    private function validarParametrosCrear()
    {
        global $config;

        //Identificación
        if ($this->identificacion == null) {
            throw new ModelsException($config['errors']['identificacionObligatorio']['message'], 1);
        }

        //Tipo de identificación
        if ($this->tipoIdentificacion == null) {
            throw new ModelsException($config['errors']['tipoIdentificacionObligatorio']['message'], 1);
        }

        //Primer apellido
        if ($this->primerApellido == null) {
            throw new ModelsException($config['errors']['primerApellidoObligatorio']['message'], 1);
        }

        //Primer nombre
        if ($this->primerNombre == null) {
            throw new ModelsException($config['errors']['primerNombreObligatorio']['message'], 1);
        }

        //Fecha de nacimiento
        if ($this->fechaNacimiento == null) {
            throw new ModelsException($config['errors']['fechaNacimientoObligatorio']['message'], 1);
        }

        //Estado civil
        if ($this->estadoCivil == null) {
            throw new ModelsException($config['errors']['estadoCivilObligatorio']['message'], 1);
        }

        //Genero
        if ($this->genero == null) {
            throw new ModelsException($config['errors']['generoObligatorio']['message'], 1);
        }

        //Calle
        if ($this->calle == null) {
            throw new ModelsException($config['errors']['calleObligatorio']['message'], 1);
        }

        //Ciudad
        if ($this->ciudad == null) {
            throw new ModelsException($config['errors']['ciudadObligatorio']['message'], 1);
        }

        //Celular
        if ($this->celular == null) {
            throw new ModelsException($config['errors']['celularObligatorio']['message'], 1);
        }

        //Email
        if ($this->email == null) {
            throw new ModelsException($config['errors']['emailObligatorio']['message'], 1);
        }

        //Pais
        if ($this->pais == null) {
            throw new ModelsException($config['errors']['paisObligatorio']['message'], 1);
        }

        //Provincia
        if ($this->provincia == null) {
            throw new ModelsException($config['errors']['provinciaObligatorio']['message'], 1);
        }

        //Ciudad
        if ($this->ciudad == null) {
            throw new ModelsException($config['errors']['ciudadObligatorio']['message'], 1);
        }

        //Distrito
        if ($this->distrito == null) {
            throw new ModelsException($config['errors']['distritoObligatorio']['message'], 1);
        }

    }

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
            $key = $auth->GetData($token);

            $this->USER = $key;

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    private function errorsValidacion()
    {

        $dni = new Model\ValidacionDNI;
        # si es cédula
        if (ctype_digit(Helper\Strings::remove_spaces($this->val))) {

            #validar si es ruc o si es cedula normal
            if (strlen(Helper\Strings::remove_spaces($this->val)) == 10) {
                if (!$dni->validarCedula($this->val)) {
                    throw new ModelsException('!Error! Cédula ingresada no es válida.', 4003);
                }
            }

            # si documento estrangero
            if ((strlen(Helper\Strings::remove_spaces($this->val)) > 13 and
                strlen(Helper\Strings::remove_spaces($this->val)) > 25)) {
                throw new ModelsException('!Error! Documento extrangero no puede ser mayor que 25 caracteres.', 4005);
            }

        }
    }

    private function errorsPagination()
    {

        if ($this->limit > 25) {
            throw new ModelsException('!Error! Solo se pueden mostrar 25 resultados por página.');
        }

        if ($this->limit == 0 or $this->limit < 0) {
            throw new ModelsException('!Error! {Limit} no puede ser 0 o negativo');
        }

        if ($this->offset == 0 or $this->offset < 0) {
            throw new ModelsException('!Error! {Offset} no puede ser 0 o negativo.');
        }
    }

    private function setParameters()
    {

        try {

            global $http;

            foreach ($http->request->all() as $key => $value) {
                $this->$key = strtoupper($value);
            }

            return false;
        } catch (ModelsException $e) {
            throw new ModelsException($e->getMessage(), 0);
        }
    }

    /**
     * Valida los parámetros de entrada agendamiento admisión consulta
     */
    private function validarParametrosAgendamientoAdmisionConsulta()
    {
        global $config;

        //Código de la especialidad del médico
        if ($this->codigoEspecialidadMedico == null) {
            throw new ModelsException($config['errors']['codigoEspecialidadMedicoObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoEspecialidadMedico)) {
                throw new ModelsException($config['errors']['codigoEspecialidadMedicoNumerico']['message'], 1);
            }
        }

        //Código de horario
        if ($this->codigoHorario == null) {
            throw new ModelsException($config['errors']['codigoHorarioObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoHorario)) {
                throw new ModelsException($config['errors']['codigoHorarioNumerico']['message'], 1);
            }
        }

        //Código de consulta
        if ($this->codigoConsulta == null) {
            throw new ModelsException($config['errors']['codigoConsultaObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoConsulta)) {
                throw new ModelsException($config['errors']['codigoConsultaNumerico']['message'], 1);
            }
        }

        //Identificación del paciente
        if ($this->indentificacionPaciente == null) {
            throw new ModelsException($config['errors']['identificacionObligatorio']['message'], 1);
        }

        /*
        //Monto
        if ($this->valorConsulta == null) {
        throw new ModelsException($config['errors']['valorConsultaObligatorio']['message'], 1);
        }
         */

        //Número de turno
        if ($this->numeroTurno == null) {
            throw new ModelsException($config['errors']['numeroTurnoObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->numeroTurno)) {
                throw new ModelsException($config['errors']['numeroTurnoNumerico']['message'], 1);
            }
        }

        //Código del lugar de atención
        if ($this->codigoLugarAtencion == null) {
            throw new ModelsException($config['errors']['codigoLugarAtencionObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoLugarAtencion)) {
                throw new ModelsException($config['errors']['codigoLugarAtencionNumerico']['message'], 1);
            }
        }

        /*
        //Cobertura
        if ($this->valorCobertura == null) {
        throw new ModelsException($config['errors']['valorCoberturaObligatorio']['message'], 1);
        }
         */

        //Tipo de consulta
        if ($this->tipoCita == null) {
            throw new ModelsException($config['errors']['tipoCitaObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            //S = Subsecuente / P = Primera vez
            if (!Helper\Strings::contain($this->tipoCita, 'SP')) {
                throw new ModelsException($config['errors']['tipoCitaNoPermitido']['message'], 1);
            }
        }

    }

    /**
     * Permite realizar el agendamiento de una consulta
     * y la creación de la admisión de una paciente
     */
    public function realizarAgendamientoAdmisionConsulta()
    {
        global $config;

        //Inicialización de variables
        $stmt = null;
        $codigoRetorno = null;
        $mensajeRetorno = null;

        //Siempre es 1 para el Hospital Metropolitano
        //$this->codigoInstitucion  = 1;

        try {

            //Asignar parámetros de entrada
            $this->setParameters();

            //Validar parámetros de entrada
            $this->validarParametrosAgendamientoAdmisionConsulta();

            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stmt);

            $stmt = oci_parse($this->conexion->getConexion(), 'BEGIN PRO_REGISTRA_DATOS_WEB(:pc_codigo_espec_medico, :pc_codigo_horario, :pc_fk_arinda_no_arti, :pc_identificacion_paciente, :pc_monto, :pc_numero_turno, :pc_servicio,  :pc_valor_cobertura, :pc_apellidos_factura, :pc_correo_factura, :pc_direccion_factura, :pc_identificacion_factura, :pc_nombres_factura, :pc_tipo_id_factura, :pc_identificacion_titular, :pc_nombre_titular, :pc_numero_autorizacion, :pc_numero_voucher, :pc_tipo_tarjeta_credito, :pc_telefono, :pc_tipo_cita, :pc_error, :pc_mensaje_error); END;');

            // Bind the input parameter
            //Obligatorios
            oci_bind_by_name($stmt, ':pc_codigo_espec_medico', $this->codigoEspecialidadMedico, 32);
            oci_bind_by_name($stmt, ':pc_codigo_horario', $this->codigoHorario, 32);
            oci_bind_by_name($stmt, ':pc_fk_arinda_no_arti', $this->codigoConsulta, 32);
            oci_bind_by_name($stmt, ':pc_identificacion_paciente', $this->indentificacionPaciente, 32);
            oci_bind_by_name($stmt, ':pc_numero_turno', $this->numeroTurno, 32);
            oci_bind_by_name($stmt, ':pc_servicio', $this->codigoLugarAtencion, 32);
            oci_bind_by_name($stmt, ':pc_tipo_cita', $this->tipoCita, 1);

            //No obligaorios
            $this->apellidosFactura = null;
            $this->correoFactura = null;
            $this->direccionFactura = null;
            $this->identificacionFactura = null;
            $this->nombresFactura = null;
            $this->tipoIdentificacionFactura = null;
            $this->identificacionTitular = null;
            $this->nombreTitular = null;
            $this->numeroAutorizacion = null;
            $this->numeroVoucher = null;
            $this->tipoTarjetaCredito = null;
            $this->telefono = null;
            $this->valorCobertura = null;
            $this->valorConsulta = null;

            oci_bind_by_name($stmt, ':pc_valor_cobertura', $this->valorCobertura, 32);
            oci_bind_by_name($stmt, ':pc_monto', $this->valorConsulta, 32);
            oci_bind_by_name($stmt, ':pc_apellidos_factura', $this->apellidosFactura, 120);
            oci_bind_by_name($stmt, ':pc_correo_factura', $this->correoFactura, 120);
            oci_bind_by_name($stmt, ':pc_direccion_factura', $this->direccionFactura, 250);
            oci_bind_by_name($stmt, ':pc_identificacion_factura', $this->identificacionFactura, 32);
            oci_bind_by_name($stmt, ':pc_nombres_factura', $this->nombresFactura, 120);
            oci_bind_by_name($stmt, ':pc_tipo_id_factura', $this->tipoIdentificacionFactura, 32);
            oci_bind_by_name($stmt, ':pc_identificacion_titular', $this->identificacionTitular, 32);
            oci_bind_by_name($stmt, ':pc_nombre_titular', $this->nombreTitular, 120);
            oci_bind_by_name($stmt, ':pc_numero_autorizacion', $this->numeroAutorizacion, 32);
            oci_bind_by_name($stmt, ':pc_numero_voucher', $this->numeroVoucher, 32);
            oci_bind_by_name($stmt, ':pc_tipo_tarjeta_credito', $this->tipoTarjetaCredito, 32);
            oci_bind_by_name($stmt, ':pc_telefono', $this->telefono, 32);

            // Bind the output parameter
            oci_bind_by_name($stmt, ':pc_error', $codigoRetorno, 32);
            oci_bind_by_name($stmt, ':pc_mensaje_error', $mensajeRetorno, 500);

            oci_execute($stmt);

            //Valida el código de retorno del SP
            if ($codigoRetorno == 0) {

                //Cita cancelada exitosamente
                return array(
                    'status' => true,
                    'data' => [],
                    'message' => $mensajeRetorno
                );
            } elseif ($codigoRetorno == 1) {
                //Mensajes de aplicación
                throw new ModelsException($mensajeRetorno, $codigoRetorno);
            } else {
                //Mensajes de errores técnicos
                throw new Exception($mensajeRetorno, -1);
            }

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $e->getMessage(),
                'errorCode' => $e->getCode()
            );

        } catch (Exception $ex) {

            return array(
                'status' => false,
                'data' => [],
                'message' => $ex->getMessage(),
                'errorCode' => $ex->getCode()
            );

        } finally {
            //Libera recursos de conexión
            if ($stmt != null) {
                oci_free_statement($stmt);
            }

            //Cierra la conexión
            $this->conexion->cerrar();
        }

    }

    public function getPaciente(): array
    {

        try {

            # EXTRAER VALOR CEDULA DEL TOKEN PARA CONSULTA
            $this->getAuthorizationn('DNI');

            # ERRORES DE PETICION
            $this->errorsValidacion();

            # EXTRAER VALOR CODIGO PERSONA DEL TOKEN PARA CONSULTA
            $this->getAuthorizationn('COD');

            # Consulta SQL
            $sql = "SELECT WEB_VW_PERSONAS.*, ROWNUM as ROWNUM_ FROM WEB_VW_PERSONAS WHERE FK_PERSONA='$this->val'";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # Cerrar conexion
            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $pte = $stmt->fetch();

            if (false === $pte) {
                throw new ModelsException('Error No existen elementos.', 4080);
            }

            unset($pte['PK_NHCL']);
            unset($pte['FK_PERSONA']);
            unset($pte['ROWNUM_']);
            unset($pte['EMAILS']);

            # PARSEO DE INFORMACION

            $DIRECCIONES = array();

            foreach (explode(';', $pte['DIRECCIONES']) as $key => $value) {

                $FIELD = explode(' ', $value);
                unset($FIELD[0]);

                $DIRECCIONES[] = array(
                    'ID' => intval(explode(' ', $value)[0]),
                    'FIELD' => implode(' ', $FIELD),
                );
            }

            $pte['DIRECCIONES'] = $DIRECCIONES;

            $TELEFONOS = array();

            foreach (explode(';', $pte['TELEFONOS']) as $key => $value) {

                $FIELD = explode(' ', $value);
                unset($FIELD[0]);

                $TELEFONOS[] = array(
                    'ID' => intval(explode(' ', $value)[0]),
                    'FIELD' => implode(' ', $FIELD),
                );
            }

            $pte['TELEFONOS'] = $TELEFONOS;

            $CELULARES = array();

            foreach (explode(';', $pte['CELULARES']) as $key => $value) {

                $FIELD = explode(' ', $value);
                unset($FIELD[0]);

                $CELULARES[] = array(
                    'ID' => intval(explode(' ', $value)[0]),
                    'FIELD' => implode(' ', $FIELD),
                );
            }

            $pte['CELULARES'] = $CELULARES;

            $pte['EMAIL_ACCOUNT'] = $this->getEmailAccount($this->val);

            # DATOS DE FACTURACION DE PRUEBA

            $this->getAuthorizationn('DNI');

            $pte['FACTURACION'] = array(
                'DNI' => $this->val,
                'USER' => $pte['APELLIDOS'] . ' ' . $pte['NOMBRES'],
                'EMAIL' => $pte['EMAIL_ACCOUNT'],
                'DIR' => 'AV. FCO DALMAU Y CALLE 9',
                'CELULAR' => '0996387644',
                'CUIDAD' => 'QUITO',
            );

            # vERIFICAR SI ES MÉDICO

            $PERFIL_MEDICO = new Model\Medicos;
            $PERFIL = $PERFIL_MEDICO->getPerfil_Medico($this->val);

            # Si es médico devolver los datos del medico
            if ($PERFIL['status']) {
                $pte['PERFIL_MEDICO'] = $PERFIL['data'];
            }

            # RESULTADO OBJETO
            return array(
                'status' => true,
                'data' => $pte,
            );

        } catch (Exception $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status' => false,
                    'data' => [],
                    'message' => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getHistorialPaciente(): array
    {

        try {

            # SETEAR VARIABLES DE CLASE
            $this->setParameters();

            # ERRORES DE PETICION
            $this->errorsPagination();

            # EXTRAER VALOR DEL TOKEN PARA CONSULTA
            $this->getAuthorizationn();

            # eXTRAER CODIGOS DE cp_pte
            $codes = implode(',', $this->USER->CP_PTE);

            # CONULTA BDD GEMA
            if ($this->startDate != null and $this->endDate != null and $this->sortField === 'FECHA_ADMISION') {

                $sql = "SELECT WEB_VW_ATENCIONES.*, ROWNUM AS ROWNUM_ FROM WEB_VW_ATENCIONES WHERE COD_PERSONA IN ($codes) AND FECHA_ADMISION >= TO_DATE('$this->startDate', 'dd-mm-yyyy') AND FECHA_ADMISION <= TO_DATE('$this->endDate', 'dd-mm-yyyy') ORDER BY $this->sortField $this->sortType ";

            } elseif ($this->startDate != null and $this->endDate != null and $this->sortField === 'FECHA_ALTA') {

                $sql = "SELECT WEB_VW_ATENCIONES.*, ROWNUM AS ROWNUM_ FROM WEB_VW_ATENCIONES WHERE COD_PERSONA IN ($codes) AND FECHA_ALTA >= TO_DATE('$this->startDate', 'dd-mm-yyyy') AND FECHA_ALTA <= TO_DATE('$this->endDate', 'dd-mm-yyyy') ORDER BY $this->sortField $this->sortType ";

            } elseif ($this->searchField != null) {

                $sql = "SELECT WEB_VW_ATENCIONES.*, ROWNUM AS ROWNUM_ FROM WEB_VW_ATENCIONES WHERE COD_PERSONA IN ($codes) AND (ORIGEN_ATENCION LIKE '%$this->searchField%' OR ESPECIALIDAD LIKE '%$this->searchField%' OR MEDICO_TRATANTE LIKE '%$this->searchField%') ORDER BY ROWNUM_ $this->sortType ";

            } else {

                $sql = "SELECT WEB_VW_ATENCIONES.*, ROWNUM AS ROWNUM_ FROM WEB_VW_ATENCIONES WHERE COD_PERSONA IN ($codes) ORDER BY FECHA_ADMISION  $this->sortType ";

            }

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # Cerrar conexion
            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $historial = array();

            $NUM = 1; // ITERADO

            $data = $stmt->fetchAll();

            foreach ($data as $key) {

                $key['HCU'] = $key['HCL'];
                $key['NUM'] = $key['ROWNUM_'];
                $key['ADM'] = $key['ADMISION'];

                $key['FECHA_ADMISION'] = date('d-m-Y', strtotime($key['FECHA_ADMISION']));
                $key['FECHA_ALTA'] = date('d-m-Y', strtotime($key['FECHA_ALTA']));

                unset($key['ADMISION']);
                unset($key['ROWNUM_']);
                unset($key['HCL']);
                unset($key['COD_PERSONA']);

                $historial[] = $key;

            }

            // RESULTADO DE CONSULTA

            # Order by asc to desc
            $ATENCIONES = $this->get_Order_Pagination($historial);

            # Ya no existe resultadso
            if (count($historial) == 0) {
                throw new ModelsException('No existe más resultados.', 4080);
            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->get_page($ATENCIONES, $this->offset, $this->limit),
                'total' => count($historial),
                'limit' => intval($this->limit),
                'offset' => intval($this->offset),
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status' => false,
                    'data' => [],
                    'message' => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getEmailAccount($fk_persona)
    {

        try {

            # paciente ya tiene cuenta electrónica
            $sql = "SELECT AAS_CLAVES_WEB.*, ROWNUM as ROWNUM_ FROM AAS_CLAVES_WEB WHERE PK_FK_PERSONA='$fk_persona'";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # Cerrar conexion
            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $pte = $stmt->fetch();

            if (false === $pte) {
                throw new ModelsException('!Error! Usuario ya tiene una cuenta electrónica registrada.', 4010);
            }

            # valor final
            return $pte['CORREO'];

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }
    }

    private function get_Order_Pagination(array $arr_input)
    {
        # SI ES DESCENDENTE

        $arr = array();
        $NUM = 1;

        if ($this->sortType == 'desc') {

            $NUM = count($arr_input);
            foreach ($arr_input as $key) {
                $key['NUM'] = $NUM;
                $arr[] = $key;
                $NUM--;
            }

            return $arr;

        }

        # SI ES ASCENDENTE

        foreach ($arr_input as $key) {
            $key['NUM'] = $NUM;
            $arr[] = $key;
            $NUM++;
        }

        return $arr;
    }

    private function get_page(array $input, $pageNum, $perPage)
    {
        $start = ($pageNum - 1) * $perPage;
        $end = $start + $perPage;
        $count = count($input);

        // Conditionally return results
        if ($start < 0 || $count <= $start) {
            // Page is out of range
            return array();
        } else if ($count <= $end) {
            // Partially-filled page
            return array_slice($input, $start);
        } else {
            // Full page
            return array_slice($input, $start, $end - $start);
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
