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
use Exception;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Odbc GEMA -> FACTURAS
 */

class Facturas extends Models implements IModels
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
    private $tresMeses = null; # Se muestran resultados solo hasta los tres meses de la fecha actual
    private $_conexion = null;

    # Variables de clase
    private $conexion;
    private $numeroHistoriaClinica;
    private $numeroAdmision;
    private $codigoHorario;
    private $numeroTurno;

    /**
     * Parámetros de generar una factura web
     */
    private function setParametersGenerarFacturaWeb()
    {
        global $http;

        foreach ($http->request->all() as $key => $value) {
            $this->$key = strtoupper($value);
        }

    }

    /**
     * Valida los parámetros de entrada generar factura web
     */
    private function validarParametrosGenerarFacturaWeb()
    {
        global $config;

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

        //Número de historia clínica
        if ($this->numeroHistoriaClinica == null) {
            throw new ModelsException($config['errors']['numeroHistoriaClinicaObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->numeroHistoriaClinica)) {
                throw new ModelsException($config['errors']['numeroHistoriaClinicaNumerico']['message'], 1);
            }
        }

        //Número de admisión
        if ($this->numeroAdmision == null) {
            throw new ModelsException($config['errors']['numeroAdmisionObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->numeroAdmision)) {
                throw new ModelsException($config['errors']['numeroAdmisionNumerico']['message'], 1);
            }
        }
    }

    /**
     * Permite generar una factura web
     */
    public function generarFacturaWeb()
    {
        global $config;

        //Inicialización de variables
        $stmt = null;
        $codigoRetorno = null;
        $mensajeRetorno = null;

        try {

            //Asignar parámetros de entrada
            $this->setParametersGenerarFacturaWeb();

            //Validar parámetros de entrada
            $this->validarParametrosGenerarFacturaWeb();

            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stmt);

            $stmt = oci_parse($this->conexion->getConexion(), 'BEGIN
                PRO_GENERA_FACTURA_WEB(:pn_hcl, :pn_adm, :pn_horario, :pn_turno, :pc_error, :pc_desc_error); END;');

            // Bind the input parameter
            oci_bind_by_name($stmt, ':pn_hcl', $this->numeroHistoriaClinica, 32);
            oci_bind_by_name($stmt, ':pn_adm', $this->numeroAdmision, 32);
            oci_bind_by_name($stmt, ':pn_horario', $this->codigoHorario, 32);
            oci_bind_by_name($stmt, ':pn_turno', $this->numeroTurno, 32);

            // Bind the output parameter
            oci_bind_by_name($stmt, ':pc_error', $codigoRetorno, 32);
            oci_bind_by_name($stmt, ':pc_desc_error', $mensajeRetorno, 500);

            oci_execute($stmt);

            //Valida el código de retorno del SP
            if ($codigoRetorno == 0) {
                //Cita cancelada exitosamente
                return array(
                    'status' => true,
                    'data' => [],
                    'message' => $mensajeRetorno
                );
            } elseif ($codigoRetorno == -1) {
                //Mensajes de aplicación
                throw new ModelsException($mensajeRetorno, 1);
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

    private function errorsPagination()
    {

        try {

            if ($this->limit > 25) {
                throw new ModelsException('!Error! Solo se pueden mostrar 25 resultados por página.');
            }

            if ($this->limit == 0 or $this->limit < 0) {
                throw new ModelsException('!Error! {Limit} no puede ser 0 o negativo');
            }

            if ($this->offset == 0 or $this->offset < 0) {
                throw new ModelsException('!Error! {Offset} no puede ser 0 o negativo.');
            }

            return false;
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    private function setParameters(array $data)
    {

        try {

            foreach ($data as $key => $value) {

                $this->$key = strtoupper($value);
            }

            if ($this->startDate != null and $this->endDate != null) {

                $startDate = $this->startDate;
                $endDate = $this->endDate;

                $sd = new DateTime($startDate);
                $ed = new DateTime($endDate);

                if ($sd->getTimestamp() > $ed->getTimestamp()) {
                    throw new ModelsException('!Error! Fecha inicial no puede ser mayor a fecha final.');
                }

            }

            $fecha = date('d-m-Y');
            $nuevafecha = strtotime('-3 month', strtotime($fecha));

            # SETEAR FILTRO HASTA TRES MESES
            $this->tresMeses = date('d-m-Y', $nuevafecha);

            return false;
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    public function getFacturas(): array
    {

        try {

            global $http, $config;

            # SETEAR VARIABLES DE CLASE
            $errorsSetParameters = $this->setParameters($http->request->all());

            if (!is_bool($errorsSetParameters)) {
                return $errorsSetParameters;
            }

            # ERRORES DE PETICION
            $errorsPagination = $this->errorsPagination();

            if (!is_bool($errorsPagination)) {
                return $errorsPagination;
            }

            # EXTRAER VALOR DEL TOKEN PARA CONSULTA
            $this->getAuthorizationn();

            # setear codigos para query
            $codes = implode(',', $this->USER->CP_PTE);

            # CONULTA BDD GEMA
            if ($this->startDate != null and $this->endDate != null) {

                $sql = "SELECT WEB2_VW_FACTURAS.*, ROWNUM AS ROWNUM_ FROM WEB2_VW_FACTURAS WHERE COD_PERSONA IN ($codes)  AND FECHA_FACTURA >= TO_DATE('$this->startDate', 'dd-mm-yyyy') AND FECHA_FACTURA <= TO_DATE('$this->endDate', 'dd-mm-yyyy') AND FECHA_FACTURA >= TO_DATE('$this->tresMeses', 'dd-mm-yyyy') ORDER BY FECHA_FACTURA $this->sortType";

            } elseif ($this->sortField == 'FACT' and $this->searchField == '') {

                $sql = "SELECT WEB2_VW_FACTURAS.*, ROWNUM AS ROWNUM_ FROM WEB2_VW_FACTURAS WHERE COD_PERSONA IN ($codes) AND FECHA_FACTURA >= TO_DATE('$this->tresMeses', 'dd-mm-yyyy') ORDER BY ROWNUM_ $this->sortType";

            } elseif ($this->sortField == 'FACT' and $this->searchField != null) {

                $sql = "SELECT WEB2_VW_FACTURAS.*, ROWNUM AS ROWNUM_ FROM WEB2_VW_FACTURAS WHERE COD_PERSONA IN ($codes) AND (ORIGEN LIKE '%$this->searchField%' OR SERIE LIKE '%$this->searchField%' OR NUMERO LIKE '%$this->searchField%' OR TOTAL LIKE '%$this->searchField%' OR PAGADOR LIKE '%$this->searchField%'  OR ADMISION LIKE '%$this->searchField%') AND FECHA_FACTURA >= TO_DATE('$this->tresMeses', 'dd-mm-yyyy') ORDER BY ROWNUM_ $this->sortType ";

            } elseif ($this->searchField != null) {

                $sql = "SELECT WEB2_VW_FACTURAS.*, ROWNUM AS ROWNUM_ FROM WEB2_VW_FACTURAS WHERE COD_PERSONA IN ($codes) AND (ORIGEN LIKE '%$this->searchField%' OR SERIE LIKE '%$this->searchField%' OR NUMERO LIKE '%$this->searchField%' OR TOTAL LIKE '%$this->searchField%' OR PAGADOR LIKE '%$this->searchField%' OR ADMISION LIKE '%$this->searchField%') AND FECHA_FACTURA >= TO_DATE('$this->tresMeses', 'dd-mm-yyyy') ORDER BY $this->sortField $this->sortType ";

            } else {

                $sql = "SELECT WEB2_VW_FACTURAS.*, ROWNUM AS ROWNUM_ FROM WEB2_VW_FACTURAS WHERE COD_PERSONA IN ($codes) AND FECHA_FACTURA >= TO_DATE('$this->tresMeses', 'dd-mm-yyyy') ORDER BY $this->sortField $this->sortType";

            }

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            # Datos de usuario cuenta activa
            $facturas = array();

            foreach ($stmt->fetchAll() as $key) {

                $key['NUM'] = intval($key['ROWNUM_']);

                $key['ADM'] = $key['ADMISION'];

                $key['EST'] = substr($key['SERIE'], 0, -3);
                $key['PTO'] = substr($key['SERIE'], 3);
                $key['SEC'] = $key['NUMERO'];

                $key['FECHA_ADM'] = date('d-m-Y', strtotime($key['FECHA_ADM']));
                $key['FECHA_ALTA'] = date('d-m-Y', strtotime($key['FECHA_ALTA']));
                $key['FECHA_FACTURA'] = date('d-m-Y', strtotime($key['FECHA_FACTURA']));
                $key['FECHA_REGISTRO'] = date('d-m-Y', strtotime($key['FECHA_FACTURA']));

                switch ($key['TIPO']) {

                    case 'NC':
                        # NOTA DE CREDITO
                        $key['TIPO'] = '04';
                        break;

                    case 'AF':
                        # ANULACION DE FACTURA
                        $key['TIPO'] = '04';
                        break;

                    default:
                        # FACTURA
                        $key['TIPO'] = '01';
                        break;
                }

                $facturas[] = array(
                    'NUM' => $key['NUM'],
                    'TIPO' => $key['TIPO'],
                    'ORIGEN' => $key['ORIGEN'],
                    'FECHA_ADM' => $key['FECHA_ADM'],
                    'FECHA_ALTA' => $key['FECHA_ALTA'],
                    'FECHA_FACTURA' => $key['FECHA_FACTURA'],
                    'FECHA_REGISTRO' => $key['FECHA_REGISTRO'],
                    'TOTAL' => $key['TOTAL'],
                    'PAGADOR' => $key['PAGADOR'],
                    'NHC' => '',
                    'ADM' => $key['ADM'],
                    'FACT' => $key['EST'] . '-' . $key['PTO'] . '-' . $key['SEC'],
                );
            }

            // RESULTADO DE CONSULTA

            # Ya no existe resultadso
            if (count($facturas) == 0) {
                throw new ModelsException('No existen resultados.', 4080);
            }

            # Order by asc to desc
            $FACTURAS = $this->get_Order_Pagination($facturas);

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->get_page($FACTURAS, $this->offset, $this->limit),
                'total' => count($facturas),
                'limit' => intval($this->limit),
                'offset' => intval($this->offset),
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

    # Ordenar array por campo
    public function orderMultiDimensionalArray($toOrderArray, $field, $inverse = 'desc')
    {
        $position = array();
        $newRow = array();
        foreach ($toOrderArray as $key => $row) {
            $position[$key] = $row[$field];
            $newRow[$key] = $row;
        }
        if ($inverse == 'desc') {
            arsort($position);
        } else {
            asort($position);
        }
        $returnArray = array();
        foreach ($position as $key => $pos) {
            $returnArray[] = $newRow[$key];
        }
        return $returnArray;
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
