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

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            # Query
            $queryBuilder
                ->insert('GEMA.LOGS_NOTIFICACION_LABORATORIO')
                ->values(
                    array(
                        'ID_STUDIO' => '?',
                        'ID_REPORT' => '?',
                        'STATUS_REGISTRADO' => '?',
                        'FECHA_REGISTRADO' => '?',
                    )
                )
                ->setParameter(0, (string) $dataInforme['ID_STUDIO'])
                ->setParameter(1, (string) $dataInforme['COD_PERSONA'])
                ->setParameter(2, (int) 1)
                ->setParameter(3, date('Y-m-d'))
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

    // Extrae resultados para notificacion
    public function getTasksResultados(): array
    {

        try {

            global $config, $http;

            $sql = " SELECT *
            FROM WEB2_RESULTADOS_LAB
            WHERE  to_char(FECHA, 'YYYY-MM-DD') = '" . date('Y-m-d') . "' AND TOT_SC != TOD_DC
            ORDER BY FECHA DESC ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Set spanish
            $this->setSpanishOracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # NO EXITEN RESULTADOS
            $this->notResults($data);

            # Datos de usuario cuenta activa
            $resultados = array();

            $time = time();

            foreach ($data as $key) {

                // INSERTAR LOGS PARA LABORATORIO
                $log = array(
                    'ID_STUDIO' => $key['SC'],
                    'COD_PERSONA' => $key['COD_PERSONA'],
                    'FECHA_REGISTRADO' => $key['FECHA'],
                    'STATUS_REGISTRADO' => (int) 1,
                );

                $registrado = $this->getRegistroResultado($key['SC']);

                // No existe log procesado
                if (!$registrado) {
                    $this->insertarNuevoRegistroLogs($log);
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

    # Metodo wReports webservice laboratorio ROCHEdevuelve el resultado pdf del paciente
    public function getInformeResultado(string $SC, string $FECHA)
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
                        'data' => $Preview->PreviewResult,
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

    private function actualizarRegistroSinFirmaLogs($dataInforme)
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

    private function actualizarRegistroLogs($dataInforme)
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
                ->setParameter(0, (int) 1)
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

    // Estraer datos del paciente para ntoificar.
    public function getTasksInformarResultado()
    {

        try {

            global $config;

            $sql = " SELECT * FROM GEMA.LOGS_NOTIFICACION_LABORATORIO WHERE STATUS_REGISTRADO = '1' AND STATUS_GENERADO IS NULL AND STATUS_ENVIADO IS NULL AND FECHA_REGISTRADO = '" . date('Y-m-d') . "' ORDER BY FECHA_REGISTRADO DESC ";

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

                $dataEstudio = array(
                    'idResultado' => $data['ID_STUDIO'],
                    'fecha_resultado' => $data['FECHA_REGISTRADO'],
                );

                $this->actualizarRegistroSinFirmaLogs($dataEstudio);

                return array('status' => false, 'message' => 'No procesado => ' . $dataEstudio['idResultado']);

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

                $sts = $this->getInformeResultado($idResultado['ID_STUDIO'], $idResultado['FECHA_REGISTRADO']);

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

                foreach ($_correosPacientes as $key => $val) {

                    $sts = $this->getInformeResultado($idResultado['ID_STUDIO'], $idResultado['FECHA_REGISTRADO']);

                    # return $sts;

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

            $sql = " SELECT * FROM GEMA.LOGS_NOTIFICACION_LABORATORIO WHERE STATUS_REGISTRADO = '1' AND STATUS_GENERADO = '1' AND STATUS_ENVIADO IS NULL AND TO_CHAR(FECHA_GENERADO, 'YYYY-MM-DD') = '" . date('Y-m-d') . "'  ";

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

                    $this->setEstadoNotificado($key['ID_STUDIO']);

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

    private function setEstadoNotificado($idEstudio)
    {

        try {

            # Conectar base de datos
            $this->conectar_Oracle();

            # QueryBuilder
            $queryBuilder = $this->_conexion->createQueryBuilder();

            $queryBuilder
                ->update('GEMA.LOGS_NOTIFICACION_LABORATORIO', 'u')
                ->set('u.STATUS_ENVIADO', '?')
                ->set('u.FECHA_ENVIADO', '?')
                ->where('u.ID_STUDIO=?')
                ->setParameter(0, (int) 1)
                ->setParameter(1, date('Y-m-d'))
                ->setParameter(2, (string) $idEstudio)
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
                    Estimado(a).- <br /><br /><b>' . $data['PACIENTE'] . '</b> paciente esta disponible un nuevo resultado de Laboratorio.
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
        if ($this->sendMailNotificacion($_html, 'mchang@hmetro.med.ec', 'Nuevo Resultado de Laboratorio - Metrovirtual Hospital Metropolitano', $data) != true) {
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
            'Bcc' => 'mchang@hmetro.med.ec;gracerecalde@hotmail.com',
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
            'X-Postmark-Server-Token: 6f347f1d-faa5-4989-aee4-a955c677dc6b',
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
