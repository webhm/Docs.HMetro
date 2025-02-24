<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.

SELECT
TO_CHAR(A_US_APPOINTMENT.APPOINTMENT_START , 'DD/MM/YYYY hh24:mi') AS FECHA_EXAMEN,
A_US_PATIENT_BASIC.PAT_LAST_NAME AS APELLIDOS,
A_US_PATIENT_BASIC.PAT_FIRST_NAME AS NOMBRES,
A_US_PATIENT_BASIC.PAT_TELEPHONE AS TELEFONO,
A_US_PATIENT_BASIC.PAT_TELEPHONE_MOBILE AS CELULAR,
A_US_APPOINTMENT.APPOINTMENT_BOOK AS AREA
FROM MEDORA.A_US_APPOINTMENT A_US_APPOINTMENT, MEDORA.A_US_PATIENT_BASIC A_US_PATIENT_BASIC
WHERE A_US_APPOINTMENT.PATIENT_KEY = A_US_PATIENT_BASIC.PATIENT_KEY
AND to_char(A_US_APPOINTMENT.APPOINTMENT_START, 'DD/MM/YYYY hh24:mi:ss')>= '26/06/2019 00:00:00'
AND to_char(A_US_APPOINTMENT.APPOINTMENT_START, 'DD/MM/YYYY hh24:mi:ss')<= '26/06/2019 23:59:59'
AND (A_US_APPOINTMENT.APPOINTMENT_BOOK LIKE 'AE%' OR A_US_APPOINTMENT.APPOINTMENT_BOOK LIKE 'HM%')

 */

namespace app\models\hm\tasks;

use app\models\hm\tasks as Model;
use DateTime;
use Doctrine\DBAL\DriverManager;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Models\Traits\DBModel;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo SMSToImagen
 */

class SMSToImagen extends Models implements IModels
{
    # Variables de clase
    use DBModel;

    private $sortField                   = 'ROWNUM';
    private $sortType                    = 'desc'; # desc
    private $offset                      = 1;
    private $limit                       = 25;
    private $mensaje_recordatorio_imagen = 'Le recordamos su cita el %dia% a las %hora%. En %area%. Gracias por preferirnos. Hospital Metropolitano. ';

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
//..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['medora'], $_config);

    }

    public function getCitasImagenCC()
    {

        try {

            # verificar si es viernes

            if ($this->getDay(date('l')) != 'Viernes'
                && $this->getDay(date('l')) != 'Sabado'
                && $this->getDay(date('l')) != 'Domingo'
            ) {

                $fecha = date('m-d-Y');

                #SETEAR FILTRO sumar un dia
                $fecha = DateTime::createFromFormat('m-d-Y', $fecha)->modify('+1 day')->format('d/m/Y');

                # CONULTA BDD GEMA
                $sql = " SELECT
                TO_CHAR(A_US_APPOINTMENT.APPOINTMENT_START , 'Day') AS DIA,
                TO_CHAR(A_US_APPOINTMENT.APPOINTMENT_START , 'DD/MM/YYYY') AS FECHA,
                TO_CHAR(A_US_APPOINTMENT.APPOINTMENT_START , 'hh24:mi') AS HORA,
                A_US_PATIENT_BASIC.PAT_LAST_NAME AS APELLIDOS,
                A_US_PATIENT_BASIC.PAT_FIRST_NAME AS NOMBRES,
                A_US_PATIENT_BASIC.PAT_TELEPHONE AS TELEFONO,
                A_US_PATIENT_BASIC.PAT_TELEPHONE_MOBILE AS CELULAR,
                A_US_APPOINTMENT.APPOINTMENT_BOOK AS AREA
                FROM MEDORA.A_US_APPOINTMENT A_US_APPOINTMENT, MEDORA.A_US_PATIENT_BASIC A_US_PATIENT_BASIC
                WHERE A_US_APPOINTMENT.PATIENT_KEY = A_US_PATIENT_BASIC.PATIENT_KEY
                AND to_char(A_US_APPOINTMENT.APPOINTMENT_START, 'DD/MM/YYYY hh24:mi:ss') >= '" . $fecha . " 00:00:00'
                AND to_char(A_US_APPOINTMENT.APPOINTMENT_START, 'DD/MM/YYYY hh24:mi:ss') <= '" . $fecha . " 23:59:59'
                AND (A_US_APPOINTMENT.APPOINTMENT_BOOK LIKE 'AE%' OR A_US_APPOINTMENT.APPOINTMENT_BOOK LIKE 'HM%') ";

                # Conectar base de datos
                $this->conectar_Oracle();

                # Execute
                $stmt = $this->_conexion->query($sql);

                # Cerrar conexion
                $this->_conexion->close();

                # Datos
                $data = $stmt->fetch();

            } else {

                $fecha = date('m-d-Y');

                #SETEAR FILTRO sumar un dia
                $sabado = DateTime::createFromFormat('m-d-Y', $fecha)->modify('+1 day')->format('d/m/Y');

                #SETEAR FILTRO sumar 3 dia hasta le lunes
                $lunes = DateTime::createFromFormat('m-d-Y', $fecha)->modify('+3 day')->format('d/m/Y');

                # CONULTA BDD GEMA
                $sql = " SELECT
                TO_CHAR(A_US_APPOINTMENT.APPOINTMENT_START , 'Day') AS DIA,
                TO_CHAR(A_US_APPOINTMENT.APPOINTMENT_START , 'DD/MM/YYYY') AS FECHA,
                TO_CHAR(A_US_APPOINTMENT.APPOINTMENT_START , 'hh24:mi') AS HORA,
                A_US_PATIENT_BASIC.PAT_LAST_NAME AS APELLIDOS,
                A_US_PATIENT_BASIC.PAT_FIRST_NAME AS NOMBRES,
                A_US_PATIENT_BASIC.PAT_TELEPHONE AS TELEFONO,
                A_US_PATIENT_BASIC.PAT_TELEPHONE_MOBILE AS CELULAR,
                A_US_APPOINTMENT.APPOINTMENT_BOOK AS AREA
                FROM MEDORA.A_US_APPOINTMENT A_US_APPOINTMENT, MEDORA.A_US_PATIENT_BASIC A_US_PATIENT_BASIC
                WHERE A_US_APPOINTMENT.PATIENT_KEY = A_US_PATIENT_BASIC.PATIENT_KEY
                AND to_char(A_US_APPOINTMENT.APPOINTMENT_START, 'DD/MM/YYYY hh24:mi:ss')>= '" . $sabado . " 00:00:00'
                AND to_char(A_US_APPOINTMENT.APPOINTMENT_START, 'DD/MM/YYYY hh24:mi:ss')<= '" . $sabado . " 23:59:59'
                AND (A_US_APPOINTMENT.APPOINTMENT_BOOK LIKE 'AE%' OR A_US_APPOINTMENT.APPOINTMENT_BOOK LIKE 'HM%') ";

                # Conectar base de datos
                $this->conectar_Oracle();

                # Execute
                $stmt = $this->_conexion->query($sql);

                # Cerrar conexion
                $this->_conexion->close();

                # Datos
                $data_sabado = $stmt->fetch();

                # CONULTA BDD GEMA
                $sql = " SELECT
                TO_CHAR(A_US_APPOINTMENT.APPOINTMENT_START , 'Day') AS DIA,
                TO_CHAR(A_US_APPOINTMENT.APPOINTMENT_START , 'DD/MM/YYYY') AS FECHA,
                TO_CHAR(A_US_APPOINTMENT.APPOINTMENT_START , 'hh24:mi') AS HORA,
                A_US_PATIENT_BASIC.PAT_LAST_NAME AS APELLIDOS,
                A_US_PATIENT_BASIC.PAT_FIRST_NAME AS NOMBRES,
                A_US_PATIENT_BASIC.PAT_TELEPHONE AS TELEFONO,
                A_US_PATIENT_BASIC.PAT_TELEPHONE_MOBILE AS CELULAR,
                A_US_APPOINTMENT.APPOINTMENT_BOOK AS AREA
                FROM MEDORA.A_US_APPOINTMENT A_US_APPOINTMENT, MEDORA.A_US_PATIENT_BASIC A_US_PATIENT_BASIC
                WHERE A_US_APPOINTMENT.PATIENT_KEY = A_US_PATIENT_BASIC.PATIENT_KEY
                AND to_char(A_US_APPOINTMENT.APPOINTMENT_START, 'DD/MM/YYYY hh24:mi:ss')>= '" . $lunes . " 00:00:00'
                AND to_char(A_US_APPOINTMENT.APPOINTMENT_START, 'DD/MM/YYYY hh24:mi:ss')<= '" . $lunes . " 23:59:59'
                AND (A_US_APPOINTMENT.APPOINTMENT_BOOK LIKE 'AE%' OR A_US_APPOINTMENT.APPOINTMENT_BOOK LIKE 'HM%') ";

                # Conectar base de datos
                $this->conectar_Oracle();

                # Execute
                $stmt = $this->_conexion->query($sql);

                # Cerrar conexion
                $this->_conexion->close();

                # Datos
                $data_lunes = $stmt->fetch();

            }

            if ($this->getDay(date('l')) != 'Viernes' && $this->getDay(date('l')) != 'Sabado'
                && $this->getDay(date('l')) != 'Domingo') {

                if (false === $data) {
                    throw new ModelsException('No existe resultados.', 4080);
                }

                # Datos
                $data = $stmt->fetchAll();

            } else {

                if (false === $data_sabado && false === $data_lunes) {
                    throw new ModelsException('No existe resultados.', 4080);
                }

                # Datos
                $data_sabado = $stmt->fetchAll();

                # Datos
                $data_lunes = $stmt->fetchAll();

                # Datos
                $data = array_merge($data_sabado, $data_lunes);

            }

            # Dta de Citas
            $citas = array();

            foreach ($data as $key) {

                # SI CONTACTO NO ESTA DISPONIBLE EN CELULAR NI TELEFONO
                if (is_null($key['CELULAR']) && is_null($key['TELEFONO'])) {

                    $key['CONTACT'] = false;

                } else {

                    # SI CAMPO CELULAR o telefono ES IGUAL 10 DIGITOS Y  EMPIEZA CON 09
                    if (strlen(strstr($key['CELULAR'], '09')) == 10) {
                        $key['CONTACT'] = $key['CELULAR'];
                    } elseif (strlen(strstr($key['TELEFONO'], '09')) == 10) {
                        $key['CONTACT'] = $key['TELEFONO'];
                    } else {
                        $key['CONTACT'] = false;
                    }

                }

                # SETEAR AREA DE LA CITA
                if (strstr($key['AREA'], 'AE-')) {
                    $key['AREA_CITA'] = 'Imagen PB del Meditropoli';
                } elseif (strstr($key['AREA'], 'HM-RM')) {
                    $key['AREA_CITA'] = 'RM Subsuelo del Hospital';
                } else {
                    $key['AREA_CITA'] = 'Imagen PB del Hospital';
                }

                # Insertar en bdd -> TABLA TEMPRAL -> solo si no esta previamente registrado
                $query = $this->db->select('id',
                    'citas_imagen_task_sms',
                    null,
                    "
                    fecha_cita='" . $key['FECHA'] . "'
                    AND  hora_cita='" . $key['HORA'] . "'
                    AND nombres='" . $key['NOMBRES'] . "'
                    AND apellidos='" . $key['APELLIDOS'] . "'
                    AND area='" . $key['AREA'] . "'
                    ",
                    1);

                if (false === $query) {

                    # Registrar LA CITA
                    $this->db->insert('citas_imagen_task_sms', array(
                        'dia_cita'   => $this->getDay(trim($key['DIA'])),
                        'fecha_cita' => $key['FECHA'],
                        'hora_cita'  => $key['HORA'],
                        'nombres'    => $key['NOMBRES'],
                        'apellidos'  => $key['APELLIDOS'],
                        'telefono'   => $key['TELEFONO'],
                        'celular'    => $key['CELULAR'],
                        'area'       => $key['AREA'],
                        'area_cita'  => $key['AREA_CITA'],
                        'contact'    => $key['CONTACT'],
                        'status'     => 0,
                    ));

                }

                $citas[] = $key;
            }

            # Devolver Información
            return array(
                'status' => true,
                'data'   => $citas,
                'total'  => count($citas),
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function sendSMSToImagen()
    {

        try {

            # Solo si es hora de envio de sms
            if (date('H') >= '08' && date('H') <= '18') {

                $data = $this->db->select('*', 'citas_imagen_task_sms', null, "status='0'");

                $citas = array();

                $sms = new Model\SMSEmpresarial;

                foreach ($data as $key) {

                    $a              = array('%dia%', '%hora%', '%area%');
                    $b              = array($key['dia_cita'] . ' ' . $key['fecha_cita'], $key['hora_cita'], $key['area_cita']);
                    $mensaje        = str_replace($a, $b, $this->mensaje_recordatorio_imagen);
                    $key['mensaje'] = $mensaje;
                    $citas[]        = $key;

                    if ($key['contact'] != '') {

                        # Enviar Mensaje de texto a contacto
                        $res = $sms->enviarSMSToImagen($mensaje, $key['contact']);

                        if ($res['status'] == 1) {

                            # Actualizar registro con reporte de envio exitoso
                            $this->db->update('citas_imagen_task_sms', array(
                                'status'          => 1,
                                'timestamp_envio' => date('d-m-Y H:i'),
                                'logs'            => json_encode($res, JSON_UNESCAPED_UNICODE),
                            ), "id='" . $key['id'] . "'", 1);

                        } else {

                            # Actualizar registro con reporte de envio exitoso
                            $this->db->update('citas_imagen_task_sms', array(
                                'timestamp_envio' => date('d-m-Y H:i'),
                                'logs'            => json_encode($res, JSON_UNESCAPED_UNICODE),
                            ), "id='" . $key['id'] . "'", 1);

                        }

                    }

                }

                # Devolver Información
                #  $data = $this->db->select('*', 'citas_imagen_task_sms');

                return array(
                    'status' => true,
                    // 'data'   => $data,
                    // 'total'  => count($data),
                );

            } else {
                throw new ModelsException('No existe resultados. No es hora de envio de mensajes', 4080);
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    private function getDay($dia = '')
    {
        if ($dia == "Monday") {
            $dia = "Lunes";
        }

        if ($dia == "Tuesday") {
            $dia = "Martes";
        }

        if ($dia == "Wednesday") {
            $dia = "Miércoles";
        }

        if ($dia == "Thursday") {
            $dia = "Jueves";
        }

        if ($dia == "Friday") {
            $dia = "Viernes";
        }

        if ($dia == "Saturday") {
            $dia = "Sabado";
        }

        if ($dia == "Sunday") {
            $dia = "Domingo";
        }

        return $dia;

    }

/**
 * __construct()
 */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);
        $this->startDBConexion();

    }
}
