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
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Metrored GEMA -> Metrored
 */

class Metrored extends Models implements IModels
{

    private $dia     = null;
    private $mes     = null;
    private $anio    = null;
    private $hora    = null;
    private $apiKey  = '21983bc4-5f6a-4e47-a5e7-f78260bef614';
    private $from    = 'servicios@metrored.med.ec';
    private $urlPoll = 'http://servicios.metrored.med.ec/gracias-por-preferirnos/?token=';

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
//..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle_metrored'], $_config);

    }

    public function setEnvio($hc, $adm)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute queryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            # Insertar nuevo registro de cuenta electrónica.
            $queryBuilder
                ->insert('CAD_ICOMMKT_UPLOADS')
                ->values(
                    array(
                        'HC'          => '?',
                        'ADM'         => '?',
                        'ESTADO'      => '?',
                        'FECHA_ENVIO' => '?',
                    )
                )
                ->setParameter(0, $hc)
                ->setParameter(1, $adm)
                ->setParameter(2, 1)
                ->setParameter(3, date('d-m-Y H:i:s'))
            ;

            $nuevo_registro = $queryBuilder->execute();

            $this->_conexion->close();

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function setRespuesta($cal, $token)
    {

        try {

            $token = Helper\Strings::ocrend_decode($token, 'metroambicommkt');

            if ($token == '') {
                throw new ModelsException('Error No existen datos de paciente o Paciente ya realizo la encuensta.', 4080);
            }

            if ($cal == 0) {
                throw new ModelsException('Error No existen datos de paciente o Paciente ya realizo la encuensta.', 4080);
            }

            # PARSE token
            $hc  = explode('&', $token)[0];
            $adm = explode('&', $token)[1];

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute queryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            # Insertar nuevo registro de cuenta electrónica.

            $queryBuilder
                ->update('CAD_ICOMMKT_UPLOADS', 'u')
                ->set('u.cal', '?')
                ->set('u.fecha_respuesta', '?')
                ->where('u.hc = ?')
                ->andWhere('u.adm = ?')
                ->setParameter(0, $cal)
                ->setParameter(1, date('d-m-Y H:i:s'))
                ->setParameter(2, $hc)
                ->setParameter(3, $adm)
            ;

            $nuevo_registro = $queryBuilder->execute();

            $this->_conexion->close();

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function getAtencion($token): array
    {

        try {

            global $config, $http;

            # gET TOKEN
            # $token = $http->request->get('token');

            # extract token
            $token = Helper\Strings::ocrend_decode($token, 'metroambicommkt');

            if ($token == '') {
                throw new ModelsException('Error No existen datos de paciente o Paciente ya realizo la encuensta.', 4080);
            }

            # PARSE token
            $hc  = explode('&', $token)[0];
            $adm = explode('&', $token)[1];

            # COECTAR A BDD
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            # Query
            $queryBuilder
                ->select("cad_vw_icommkt_trx.*", "TO_CHAR(FECHA_ADMISION, 'DD/MM/YYYY hh24:mi') as FECHA_ADMISION")
                ->from('cad_vw_icommkt_trx')
                ->where('HC = :HC')
                ->andWhere('ADM = :ADM')
                ->andWhere('CAL IS NULL')
                ->setParameter('HC', $hc)
                ->setParameter('ADM', $adm)

            ;

            # Execute
            $stmt = $queryBuilder->execute();

            # Cerrar conexion
            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $data = $stmt->fetch();

            if (false == $data) {
                throw new ModelsException('Error No existen datos de paciente o Paciente ya realizo la encuensta.', 4080);
            }

            $data['FECHA_NACIMIENTO'] = date('d-m-Y', strtotime($data['FECHA_NACIMIENTO']));
            $data['PTE']              = $data['PRIMER_NOMBRE'] . ' ' . $data['PRIMER_APELLIDO'];

            # retornar valores para vista de pte
            return array(
                'status' => true,
                'data'   => $data,
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function postPolls(): array
    {

        try {

            $date = new DateTime();
            $date->modify('-2 hours');

            $this->dia  = $date->format('d');
            $this->mes  = $date->format('m');
            $this->anio = $date->format('Y');
            $this->hora = $date->format('H');

            global $config;

            # Conectar base de datos
            $this->conectar_Oracle();

            $sql = "SELECT cad_vw_icommkt_trx.*, TO_CHAR(FECHA_ADMISION, 'DD/MM/YYYY hh24:mi') as TIMESTAMP
                    FROM cad_vw_icommkt_trx
                    WHERE FECHA_ADMISION >= TO_DATE('" . $this->dia . "." . $this->mes . "." . $this->anio . ":00:00:00','DD.MM.YYYY:HH24:MI:SS')
                    AND FECHA_ADMISION <= TO_DATE('" . $this->dia . "." . $this->mes . "." . $this->anio . ":" . $this->hora . ":59:59','DD.MM.YYYY:HH24:MI:SS')
                    AND E_MAIL IS NOT NULL AND ESPECIALIDAD != 'LABORATORIO' AND ESTADO IS NULL ORDER BY FECHA_ADMISION desc";

            # Execute
            $stmt = $this->_conexion->query($sql);

            # Cerrar conexion
            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $admisiones = $stmt->fetchAll();

            # return array($admisiones, count($admisiones));

            if (false === $admisiones) {
                throw new ModelsException('Error No existen elementos.', 4080);
            }

            # parsear informacion para el envío de correos

            $data = array();

            $i = 0;

            foreach ($admisiones as $key) {

                if ($i <= 12) {

                    $token = Helper\Strings::ocrend_encode($key['HC'] . '&' . $key['ADM'], 'metroambicommkt');

                    $url = $this->urlPoll . $token;

                    $estate = $this->postEmialTrx(array(
                        'url'   => $this->urlPoll . $token,
                        'sexo'  => $key['SEXO'],
                        'email' => $key['E_MAIL'],
                        'pte'   => $key['PRIMER_NOMBRE'] . ' ' . $key['PRIMER_APELLIDO'],
                    ));

                    # Si es envío fue exitoso

                    if ($estate == 0) {

                        $this->setEnvio($key['HC'], $key['ADM']);

                    }

                    $data[] = array(
                        'url'   => $this->urlPoll . $token,
                        'state' => $estate,
                    );

                    $i++;

                }

            }

            return $data;

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    private function postEmialTrx(array $data)
    {

        $html = Helper\Emails::loadTemplate(array(
            '{{url}}'  => $data['url'],
            '{{sexo}}' => ($data['sexo'] === 'F') ? 'a' : 'o',
            '{{pte}}'  => $data['pte'],
        ), 2);

        $stringData = array(
            'Cc'          => '',
            'Bcc'         => '',
            'Headers'     => '',
            'TrackLinks'  => 'HtmlAndText',
            'From'        => $this->from,
            'Attachments' => '',
            'Subject'     => 'Metrored agradece su confianza.',
            'TrackOpens'  => true,
            'TextBody'    => 'Metrored agradece su confianza.',
            'ReplyTo'     => $this->from,
            'HtmlBody'    => $html,
            'To'          => $data['email'],
            'Tag'         => '',
        );

        $data = json_encode($stringData);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.trx.icommarketing.com/email");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data),
            "X-Trx-Api-Key: " . $this->apiKey . "",
            'Access-Control-Allow-Origin: *')
        );

        $result = curl_exec($ch);
        curl_close($ch);

        $resultobj = json_decode($result, true);
        return $resultobj['ErrorCode'];

    }

    private function fechaCastellano($fecha)
    {
        $fecha     = substr($fecha, 0, 10);
        $numeroDia = date('d', strtotime($fecha));
        $dia       = date('l', strtotime($fecha));
        $mes       = date('F', strtotime($fecha));
        $anio      = date('Y', strtotime($fecha));
        $dias_ES   = array("Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado", "Domingo");
        $dias_EN   = array("Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday");
        $nombredia = str_replace($dias_EN, $dias_ES, $dia);
        $meses_ES  = array("Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");
        $meses_EN  = array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
        $nombreMes = str_replace($meses_EN, $meses_ES, $mes);
        return $nombredia;
    }

/**
 * __construct()
 */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);

    }
}
