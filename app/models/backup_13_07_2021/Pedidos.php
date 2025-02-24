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
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Exception;

/**
 * Modelo Odbc GEMA -> Exámenes
 */

class Pedidos extends Models implements IModels
{
    use DBModel;

    # Variables de clase    
    private $conexion;
    private $numeroCompania = '01';
    private $codigoInstitucion = 1;
    private $numeroHistoriaClinica;
    private $numeroAdmision;        
    private $tamanioCodigoExamen = 9;
    private $tamanioDescripcionExamen = 120;
    private $entidadPedido = null;
    private $tipoPedido = '';

    /**
     * Asigna los parámetros de entrada
     */
    private function setParametersPedidos()
    {
        global $http, $config;

        $this->entidadPedido = $http->request->all();

        //Antes de asignar todos los datos de la HCL se valida la información
        $this->validarParametrosPedidos($this->entidadPedido);
    }
    
    /**
     * Valida los parámetros de entrada
     */
    private function validarParametrosPedidos($entidadPedido){
        global $config;
        
        //Viene datos de la HCl
        if (count($entidadPedido) == 0) {
            throw new ModelsException($config['errors']['pedidoObligatorio']['message'], 1);
        }

        //Número de historia clínica
        if ($entidadPedido['numeroHistoriaClinica'] == null){
             throw new ModelsException($config['errors']['numeroHistoriaClinicaObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($entidadPedido['numeroHistoriaClinica'])) {
                    throw new ModelsException($config['errors']['numeroHistoriaClinicaNumerico']['message'], 1);
            }
        }
        
        //Número de admisión
        if ($entidadPedido['numeroAdmision'] == null){
             throw new ModelsException($config['errors']['numeroAdmisionObligatorio']['message'], 1);
        } else {            
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($entidadPedido['numeroAdmision'])) {
                    throw new ModelsException($config['errors']['numeroAdmisionNumerico']['message'], 1);
            }
        }

        //Pedidos laboratorio debe tener datos
        if (empty($entidadPedido['pedidos'])){
             throw new ModelsException($config['errors']['pedidosObligatorio']['message'], 1);
        } else {
            foreach ($entidadPedido['pedidos'] as $pedido) {
                //Código
                if ($pedido['codigoExamen'] == null){
                        throw new ModelsException($config['errors']['codigoExamenObligatorio']['message'], 1);
                } else {
                    //Validaciones de tipo de datos y rangos permitidos
                    if (strlen($pedido['codigoExamen']) > $this->tamanioCodigoExamen) {
                            throw new ModelsException($config['errors']['codigoExamenTamanio']['message'], 1);
                    }
                }

                //Descripción
                if ($pedido['descripcionExamen'] == null){
                        throw new ModelsException($config['errors']['descripcionExamenObligatorio']['message'], 1);
                }  else {
                    //Validaciones de tipo de datos y rangos permitidos
                    if (strlen($pedido['descripcionExamen']) > $this->tamanioDescripcionExamen) {
                            throw new ModelsException($config['errors']['descripcionExamenTamanio']['message'], 1);
                    }
                }

                //Precio venta al público
                if ($pedido['precioVentaPublico'] == null){
                        throw new ModelsException($config['errors']['precioVentaPublicoObligatorio']['message'], 1);
                }

            }
        }
    }   

    /**
     * Asigna los parámetros de entrada
     */
    private function setParametersConsultaPedidos()
    {
        global $http;

        foreach ($http->request->all() as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Valida los parámetros de entrada
     */
    private function validarParametrosConsultaPedidos(){
        global $config;
                
        //Número de historia clínica
        if ($this->numeroHistoriaClinica == null){
             throw new ModelsException($config['errors']['numeroHistoriaClinicaObligatorio']['message'], 1);
        } else {
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->numeroHistoriaClinica)) {
                    throw new ModelsException($config['errors']['numeroHistoriaClinicaNumerico']['message'], 1);
            }
        }
        
        //Número de admisión
        if ($this->numeroAdmision == null){
             throw new ModelsException($config['errors']['numeroAdmisionObligatorio']['message'], 1);
        } else {            
            //Validaciones de tipo de datos y rangos permitidos
            if (!is_numeric($this->numeroAdmision)) {
                    throw new ModelsException($config['errors']['numeroAdmisionNumerico']['message'], 1);
            }
        }       
    } 

    /**
     * Permite crear pedidos de laboratorio
     */
    public function crearPedidosLaboratorio()
    {
        global $config;

        //Inicialización de variables
        $stmt = null;
        $codigoRetorno = null;
        $mensajeRetorno = 'Pedidos de laboratorio insertados correctamente.';        
        $rCommit = FALSE;
        $codigoError = -1;
        $mensajeError;           
       
        try {
            //Asignar parámetros de entrada            
            $this->setParametersPedidos();

            //Conectar a la BDD
            $this->conexion->conectar();

             //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stmt);

             //Pedidos de Laboratorio
            foreach ($this->entidadPedido['pedidos'] as $pedidoLaboratorio) {
                
                $this->insertarPedidoLaboratorio($pedidoLaboratorio, $this->entidadPedido['numeroHistoriaClinica'], $this->entidadPedido['numeroAdmision'], $this->codigoInstitucion, $this->numeroCompania, $stmt, $this->conexion->getConexion());

            }
             
            $rCommit = oci_commit($this->conexion->getConexion());

            return array(
                        'status' => true,
                        'data'   => [],
                        'message'   => $mensajeRetorno
                    );
                                        
        } catch (ModelsException $e) {

            return array(
                    'status'    => false,
                    'data'      => [],
                    'message'   => $e->getMessage(),
                    'errorCode' => $e->getCode()
                );

        } catch (Exception $ex) {

             //
            $mensajeError = $ex->getMessage();

            return array(
                    'status'    => false,
                    'data'      => [],
                    'message'   => $mensajeError,
                    'errorCode' => $codigoError
                );

        }
        finally {
            //Libera recursos de conexión
            if ($stmt != null){
                oci_free_statement($stmt);
            }

            //Cierra la conexión
            $this->conexion->cerrar();
        }

    }

    /**
     * Permite crear pedidos de imagen
     */
    public function crearPedidosImagen()
    {
        global $config;

        //Inicialización de variables
        $stmt = null;
        $codigoRetorno = null;
        $mensajeRetorno = 'Pedidos de imagen insertados correctamente.';        
        $rCommit = FALSE;
        $codigoError = -1;
        $mensajeError;   
       
        try {
            //Asignar parámetros de entrada            
            $this->setParametersPedidos();

            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stmt);

             //Pedidos de Laboratorio
            foreach ($this->entidadPedido['pedidos'] as $pedidoImagen) {

                $this->insertarPedidoImagen($pedidoImagen, $this->entidadPedido['numeroHistoriaClinica'], $this->entidadPedido['numeroAdmision'], $this->codigoInstitucion, $this->numeroCompania, $stmt, $this->conexion->getConexion());

            }
             
            $rCommit = oci_commit($this->conexion->getConexion());

            return array(
                        'status' => true,
                        'data'   => [],
                        'message'   => $mensajeRetorno
                    );
                                        
        } catch (ModelsException $e) {

            return array(
                    'status'    => false,
                    'data'      => [],
                    'message'   => $e->getMessage(),
                    'errorCode' => $e->getCode()
                );

        } catch (Exception $ex) {

             //
            $mensajeError = $ex->getMessage();

            return array(
                    'status'    => false,
                    'data'      => [],
                    'message'   => $mensajeError,
                    'errorCode' => $codigoError
                );

        }
        finally {
            //Libera recursos de conexión
            if ($stmt != null){
                oci_free_statement($stmt);
            }

            //Cierra la conexión
            $this->conexion->cerrar();
        }

    }

    /**
     * Inserta pedido Laboratorio
    */
    public function insertarPedidoLaboratorio($pedidoLaboratorio, $numeroHistoriaClinica, $numeroAdmision, $codigoInstitucion, $numeroCompania, $stid, $conexion1)
    {
        //Inicialización de variables
        $r = FALSE;
        $codigoError = -1;
        $mensajeError;

        $codigoExamen = null;
        $descripcionExamen = null;
        $precioVentaPublico = null;

        try {         
            //Setear idioma y formatos en español para Oracle
            //$this->setSpanishOracle($stid);

            $stid = oci_parse($conexion1, "BEGIN PRO_TEL_PEDIDO_LAB_INS(:pn_hc, :pn_adm, :pn_institucion, :pc_cia_naf, :pc_no_arti, :pn_pvp, :pc_observacion, :pn_retorno, :pc_mensaje); END;");
                 
            $codigoExamen = $pedidoLaboratorio['codigoExamen'];
            $descripcionExamen = strtoupper($pedidoLaboratorio['descripcionExamen']); 
            $precioVentaPublico = $pedidoLaboratorio['precioVentaPublico'];         
                    
            // Bind the input num_entries argument to the $max_entries PHP variable             
            oci_bind_by_name($stid,":pn_hc",$numeroHistoriaClinica,32);
            oci_bind_by_name($stid,":pn_adm",$numeroAdmision,3);
            oci_bind_by_name($stid,":pn_institucion",$codigoInstitucion,32);
            oci_bind_by_name($stid,":pc_cia_naf",$numeroCompania,2);
            
            oci_bind_by_name($stid,":pc_no_arti",$codigoExamen, 9);  
            oci_bind_by_name($stid,":pn_pvp",$precioVentaPublico, 32);  
            oci_bind_by_name($stid,":pc_observacion",$descripcionExamen, 120);  

            // Bind the output parameter
            oci_bind_by_name($stid,':pn_retorno',$codigoRetorno,32);
            oci_bind_by_name($stid,':pc_mensaje',$mensajeRetorno,500);

            //Ejecuta el SP            
            $r = oci_execute($stid, OCI_DEFAULT);

            if ($codigoRetorno == 1){
                throw new ModelsException($mensajeRetorno, $codigoRetorno);
            }
                                        
        } 
        catch (ModelsException $e) {

            throw $e;
            
        }
        catch (Exception $ex) {
                       
            if (!$r) {
                $e = oci_error($stid);
                
                $mensajeError = "Error al insertar los datos del pedido examen de laboratorio, consulte con el Administrador del Sistema. " . $e['message'];

                //Verifica los mensajes de error del Oracle
                //Llave primaria duplicada
                if ($e['code'] == 1) {
                    $mensajeError = "Pedido de examen de Laboratorio ya existe. " . $e['message'];
                }

                oci_rollback($conexion1);
                throw new Exception($mensajeError, $codigoError);                
            }

        }               
    }    

     /**
     * Inserta pedido Imagen
    */
    public function insertarPedidoImagen($pedidoImagen, $numeroHistoriaClinica, $numeroAdmision, $codigoInstitucion, $numeroCompania, $stid, $conexion1)
    {
        //Inicialización de variables
        $r = FALSE;
        $codigoError = -1;
        $mensajeError;

        $codigoExamen = null;
        $descripcionExamen = null;
        $precioVentaPublico = null;

        try {         
            //Setear idioma y formatos en español para Oracle
            //$this->setSpanishOracle($stid);

            $stid = oci_parse($conexion1, "BEGIN PRO_TEL_PEDIDO_IMG_INS(:pn_hc, :pn_adm, :pn_institucion, :pc_cia_naf, :pc_no_arti, :pn_pvp, :pc_observacion, :pn_retorno, :pc_mensaje); END;");
                 
            $codigoExamen = strtoupper($pedidoImagen['codigoExamen']);
            $descripcionExamen = strtoupper($pedidoImagen['descripcionExamen']); 
            $precioVentaPublico = $pedidoImagen['precioVentaPublico'];         
            
            // Bind the input num_entries argument to the $max_entries PHP variable             
            oci_bind_by_name($stid,":pn_hc",$numeroHistoriaClinica,32);
            oci_bind_by_name($stid,":pn_adm",$numeroAdmision,32);
            oci_bind_by_name($stid,":pn_institucion",$codigoInstitucion,32);
            oci_bind_by_name($stid,":pc_cia_naf",$numeroCompania,2);

            oci_bind_by_name($stid,":pc_no_arti",$codigoExamen, 9);  
            oci_bind_by_name($stid,":pn_pvp",$precioVentaPublico, 32);  
            oci_bind_by_name($stid,":pc_observacion",$descripcionExamen, 120);  

            // Bind the output parameter
            oci_bind_by_name($stid,':pn_retorno',$codigoRetorno,32);
            oci_bind_by_name($stid,':pc_mensaje',$mensajeRetorno,500);

            //Ejecuta el SP            
            $r = oci_execute($stid, OCI_DEFAULT);

            if ($codigoRetorno == 1){
                throw new ModelsException($mensajeRetorno, $codigoRetorno);
            }
                                        
        } catch (ModelsException $e) {

            throw $e;

        } catch (Exception $ex) {
                       
            if (!$r) {
                $e = oci_error($stid);
                
                $mensajeError = "Error al insertar los datos del pedido examen de imagen, consulte con el Administrador del Sistema. " . $e['message'];

                //Verifica los mensajes de error del Oracle
                //Llave primaria duplicada
                if ($e['code'] == 1) {
                    $mensajeError = "Pedido de examen de Imagen ya existe."  . $e['message'];
                }

                oci_rollback($conexion1);
                throw new Exception($mensajeError, $codigoError);                
            }

        }               
    }  

    private function setSpanishOracle($stid)
    {

        $sql = "alter session set NLS_LANGUAGE = 'SPANISH'";
        # Execute
        $stid = oci_parse($this->conexion->getConexion(),  $sql);
        oci_execute($stid);

        $sql = "alter session set NLS_TERRITORY = 'SPAIN'";
        # Execute
        $stid = oci_parse($this->conexion->getConexion(),  $sql);
        oci_execute($stid);

        $sql = " alter session set NLS_DATE_FORMAT = 'DD/MM/YYYY HH24:MI'";
        # Execute
        $stid = oci_parse($this->conexion->getConexion(),  $sql);
        oci_execute($stid);

    }

    /**
     * Consulta los pedidos 
    */
    public function consultarPedidosLaboratorioImagen()
    {
        global $config;

        //Inicialización de variables
        $stid = null;        
        $pedidos = null;

        try {         
            //Asignar parámetros de entrada            
            $this->setParametersConsultaPedidos();

            //Validar parámetros de entrada   
            $this->validarParametrosConsultaPedidos();
            
            //Conectar a la BDD
            $this->conexion->conectar();

            //Setear idioma y formatos en español para Oracle
            $this->setSpanishOracle($stid);

            $pedidos = $this->consultarPedidos($this->numeroHistoriaClinica, $this->numeroAdmision, $this->tipoPedido, $stid, $this->conexion->getConexion());

            //Verificar si la consulta devolvió datos
            if (count($pedidos) > 0) {
                return array(
                    'status' => true,                    
                    'data'   => $pedidos
                        );
            }
            else {
                throw new ModelsException($config['errors']['noExistenResultados']['message'], 1);
            }

        } catch (ModelsException $e) {

            return array(
                    'status'    => false,
                    'data'      => [],
                    'message'   => $e->getMessage(),
                    'errorCode' => $e->getCode()
                );

        } catch (Exception $ex) {

            return array(
                    'status'    => false,
                    'data'      => [],
                    'message'   => $ex->getMessage(),
                    'errorCode' => -1
                );

        }
        finally {
            //Libera recursos de conexión
            if ($stid != null){
                oci_free_statement($stid);
            }

            //Cierra la conexión
            $this->conexion->cerrar();
        }
    }
    /**
     * Consulta los pedidos 
    */
    public function consultarPedidos($numeroHistoriaClinica, $numeroAdmision, $tipoPedido, $stid, $conexion1)
    {
        global $config;

        //Inicialización de variables
        $pc_datos = null;
        $existeDatos = false;
        $pedidos = null;

        try {         
            $pc_datos = oci_new_cursor($conexion1);

            $stid = oci_parse($conexion1, "BEGIN PRO_TEL_PEDIDO_LAB_IMG_LEE(:pn_hc, :pn_adm, :pc_tipo, :pc_datos); END;");

            // Bind the input num_entries argument to the $max_entries PHP variable             
            oci_bind_by_name($stid,":pn_hc",$numeroHistoriaClinica,32);
            oci_bind_by_name($stid,":pn_adm",$numeroAdmision,32);
            oci_bind_by_name($stid,":pc_tipo",$tipoPedido,1);
            oci_bind_by_name($stid, ":pc_datos", $pc_datos, -1, OCI_B_CURSOR);


            //Ejecuta el SP
            oci_execute($stid);

            //Ejecutar el REF CURSOR como un ide de sentencia normal
            oci_execute($pc_datos);  

            //Resultados de la consulta
            $pedidos = array();

            while (($row = oci_fetch_array($pc_datos, OCI_BOTH+OCI_RETURN_NULLS)) != false) {
                $existeDatos = true;
               
                # RESULTADO OBJETO
                $pedidos[] = array(
                    'tipoPedido' => $row[0] == null ? '' : $row[0],
                    'codigoExamen'=> $row[1] == null ? '' : $row[1],
                    'descripcionExamen'=> $row[2] == null ? '' : $row[2],
                    'observacion'=> $row[3] == null ? '' : $row[3],
                    'fecha'=> $row[4] == null ? '' : $row[4]
                );
                                
            }

            return $pedidos;            
               
        }
        finally {
            //Libera recursos de conexión
            if ($pc_datos != null){
                oci_free_statement($pc_datos);
            }

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