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
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;
use PDO;
use SoapClient;
use SoapFault;

/**
 * Modelo Laboratorio
 */

class Laboratorio extends Models implements IModels
{

    # Variables de clase
    private $pstrSessionKey = 0;
    private $cod_paciente = null;
    private $sortField = 'ROWNUM';
    private $sortType = 'desc'; # desc
    private $start = 1;
    private $length = 10;
    private $searchField = null;
    private $startDate = null;
    private $endDate = null;
    private $tresMeses = null;
    private $_conexion = null;
    private $dia = null;
    private $mes = null;
    private $anio = null;
    private $hora = null;
    private $hash = 'SC';
    private $id_convenio = null;
    private $name_convenio = null;

    /**
     * Conexion
     *
     */

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
        //..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle_produccion'], $_config);

    }

    private function conectar_Oracle_MTR()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
        //..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle_mtr'], $_config);

    }

    private function setSpanishOracle()
    {

        # 71001 71101
        $sql = "alter session set NLS_LANGUAGE = 'LATIN AMERICAN SPANISH'";
        # Execute
        $stmt = $this->_conexion->query($sql);

        $sql = "alter session set NLS_TERRITORY = 'ECUADOR'";
        # Execute
        $stmt = $this->_conexion->query($sql);

        $sql = " alter session set NLS_DATE_FORMAT = 'YYYY-MM-DD' ";
        # Execute
        $stmt = $this->_conexion->query($sql);

    }

    private function errorsPagination()
    {

        if ($this->length > 11) {
            throw new ModelsException('!Error! Solo se pueden mostrar 10 resultados por página.');
        }

    }

    public function agregarCorreoElectrónicoPaciente()
    {
        try {

            global $http;

            $this->codigoPersona = $http->request->get('codigoPersona');
            $this->correoElectronico = $http->request->get('correoElectronico');

            # Consulta SQL
            $sql = "CALL WEB_PRO_GRABA_CORREO(
            '" . $this->codigoPersona . "',
            '" . $this->correoElectronico . "',
            :pn_error,
            :pc_desc_error)";

            # Conectar base de datos
            $this->conectar_Oracle();
            # Execute
            $stmt = $this->_conexion->prepare($sql);

            $stmt->bindParam(':pn_error', $vn_sec, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 10);
            $stmt->bindParam(':pc_desc_error', $vc_error, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 2000);

            # Datos de usuario cuenta activa
            $result = $stmt->execute();

            $this->_conexion->close();

            if (false == $result) {
                throw new ModelsException('¡Error! No se pudo ejecutar con éxito. ', 4001);
            }

            # Pedido electrónico registrado con éxito
            return array(
                'status' => true,
                'message' => 'Proceso realizado con exito.',
                #'data'    => $vn_sec,
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    public function obtenerResultadosHM()
    {

        try {

            global $config, $http;

            # ERRORES DE PETICION
            $this->errorsPagination();

            $codigoPersonaPaciente = $http->query->get('codigoPersonaPaciente');

            # seteo de valores para paginacion
            $this->start = (int) $http->query->get('start');

            $this->length = (int) $http->query->get('length');

            $this->cod_paciente = $codigoPersonaPaciente;

            if ($this->start >= 10) {
                $this->length = $this->start + 10;
            }

            $sql = " SELECT *
                FROM (
                  SELECT b.*, ROWNUM AS NUM
                  FROM (
                    SELECT *
                    FROM WEB2_RESULTADOS_LAB_VWMTR
                    ORDER BY FECHA DESC
                  ) b
                  WHERE ROWNUM <= " . $this->length . "
                  AND COD_PERSONA = " . $this->cod_paciente . "
                  AND TOT_SC != TOD_DC
                  ORDER BY FECHA DESC
                )
                WHERE NUM > " . $this->start . " ";

            # Conectar base de datos
            $this->conectar_Oracle_MTR();

            # set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # cERRAR CONEXION
            $this->_conexion->close();

            # VERIFICAR RESULTADOS
            $data = $stmt->fetchAll();

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $resultados = array();

            foreach ($data as $key) {

                $id_resultado = Helper\Strings::ocrend_encode($key['SC'], $this->hash);

                $key['FECHA_RES'] = str_replace('/', '-', $key['FECHA']);
                $key['ID_RESULTADO'] = $id_resultado;
                $key['PDF'] = $config['build']['url'] . 'v1/documentos/resultados/' . $id_resultado . '.pdf';
                unset($key['TOT_SC']);
                unset($key['TOD_DC']);
                // unset($key['ROWNUM']);

                $resultados[] = $key;
            }

            # Ya no existe resultadso
            $this->notResults($resultados);

            # Devolver Información
            return array(
                'status' => true,
                'data' => $resultados,
                'total' => count($resultados),
                'start' => intval($this->start),
                'length' => intval($this->length),
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage());

        }

    }

    /**
     * Get Auth Retorna Valores por defecto del usuario que consume el Api
     */

    private function getAuthorization()
    {

        try {

            global $http;

            $token = $http->headers->get("Authorization");

            $auth = new Model\Auth;
            $data = $auth->GetData($token);

            # Set data User
            $this->id_user = $data;

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    public function obtenerResultadosMedicoHM()
    {

        try {

            global $config, $http;

            # Get data Autorización
            $this->getAuthorization();

            $codigoMedico = $this->id_user->codMedico;

            # seteo de valores para paginacion
            $this->start = (int) $http->query->get('start');

            $this->length = (int) $http->query->get('length');

            # seteo de valores para busqueda

            $sql = " SELECT *
            FROM (
              SELECT b.*, ROWNUM AS NUM
              FROM (
                SELECT *
                FROM web_resul_lab_con_medico
                ORDER BY FECHA DESC
              ) b
              WHERE ROWNUM <= " . $this->length . "
              AND TOT_SC != TOD_DC
              AND COD_MEDICO = " . $codigoMedico . "
              ORDER BY FECHA DESC
            )
            WHERE NUM > " . $this->start . " ";

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # cERRAR CONEXION
            $this->_conexion->close();

            # VERIFICAR RESULTADOS
            $data = $stmt->fetchAll();

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $resultados = array();

            foreach ($data as $key) {

                $ID_RESULTADO = Helper\Strings::ocrend_encode($key['SC'], $this->hash);

                $key['ORIGEN'] = strtoupper($this->name_convenio);
                $key['FECHA_RES'] = str_replace('/', '-', $key['FECHA']);
                $key['ID_RESULTADO'] = $ID_RESULTADO;
                $key['PDF'] = $config['build']['url'] . 'api/documentos/resultados/' . $ID_RESULTADO . '.pdf';
                unset($key['TOT_SC']);
                unset($key['TOD_DC']);

                $key['NOMBRE_PERSONA'] = iconv('windows-1252', 'utf-8', $key['NOMBRE_PERSONA']);

                #unset($key['ROWNUM']);

                $resultados[] = $key;
            }

            # Ya no existe resultadso
            $this->notResults($resultados);

            # Devolver Información
            return array(
                'status' => true,
                'data' => $resultados,
                'total' => count($resultados),
                'start' => intval($this->start),
                'length' => intval($this->length),
                // 'data'       => $http->request->all(),
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage());

        }

    }

    public function obtenerResultadoLabHM()
    {

        try {

            global $config, $http;

            $idResultado = $http->request->get('idResultado');

            $fecha = $http->request->get('fecha');

            $idResultado = Helper\Strings::ocrend_decode($idResultado, $this->hash);

            # Verificar si el reporte ya existe
            $resultado = 'downloads/resultados/' . $http->request->get('idResultado') . '.pdf';

            if (file_exists($resultado)) {
                return array(
                    'status' => true,
                    'idDocumento' => $idResultado,
                    'id_resultado' => $http->request->get('idResultado'),
                    'pdf' => $config['build']['url'] . 'v1/documentos/resultados/' . $http->request->get('idResultado') . ".pdf",
                );

            }

            $doc_resultado = $this->wsLab_GET_REPORT_PDF($idResultado, $fecha);

            // No existe documeneto
            if (!$doc_resultado['status']) {
                throw new ModelsException($doc_resultado['message']);
            }

            $id_resultado = Helper\Strings::ocrend_encode($idResultado, $this->hash);

            $url = $doc_resultado['data'];
            $destination = "../v1/downloads/resultados/" . $id_resultado . ".pdf";
            $fp = fopen($destination, 'w+');
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_exec($ch);
            curl_close($ch);

            fclose($fp);

            return array(
                'status' => true,
                'idDocumento' => $idResultado,
                'id_resultado' => $id_resultado,
                'pdf' => $config['build']['url'] . 'v1/documentos/resultados/' . $id_resultado . ".pdf",
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage());

        }

    }

    public function getResultadosLabById($id_resultado, $fecha)
    {

        try {

            global $config;

            // Volver a encriptar
            $id_resultado = Helper\Strings::ocrend_decode($id_resultado, $this->hash);

            $doc_resultado = $this->wsLab_GET_REPORT_PDF($id_resultado, $fecha);

            // No existe documeneto
            if (!$doc_resultado['status']) {
                throw new ModelsException($doc_resultado['message']);
            }

            $id_resultado = Helper\Strings::ocrend_encode($id_resultado, $this->hash);

            $url = $doc_resultado['data'];
            $destination = "../../assets/descargas/" . $id_resultado . ".pdf";
            $fp = fopen($destination, 'w+');
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_exec($ch);
            curl_close($ch);

            fclose($fp);

            return array(
                'status' => true,
                'id_resultado' => $id_resultado,
                'pdf' => $config['build']['url'] . 'v1/documentos/resultados/' . $id_resultado . ".pdf",
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage());

        }

    }

    public function getOldResultadosLab($cod_paciente)
    {

        try {

            global $config, $http;

            # ERRORES DE PETICION
            $this->errorsPagination();

            # seteo de valores para paginacion
            $this->start = (int) $http->query->get('start');

            $this->length = (int) $http->query->get('length');

            $this->cod_paciente = $cod_paciente;

            if ($this->start >= 10) {
                $this->length = $this->start + 10;
            }

            $sql = " SELECT *
                FROM (
                  SELECT b.*, ROWNUM AS NUM
                  FROM (
                    SELECT *
                    FROM WEB2_RESULTADOS_LAB
                    ORDER BY FECHA DESC
                  ) b
                  WHERE ROWNUM <= " . $this->length . "
                  AND COD_PERSONA = " . $this->cod_paciente . "
                  AND TOT_SC != TOD_DC
                  ORDER BY FECHA DESC
                )
                WHERE NUM > " . $this->start . " ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            # cERRAR CONEXION
            $this->_conexion->close();

            # VERIFICAR RESULTADOS
            $data = $stmt->fetchAll();

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $resultados = array();

            foreach ($data as $key) {

                $id_resultado = Helper\Strings::ocrend_encode($key['SC'], $this->hash);

                $key['FECHA_RES'] = str_replace('/', '-', $key['FECHA']);
                $key['ID_RESULTADO'] = $id_resultado;
                $key['PDF'] = $config['build']['url'] . 'api/documentos/resultados/' . $id_resultado . '.pdf';
                unset($key['TOT_SC']);
                unset($key['TOD_DC']);
                unset($key['ROWNUM']);

                $resultados[] = $key;
            }

            # Ya no existe resultadso
            $this->notResults($resultados);

            # Devolver Información
            return array(
                'status' => true,
                'customData' => $resultados,
                'total' => count($resultados),
                'start' => intval($this->start),
                'length' => intval($this->length),
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage());

        }

    }

    public function getOldResultadosLabById($id_resultado, $fecha)
    {

        try {

            global $config;

            // Volver a encriptar
            $id_resultado = Helper\Strings::ocrend_decode($id_resultado, $this->hash);

            $doc_resultado = $this->wsLab_GET_REPORT_PDF($id_resultado, $fecha);

            // No existe documeneto
            if (!$doc_resultado['status']) {
                throw new ModelsException($doc_resultado['message']);
            }

            $id_resultado = Helper\Strings::ocrend_encode($id_resultado, $this->hash);

            $url = $doc_resultado['data'];
            $destination = "../../assets/descargas/" . $id_resultado . ".pdf";
            $fp = fopen($destination, 'w+');
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_exec($ch);
            curl_close($ch);

            fclose($fp);

            return array(
                'status' => true,
                'id_resultado' => $id_resultado,
                'pdf' => $config['build']['url'] . 'v1/documentos/resultados/' . $id_resultado . ".pdf",
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage());

        }

    }

    private function setParameters()
    {

        global $http;

        foreach ($http->query->all() as $key => $value) {
            $this->$key = strtoupper($value);
        }

    }

    public function getResultadosLab(): array
    {

        try {

            global $config, $http;

            # SETEAR VARIABLES DE CLASE
            $this->setParameters();

            # EXTRAER VALOR DEL TOKEN PARA CONSULTA
            $this->getAuthorization();

            $codMedico = $this->id_user->codMedico;

            # Verificar que no están vacíos
            if (Helper\Functions::e($codMedico)) {
                throw new ModelsException('Código del Médico es necesario.');
            }

            # seteo de valores para paginacion
            $this->limit = (int) $http->query->get('length');

            $this->offset = (int) $http->query->get('start');

            if ($this->offset > 10) {
                $this->limit = $this->offset + 10;
            }

            # SELECT * FROM A_US_REPORT_TEXT WHERE REPORT_KEY = '232095' // 60055801

            # CONULTA BDD GEMA
            if ($this->startDate != null) {

                # $tempFecha = explode('-', $this->startDate);

                #   $this->startDate = $tempFecha[2] . '/' . $tempFecha[1] . '/' . $tempFecha[0];

                $sql = " SELECT *
                FROM (
                SELECT b.*, ROWNUM AS NUM
                FROM (
                    SELECT *
                    FROM web_resul_lab_con_medico
                    WHERE COD_MEDICO = '" . $codMedico . "'
                    AND to_char(FECHA, 'YYYY-MM-DD') >= '$this->startDate'
                    AND to_char(FECHA, 'YYYY-MM-DD') <= '$this->startDate'
                    ORDER BY FECHA DESC
                ) b
                WHERE ROWNUM <= " . $this->limit . "
                )
                WHERE NUM > " . $this->offset . "
                ";

            } elseif ($this->searchField != null and $this->searchField != '') {

                $sql = " SELECT *
                FROM (
                SELECT b.*, ROWNUM AS NUM
                FROM (
                    SELECT *
                    FROM web_resul_lab_con_medico
                    WHERE COD_MEDICO = '" . $codMedico . "'
                     AND to_char(FECHA, 'YYYY-MM-DD') >= '2016-01-01'
                     AND NOMBRE LIKE '%$this->searchField%'
                     ORDER BY FECHA DESC
                ) b
                WHERE ROWNUM <= " . $this->limit . "
                )
                WHERE NUM > " . $this->offset . "
                ";

            } else {

                $sql = " SELECT *
                FROM (
                SELECT b.*, ROWNUM AS NUM
                FROM (
                    SELECT *
                    FROM web_resul_lab_con_medico
                    WHERE COD_MEDICO = '" . $codMedico . "'
                    AND to_char(FECHA, 'YYYY-MM-DD') >= '2016-01-01'
                    ORDER BY FECHA DESC
                ) b
                WHERE ROWNUM <= " . $this->limit . "
                )
                WHERE NUM > " . $this->offset . "
                ";

            }
            # Conectar base de datos
            $this->conectar_Oracle();

            # Conectar base de datos
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $resultados = array();

            foreach ($data as $key) {

                $id_resultado = Helper\Strings::ocrend_encode($key['SC'], $this->hash);

                $key['FECHA_RES'] = str_replace('/', '-', $key['FECHA']);
                $key['ID_RESULTADO'] = $id_resultado;
                $key['PDF'] = $config['build']['url'] . 'api/documentos/resultados/' . $id_resultado . '.pdf';
                unset($key['TOT_SC']);
                unset($key['TOD_DC']);
                unset($key['ROWNUM']);

                $resultados[] = $key;
            }

            # Ya no existe resultadso
            $this->notResults($resultados);

            # Devolver Información
            return array(
                'status' => true,
                # 'data' => $this->get_page($RESULTADOS, $this->start, $this->length),
                'data' => $resultados,
                'total' => count($resultados),
                'start' => intval($this->start),
                'length' => intval($this->length),

            );

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    # Metodo LOGIN webservice laboratorio ROCHE
    public function wsLab_LOGIN()
    {

        try {

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'zdk.ws.wSessions.wsdl.xml');

            $Login = $client->Login(array(
                "pstrUserName" => "CONSULTA",
                "pstrPassword" => "CONSULTA1",
            ));

            # Guaradar  KEY de session WS
            $this->pstrSessionKey = $Login->LoginResult;

            # Retorna KEY de session WS
            # return $Login->LoginResult;

        } catch (SoapFault $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }

    }

    # Metodo LOGOUT webservice laboratorio ROCHE
    public function wsLab_LOGOUT()
    {

        try {

            # INICIAR SESSION
            # $this->wsLab_LOGIN();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'zdk.ws.wSessions.wsdl.xml');

            $Logout = $client->Logout(array(
                "pstrSessionKey" => $this->pstrSessionKey,
            ));

            # return $Logout->LogoutResult;

        } catch (SoapFault $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }

    }

    private function insertarNuevoRegistroLogs($dataInforme)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            # Query
            $queryBuilder
                ->insert('GEMA.LOGS_NOTIFICACION_LABORATORIO')
                ->values(
                    array(
                        'ID_STUDIO' => '?',
                        'STATUS_REGISTRADO' => '?',
                        'FECHA_REGISTRADO' => '?',
                    )
                )
                ->setParameter(0, (string) $dataInforme['ID_STUDIO'])
                ->setParameter(1, (int) 1)
                ->setParameter(2, $dataInforme['FECHA_REGISTRADO'])
            ;

            # Execute
            $result = $queryBuilder->execute();

            $this->_conexion->close();

            if (false === $result) {
                throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    private function insertarNuevoRegistroLogsMicro($dataInforme)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            # Query
            $queryBuilder
                ->insert('GEMA.LOGS_NOTIFICACION_LABORATORIO')
                ->values(
                    array(
                        'ID_STUDIO' => '?',
                        'STATUS_REGISTRADO' => '?',
                        'STATUS_GENERADO' => '?',
                        'FECHA_GENERADO' => '?',
                        'FECHA_REGISTRADO' => '?',
                    )
                )
                ->setParameter(0, (string) $dataInforme['ID_STUDIO'])
                ->setParameter(1, (int) 1)
                ->setParameter(2, (int) 8)
                ->setParameter(3, date('Y-m-d'))
                ->setParameter(4, $dataInforme['FECHA_REGISTRADO'])
            ;

            # Execute
            $result = $queryBuilder->execute();

            $this->_conexion->close();

            if (false === $result) {
                throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    // Actualizar resultado pendeinte por no estar completo
    private function updatePendienteRegistroLogs($dataInforme)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            # Query
            # Insertar nuevo registro de cuenta electrónica.
            $queryBuilder
                ->update('GEMA.LOGS_NOTIFICACION_LABORATORIO', 'u')
                ->set('u.FECHA_REGISTRADO', '?')
                ->set('u.STATUS_GENERADO', '?')
                ->set('u.STATUS_ENVIADO', '?')
                ->where('u.ID_STUDIO = ?')
                ->setParameter(0, date("d-m-Y", strtotime(date("d-m-Y") . " + 1 days")))
                ->setParameter(1, null)
                ->setParameter(2, null)
                ->setParameter(3, (string) $dataInforme['ID_STUDIO'])
            ;

            # Execute
            $result = $queryBuilder->execute();

            $this->_conexion->close();

            if (false === $result) {
                throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    private function updateNuevoRegistroLogs($dataInforme)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            # Query
            # Insertar nuevo registro de cuenta electrónica.
            $queryBuilder
                ->update('GEMA.LOGS_NOTIFICACION_LABORATORIO', 'u')
                ->set('u.STATUS_GENERADO', '?')
                ->set('u.FECHA_GENERADO', '?')
                ->set('u.STATUS_ENVIADO', '?')
                ->set('u.FECHA_ENVIADO', '?')
                ->where('u.ID_STUDIO = ?')
                ->setParameter(0, null)
                ->setParameter(1, null)
                ->setParameter(2, null)
                ->setParameter(3, null)
                ->setParameter(4, (string) $dataInforme['ID_STUDIO'])
            ;

            # Execute
            $result = $queryBuilder->execute();

            $this->_conexion->close();

            if (false === $result) {
                throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    private function getRegistroResultado($idResultado)
    {

        # CONULTA BDD GEMA
        $sql = "SELECT ID_STUDIO FROM
         GEMA.LOGS_NOTIFICACION_LABORATORIO WHERE ID_STUDIO='$idResultado'  ";

        # Conectar base de datos
        $this->conectar_Oracle();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $data = $stmt->fetch();

        # Cerrar conexion
        $this->_conexion->close();

        # Ya no existe resultadso
        if (false == $data) {
            return false;
        }

        return true;
    }

    /*
    Obtener resultados desde Lis cada Dos horas periodico diía
    Insertar registro SC, FECHA EXAMEN
     */
    public function getTasksResultados_Fechas($fecha = ''): array
    {

        try {

            global $config, $http;

            $this->wsLab_LOGIN();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wResults.xml', array(
                'soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ));

            $Preview = $client->GetResults(array(
                'pstrSessionKey' => $this->pstrSessionKey,
                'pintMinStatusTest' => "4",
                'pintMaxStatusTest' => "4",
                'pintUse' => "5",
                'pstrOTStatusDateFrom' => $fecha,
                'pstrOTStatusDateTo' => $fecha,
            ));

            $this->wsLab_LOGOUT();

            # return array($Preview);

            if (!isset($Preview->GetResultsResult)) {
                throw new ModelsException('Error 2 => Resultado no disponible.');
            }

            $resultados = array();

            $time = time();

            foreach ($Preview->GetResultsResult->Orders->LISOrder as $key) {

                // INSERTAR LOGS PARA LABORATORIO
                $log = array(
                    'ID_STUDIO' => $key->SampleID,
                    'FECHA_REGISTRADO' => $key->RegisterDate,
                    'STATUS_REGISTRADO' => (int) 1,
                );

                $registrado = $this->getRegistroResultado($key->SampleID);

                // No existe log procesado
                if (!$registrado) {
                    $this->insertarNuevoRegistroLogs($log);
                } else {
                    $this->updateNuevoRegistroLogs($log);
                }

                $resultados[] = $key;

            }

            # Ya no existe resultadso
            $this->notResults($resultados);

            # Devolver Información
            return array(
                'status' => true,
                'data' => $resultados,
                'total' => count($resultados),
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status' => true,
                    'data' => [],
                    'message' => $e->getMessage(),
                    'errorCode' => 4080,

                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    /*
    Obtener resultados desde Lis cada Dos horas periodico diía
    Insertar registro SC, FECHA EXAMEN
     */
    public function getTasksResultadosMicro(): array
    {

        try {

            global $config, $http;

            $this->wsLab_LOGIN();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wResults.xml', array(
                'soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ));

            $Preview = $client->GetMicroResults(array(
                'pstrSessionKey' => $this->pstrSessionKey,
                'pintMinStatusTest' => "4",
                'pintMaxStatusTest' => "4",
                'pintUse' => "5",
                'pstrOTStatusDateFrom' => date('Y-m-d'),
                'pstrOTStatusDateTo' => date('Y-m-d'),
            ));

            $this->wsLab_LOGOUT();

            # return array($Preview);

            if (!isset($Preview->GetMicroResultsResult)) {
                throw new ModelsException('Error 2 => Resultado no disponible.');
            }

            $resultados = array();

            $time = time();

            foreach ($Preview->GetMicroResultsResult->Orders->LISOrder as $key) {

                // INSERTAR LOGS PARA LABORATORIO
                $log = array(
                    'ID_STUDIO' => $key->SampleID,
                    'FECHA_REGISTRADO' => $key->RegisterDate,
                    'STATUS_REGISTRADO' => (int) 1,
                );

                $registrado = $this->getRegistroResultado($key->SampleID);

                // No existe log procesado
                if (!$registrado) {
                    $this->insertarNuevoRegistroLogsMicro($log);
                }

                $resultados[] = $key;

            }

            # Ya no existe resultadso
            $this->notResults($resultados);

            # Devolver Información
            return array(
                'status' => true,
                'data' => $resultados,
                'total' => count($resultados),
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status' => true,
                    'data' => [],
                    'message' => $e->getMessage(),
                    'errorCode' => 4080,

                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    /*
    Obtener resultados desde Lis cada Dos horas periodico diía
    Insertar registro SC, FECHA EXAMEN
     */
    public function getTasksResultados(): array
    {

        try {

            global $config, $http;

            $this->wsLab_LOGIN();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wResults.xml', array(
                'soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ));

            $Preview = $client->GetResults(array(
                'pstrSessionKey' => $this->pstrSessionKey,
                'pintMinStatusTest' => "4",
                'pintMaxStatusTest' => "4",
                'pintUse' => "5",
                'pstrOTStatusDateFrom' => date('Y-m-d'),
                'pstrOTStatusDateTo' => date('Y-m-d'),
            ));

            $this->wsLab_LOGOUT();

            # return array($Preview);

            if (!isset($Preview->GetResultsResult)) {
                throw new ModelsException('Error 2 => Resultado no disponible.');
            }

            $resultados = array();

            $time = time();

            foreach ($Preview->GetResultsResult->Orders->LISOrder as $key) {

                // INSERTAR LOGS PARA LABORATORIO
                $log = array(
                    'ID_STUDIO' => $key->SampleID,
                    'FECHA_REGISTRADO' => $key->RegisterDate,
                    'STATUS_REGISTRADO' => (int) 1,
                );

                $registrado = $this->getRegistroResultado($key->SampleID);

                // No existe log procesado
                if (!$registrado) {
                    $this->insertarNuevoRegistroLogs($log);
                } /*else {
                $this->updateNuevoRegistroLogs($log);
                }*/

                $resultados[] = $key;

            }

            # Ya no existe resultadso
            $this->notResults($resultados);

            # Devolver Información
            return array(
                'status' => true,
                'data' => $resultados,
                'total' => count($resultados),
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status' => true,
                    'data' => [],
                    'message' => $e->getMessage(),
                    'errorCode' => 4080,

                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    # Metodo wReports webservice laboratorio ROCHEdevuelve el resultado pdf del paciente
    public function getCopyInformeResultado(string $SC, string $FECHA)
    {

        try {

            # Login SOAP CLIENT
            $this->wsLab_LOGIN();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wReports.wsdl.xml');

            $Preview = $client->Preview(array(
                "pstrSessionKey" => $this->pstrSessionKey,
                "pstrSampleID" => $SC, # '0015052333',
                "pstrRegisterDate" => $FECHA, # $FECHA_final[2] . '-' . $FECHA_final[1] . '-' . $FECHA_final[0], # '2018-11-05',
                "pstrFormatDescription" => 'METROPOLITANO',
                "pstrPrintTarget" => 'Destino por defecto',
            ));

            # CERRAR SESSION POR LICENCIAS HSF
            $this->wsLab_LOGOUT();

            # No existe documento

            if (!isset($Preview->PreviewResult)) {
                throw new ModelsException('Error 0 => No existe el documento solicitado.');
            }

            # No existe documento

            if (isset($Preview->PreviewResult) or $Preview->PreviewResult == '0') {

                if ($Preview->PreviewResult == '0') {

                    throw new ModelsException('Error 1 => No existe el documento solicitado.');

                } else {

                    return array(
                        'status' => true,
                        'data' => str_replace("172.16.2.221", "resultadosweb.hospitalmetropolitano.org", $Preview->PreviewResult),
                    );

                }

            }

            throw new ModelsException('Error 2 => No existe el documento solicitado.');

        } catch (SoapFault $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        } catch (ModelsException $b) {

            return array('status' => false, 'message' => $b->getMessage(), 'errorCode' => $b->getCode());

        }

    }

    # Metodo wReports webservice laboratorio ROCHEdevuelve el resultado pdf del paciente
    public function getInformeResultado(string $SC, string $FECHA)
    {

        try {

            # INICIAR SESSION

            $this->wsLab_LOGIN();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wResults.xml', array('soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ));

            $Preview = $client->GetResults(array(
                'pstrSessionKey' => $this->pstrSessionKey,
                'pstrSampleID' => $SC, # '0015052333',
                'pstrRegisterDate' => $FECHA,
            ));

            $this->wsLab_LOGOUT();

            #  return $Preview;

            if (!isset($Preview->GetResultsResult)) {
                throw new ModelsException('Error 2 => Resultado no disponible.');
            }

            # REVISAR SI EXISTEN PRUEBAS NO DISPONIBLES
            $listaPruebas = $Preview->GetResultsResult->Orders->LISOrder->LabTests->LISLabTest;

            if (!isset($Preview->GetResultsResult->Orders->LISOrder->MotiveDesc)) {
                $MotiveDesc = 'LABORATORIO';
            } else {
                $MotiveDesc = $Preview->GetResultsResult->Orders->LISOrder->MotiveDesc;
            }

            $i = 0;

            $lista = array();

            if (is_array($listaPruebas)) {
                foreach ($listaPruebas as $key) {
                    $lista[] = array(
                        'TestID' => $key->TestID,
                        'TestStatus' => $key->TestStatus,
                        'TestName' => $key->TestName,
                        'MotiveDesc' => $MotiveDesc,
                    );
                }
            } else {
                $lista[] = array(
                    'TestID' => $listaPruebas->TestID,
                    'TestStatus' => $listaPruebas->TestStatus,
                    'TestName' => $listaPruebas->TestName,
                    'MotiveDesc' => $MotiveDesc,
                );
            }

            # return $lista;

            foreach ($lista as $k) {

                // 3420
                if ($k['TestID'] == '3420') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3422
                if ($k['TestID'] == '3422') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3426
                if ($k['TestID'] == '3426') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3418
                if ($k['TestID'] == '3418') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3414
                if ($k['TestID'] == '3414') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3402
                if ($k['TestID'] == '3402') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3429
                if ($k['TestID'] == '3429') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3438
                if ($k['TestID'] == '3438') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3441
                if ($k['TestID'] == '3441') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 1866
                if ($k['TestID'] == '1866') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 8201
                if ($k['TestID'] == '8201') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3774
                if ($k['TestID'] == '3774') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3775
                if ($k['TestID'] == '3775') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 1559
                if ($k['TestID'] == '1559') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 1585
                if ($k['TestID'] == '1585') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 1872
                if ($k['TestID'] == '1872') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3775
                if ($k['TestID'] == '3775') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3776
                if ($k['TestID'] == '3776') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3777
                if ($k['TestID'] == '3777') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3778
                if ($k['TestID'] == '3778') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3779
                if ($k['TestID'] == '3779') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3780
                if ($k['TestID'] == '3780') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3781
                if ($k['TestID'] == '3781') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3784
                if ($k['TestID'] == '3784') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3785
                if ($k['TestID'] == '3785') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3787
                if ($k['TestID'] == '3787') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3790
                if ($k['TestID'] == '3790') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3801
                if ($k['TestID'] == '3801') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 3802
                if ($k['TestID'] == '3802') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 10529
                if ($k['TestID'] == '10529') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 37811
                if ($k['TestID'] == '37811') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 34949
                if ($k['TestID'] == '34949') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 16868
                if ($k['TestID'] == '16868') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // 96692
                if ($k['TestID'] == '96692') {
                    throw new ModelsException('Error 3 => Restriccion de prueba..', 3);
                }

                // Serice Desc // Metrolab
                if ($k['MotiveDesc'] == 'METROLAB') {
                    throw new ModelsException('Error 4 => Resultado no disponible. Restricción de Centro de Servicio ' . $k['MotiveDesc'], 4);
                }

                // Serice Desc // Prueba Cruzada Banco de Sangre
                if ($k['TestName'] == 'Prueba Cruzada' || $k['TestName'] == 'Prueba Cruzada.') {
                    throw new ModelsException('Error 5 => Resultado no disponible. Restricción de Prueba Cruzada', 5);
                }

                if ($k['TestStatus'] < '4') {
                    throw new ModelsException('Error 2 => Resultado no disponible. Pruebas en estado diferente de 4.', 2);
                }

            }

            #  return $lista;
            return array('status' => true, 'data' => $Preview);

        } catch (SoapFault $e) {

            $this->wsLab_LOGOUT();

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => 2);

        } catch (ModelsException $b) {

            return array('status' => false, 'message' => $b->getMessage(), 'errorCode' => 0);

        }
    }

    # Metodo wReports webservice laboratorio ROCHEdevuelve el resultado pdf del paciente
    public function getInformeResultadoMicro(string $SC, string $FECHA)
    {

        try {

            # INICIAR SESSION

            $this->wsLab_LOGIN();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wResults.xml', array('soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ));

            $Preview = $client->GetMicroResults(array(
                'pstrSessionKey' => $this->pstrSessionKey,
                'pstrSampleID' => $SC, # '0015052333',
                'pstrRegisterDate' => $FECHA,
            ));

            $this->wsLab_LOGOUT();

            #  return $Preview;

            if (!isset($Preview->GetMicroResultsResult)) {
                throw new ModelsException('Error 2 => Resultado no disponible. No es resultado de Microbiologia', 6);
            }

            # REVISAR SI EXISTEN PRUEBAS NO DISPONIBLES
            $listaPruebas = $Preview->GetMicroResultsResult->Orders->LISOrder->MicSpecs->LISMicSpec;

            if (!isset($Preview->GetMicroResultsResult->Orders->LISOrder->MotiveDesc)) {
                $MotiveDesc = 'LABORATORIO';
            } else {
                $MotiveDesc = $Preview->GetMicroResultsResult->Orders->LISOrder->MotiveDesc;
            }

            $i = 0;

            $cultivos = array();

            if (is_array($listaPruebas)) {
                foreach ($listaPruebas as $key) {
                    $cultivos[] = array(
                        'SpecimenName' => $key->SpecimenName,
                        'Tests' => $key->MicTests->LISLabTest,
                    );
                }
            } else {
                $cultivos[] = array(
                    'SpecimenName' => $listaPruebas->SpecimenName,
                    'Tests' => $listaPruebas->MicTests->LISLabTest,
                );
            }

            #Validacion de status
            # return $cultivos;

            $lista = array();

            foreach ($cultivos as $k) {

                if (is_array($k['Tests'])) {
                    foreach ($k['Tests'] as $b) {
                        $lista[] = array(
                            'TestID' => $b->TestID,
                            'TestStatus' => $b->TestStatus,
                            'TestName' => $b->TestName,
                            'MotiveDesc' => $MotiveDesc,
                        );
                    }
                } else {
                    $lista[] = array(
                        'TestID' => $k['Tests']->TestID,
                        'TestStatus' => $k['Tests']->TestStatus,
                        'TestName' => $k['Tests']->TestName,
                        'MotiveDesc' => $MotiveDesc,
                    );
                }

            }

            # return $lista;

            foreach ($lista as $k) {

                // Serice Desc // Metrolab
                if ($k['MotiveDesc'] == 'METROLAB') {
                    throw new ModelsException('Error 4 => Resultado no disponible. Restricción de Centro de Servicio ' . $k['MotiveDesc'], 4);
                }

                if ($k['TestStatus'] < '4') {
                    throw new ModelsException('Error 2 => Resultado no disponible. Pruebas en estado diferente de 4.', 2);
                }

            }

            # return $lista;
            return array('status' => true, 'data' => $lista);

        } catch (SoapFault $e) {

            $this->wsLab_LOGOUT();

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => 2);

        } catch (ModelsException $b) {

            return array('status' => false, 'message' => $b->getMessage(), 'errorCode' => $b->getCode());

        }
    }

    private function actualizarRegistroSinFirmaLogs($dataInforme)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            $dia = date("d-m-Y");
            $nuevafecha = date("Y-m-d", strtotime($dia . " + 1 days"));

            $queryBuilder
                ->update('GEMA.LOGS_NOTIFICACION_LABORATORIO', 'u')
                ->set('u.STATUS_GENERADO', '?')
                ->where('u.ID_STUDIO=?')
                ->setParameter(0, (int) 2)
                ->setParameter(1, (string) $dataInforme['idResultado'])
            ;

            # Execute
            $result = $queryBuilder->execute();

            $this->_conexion->close();

            if (false === $result) {
                throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    private function actualizarRegistroLogsMicro($dataInforme)
    {

        try {

            $idResultado = $dataInforme['idResultado'];

            $sql = "SELECT * from WEB_VW_NOTIFICACIONES_LAB
            WHERE SC = '$idResultado'  ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $data = $stmt->fetch();

            if (false == $data) {
                $codPersona = null;
            } else {
                $codPersona = $data['COD_PERSONA'];
            }

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            $queryBuilder
                ->update('GEMA.LOGS_NOTIFICACION_LABORATORIO', 'u')
                ->set('u.STATUS_GENERADO', '?')
                ->set('u.FECHA_GENERADO', '?')
                ->set('u.STATUS_ENVIADO', '?')
                ->set('u.ID_REPORT', '?')
                ->where('u.ID_STUDIO = ?')
                ->setParameter(0, (int) 9)
                ->setParameter(1, date('Y-m-d'))
                ->setParameter(2, (int) 2)
                ->setParameter(3, $codPersona)
                ->setParameter(4, (string) $dataInforme['idResultado'])
            ;

            # Execute
            $result = $queryBuilder->execute();

            $this->_conexion->close();

            if (false === $result) {
                throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    private function actualizarRegistroLogs($dataInforme)
    {

        try {

            $idResultado = $dataInforme['idResultado'];

            $sql = "SELECT * from WEB_VW_NOTIFICACIONES_LAB
            WHERE SC = '$idResultado'  ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $data = $stmt->fetch();

            if (false == $data) {
                $codPersona = null;
            } else {
                $codPersona = $data['COD_PERSONA'];
            }

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            $queryBuilder
                ->update('GEMA.LOGS_NOTIFICACION_LABORATORIO', 'u')
                ->set('u.STATUS_GENERADO', '?')
                ->set('u.FECHA_GENERADO', '?')
                ->set('u.ID_REPORT', '?')
                ->where('u.ID_STUDIO=?')
                ->setParameter(0, (int) 1)
                ->setParameter(1, date('Y-m-d'))
                ->setParameter(2, $codPersona)
                ->setParameter(3, (string) $dataInforme['idResultado'])
            ;

            # Execute
            $result = $queryBuilder->execute();

            $this->_conexion->close();

            if (false === $result) {
                throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    // Reproceso firmconsulta status  de resultados
    public function getTasksInformarReproceso()
    {

        try {

            global $config;

            $sql = " SELECT * FROM GEMA.LOGS_NOTIFICACION_LABORATORIO WHERE
            STATUS_GENERADO = '2' OR STATUS_GENERADO = '6' ORDER BY ID_STUDIO ASC ";

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            $this->notResults($data);

            if (count($data) == 0) {
                throw new ModelsException('No existen registros para procesar.', 0);
            }

            $resultados = array();

            foreach ($data as $key) {
                $this->actualizarRegistroStatusReproceso(array('idResultado' => $key['ID_STUDIO'], 'status' => $key['STATUS_GENERADO']));
                $resultados[] = $key;
            }

            return array('status' => true, 'data' => $resultados);

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    private function actualizarRegistroStatusReproceso($dataInforme)
    {

        try {

            if ($dataInforme['status'] == '6') {

                # Conectar base de datos
                $this->conectar_Oracle();

                # QueryBuilder
                $queryBuilder = $this->_conexion->createQueryBuilder();

                $queryBuilder
                    ->update('GEMA.LOGS_NOTIFICACION_LABORATORIO', 'u')
                    ->set('u.STATUS_GENERADO', '?')
                    ->set('u.FECHA_GENERADO', '?')
                    ->where('u.ID_STUDIO=?')
                    ->setParameter(0, 8)
                    ->setParameter(1, null)
                    ->setParameter(2, (string) $dataInforme['idResultado'])
                ;

                # Execute
                $result = $queryBuilder->execute();

                $this->_conexion->close();

                if (false === $result) {
                    throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
                }

            } else {

                # Conectar base de datos
                $this->conectar_Oracle();

                # QueryBuilder
                $queryBuilder = $this->_conexion->createQueryBuilder();

                $queryBuilder
                    ->update('GEMA.LOGS_NOTIFICACION_LABORATORIO', 'u')
                    ->set('u.STATUS_GENERADO', '?')
                    ->set('u.FECHA_GENERADO', '?')
                    ->where('u.ID_STUDIO=?')
                    ->setParameter(0, null)
                    ->setParameter(1, null)
                    ->setParameter(2, (string) $dataInforme['idResultado'])
                ;

                # Execute
                $result = $queryBuilder->execute();

                $this->_conexion->close();

                if (false === $result) {
                    throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
                }
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    // Estraer datos del paciente para ntoificar.
    public function getTasksInformarResultadoMicro()
    {

        try {

            global $config;

            $sql = " SELECT * FROM GEMA.LOGS_NOTIFICACION_LABORATORIO WHERE
            STATUS_REGISTRADO  = '1'
            AND STATUS_GENERADO = '8'
            ORDER BY ID_STUDIO DESC";

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            if ($data == false) {
                return array('status' => false, 'message' => 'No existe proceso pendientes.');
            }

            $dataEstudio = array(
                'idResultado' => $data['ID_STUDIO'],
                'fecha_resultado' => $data['FECHA_REGISTRADO'],
            );

            $sts = $this->getInformeResultadoMicro($dataEstudio['idResultado'], $dataEstudio['fecha_resultado']);

            #  return $sts;

            if ($sts['status']) {

                $dataEstudio = array(
                    'idResultado' => $data['ID_STUDIO'],
                    'fecha_resultado' => $data['FECHA_REGISTRADO'],
                );

                # Actulizar estado firmado
                $this->actualizarRegistroLogsMicro($dataEstudio);

                return $sts;

            } else {

                if ($sts['errorCode'] == 2 || $sts['errorCode'] == 0) {

                    $dataEstudio = array(
                        'idResultado' => $data['ID_STUDIO'],
                        'fecha_resultado' => $data['FECHA_REGISTRADO'],
                    );

                    $this->actualizarRegistroStatusPendienteMicro($dataEstudio);
                    return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);

                }

                if ($sts['errorCode'] == 6 || $sts['errorCode'] == 4) {

                    $dataEstudio = array(
                        'idResultado' => $data['ID_STUDIO'],
                        'fecha_resultado' => $data['FECHA_REGISTRADO'],
                    );

                    $this->actualizarRegistroStatusExamenNoPermitidoMicro($dataEstudio);
                    return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);

                }

                return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);

            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    // Estraer datos del paciente para ntoificar.
    public function getTasksInformarResultado_Fechas($fecha = '2021-09-20')
    {

        try {

            global $config;

            /*
            $sql = " SELECT * FROM GEMA.LOGS_NOTIFICACION_LABORATORIO WHERE
            (STATUS_GENERADO IS NULL OR STATUS_GENERADO = 2) AND STATUS_REGISTRADO = '1'  AND STATUS_ENVIADO IS NULL AND FECHA_REGISTRADO = '2021-09-21' ORDER BY FECHA_REGISTRADO DESC ";

             */

            $sql = " SELECT * FROM GEMA.LOGS_NOTIFICACION_LABORATORIO WHERE
            STATUS_REGISTRADO  = '1'
             AND STATUS_GENERADO IS NULL
             AND STATUS_ENVIADO IS NULL
             AND FECHA_REGISTRADO = '$fecha' ORDER BY ID_STUDIO ASC  ";

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            if ($data == false) {
                return array('status' => false, 'message' => 'No existe proceso pendientes.');
            }

            $dataEstudio = array(
                'idResultado' => $data['ID_STUDIO'],
                'fecha_resultado' => $data['FECHA_REGISTRADO'],
            );

            $sts = $this->getInformeResultado($dataEstudio['idResultado'], $dataEstudio['fecha_resultado']);

            # return $sts;

            if ($sts['status']) {

                $dataEstudio = array(
                    'idResultado' => $data['ID_STUDIO'],
                    'fecha_resultado' => $data['FECHA_REGISTRADO'],
                );

                # Actulizar estado firmado
                $this->actualizarRegistroLogs($dataEstudio);

                return $sts;

            } else {

                switch ($sts['errorCode']) {
                    case 0:
                        $dataEstudio = array(
                            'idResultado' => $data['ID_STUDIO'],
                            'fecha_resultado' => $data['FECHA_REGISTRADO'],
                        );

                        $this->actualizarRegistroStatusPendiente($dataEstudio);
                        return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);
                        break;

                    case 2:
                        $dataEstudio = array(
                            'idResultado' => $data['ID_STUDIO'],
                            'fecha_resultado' => $data['FECHA_REGISTRADO'],
                        );

                        $this->actualizarRegistroStatusPendiente($dataEstudio);
                        return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);
                        break;

                    case 3:
                        $dataEstudio = array(
                            'idResultado' => $data['ID_STUDIO'],
                            'fecha_resultado' => $data['FECHA_REGISTRADO'],
                        );

                        $this->actualizarRegistroStatusExamenNoPermitido($dataEstudio);
                        return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);

                        break;

                    case 4:
                        $dataEstudio = array(
                            'idResultado' => $data['ID_STUDIO'],
                            'fecha_resultado' => $data['FECHA_REGISTRADO'],
                        );

                        $this->actualizarRegistroStatusMetrolab($dataEstudio);
                        return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);

                        break;

                    case 5:
                        $dataEstudio = array(
                            'idResultado' => $data['ID_STUDIO'],
                            'fecha_resultado' => $data['FECHA_REGISTRADO'],
                        );

                        $this->actualizarRegistroStatusPruebaCruzada($dataEstudio);

                        return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);

                        break;

                    default:
                        return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);
                }

            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    // Estraer datos del paciente para ntoificar.
    public function getTasksInformarResultadoAsc()
    {

        try {

            global $config;

            /*
            $sql = " SELECT * FROM GEMA.LOGS_NOTIFICACION_LABORATORIO WHERE
            (STATUS_GENERADO IS NULL OR STATUS_GENERADO = 2) AND STATUS_REGISTRADO = '1'  AND STATUS_ENVIADO IS NULL AND FECHA_REGISTRADO = '2021-09-21' ORDER BY FECHA_REGISTRADO DESC ";

             */

            $sql = " SELECT * FROM GEMA.LOGS_NOTIFICACION_LABORATORIO WHERE
              STATUS_REGISTRADO  = '1'
              AND STATUS_GENERADO IS NULL
              AND STATUS_ENVIADO IS NULL
              ORDER BY ID_STUDIO ASC";

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            /*

            $sts = $this->getInformeResultado('0017965512', '2021-10-01');

            return $sts;

             */

            if ($data == false) {
                return array('status' => false, 'message' => 'No existe proceso pendientes.');
            }

            $dataEstudio = array(
                'idResultado' => $data['ID_STUDIO'],
                'fecha_resultado' => $data['FECHA_REGISTRADO'],
            );

            $sts = $this->getInformeResultado($dataEstudio['idResultado'], $dataEstudio['fecha_resultado']);

            #  return $sts;

            if ($sts['status']) {

                $dataEstudio = array(
                    'idResultado' => $data['ID_STUDIO'],
                    'fecha_resultado' => $data['FECHA_REGISTRADO'],
                );

                # Actulizar estado firmado
                $this->actualizarRegistroLogs($dataEstudio);

                return $sts;

            } else {

                switch ($sts['errorCode']) {
                    case 0:
                        $dataEstudio = array(
                            'idResultado' => $data['ID_STUDIO'],
                            'fecha_resultado' => $data['FECHA_REGISTRADO'],
                        );

                        $this->actualizarRegistroStatusPendiente($dataEstudio);
                        return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);
                        break;

                    case 2:
                        $dataEstudio = array(
                            'idResultado' => $data['ID_STUDIO'],
                            'fecha_resultado' => $data['FECHA_REGISTRADO'],
                        );

                        $this->actualizarRegistroStatusPendiente($dataEstudio);
                        return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);
                        break;

                    case 3:
                        $dataEstudio = array(
                            'idResultado' => $data['ID_STUDIO'],
                            'fecha_resultado' => $data['FECHA_REGISTRADO'],
                        );

                        $this->actualizarRegistroStatusExamenNoPermitido($dataEstudio);
                        return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);

                        break;

                    case 4:
                        $dataEstudio = array(
                            'idResultado' => $data['ID_STUDIO'],
                            'fecha_resultado' => $data['FECHA_REGISTRADO'],
                        );

                        $this->actualizarRegistroStatusMetrolab($dataEstudio);
                        return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);

                        break;

                    case 5:
                        $dataEstudio = array(
                            'idResultado' => $data['ID_STUDIO'],
                            'fecha_resultado' => $data['FECHA_REGISTRADO'],
                        );

                        $this->actualizarRegistroStatusPruebaCruzada($dataEstudio);

                        return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);

                        break;

                    default:
                        return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);
                }

            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    // Estraer datos del paciente para ntoificar.
    public function getTasksInformarResultado()
    {

        try {

            global $config;

            /*
            $sql = " SELECT * FROM GEMA.LOGS_NOTIFICACION_LABORATORIO WHERE
            (STATUS_GENERADO IS NULL OR STATUS_GENERADO = 2) AND STATUS_REGISTRADO = '1'  AND STATUS_ENVIADO IS NULL AND FECHA_REGISTRADO = '2021-09-21' ORDER BY FECHA_REGISTRADO DESC ";

             */

            $sql = " SELECT * FROM GEMA.LOGS_NOTIFICACION_LABORATORIO WHERE
            STATUS_REGISTRADO  = '1'
            AND STATUS_GENERADO IS NULL
            AND STATUS_ENVIADO IS NULL
            ORDER BY ID_STUDIO DESC";

            # Conectar base de datos
            $this->conectar_Oracle();

            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            /*

            $sts = $this->getInformeResultado('0017965512', '2021-10-01');

            return $sts;

             */

            if ($data == false) {
                return array('status' => false, 'message' => 'No existe proceso pendientes.');
            }

            $dataEstudio = array(
                'idResultado' => $data['ID_STUDIO'],
                'fecha_resultado' => $data['FECHA_REGISTRADO'],
            );

            $sts = $this->getInformeResultado($dataEstudio['idResultado'], $dataEstudio['fecha_resultado']);

            #  return $sts;

            if ($sts['status']) {

                $dataEstudio = array(
                    'idResultado' => $data['ID_STUDIO'],
                    'fecha_resultado' => $data['FECHA_REGISTRADO'],
                );

                # Actulizar estado firmado
                $this->actualizarRegistroLogs($dataEstudio);

                return $sts;

            } else {

                switch ($sts['errorCode']) {
                    case 0:
                        $dataEstudio = array(
                            'idResultado' => $data['ID_STUDIO'],
                            'fecha_resultado' => $data['FECHA_REGISTRADO'],
                        );

                        $this->actualizarRegistroStatusPendiente($dataEstudio);
                        return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);
                        break;

                    case 2:
                        $dataEstudio = array(
                            'idResultado' => $data['ID_STUDIO'],
                            'fecha_resultado' => $data['FECHA_REGISTRADO'],
                        );

                        $this->actualizarRegistroStatusPendiente($dataEstudio);
                        return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);
                        break;

                    case 3:
                        $dataEstudio = array(
                            'idResultado' => $data['ID_STUDIO'],
                            'fecha_resultado' => $data['FECHA_REGISTRADO'],
                        );

                        $this->actualizarRegistroStatusExamenNoPermitido($dataEstudio);
                        return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);

                        break;

                    case 4:
                        $dataEstudio = array(
                            'idResultado' => $data['ID_STUDIO'],
                            'fecha_resultado' => $data['FECHA_REGISTRADO'],
                        );

                        $this->actualizarRegistroStatusMetrolab($dataEstudio);
                        return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);

                        break;

                    case 5:
                        $dataEstudio = array(
                            'idResultado' => $data['ID_STUDIO'],
                            'fecha_resultado' => $data['FECHA_REGISTRADO'],
                        );

                        $this->actualizarRegistroStatusPruebaCruzada($dataEstudio);

                        return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);

                        break;

                    default:
                        return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);
                }
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    private function actualizarRegistroStatusPruebaCruzada($dataInforme)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            $queryBuilder
                ->update('GEMA.LOGS_NOTIFICACION_LABORATORIO', 'u')
                ->set('u.STATUS_GENERADO', '?')
                ->set('u.FECHA_GENERADO', '?')
                ->where('u.ID_STUDIO=?')
                ->setParameter(0, (int) 7)
                ->setParameter(1, date('Y-m-d'))
                ->setParameter(2, (string) $dataInforme['idResultado'])
            ;

            # Execute
            $result = $queryBuilder->execute();

            $this->_conexion->close();

            if (false === $result) {
                throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    private function actualizarRegistroStatusMetrolab($dataInforme)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            $queryBuilder
                ->update('GEMA.LOGS_NOTIFICACION_LABORATORIO', 'u')
                ->set('u.STATUS_GENERADO', '?')
                ->set('u.FECHA_GENERADO', '?')
                ->where('u.ID_STUDIO=?')
                ->setParameter(0, (int) 7)
                ->setParameter(1, date('Y-m-d'))
                ->setParameter(2, (string) $dataInforme['idResultado'])
            ;

            # Execute
            $result = $queryBuilder->execute();

            $this->_conexion->close();

            if (false === $result) {
                throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    private function actualizarRegistroStatusExamenNoPermitidoMicro($dataInforme)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            $queryBuilder
                ->update('GEMA.LOGS_NOTIFICACION_LABORATORIO', 'u')
                ->set('u.STATUS_GENERADO', '?')
                ->set('u.FECHA_GENERADO', '?')
                ->where('u.ID_STUDIO=?')
                ->setParameter(0, (int) 7)
                ->setParameter(1, date('Y-m-d'))
                ->setParameter(2, (string) $dataInforme['idResultado'])
            ;

            # Execute
            $result = $queryBuilder->execute();

            $this->_conexion->close();

            if (false === $result) {
                throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    private function actualizarRegistroStatusExamenNoPermitido($dataInforme)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            $queryBuilder
                ->update('GEMA.LOGS_NOTIFICACION_LABORATORIO', 'u')
                ->set('u.STATUS_GENERADO', '?')
                ->set('u.FECHA_GENERADO', '?')
                ->where('u.ID_STUDIO=?')
                ->setParameter(0, (int) 7)
                ->setParameter(1, date('Y-m-d'))
                ->setParameter(2, (string) $dataInforme['idResultado'])
            ;

            # Execute
            $result = $queryBuilder->execute();

            $this->_conexion->close();

            if (false === $result) {
                throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    private function actualizarRegistroStatusPendienteMicro($dataInforme)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            $queryBuilder
                ->update('GEMA.LOGS_NOTIFICACION_LABORATORIO', 'u')
                ->set('u.STATUS_GENERADO', '?')
                ->set('u.FECHA_GENERADO', '?')
                ->where('u.ID_STUDIO=?')
                ->setParameter(0, (int) 6)
                ->setParameter(1, date('Y-m-d'))
                ->setParameter(2, (string) $dataInforme['idResultado'])
            ;

            # Execute
            $result = $queryBuilder->execute();

            $this->_conexion->close();

            if (false === $result) {
                throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    private function actualizarRegistroStatusPendiente($dataInforme)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            $queryBuilder
                ->update('GEMA.LOGS_NOTIFICACION_LABORATORIO', 'u')
                ->set('u.STATUS_GENERADO', '?')
                ->set('u.FECHA_GENERADO', '?')
                ->where('u.ID_STUDIO=?')
                ->setParameter(0, (int) 2)
                ->setParameter(1, date('Y-m-d'))
                ->setParameter(2, (string) $dataInforme['idResultado'])
            ;

            # Execute
            $result = $queryBuilder->execute();

            $this->_conexion->close();

            if (false === $result) {
                throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    private function getCorreosPaciente($codPersona)
    {

        global $http;

        $sql = "SELECT fun_busca_mail_persona(" . $codPersona . ") as emailsPaciente from dual ";

        # Conectar base de datos
        $this->conectar_Oracle();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetch();

        if (is_null($data['EMAILSPACIENTE'])) {
            return false;
        }

        return $data['EMAILSPACIENTE'];

        # return 'mchangcnt@gmail.com';

    }

    private function getNombresPaciente($codPersona)
    {

        global $http;

        $sql = "SELECT fun_busca_nombre_persona(" . $codPersona . ") as emailsPaciente from dual ";

        # Conectar base de datos
        $this->conectar_Oracle();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetch();

        if (is_null($data['EMAILSPACIENTE'])) {
            return false;
        }

        return $data['EMAILSPACIENTE'];

    }

    # Procesar envío de Notificación
    private function procesarEnvioMail($idResultado)
    {

        try {

            # Verificar correos del paciente
            $getCorreos = $this->getCorreosPaciente($idResultado['ID_REPORT']);

            $nombresPaciente = $this->getNombresPaciente($idResultado['ID_REPORT']);

            $pos = strpos($getCorreos, '|');

            # Solo un correo
            if ($pos === false) {

                $correoPaiente = $getCorreos;

                $time = time();

                $sts = $this->getCopyInformeResultado($idResultado['ID_STUDIO'], $idResultado['FECHA_REGISTRADO']);

                # return $sts;

                if ($sts['status']) {

                    // Objeto para notificación
                    $dataNotificacion = array(
                        'PACIENTE' => $nombresPaciente,
                        'FECHA' => $idResultado['FECHA_REGISTRADO'],
                        'LINK_INFORME' => $sts['data'],
                        'SC' => $idResultado['ID_STUDIO'],
                    );

                    $getTemplate = $this->getMailNotificacion($dataNotificacion, $correoPaiente);

                    if ($getTemplate) {
                        # Envío existoso
                        return 2;
                    } else {
                        # No se pudo envíar correo correctamente
                        return 3;
                    }

                } else {

                    # No se pudo envíar correo correctamente Error de Lis
                    return 4;

                }

            } else {

                $_correosPacientes = explode('|', $getCorreos);

                $valores = array();

                $sts = $this->getCopyInformeResultado($idResultado['ID_STUDIO'], $idResultado['FECHA_REGISTRADO']);

                # return $sts;

                foreach ($_correosPacientes as $key => $val) {

                    if ($sts['status']) {

                        // Objeto para notificación
                        $dataNotificacion = array(
                            'PACIENTE' => $nombresPaciente,
                            'FECHA' => $idResultado['FECHA_REGISTRADO'],
                            'LINK_INFORME' => $sts['data'],
                            'SC' => $idResultado['ID_STUDIO'],

                        );

                        $getTemplate = $this->getMailNotificacion($dataNotificacion, $val);

                        if ($getTemplate) {
                            # Envío existoso
                            $valores[] = $val;
                        }

                    } else {

                        # No se pudo envíar correo correctamente Error de Lis
                        return 4;

                    }

                }

                if (count($valores) !== 0) {
                    # Envío existoso
                    return 2;
                } else {
                    # No se pudo envíar correo correctamente
                    return 3;
                }

            }
        } catch (ModelsException $e) {

            return false;
        }

    }

    // Notificar Resultados de Laboratorio
    public function notificarResultados(): array
    {

        try {

            global $config, $http;

            $sql = " SELECT * FROM GEMA.LOGS_NOTIFICACION_LABORATORIO
            WHERE STATUS_REGISTRADO = '1'
            AND (STATUS_GENERADO = '1' OR STATUS_GENERADO = '9' )
            AND (STATUS_ENVIADO IS NULL OR STATUS_ENVIADO = '2')
            AND ID_REPORT IS NOT NULL
            ORDER BY ID_STUDIO DESC ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            $this->notResults($data);

            $resultados = array();

            foreach ($data as $key) {

                $statusEnviado = $this->procesarEnvioMail($key);

                if ($statusEnviado == 0) {
                    $log = "User: " . $_SERVER['REMOTE_ADDR'] . ' - ' . date("F j, Y, g:i a") . PHP_EOL .
                        "Message: No existe SC => " . $key['ID_STUDIO'] . PHP_EOL .
                        "-------------------------" . PHP_EOL;

                    //Save string to log, use FILE_APPEND to append.
                    file_put_contents('logs/lab/log_error_0_' . $key['ID_STUDIO'] . '_' . date("j.n.Y.H.i.s") . '.log', $log, FILE_APPEND);
                }

                if ($statusEnviado == 1) {
                    $log = "User: " . $_SERVER['REMOTE_ADDR'] . ' - ' . date("F j, Y, g:i a") . PHP_EOL .
                        "Message: Estudio sin informar todavía SC => " . $key['ID_STUDIO'] . PHP_EOL .
                        "-------------------------" . PHP_EOL;

                    //Save string to log, use FILE_APPEND to append.
                    file_put_contents('logs/lab/log_error_1_' . $key['ID_STUDIO'] . '_' . date("j.n.Y.H.i.s") . '.log', $log, FILE_APPEND);
                }

                if ($statusEnviado == -1) {
                    $log = "User: " . $_SERVER['REMOTE_ADDR'] . ' - ' . date("F j, Y, g:i a") . PHP_EOL .
                        "Message: NHC no existe en BDD GEMA SC => " . $key['ID_STUDIO'] . PHP_EOL .
                        "-------------------------" . PHP_EOL;

                    //Save string to log, use FILE_APPEND to append.
                    file_put_contents('logs/lab/log_error__1_' . $key['ID_STUDIO'] . '_' . date("j.n.Y.H.i.s") . '.log', $log, FILE_APPEND);
                }

                if ($statusEnviado == -2) {
                    $log = "User: " . $_SERVER['REMOTE_ADDR'] . ' - ' . date("F j, Y, g:i a") . PHP_EOL .
                        "Message: NHC no registra correos electrónicos para envío BDD GEMA SC => " . $key['ID_STUDIO'] . PHP_EOL .
                        "-------------------------" . PHP_EOL;

                    //Save string to log, use FILE_APPEND to append.
                    file_put_contents('logs/lab/log_error__2_' . $key['ID_STUDIO'] . '_' . date("j.n.Y.H.i.s") . '.log', $log, FILE_APPEND);
                }

                # SUCCESS
                if ($statusEnviado == 2) {
                    $log = "User: " . $_SERVER['REMOTE_ADDR'] . ' - ' . date("F j, Y, g:i a") . PHP_EOL .
                        "Message: Envío exitoso de notificación SC => " . $key['ID_STUDIO'] . PHP_EOL .
                        "-------------------------" . PHP_EOL;

                    if ($key['STATUS_GENERADO'] == '9') {
                        $this->setEstadoNotificadoMicro($key['ID_STUDIO']);

                    } else {
                        $this->setEstadoNotificado($key['ID_STUDIO']);

                    }

                    //Save string to log, use FILE_APPEND to append.
                    file_put_contents('logs/lab/log_sucess_' . $key['ID_STUDIO'] . '_' . date("j.n.Y.H.i.s") . '.log', $log, FILE_APPEND);
                }

                if ($statusEnviado == 3) {
                    $log = "User: " . $_SERVER['REMOTE_ADDR'] . ' - ' . date("F j, Y, g:i a") . PHP_EOL .
                        "Message: Api Icommkt error en envío SC => " . $key['ID_STUDIO'] . PHP_EOL .
                        "-------------------------" . PHP_EOL;

                    //Save string to log, use FILE_APPEND to append.
                    file_put_contents('logs/lab/log_error_3_' . $key['ID_STUDIO'] . '_' . date("j.n.Y.H.i.s") . '.log', $log, FILE_APPEND);
                }

                $key['statusEnviado'] = $statusEnviado;
                $resultados[] = $key;

            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $resultados,
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    private function setEstadoNotificadoMicro($idEstudio)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            $queryBuilder
                ->update('GEMA.LOGS_NOTIFICACION_LABORATORIO', 'u')
                ->set('u.STATUS_ENVIADO', '?')
                ->set('u.STATUS_GENERADO', '?')
                ->set('u.FECHA_ENVIADO', '?')
                ->where('u.ID_STUDIO=?')
                ->setParameter(0, (int) 1)
                ->setParameter(1, (int) 7)
                ->setParameter(2, date('Y-m-d'))
                ->setParameter(3, (string) $idEstudio)
            ;

            # Execute
            $result = $queryBuilder->execute();

            $this->_conexion->close();

            if (false === $result) {
                throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    private function setEstadoNotificado($idEstudio)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            $queryBuilder
                ->update('GEMA.LOGS_NOTIFICACION_LABORATORIO', 'u')
                ->set('u.STATUS_GENERADO', '?')
                ->set('u.STATUS_ENVIADO', '?')
                ->set('u.FECHA_ENVIADO', '?')
                ->where('u.ID_STUDIO=?')
                ->setParameter(0, (int) 8)
                ->setParameter(1, (int) 1)
                ->setParameter(2, date('Y-m-d'))
                ->setParameter(3, (string) $idEstudio)
            ;

            # Execute
            $result = $queryBuilder->execute();

            $this->_conexion->close();

            if (false === $result) {
                throw new ModelsException('¡Error! Log Informe -> No registrado ', 4001);
            }

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());
        }

    }

    public function getMailNotificacion(array $data = array(), string $correo = 'mchang@hmetro.med.ec')
    {

        global $config, $http;

        # Construir mensaje y enviar mensaje
        $content = '<br />
                    Estimado(a).- <br /><br /><b>' . $data['PACIENTE'] . '</b> esta disponible un nuevo resultado de Laboratorio.
                    <br />
                    <b>Fecha de Examen:</b> ' . $data['FECHA'] . '
                    <br />';

        # Enviar el correo electrónico
        $_html = Helper\Emails::loadTemplate(array(
            # Título del mensaje
            '{{title}}' => 'Nuevo Resultado de Laboratorio - Metrovirtual Hospital Metropolitano',
            # Contenido del mensaje
            '{{content}}' => $content,

            # Texto del boton
            '{{btn-name}}' => 'Ver Resultado de Laboratorio',
            # Copyright
            '{{copyright}}' => '&copy; ' . date('Y') . ' <a href="https://www.hospitalmetropolitano.org">Metrovirtual Hospital Metropolitano</a> Todos los derechos reservados.',
        ), 4);

        # Verificar si hubo algún problema con el envió del correo
        if ($this->sendMailNotificacion($_html, $correo, 'Nuevo Resultado de Laboratorio - Metrovirtual Hospital Metropolitano', $data) != true) {
            return false;
        } else {
            return true;
        }
    }

    public function sendMailNotificacion($html, $to, $subject, $_data)
    {

        global $config;

        $file = $_data['LINK_INFORME'];

        $_file = base64_encode(file_get_contents($file));

        $adjunto = array();

        $adjunto[] = array(
            'Name' => 'resultado_' . $_data['SC'] . '.pdf',
            'ContentType' => 'application/pdf',
            'Content' => $_file,
        );

        $stringData = array(
            "TextBody" => "Resultado de Laboratorio - Metrovirtual",
            'From' => 'Metrovirtual metrovirtual@hospitalmetropolitano.org',
            'To' => $to,
            'Subject' => $subject,
            'HtmlBody' => $html,
            'Attachments' => $adjunto,
            'Tag' => 'NRLP',
            'Bcc' => 'mchang@hmetro.med.ec;resultadoslaboratorio@hmetro.med.ec',
            'TrackLinks' => 'HtmlAndText',
            'TrackOpens' => true,
        );

        $data = json_encode($stringData);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.trx.icommarketing.com/email");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data),
            'X-Postmark-Server-Token: 75032b22-cf9b-4fd7-8eb4-e7446c8b118b',
        ));

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $resultobj = curl_error($ch);
            return false;
        }
        curl_close($ch);
        $resultobj = json_decode($result);

        return true;

    }

    # Metodo wReports webservice laboratorio ROCHEdevuelve el resultado pdf del paciente
    public function wsLab_GET_REPORT_PDF(string $SC, string $FECHA)
    {

        try {

            # INICIAR SESSION
            $this->wsLab_LOGIN();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wReports.wsdl.xml');

            # $FECHA_final = explode('-', $FECHA);

            $Preview = $client->Preview(array(
                "pstrSessionKey" => $this->pstrSessionKey,
                "pstrSampleID" => $SC, # '0015052333',
                "pstrRegisterDate" => $FECHA, # $FECHA_final[2] . '-' . $FECHA_final[1] . '-' . $FECHA_final[0], # '2018-11-05',
                "pstrFormatDescription" => 'METROPOLITANO',
                "pstrPrintTarget" => 'Destino por defecto',
            ));

            # CERRAR SESSION POR LICENCIAS HSF
            $this->wsLab_LOGOUT();

            # No existe documento

            if (!isset($Preview->PreviewResult)) {
                throw new ModelsException('Error 0 => No existe el documento solicitado.');
            }

            # No existe documento

            if (isset($Preview->PreviewResult) or $Preview->PreviewResult == '0') {

                if ($Preview->PreviewResult == '0') {

                    throw new ModelsException('Error 1 => No existe el documento solicitado.');

                } else {

                    return array(
                        'status' => true,
                        'data' => str_replace("172.16.2.221", "resultadosweb.hospitalmetropolitano.org", $Preview->PreviewResult),
                    );

                }

            }

            #
            throw new ModelsException('Error 2 => No existe el documento solicitado.');

        } catch (SoapFault $e) {

            if ($e->getCode() == 0) {
                return array('status' => false, 'message' => $e->getMessage());
            } else {
                return array('status' => false, 'message' => $e->getMessage());

            }

        } catch (ModelsException $b) {

            if ($b->getCode() == 0) {
                return array('status' => false, 'message' => $b->getMessage());
            } else {
                return array('status' => false, 'message' => $b->getMessage());

            }
        }

    }

    private function notResults(array $data)
    {
        if (count($data) == 0) {
            return array(
                'status' => true,
                'customData' => false,
                'total' => 0,
                'start' => 1,
                'length' => 10,
                # 'dataddd' => $http->request->all(),
            );
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

    }
}
