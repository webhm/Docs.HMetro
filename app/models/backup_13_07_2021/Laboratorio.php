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

        $sql = " alter session set NLS_DATE_FORMAT = 'DD/MM/YYYY' ";
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

            $codigoPersonaPaciente = $http->request->get('codigoPersonaPaciente');

            # seteo de valores para paginacion
            $this->start = (int) $http->request->get('start');

            $this->length = (int) $http->request->get('length');

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

                $resultados[$key['ROWNUM']] = $key;
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

            # Set accestoken
            $this->accessToken = $auth->generateKeyZoom()['zoom_token'];

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

# Metodo wReports webservice laboratorio ROCHEdevuelve el resultado pdf del paciente
    public function wsLab_GET_REPORT_PDF(string $SC, string $FECHA)
    {

        try {

            # INICIAR SESSION
            $this->wsLab_LOGIN();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wReports.wsdl.xml');

            $FECHA_final = explode('-', $FECHA);

            $Preview = $client->Preview(array(
                "pstrSessionKey" => $this->pstrSessionKey,
                "pstrSampleID" => $SC, # '0015052333',
                "pstrRegisterDate" => $FECHA_final[2] . '-' . $FECHA_final[1] . '-' . $FECHA_final[0], # '2018-11-05',
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
                        'data' => str_replace("SERVER-ROCHE", "resultados.hmetro.med.ec", $Preview->PreviewResult),
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

/**
 * __construct()
 */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);

    }
}
