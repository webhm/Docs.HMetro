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
use DateInterval;
use DateTime;
use Exception;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Models\Traits\DBModel;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Odbc GEMA -> Medicos
 */

class Medicos extends Models implements IModels
{
    use DBModel;

    # Variables de clase
    private $conexion;

    private $start = 0;
    private $length = 10;
    private $startDate = null;
    private $endDate = null;
    private $codigoMedico = null;
    private $tipoHorario = null;
    private $codigoInstitucion = 1;
    private $fechaInicial = null;
    private $fechaFinal = null;
    private $horaInicial = null;
    private $horaFinal = null;
    private $nombresPaciente = null;
    private $duracion = null;
    private $codigoOrganigrama = null;
    private $lunes = null;
    private $martes = null;
    private $miercoles = null;
    private $jueves = null;
    private $viernes = null;
    private $sabado = null;
    private $domingo = null;
    private $codigoHorario = null;

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

    /*
     * Quita las tildes de una cadena
     */
    private function quitar_tildes($cadena)
    {
        $no_permitidas = array("%", "é", "í", "ó", "ú", "É", "Í", "Ó", "Ú", "ñ", "À", "Ã", "Ì", "Ò", "Ù", "Ã™", "Ã ", "Ã¨", "Ã¬", "Ã²", "Ã¹", "ç", "Ç", "Ã¢", "ê", "Ã®", "Ã´", "Ã»", "Ã‚", "ÃŠ", "ÃŽ", "Ã”", "Ã›", "ü", "Ã¶", "Ã–", "Ã¯", "Ã¤", "«", "Ò", "Ã", "Ã„", "Ã‹");
        $permitidas = array("", "e", "i", "o", "u", "E", "I", "O", "U", "n", "N", "A", "E", "I", "O", "U", "a", "e", "i", "o", "u", "c", "C", "a", "e", "i", "o", "u", "A", "E", "I", "O", "U", "u", "o", "O", "i", "a", "e", "U", "I", "A", "E");
        $texto = str_replace($no_permitidas, $permitidas, $cadena);
        return $texto;
    }

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
    private function validarParametrosConsultaCitasDisponibles()
    {
        global $config;

        //Código del médico
        if ($this->codigoMedico == null) {
            throw new ModelsException($config['errors']['codigoMedicoObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoMedico)) {
                throw new ModelsException($config['errors']['codigoMedicoNumerico']['message'], 1);
            }
        }

        //Fecha de inicio
        if ($this->startDate == null) {
            throw new ModelsException($config['errors']['startDateObligatorio']['message'], 1);
        } else {
            if ($this->endDate != null) {

                $startDate = $this->startDate;
                $endDate = $this->endDate;

                $sd = new DateTime($startDate);
                $ed = new DateTime($endDate);

                if ($sd->getTimestamp() > $ed->getTimestamp()) {
                    throw new ModelsException($config['errors']['startDateIncorrecta']['message'], 1);
                }
            }
        }

        //Fecha final
        if ($this->endDate == null) {
            throw new ModelsException($config['errors']['endDateObligatorio']['message'], 1);
        }

        //Max row to fetch
        if ($this->length == null) {
            throw new ModelsException($config['errors']['lengthObligatorio']['message'], 1);
        } else {
            if ($this->length <= 0) {
                throw new ModelsException($config['errors']['lengthIncorrecto']['message'], 1);
            }
        }

        //Min row to fetch
        if ($this->start == null) {
            throw new ModelsException($config['errors']['startObligatorio']['message'], 1);
        } else {
            if ($this->start < 0) {
                throw new ModelsException($config['errors']['startIncorrecto']['message'], 1);
            }
        }

        //Código de tipo de horario
        if ($this->tipoHorario == null) {
            throw new ModelsException($config['errors']['tipoHorarioObligatorio']['message'], 1);
        } else {
            if ($this->tipoHorario < 0) {
                throw new ModelsException($config['errors']['tipoHorarioIncorrecto']['message'], 1);
            }
        }
    }

    /**
     * Valida los parámetros de entrada
     */
    private function validarParametrosConsultaCitasPacientePasadas()
    {
        global $config;

        //Código del médico
        if ($this->codigoMedico == null) {
            throw new ModelsException($config['errors']['codigoMedicoObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoMedico)) {
                throw new ModelsException($config['errors']['codigoMedicoNumerico']['message'], 1);
            }
        }

        //Fecha de inicio
        if ($this->startDate == null) {
            throw new ModelsException($config['errors']['startDateObligatorio']['message'], 1);
        } else {
            $startDate = $this->startDate;

            $sd = new DateTime($startDate);
            $ed = new DateTime();
            $ed = $ed->sub(new DateInterval('P1D'));

            if ($sd->getTimestamp() > $ed->getTimestamp()) {
                throw new ModelsException($config['errors']['startDateIncorrectaFechaAyer']['message'], 1);
            }
        }

        //Max row to fetch
        if ($this->length == null) {
            throw new ModelsException($config['errors']['lengthObligatorio']['message'], 1);
        } else {
            if ($this->length <= 0) {
                throw new ModelsException($config['errors']['lengthIncorrecto']['message'], 1);
            }
        }

        //Min row to fetch
        if ($this->start == null) {
            throw new ModelsException($config['errors']['startObligatorio']['message'], 1);
        } else {
            if ($this->start < 0) {
                throw new ModelsException($config['errors']['startIncorrecto']['message'], 1);
            }
        }

        //Código de tipo de horario
        if ($this->tipoHorario == null) {
            throw new ModelsException($config['errors']['tipoHorarioObligatorio']['message'], 1);
        } else {
            if ($this->tipoHorario < 0) {
                throw new ModelsException($config['errors']['tipoHorarioIncorrecto']['message'], 1);
            }
        }
    }

    /**
     * Valida los parámetros de entrada
     */
    private function validarParametrosConsultaCitasPacientePasadasPorNombres()
    {
        global $config;

        //Código del médico
        if ($this->codigoMedico == null) {
            throw new ModelsException($config['errors']['codigoMedicoObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoMedico)) {
                throw new ModelsException($config['errors']['codigoMedicoNumerico']['message'], 1);
            }
        }

        //Fecha de inicio
        if ($this->startDate == null) {
            throw new ModelsException($config['errors']['startDateObligatorio']['message'], 1);
        } else {
            $startDate = $this->startDate;

            $sd = new DateTime($startDate);
            $ed = new DateTime();
            $ed = $ed->sub(new DateInterval('P1D'));

            if ($sd->getTimestamp() > $ed->getTimestamp()) {
                throw new ModelsException($config['errors']['startDateIncorrectaFechaAyer']['message'], 1);
            }
        }

        //Nombres del paciente
        if ($this->nombresPaciente == null) {
            $this->nombresPaciente = "%";
        } else {
            $this->nombresPaciente = $this->quitar_tildes(mb_strtoupper($this->sanear_string($this->nombresPaciente), 'UTF-8'));

            # Setear valores para busquedas dividadas
            if (stripos($this->nombresPaciente, ' ')) {
                $this->nombresPaciente = str_replace(' ', '%', $this->nombresPaciente);
            }
        }

        //Max row to fetch
        if ($this->length == null) {
            throw new ModelsException($config['errors']['lengthObligatorio']['message'], 1);
        } else {
            if ($this->length <= 0) {
                throw new ModelsException($config['errors']['lengthIncorrecto']['message'], 1);
            }
        }

        //Min row to fetch
        if ($this->start == null) {
            throw new ModelsException($config['errors']['startObligatorio']['message'], 1);
        } else {
            if ($this->start < 0) {
                throw new ModelsException($config['errors']['startIncorrecto']['message'], 1);
            }
        }

        //Código de tipo de horario
        if ($this->tipoHorario == null) {
            throw new ModelsException($config['errors']['tipoHorarioObligatorio']['message'], 1);
        } else {
            if ($this->tipoHorario < 0) {
                throw new ModelsException($config['errors']['tipoHorarioIncorrecto']['message'], 1);
            }
        }
    }

    /**
     * Valida los parámetros de entrada
     */
    private function validarParametrosConsultaCitasPacientePendientes()
    {
        global $config;

        //Código del médico
        if ($this->codigoMedico == null) {
            throw new ModelsException($config['errors']['codigoMedicoObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoMedico)) {
                throw new ModelsException($config['errors']['codigoMedicoNumerico']['message'], 1);
            }
        }

        //Fecha de fin
        if ($this->endDate == null) {
            throw new ModelsException($config['errors']['endDateObligatorio']['message'], 1);
        } else {
            $endDate = $this->endDate;

            $sd = new DateTime();
            $ed = new DateTime($endDate);

            if ($sd->getTimestamp() > $ed->getTimestamp()) {
                throw new ModelsException($config['errors']['endDateIncorrectaFechaHoy']['message'], 1);
            }
        }

        //Max row to fetch
        if ($this->length == null) {
            throw new ModelsException($config['errors']['lengthObligatorio']['message'], 1);
        } else {
            if ($this->length <= 0) {
                throw new ModelsException($config['errors']['lengthIncorrecto']['message'], 1);
            }
        }

        //Min row to fetch
        if ($this->start == null) {
            throw new ModelsException($config['errors']['startObligatorio']['message'], 1);
        } else {
            if ($this->start < 0) {
                throw new ModelsException($config['errors']['startIncorrecto']['message'], 1);
            }
        }

        //Código de tipo de horario
        if ($this->tipoHorario == null) {
            throw new ModelsException($config['errors']['tipoHorarioObligatorio']['message'], 1);
        } else {
            if ($this->tipoHorario < 0) {
                throw new ModelsException($config['errors']['tipoHorarioIncorrecto']['message'], 1);
            }
        }
    }

    /**
     * Valida los parámetros de entrada
     */
    private function validarParametrosPorNombres()
    {
        global $config;

        //Código del médico
        if ($this->codigoMedico == null) {
            throw new ModelsException($config['errors']['codigoMedicoObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoMedico)) {
                throw new ModelsException($config['errors']['codigoMedicoNumerico']['message'], 1);
            }
        }

        //Nombres del paciente
        if ($this->nombresPaciente == null) {
            $this->nombresPaciente = "%";
        } else {
            $this->nombresPaciente = $this->quitar_tildes(mb_strtoupper($this->sanear_string($this->nombresPaciente), 'UTF-8'));

            # Setear valores para busquedas dividadas
            if (stripos($this->nombresPaciente, ' ')) {
                $this->nombresPaciente = str_replace(' ', '%', $this->nombresPaciente);
            }
        }

        //Max row to fetch
        if ($this->length == null) {
            throw new ModelsException($config['errors']['lengthObligatorio']['message'], 1);
        } else {
            if ($this->length <= 0) {
                throw new ModelsException($config['errors']['lengthIncorrecto']['message'], 1);
            }
        }

        //Min row to fetch
        if ($this->start == null) {
            throw new ModelsException($config['errors']['startObligatorio']['message'], 1);
        } else {
            if ($this->start < 0) {
                throw new ModelsException($config['errors']['startIncorrecto']['message'], 1);
            }
        }

        //Código de tipo de horario
        if ($this->tipoHorario == null) {
            throw new ModelsException($config['errors']['tipoHorarioObligatorio']['message'], 1);
        } else {
            if ($this->tipoHorario < 0) {
                throw new ModelsException($config['errors']['tipoHorarioIncorrecto']['message'], 1);
            }
        }
    }

    /**
     * Valida los parámetros de entrada
     */
    private function validarParametrosCrearAgenda()
    {
        global $config;

        //Código del médico
        if ($this->codigoMedico == null) {
            throw new ModelsException($config['errors']['codigoMedicoObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoMedico)) {
                throw new ModelsException($config['errors']['codigoMedicoNumerico']['message'], 1);
            }
        }

        //Código del organigrama
        if ($this->codigoOrganigrama == null) {
            throw new ModelsException($config['errors']['codigoOrganigramaObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->codigoOrganigrama)) {
                throw new ModelsException($config['errors']['codigoOrganigramaNumerico']['message'], 1);
            }
        }

        //Fecha de inicio
        if ($this->fechaInicial == null) {
            throw new ModelsException($config['errors']['fechaInicialObligatorio']['message'], 1);
        } else {
            //La fecha inicial no puede ser menor que hoy
            $fechaInicial = $this->fechaInicial;
            $sd = new DateTime($fechaInicial);
            $fechaHoy = new DateTime();

            if ($sd->getTimestamp() < $fechaHoy->getTimestamp()) {
                throw new ModelsException($config['errors']['startDateMenorFechaHoy']['message'], 1);
            }

            if ($this->fechaFinal != null) {

                //$fechaInicial = $this->fechaInicial;
                $fechaFinal = $this->fechaFinal;

                //$sd = new DateTime($fechaInicial);
                $ed = new DateTime($fechaFinal);

                if ($sd->getTimestamp() > $ed->getTimestamp()) {
                    throw new ModelsException($config['errors']['fechaInicialIncorrecta']['message'], 1);
                }
            }
        }

        //Fecha final
        if ($this->fechaFinal == null) {
            throw new ModelsException($config['errors']['fechaFinalObligatorio']['message'], 1);
        }

        //Hora de inicio
        if ($this->horaInicial == null) {
            throw new ModelsException($config['errors']['horaInicialObligatorio']['message'], 1);
        } else {
            /*if ($this->horaFinal != null) {

        $horaInicial = $this->horaInicial;
        $horaFinal   = $this->horaFinal;

        $sd = new DateTime($horaInicial);
        $ed = new DateTime($horaFinal);

        if ($sd->getTimestamp() > $ed->getTimestamp()) {
        throw new ModelsException($config['errors']['horaInicialIncorrecta']['message'], 1);
        }
        }*/
        }

        //Hora final
        if ($this->horaFinal == null) {
            throw new ModelsException($config['errors']['horaFinalObligatorio']['message'], 1);
        }

        //Duración
        if ($this->duracion == null) {
            throw new ModelsException($config['errors']['duracionObligatorio']['message'], 1);
        }

        //Lunes
        if ($this->lunes == null) {
            throw new ModelsException($config['errors']['lunesObligatorio']['message'], 1);
        }

        //Martes
        if ($this->martes == null) {
            throw new ModelsException($config['errors']['martesObligatorio']['message'], 1);
        }

        //Miércoles
        if ($this->miercoles == null) {
            throw new ModelsException($config['errors']['miercolesObligatorio']['message'], 1);
        }

        //Jueves
        if ($this->jueves == null) {
            throw new ModelsException($config['errors']['juevesObligatorio']['message'], 1);
        }

        //Viernes
        if ($this->viernes == null) {
            throw new ModelsException($config['errors']['viernesObligatorio']['message'], 1);
        }

        //Sabado
        if ($this->sabado == null) {
            throw new ModelsException($config['errors']['sabadoObligatorio']['message'], 1);
        }

        //Domingo
        if ($this->domingo == null) {
            throw new ModelsException($config['errors']['domingoObligatorio']['message'], 1);
        }
    }

    private function setSpanishOracle($stid)
    {

        $sql = "alter session set NLS_LANGUAGE = 'SPANISH'";
        # Execute
        $stid = oci_parse($this->conexion->getConexion(), $sql);
        oci_execute($stid);

        $sql = "alter session set NLS_TERRITORY = 'SPAIN'";
        # Execute
        $stid = oci_parse($this->conexion->getConexion(), $sql);
        oci_execute($stid);

        $sql = " alter session set NLS_DATE_FORMAT = 'DD-MM-YYYY HH24:MI'";
        # Execute
        $stid = oci_parse($this->conexion->getConexion(), $sql);
        oci_execute($stid);

    }

    /**
     * Obtiene los datos del médico
     */
    public function obtenerDatosMedico()
    {
        global $config;

        //Inicialización de variables
        $stid = null;
        $pc_datos = null;
        $existeDatos = false;
        $datosMedico[] = null;

        try {
            //Asignar parámetros de entrada
            $this->setParameters();

            //Validar parámetros de entrada
            //Código del médico
            if ($this->codigoMedico == null) {
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

            $stid = oci_parse($this->conexion->getConexion(), "BEGIN PRO_TEL_DATOS_MEDICO(:pc_cod_medico, :pc_datos); END;");

            // Bind the input num_entries argument to the $max_entries PHP variable
            oci_bind_by_name($stid, ":pc_cod_medico", $this->codigoMedico, 32);
            oci_bind_by_name($stid, ":pc_datos", $pc_datos, -1, OCI_B_CURSOR);

            //Ejecuta el SP
            oci_execute($stid);

            //Ejecutar el REF CURSOR como un ide de sentencia normal
            oci_execute($pc_datos);

            //Resultados de la consulta
            $datosMedico = array();

            while (($row = oci_fetch_array($pc_datos, OCI_BOTH)) != false) {
                $existeDatos = true;

                if ($this->codigoMedico == 438) {

                    # RESULTADO OBJETO
                    $datosMedico[] = array(
                        'nombres' => $row[0],
                        'especialidad' => $row[1],
                        'tiposConsulta' => $this->obtenerTiposHorario($this->codigoMedico, $stid),
                        'pagoTrasferencia' => true,
                        'messageWhatsapp' => 'Hola prueba mensaje',
                        'numberWhatsapp' => '+593998785402',
                    );

                } else {
                    # RESULTADO OBJETO
                    $datosMedico[] = array(
                        'nombres' => $row[0],
                        'especialidad' => $row[1],
                        'tiposConsulta' => $this->obtenerTiposHorario($this->codigoMedico, $stid),
                        'pagoTrasferencia' => false,
                        'messageWhatsapp' => '',
                        'numberWhatsapp' => '',
                    );

                }

            }

            //Verificar si la consulta devolvió datos
            if ($existeDatos) {
                return array(
                    'status' => true,
                    'data' => $datosMedico,
                );
            } else {
                throw new ModelsException($config['errors']['noExistenResultados']['message'], 1);
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
                'errorCode' => -1
            );

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
     * Obtiene los tipos de horarios asociados al médico
     */
    public function obtenerTiposHorario($codigoMedico, $stid)
    {
        global $config;

        //Inicialización de variables
        $pc_datos = null;
        $existeDatos = false;
        $tiposHorario[] = null;

        try {
            $pc_datos = oci_new_cursor($this->conexion->getConexion());

            $stid = oci_parse($this->conexion->getConexion(), "BEGIN PRO_TEL_TIP_HOR_MED(:pc_cod_medico, :pc_datos); END;");

            // Bind the input num_entries argument to the $max_entries PHP variable
            oci_bind_by_name($stid, ":pc_cod_medico", $codigoMedico, 32);
            oci_bind_by_name($stid, ":pc_datos", $pc_datos, -1, OCI_B_CURSOR);

            //Ejecuta el SP
            oci_execute($stid);

            //Ejecutar el REF CURSOR como un ide de sentencia normal
            oci_execute($pc_datos);

            //Resultados de la consulta
            $tiposHorario = array();

            while (($row = oci_fetch_array($pc_datos, OCI_BOTH)) != false) {
                $existeDatos = true;

                # RESULTADO OBJETO
                $tiposHorario[] = array(
                    'codigoTipoConsulta' => $row[0],
                    'descripcionTipoConsulta' => $row[1],
                    'valorConsulta' => $row[2],
                );

            }

            //Verificar si la consulta devolvió datos
            if ($existeDatos) {
                return $tiposHorario;
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
     * Obtiene las citas disponibles de un médico
     */
    public function obtenerCitasDisponibles()
    {
        global $config;

        //Inicialización de variables
        $stid = null;
        $pc_datos = null;
        $existeDatos = false;
        $citasDisponibles[] = null;

        try {
            //Asignar parámetros de entrada
            $this->setParameters();

            //Validar parámetros de entrada
            $this->validarParametrosConsultaCitasDisponibles();

            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stid);

            $pc_datos = oci_new_cursor($this->conexion->getConexion());

            $stid = oci_parse($this->conexion->getConexion(), "BEGIN PRO_TEL_AGENDAS_DISP(:pc_cod_medico, :pc_tip_horario, :pc_fec_ini, :pc_fec_fin,:pn_num_reg, :pn_num_pag, :pc_datos); END;");

            // Bind the input num_entries argument to the $max_entries PHP variable
            oci_bind_by_name($stid, ":pc_cod_medico", $this->codigoMedico, 32);
            oci_bind_by_name($stid, ":pc_tip_horario", $this->tipoHorario, 32);
            oci_bind_by_name($stid, ":pc_fec_ini", $this->startDate, 32);
            oci_bind_by_name($stid, ":pc_fec_fin", $this->endDate, 32);
            oci_bind_by_name($stid, ":pn_num_reg", $this->length, 32);
            oci_bind_by_name($stid, ":pn_num_pag", $this->start, 32);
            oci_bind_by_name($stid, ":pc_datos", $pc_datos, -1, OCI_B_CURSOR);

            //Ejecuta el SP
            oci_execute($stid);

            //Ejecutar el REF CURSOR como un ide de sentencia normal
            oci_execute($pc_datos);

            //Resultados de la consulta
            $citasDisponibles = array();

            while (($row = oci_fetch_array($pc_datos, OCI_BOTH)) != false) {
                $existeDatos = true;

                # RESULTADO OBJETO
                $citasDisponibles[] = array(
                    'codigoHorario' => $row[0],
                    'numeroTurno' => $row[1],
                    'fecha' => $row[2],
                    'hora' => $row[3],
                    'duracion' => $row[4],
                );

            }

            //Verificar si la consulta devolvió datos
            if ($existeDatos) {
                return array(
                    'status' => true,
                    'data' => $citasDisponibles,
                );
            } else {
                throw new ModelsException($config['errors']['noExistenResultados']['message'], 1);
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
                'errorCode' => -1
            );

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
     * Consulta de las citas pasadas del médico
     */
    public function consultarCitasPacientePasadas()
    {
        global $config;

        //Inicialización de variables
        $stid = null;
        $pc_datos = null;
        $existeDatos = false;
        $agendaPasadaMedico[] = null;

        try {
            //Asignar parámetros de entrada
            $this->setParameters();

            //Validar parámetros de entrada
            $this->validarParametrosConsultaCitasPacientePasadas();

            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stid);

            $pc_datos = oci_new_cursor($this->conexion->getConexion());

            $stid = oci_parse($this->conexion->getConexion(), "BEGIN PRO_TEL_AGENDAS_MEDICO_PAS(:pc_cod_medico, :pn_tip_horario, :pc_fec_ini, :pn_num_reg, :pn_num_pag, :pc_datos); END;");

            // Bind the input num_entries argument to the $max_entries PHP variable
            oci_bind_by_name($stid, ":pc_cod_medico", $this->codigoMedico, 32);
            oci_bind_by_name($stid, ":pn_tip_horario", $this->tipoHorario, 32);
            oci_bind_by_name($stid, ":pc_fec_ini", $this->startDate, 32);
            oci_bind_by_name($stid, ":pn_num_reg", $this->length, 32);
            oci_bind_by_name($stid, ":pn_num_pag", $this->start, 32);
            oci_bind_by_name($stid, ":pc_datos", $pc_datos, -1, OCI_B_CURSOR);

            //Ejecuta el SP
            oci_execute($stid);

            //Ejecutar el REF CURSOR como un ide de sentencia normal
            oci_execute($pc_datos);

            //Resultados de la consulta
            $agendaPasadaMedico = array();

            while (($row = oci_fetch_array($pc_datos, OCI_BOTH + OCI_RETURN_NULLS)) != false) {
                $existeDatos = true;

                # RESULTADO OBJETO
                $agendaPasadaMedico[] = array(
                    'codigoHorario' => $row[0] == null ? '' : $row[0],
                    'numeroTurno' => $row[1] == null ? '' : $row[1],
                    'fecha' => $row[2] == null ? '' : $row[2],
                    'horaInicio' => $row[3] == null ? '' : $row[3],
                    'horaFin' => $row[4] == null ? '' : $row[4],
                    'primerApellidoPaciente' => $row[5] == null ? '' : $row[5],
                    'segundoApellidoPaciente' => $row[6] == null ? '' : $row[6],
                    'primerNombrePaciente' => $row[7] == null ? '' : $row[7],
                    'segundoNombrePaciente' => $row[8] == null ? '' : $row[8],
                    'codigoPersonaPaciente' => $row[9] == null ? '' : $row[9],
                    'numeroHistoriaClinica' => $row[10] == null ? '' : $row[10],
                    'asistio' => $row[11] == null ? '' : $row[11],
                    'numeroAdmision' => $row[12] == null ? '' : $row[12],
                );
            }

            //Verificar si la consulta devolvió datos
            if ($existeDatos) {
                return array(
                    'status' => true,
                    'data' => $agendaPasadaMedico,
                    'start' => $this->start,
                    'length' => $this->length,
                );
            } else {
                throw new ModelsException($config['errors']['noExistenResultados']['message'], 1);
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
                'errorCode' => -1
            );

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
     * Consulta de las citas pasadas del médico por nombres
     */
    public function consultarCitasPacientePasadasPorNombres()
    {
        global $config;

        //Inicialización de variables
        $stid = null;
        $pc_datos = null;
        $existeDatos = false;
        $agendaPasadaMedicoNombres[] = null;

        try {
            //Asignar parámetros de entrada
            $this->setParameters();

            //Validar parámetros de entrada
            $this->validarParametrosConsultaCitasPacientePasadasPorNombres();

            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stid);

            $pc_datos = oci_new_cursor($this->conexion->getConexion());

            $stid = oci_parse($this->conexion->getConexion(), "BEGIN PRO_TEL_AGEND_MED_PAS_NOMB(:pc_cod_medico, :pn_tip_horario, :pc_nombres, :pc_fec_ini, :pn_num_reg, :pn_num_pag, :pc_datos); END;");

            // Bind the input num_entries argument to the $max_entries PHP variable
            oci_bind_by_name($stid, ":pc_cod_medico", $this->codigoMedico, 32);
            oci_bind_by_name($stid, ":pn_tip_horario", $this->tipoHorario, 32);
            oci_bind_by_name($stid, ":pc_nombres", $this->nombresPaciente, 83);
            oci_bind_by_name($stid, ":pc_fec_ini", $this->startDate, 32);
            oci_bind_by_name($stid, ":pn_num_reg", $this->length, 32);
            oci_bind_by_name($stid, ":pn_num_pag", $this->start, 32);
            oci_bind_by_name($stid, ":pc_datos", $pc_datos, -1, OCI_B_CURSOR);

            //Ejecuta el SP
            oci_execute($stid);

            //Ejecutar el REF CURSOR como un ide de sentencia normal
            oci_execute($pc_datos);

            //Resultados de la consulta
            $agendaPasadaMedicoNombres = array();

            while (($row = oci_fetch_array($pc_datos, OCI_BOTH + OCI_RETURN_NULLS)) != false) {
                $existeDatos = true;

                # RESULTADO OBJETO
                $agendaPasadaMedicoNombres[] = array(
                    'codigoHorario' => $row[0] == null ? '' : $row[0],
                    'numeroTurno' => $row[1] == null ? '' : $row[1],
                    'fecha' => $row[2] == null ? '' : $row[2],
                    'horaInicio' => $row[3] == null ? '' : $row[3],
                    'horaFin' => $row[4] == null ? '' : $row[4],
                    'primerApellidoPaciente' => $row[5] == null ? '' : $row[5],
                    'segundoApellidoPaciente' => $row[6] == null ? '' : $row[6],
                    'primerNombrePaciente' => $row[7] == null ? '' : $row[7],
                    'segundoNombrePaciente' => $row[8] == null ? '' : $row[8],
                    'codigoPersonaPaciente' => $row[9] == null ? '' : $row[9],
                    'numeroHistoriaClinica' => $row[10] == null ? '' : $row[10],
                    'asistio' => $row[11] == null ? '' : $row[11],
                    'numeroAdmision' => $row[12] == null ? '' : $row[12],
                );
            }

            //Verificar si la consulta devolvió datos
            if ($existeDatos) {
                return array(
                    'status' => true,
                    'data' => $agendaPasadaMedicoNombres,
                    'start' => $this->start,
                    'length' => $this->length,
                );
            } else {
                throw new ModelsException($config['errors']['noExistenResultados']['message'], 1);
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
                'errorCode' => -1
            );

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
     * Consulta de las citas pendientes del médico.
     */
    public function consultarCitasPacientePendientes()
    {
        global $config, $http;

        //Inicialización de variables
        $stid = null;
        $pc_datos = null;
        $existeDatos = false;
        $agendaPendienteMedico[] = null;

        try {
            //Asignar parámetros de entrada
            $this->setParameters();

            //Validar parámetros de entrada
            $this->validarParametrosConsultaCitasPacientePendientes();

            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stid);

            $pc_datos = oci_new_cursor($this->conexion->getConexion());

            $stid = oci_parse($this->conexion->getConexion(), "BEGIN PRO_TEL_AGENDAS_MEDICO_PEN(:pc_cod_medico, :pn_tip_horario, :pc_fec_fin, :pn_num_reg, :pn_num_pag, :pc_datos); END;");

            // Bind the input num_entries argument to the $max_entries PHP variable
            oci_bind_by_name($stid, ":pc_cod_medico", $this->codigoMedico, 32);
            oci_bind_by_name($stid, ":pn_tip_horario", $this->tipoHorario, 32);
            oci_bind_by_name($stid, ":pc_fec_fin", $this->endDate, 32);
            oci_bind_by_name($stid, ":pn_num_reg", $this->length, 32);
            oci_bind_by_name($stid, ":pn_num_pag", $this->start, 32);
            oci_bind_by_name($stid, ":pc_datos", $pc_datos, -1, OCI_B_CURSOR);

            //Ejecuta el SP
            oci_execute($stid);

            //Ejecutar el REF CURSOR como un ide de sentencia normal
            oci_execute($pc_datos);

            //Resultados de la consulta
            $agendaPendienteMedico = array();

            while (($row = oci_fetch_array($pc_datos, OCI_BOTH + OCI_RETURN_NULLS)) != false) {
                $existeDatos = true;

                # RESULTADO OBJETO
                $agendaPendienteMedico[] = array(
                    'codigoHorario' => $row[0] == null ? '' : $row[0],
                    'numeroTurno' => $row[1] == null ? '' : $row[1],
                    'fecha' => $row[2] == null ? '' : $row[2],
                    'horaInicio' => $row[3] == null ? '' : $row[3],
                    'horaFin' => $row[4] == null ? '' : $row[4],
                    'primerApellidoPaciente' => $row[5] == null ? '' : $row[5],
                    'segundoApellidoPaciente' => $row[6] == null ? '' : $row[6],
                    'primerNombrePaciente' => $row[7] == null ? '' : $row[7],
                    'segundoNombrePaciente' => $row[8] == null ? '' : $row[8],
                    'codigoPersonaPaciente' => $row[9] == null ? '' : $row[9],
                    'numeroHistoriaClinica' => $row[10] == null ? '' : $row[10],
                    'numeroAdmision' => $row[11] == null ? '' : $row[11],
                    'asistio' => $row[12] == null ? '' : $row[12],
                );

            }

            //Verificar si la consulta devolvió datos
            if ($existeDatos) {
                return array(
                    'status' => true,
                    'data' => $agendaPendienteMedico,
                    'start' => $this->start,
                    'length' => $this->length,
                );
            } else {
                throw new ModelsException($config['errors']['noExistenResultados']['message'], 1);
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
                'errorCode' => -1
            );

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
     * Consulta la agenda del médico
     */
    public function consultarAgendaPorNombres()
    {
        global $config;

        //Inicialización de variables
        $stid = null;
        $pc_datos = null;
        $existeDatos = false;
        $agendaMedico[] = null;

        try {
            //Asignar parámetros de entrada
            $this->setParameters();

            //Validar parámetros de entrada
            $this->validarParametrosPorNombres();

            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stid);

            $pc_datos = oci_new_cursor($this->conexion->getConexion());

            $stid = oci_parse($this->conexion->getConexion(), "BEGIN PRO_TEL_AGENDAS_MEDICO_NOMBRES(:pc_cod_medico, :pn_tip_horario, :pc_nombres, :pn_num_reg, :pn_num_pag, :pc_datos); END;");

            // Bind the input num_entries argument to the $max_entries PHP variable
            oci_bind_by_name($stid, ":pc_cod_medico", $this->codigoMedico, 32);
            oci_bind_by_name($stid, ":pn_tip_horario", $this->tipoHorario, 32);
            oci_bind_by_name($stid, ":pc_nombres", $this->nombresPaciente, 83);
            oci_bind_by_name($stid, ":pn_num_reg", $this->length, 32);
            oci_bind_by_name($stid, ":pn_num_pag", $this->start, 32);
            oci_bind_by_name($stid, ":pc_datos", $pc_datos, -1, OCI_B_CURSOR);

            //Ejecuta el SP
            oci_execute($stid);

            //Ejecutar el REF CURSOR como un ide de sentencia normal
            oci_execute($pc_datos);

            //Resultados de la consulta
            $agendaMedico = array();

            while (($row = oci_fetch_array($pc_datos, OCI_BOTH + OCI_RETURN_NULLS)) != false) {
                $existeDatos = true;

                # RESULTADO OBJETO
                $agendaMedico[] = array(
                    'codigoHorario' => $row[0] == null ? '' : $row[0],
                    'numeroTurno' => $row[1] == null ? '' : $row[1],
                    'fecha' => $row[2] == null ? '' : $row[2],
                    'horaInicio' => $row[3] == null ? '' : $row[3],
                    'horaFin' => $row[4] == null ? '' : $row[4],
                    'primerApellidoPaciente' => $row[5] == null ? '' : $row[5],
                    'segundoApellidoPaciente' => $row[6] == null ? '' : $row[6],
                    'primerNombrePaciente' => $row[7] == null ? '' : $row[7],
                    'segundoNombrePaciente' => $row[8] == null ? '' : $row[8],
                    'codigoPersonaPaciente' => $row[9] == null ? '' : $row[9],
                    'numeroHistoriaClinica' => $row[10] == null ? '' : $row[10],
                    'numeroAdmision' => $row[11] == null ? '' : $row[11],
                    'asistio' => $row[12] == null ? '' : $row[12],
                );

            }

            //Verificar si la consulta devolvió datos
            if ($existeDatos) {
                return array(
                    'status' => true,
                    'data' => $agendaMedico,
                    'start' => $this->start,
                    'length' => $this->length,
                );
            } else {
                throw new ModelsException($config['errors']['noExistenResultados']['message'], 1);
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
                'errorCode' => -1
            );

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
     * Crear agenda del médico
     */
    public function crearAgenda()
    {
        global $config;

        //Inicialización de variables
        $stid = null;
        $pc_datos = null;
        $existeDatos = false;
        $agendaMedico[] = null;

        try {
            //Asignar parámetros de entrada
            $this->setParameters();

            //Validar parámetros de entrada
            $this->validarParametrosCrearAgenda();

            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma a inglés por el manejo de fecha en el SP
            $sql = "ALTER SESSION SET NLS_LANGUAGE = 'AMERICAN'";
            # Execute
            $stid = oci_parse($this->conexion->getConexion(), $sql);
            oci_execute($stid);

            $stid = oci_parse($this->conexion->getConexion(), "BEGIN PRO_TEL_CREA_AGENDA_MEDICO(:pn_institucion, :pd_fecha_inicial, :pd_fecha_final, :pd_hora_inicial,:pd_hora_final, :pd_duracion, :pn_cod_organigrama, :pn_cod_medico, :pc_lunes, :pc_martes, :pc_miercoles, :pc_jueves, :pc_viernes, :pc_sabado, :pc_domingo, :pn_cod_horario, :pn_retorno, :pc_mensaje); END;");

            // Bind the input num_entries argument to the $max_entries PHP variable
            oci_bind_by_name($stid, ":pn_institucion", $this->codigoInstitucion, 32);
            oci_bind_by_name($stid, ":pd_fecha_inicial", $this->fechaInicial, 32);
            oci_bind_by_name($stid, ":pd_fecha_final", $this->fechaFinal, 32);
            oci_bind_by_name($stid, ":pd_hora_inicial", $this->horaInicial, 32);
            oci_bind_by_name($stid, ":pd_hora_final", $this->horaFinal, 32);
            oci_bind_by_name($stid, ":pd_duracion", $this->duracion, 32);
            oci_bind_by_name($stid, ":pn_cod_organigrama", $this->codigoOrganigrama, 32);
            oci_bind_by_name($stid, ":pn_cod_medico", $this->codigoMedico, 32);
            oci_bind_by_name($stid, ":pc_lunes", $this->lunes, 32);
            oci_bind_by_name($stid, ":pc_martes", $this->martes, 32);
            oci_bind_by_name($stid, ":pc_miercoles", $this->miercoles, 32);
            oci_bind_by_name($stid, ":pc_jueves", $this->jueves, 32);
            oci_bind_by_name($stid, ":pc_viernes", $this->viernes, 32);
            oci_bind_by_name($stid, ":pc_sabado", $this->sabado, 32);
            oci_bind_by_name($stid, ":pc_domingo", $this->domingo, 32);

            // Bind the output parameter
            oci_bind_by_name($stid, ":pn_cod_horario", $this->codigoHorario, 32);
            oci_bind_by_name($stid, ':pn_retorno', $codigoRetorno, 32);
            oci_bind_by_name($stid, ':pc_mensaje', $mensajeRetorno, 500);

            //Ejecuta el SP
            oci_execute($stid);

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
                throw new Exception($mensajeRetorno, $codigoRetorno);
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
                'errorCode' => -1
            );

        } finally {
            //Libera recursos de conexión
            if ($stid != null) {
                oci_free_statement($stid);
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
