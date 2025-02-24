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

/**
 * Modelo Odbc GEMA -> Forms
 */

class Forms extends Models implements IModels
{
    use DBModel;

    # Variables de clase
    private $val                 = null;
    private $sortCategory        = null;
    private $sortField           = 'ROWNUM_';
    private $filterField         = null;
    private $sortType            = 'desc'; # desc
    private $offset              = 1;
    private $limit               = 25;
    private $searchField         = null;
    private $startDate           = null;
    private $endDate             = null;
    private $foto_dummy          = 'assets/doctores/doc.jpg';
    private $_conexion           = null;
    private $_medicos_first_load = array();

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();
//..
        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);

    }

    public function postData()
    {

        global $http;

        $camp = $http->request->get('camp');

        switch ($camp) {

            case 'bariatrica':

                $data = $http->request->all();

                return $this->contactBariatrica($data);

                break;

            case 'metromaternidad':

                $data = $http->request->all();

                return $this->contactMetromaternidad($data);

                break;

            case 'chequeo-mujer':

                $data = $http->request->all();

                return $this->contactChequeoMujer($data);

                break;

            case 'chequeo-medico':

                $data = $http->request->all();

                return $this->contactChequeoMedico($data);

                break;

            default:

                return array(
                    'success' => true,
                    'data'    => $http->request->all(),
                );

                break;
        }

    }

    # Insert Google Spreadsheet CAMP cHEQUEO mÉDICO
    public function contactChequeoMedico(array $data): array
    {

        //datos a enviar
        $datos = array(

            // Nombres
            "entry.1681628738" => strtoupper($data['nombres']),
            // Apellidos
            "entry.374349865"  => strtoupper($data['apellidos']),
            // Célular
            "entry.1572758056" => strtoupper($data['celular']),
            // Email
            "entry.998095440"  => strtoupper($data['email']),
            // Email
            "entry.1159254824" => strtoupper($data['edad']),
            // Genero
            "entry.113122070"  => strtoupper($data['genero']),
            // Colesterol
            "entry.2109077842" => strtoupper($data['colesterol']),
            // Ténsión Arterial
            "entry.298447530"  => strtoupper($data['tension-arterial']),
            // Ejercicio Frecuente
            "entry.809378895"  => strtoupper($data['ejercicio-frecuente']),
            // Antecedentes Familiares
            "entry.2141748804" => strtoupper($data['antecedentes-familiares']),
            // Canal
            "entry.592676469"  => (isset($data['target'])) ? strtoupper($data['target']) : 'WEB',

        );
        //url contra la que atacamos
        $ch = curl_init("https://docs.google.com/forms/d/e/1FAIpQLSe02JF2Q9Xsox4fzMMr2o5lFD0iqAPofgmBy15YvZMSOku06Q/formResponse");
        //a true, obtendremos una respuesta de la url, en otro caso,
        //true si es correcto, false si no lo es
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //establecemos el verbo http que queremos utilizar para la petición
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        //enviamos el array data
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($datos));
        //obtenemos la respuesta
        $response = curl_exec($ch);
        // Se cierra el recurso CURL y se liberan los recursos del sistema
        curl_close($ch);

        if (!$response) {

            return array(
                'success' => 0,
                'message' => false,
            );

        } else {

            return array(
                'success' => 1,
                'message' => $this->isHTML($response),
            );

        }

    }

    # Insert Google Spreadsheet CAMP cHEQUEO mÉDICO
    public function contactChequeoMujer(array $data): array
    {

        //datos a enviar
        $datos = array(

            // Nombres
            "entry.1780761519" => strtoupper($data['nombres']),
            // Apellidos
            "entry.795023913"  => strtoupper($data['apellidos']),
            // Célular
            "entry.920636439"  => strtoupper($data['telefono']),
            // Email
            "entry.110338443"  => strtoupper($data['email']),
            // Email
            "entry.1770630294" => strtoupper($data['edad']),
            // Canal
            "entry.1556342460" => (isset($data['target'])) ? strtoupper($data['target']) : 'WEB',

        );
        //url contra la que atacamos
        $ch = curl_init("https://docs.google.com/forms/d/e/1FAIpQLSeEY6ZmP46Xy5XV5wCCnszj4npdzwmUxbL3hUOWG7qJZ9o_8Q/formResponse");
        //a true, obtendremos una respuesta de la url, en otro caso,
        //true si es correcto, false si no lo es
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //establecemos el verbo http que queremos utilizar para la petición
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        //enviamos el array data
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($datos));
        //obtenemos la respuesta
        $response = curl_exec($ch);
        // Se cierra el recurso CURL y se liberan los recursos del sistema
        curl_close($ch);

        if (!$response) {

            return array(
                'success' => 0,
                'message' => false,
            );

        } else {

            return array(
                'success' => 1,
                'message' => $this->isHTML($response),
            );

        }

    }

    # Insert Google Spreadsheet contactMetromaternidad
    public function contactMetromaternidad(array $data): array
    {

        //datos a enviar
        $datos = array(

            // Nombres
            "entry.1933501728" => strtoupper($data['nombres-apellidos']),
            // Email
            "entry.1446073844" => strtoupper($data['email']),
            // Email
            "entry.1258319244" => strtoupper($data['celular']),
            // Email
            "entry.753499934"  => strtoupper($data['plan']),
            // Canal
            "entry.476360903"  => (isset($data['target'])) ? strtoupper($data['target']) : 'WEB',

        );
        //url contra la que atacamos
        $ch = curl_init("https://docs.google.com/forms/d/e/1FAIpQLSfWhYY5psNjaBdAFpKckPenbT6HK9j3Ii1Vjp3NdbTO7x1xLQ/formResponse");
        //a true, obtendremos una respuesta de la url, en otro caso,
        //true si es correcto, false si no lo es
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //establecemos el verbo http que queremos utilizar para la petición
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        //enviamos el array data
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($datos));
        //obtenemos la respuesta
        $response = curl_exec($ch);
        // Se cierra el recurso CURL y se liberan los recursos del sistema
        curl_close($ch);

        if (!$response) {

            return array(
                'success' => 0,
                'message' => false,
            );

        } else {

            return array(
                'success' => 1,
                'message' => $this->isHTML($response),
            );

        }

    }

    # Insert Google Spreadsheet contactMetrored
    public function contactMetrored(array $data): array
    {

        //datos a enviar
        $datos = array(

            // Nombres
            "entry.1933501728" => strtoupper($data['nombres-apellidos']),
            // Email
            "entry.1446073844" => strtoupper($data['email']),
            // Email
            "entry.1258319244" => strtoupper($data['celular']),
            // Email
            "entry.753499934"  => strtoupper($data['plan']),
            // Canal
            "entry.476360903"  => (isset($data['target'])) ? strtoupper($data['target']) : 'WEB',

        );
        //url contra la que atacamos
        $ch = curl_init("https://docs.google.com/forms/d/e/1FAIpQLSfWhYY5psNjaBdAFpKckPenbT6HK9j3Ii1Vjp3NdbTO7x1xLQ/formResponse");
        //a true, obtendremos una respuesta de la url, en otro caso,
        //true si es correcto, false si no lo es
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //establecemos el verbo http que queremos utilizar para la petición
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        //enviamos el array data
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($datos));
        //obtenemos la respuesta
        $response = curl_exec($ch);
        // Se cierra el recurso CURL y se liberan los recursos del sistema
        curl_close($ch);

        if (!$response) {

            return array(
                'success' => 0,
                'message' => false,
            );

        } else {

            return array(
                'success' => 1,
                'message' => $this->isHTML($response),
            );

        }

    }

    # Insert Google Spreadsheet
    public function contactBariatrica(array $data): array
    {

        //datos a enviar
        $datos = array(
            //Nombres y Apellidos
            "entry.1976101719" => strtoupper($data['name']),
            //Cedula
            #  "entry.703000541"  => strtoupper($data['cc']),
            //Edad
            "entry.225951531"  => strtolower($data['email']),
            //Fecha de parto
            # "entry.1447983478" => strtolower($data['cel']),
            //Req
            # "entry.976655909"  => strtolower($data['req']),

            #  'entry.1833570191' => $data['target'],

        );
        //url contra la que atacamos
        $ch = curl_init("https://docs.google.com/forms/d/e/1FAIpQLSdc5lHTw9f8HqajvOG3fRAXTrqwk94jyj5Ibe_llLWjwF6hTA/formResponse");
        //a true, obtendremos una respuesta de la url, en otro caso,
        //true si es correcto, false si no lo es
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //establecemos el verbo http que queremos utilizar para la petición
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        //enviamos el array data
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($datos));
        //obtenemos la respuesta
        $response = curl_exec($ch);
        // Se cierra el recurso CURL y se liberan los recursos del sistema
        curl_close($ch);

        if (!$response) {

            return array(
                'success' => 0,
                'message' => false,
            );

        } else {

            return array(
                'success' => 1,
                'message' => $this->isHTML($response),
            );

        }

    }

    public function isHTML($string)
    {
        if ($string != strip_tags($string)) {
            // is HTML
            return true;
        } else {
            // not HTML
            return false;
        }
    }

    private function getAuthorizationn($value)
    {

        try {

            global $http;

            $token = $http->headers->get("Authorization");

            $auth = new Model\Auth;
            $key  = $auth->GetData($token);

            $this->val = $key->data[0]->$value;

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    private function errorsPagination()
    {

        try {

            if ($this->limit > 25) {
                throw new ModelsException('!Error! Solo se pueden mostrar 100 resultados por página.');
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

    private function setParameters()
    {

        try {

            global $http;

            foreach ($http->request->all() as $key => $value) {
                $this->$key = $value;
            }

            if ($this->startDate != null and $this->endDate != null) {

                $startDate = $this->startDate;
                $endDate   = $this->endDate;

                $sd = new DateTime($startDate);
                $ed = new DateTime($endDate);

                if ($sd->getTimestamp() > $ed->getTimestamp()) {
                    throw new ModelsException('!Error! Fecha inicial no puede ser mayor a fecha final.');
                }

            }

            if ($this->sortCategory != null) {
                $this->sortCategory = $this->quitar_tildes(mb_strtoupper($this->sanear_string($this->sortCategory), 'UTF-8'));
            }

            if ($this->searchField != null) {
                $this->searchField = $this->quitar_tildes(mb_strtoupper($this->sanear_string($this->searchField), 'UTF-8'));
            }

            return false;
        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    public function getEspecialidades()
    {

        try {

            global $http;

            # ERRORES DE PETICION
            $this->errorsPagination();

            $sql = "SELECT descripcion, ROWNUM  FROM aas_especialidades ORDER BY ROWNUM $this->sortType";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetchAll();

            # Ya no existe resultadso
            if (false === $data) {
                throw new ModelsException('No existe más resultados.', 4080);
            }

            # Datos de usuario cuenta activa
            $notas = array();

            foreach ($data as $key) {
                unset($key['ROWNUM']);
                $notas[] = $key;
            }

            // RESULTADO DE CONSULTA

            # Order by asc to desc
            $NOTAS_DE_CREDITO = $this->get_Order_Pagination($notas);

            # Devolver Información
            return array(
                'status' => true,
                'data'   => $this->get_page($NOTAS_DE_CREDITO, $this->offset, $this->limit),
                'total'  => count($notas),
                'limit'  => intval($this->limit),
                'offset' => intval($this->offset),
            );

        } catch (ModelsException $e) {

            if ($e->getCode() == 4080) {

                return array(
                    'status'    => true,
                    'data'      => [],
                    'total'     => 0,
                    'message'   => $e->getMessage(),
                    'errorCode' => 4080,
                );

            }

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function setRename()
    {

        try {
            # hasta 90 kb

            global $http;

            $data = Helper\Files::get_files_in_dir('\\172.16.64.87\f\Respaldos EchoPAC\2016');

            # Datos de usuario cuenta activa
            $notas = array();

            foreach ($data as $key) {

                $notas[] = $key;
            }

            # Devolver Información
            return array(
                'status' => true,
                'data'   => $notas,
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

# Ordenar array por campo
    public function orderMultiDimensionalArray($toOrderArray, $field, $inverse = 'desc')
    {
        $position = array();
        $newRow   = array();
        foreach ($toOrderArray as $key => $row) {
            $position[$key] = $row[$field];
            $newRow[$key]   = $row;
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
                $arr[]      = $key;
                $NUM--;
            }

            return $arr;

        }

        # SI ES ASCENDENTE

        foreach ($arr_input as $key) {
            $key['NUM'] = $NUM;
            $arr[]      = $key;
            $NUM++;
        }

        return $arr;
    }

    private function get_page(array $input, $pageNum, $perPage)
    {
        $start = ($pageNum - 1) * $perPage;
        $end   = $start + $perPage;
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

    public function shuffle_assoc($array)
    {
        $keys = array_keys($array);

        shuffle($keys);

        foreach ($keys as $key) {
            $new[$key] = $array[$key];
        }

        $array = $new;

        return $array;
    }

    public function getFotos()
    {

        $fotos = $this->db->select('*', 'fots_medico', null, 'med like "%%"');

        $files = Helper\Files::get_files_in_dir('v1/assets/doctores/');

        $names = array();

        foreach ($files as $key => $value) {

            $name   = explode('/', $value);
            $name   = explode('copia', $name[3]);
            $name   = strtoupper($name[0]);
            $n_     = explode(' ', $name);
            $name_N = (isset($n_[1])) ? $n_[1] . ' ' . $n_[0] : $n_[0];

            $fotos = $this->db->select('*', 'fotos_medicos', null, 'MED LIKE "%' . $name_N . '%"', null);
            if (false != $fotos) {
                $names[] = array(
                    'foto' => $value,
                    'name' => $fotos[0]['MED'],
                    'cod'  => $fotos[0]['COD'],
                );
            }

        }

        return $names;

        $fotos = array(

            '850'    => 'assets/doctores/850.jpg',
            '939'    => 'assets/doctores/939.jpg',
            '10579'  => 'assets/doctores/10579.jpg',
            '10791'  => 'assets/doctores/10791.jpg',
            '010144' => 'assets/doctores/010144.jpg',
            '424'    => 'assets/doctores/424.jpg',
            '010298' => 'assets/doctores/010298.jpg',
            '197'    => 'assets/doctores/197.jpg',
            '190'    => 'assets/doctores/190.jpg',
            '421'    => 'assets/doctores/421.jpg',
            '10627'  => 'assets/doctores/10627.jpg',
            '63'     => 'assets/doctores/63.jpg',
            '10253'  => 'assets/doctores/10253.jpg',

        );
    }

    private function normaliza($cadena)
    {
        $originales = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÎÏÐÒÓÔÕÖØÙÚÛÜÝÞ
ßàáâãäåæçèéêëìîïðòóôõöøùúûýýþÿŔŕ';
        $modificadas = 'aaaaaaaceeeeiiiidnoooooouuuuy
bsaaaaaaaceeeeiiiidnoooooouuuyybyRr';
        $cadena = utf8_decode($cadena);
        $cadena = strtr($cadena, utf8_decode($originales), $modificadas);
        return utf8_encode($cadena);
    }

    private function quitar_tildes($cadena)
    {
        $no_permitidas = array("%", "é", "í", "ó", "ú", "É", "Í", "Ó", "Ú", "ñ", "À", "Ã", "Ì", "Ò", "Ù", "Ã™", "Ã ", "Ã¨", "Ã¬", "Ã²", "Ã¹", "ç", "Ç", "Ã¢", "ê", "Ã®", "Ã´", "Ã»", "Ã‚", "ÃŠ", "ÃŽ", "Ã”", "Ã›", "ü", "Ã¶", "Ã–", "Ã¯", "Ã¤", "«", "Ò", "Ã", "Ã„", "Ã‹");
        $permitidas    = array("", "e", "i", "o", "u", "E", "I", "O", "U", "n", "N", "A", "E", "I", "O", "U", "a", "e", "i", "o", "u", "c", "C", "a", "e", "i", "o", "u", "A", "E", "I", "O", "U", "u", "o", "O", "i", "a", "e", "U", "I", "A", "E");
        $texto         = str_replace($no_permitidas, $permitidas, $cadena);
        return $texto;
    }

    private function sanear_string($string)
    {

        $string = trim($string);

        //Esta parte se encarga de eliminar cualquier caracter extraño
        $string = str_replace(
            array(">", "< ", ";", ",", ":", " ", "%"),
            ' ',
            $string
        );

        return trim($string);
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
