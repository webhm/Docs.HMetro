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
 * Modelo EmailToWeb
 */

class EmailToWeb extends Models implements IModels
{
    use DBModel;
    # Variables de clase
    private $sortField   = 'ROWNUM_';
    private $filterField = null;
    private $sortType    = 'desc'; # desc
    private $offset      = 1;
    private $limit       = 25;
    private $startDate   = null;
    private $endDate     = null;
    private $_conexion   = null;
    private $apikey      = 'ODk4LTIwNDgtaG9zcGl0YWxtZXRlYw2';
    private $username    = 'hospitalmetec';
    private $dia         = null;
    private $mes         = null;
    private $anio        = null;
    private $hora        = null;

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
//..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);

    }

    public function getPtesUsrsWebHM()
    {

        try {

            $desde = new DateTime();
            $desde->modify('-3 hours');

            $desde_dia  = $desde->format('d');
            $desde_mes  = $desde->format('m');
            $desde_anio = $desde->format('Y');
            $desde_hora = $desde->format('H');

            $hasta = new DateTime();
            $hasta->modify('-2 hours');

            $hasta_dia  = $hasta->format('d');
            $hasta_mes  = $hasta->format('m');
            $hasta_anio = $hasta->format('Y');
            $hasta_hora = $hasta->format('H');

            # CONULTA BDD GEMA
            $sql = "SELECT DISTINCT t.pk_fk_paciente hcl, fun_busca_nombre_pte(t.pk_fk_paciente) Nombres,
            to_char(t.hora_admision,'DD/MM/YYYY hh24:mi') fecha_adm,
            t.pk_numero_admision adm,
            to_char(t.hora_admision,'hh24:mi') hora_adm,
            a.descripcion especialidad,
            fun_busca_emails(b.fk_persona) emails
            from cad_admisiones t, aas_especialidades a, cad_pacientes b
            where
            to_char(t.hora_admision, 'DD/MM/YYYY hh24:mi') >=
            '" . $desde_dia . "/" . $desde_mes . "/" . $desde_anio . " " . $desde_hora . ":59'
            and to_char(t.hora_admision, 'DD/MM/YYYY hh24:mi')
            <= '" . $hasta_dia . "/" . $hasta_mes . "/" . $hasta_anio . " " . $hasta_hora . ":59'
            and t.discriminante = 'CEA'
            and t.pk_fk_paciente = b.pk_nhcl
            and t.fk_especialidad in (gema.FUN_OBTIENE_PARAMETRO('ERX',SYSDATE,t.pk_fk_institucion),gema.FUN_OBTIENE_PARAMETRO('ELB',SYSDATE,t.pk_fk_institucion))
            and t.fk_especialidad = a.pk_codigo and nvl(t.anulado,'N') = 'N' and nvl(t.pre_admision,'N') = 'N'
            and fun_busca_emails(b.fk_persona) IS NOT NULL
            and t.fecha_alta is not null and t.alta_clinica is not null
            order by 3,4 ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # Cerrar conexion
            $this->_conexion->close();

            # Datos
            $data = $stmt->fetchAll();

            # Dta de Citas
            $usrs = array();

            foreach ($data as $key) {

                $key['FECHA_ADM'] = date('d-m-Y', strtotime($key['FECHA_ADM']));
                $key['EMAILS']    = explode(';', $key['EMAILS']);

                foreach ($key['EMAILS'] as $k => $v) {

                    $email = explode(' ', $v)[1];

                    $pte = array(
                        'EMAIL' => $email,
                        'ADM'   => $key['ADM'],
                        'HCL'   => $key['HCL'],
                    );

                    $this->uploadContactIcommkt($pte);

                    $usrs[] = $pte;

                }

            }

            # Devolver Información
            return array(
                'status' => true,
                'data'   => $usrs,
                'total'  => count($usrs),
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function uploadContactIcommkt(array $pte)
    {
        $apiKey     = $this->apikey;
        $profileKey = 'MzYyMjI00';
        $stringData = array(
            'ProfileKey' => $profileKey,
            'Contact'    => array(
                'Email'        => $pte['EMAIL'],
                'CustomFields' => array(
                    array('Key' => 'adm', 'Value' => $pte['ADM']),
                    array('Key' => 'hcl', 'Value' => $pte['HCL']),
                ),
            ),
        );

        $data = json_encode($stringData);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.icommarketing.com/Contacts/SaveContact.Json/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data),
            'Authorization: ' . $apiKey . ':0',
            'Access-Control-Allow-Origin: *')
        );

        $result = curl_exec($ch);
        curl_close($ch);
        $resultobj = json_decode($result);

        # return array('res' => $resultobj);
        // print_r($resultobj->{'SaveContactJsonResult'}->{'StatusCode'});
    }

    public function unique_multidim_array($array, $key)
    {
        $temp_array = array();
        $i          = 0;
        $key_array  = array();

        foreach ($array as $val) {
            if (!in_array($val[$key], $key_array)) {
                $key_array[$i] = $val[$key];
                $temp_array[]  = $val;
            }
            $i++;
        }
        return $temp_array;
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
