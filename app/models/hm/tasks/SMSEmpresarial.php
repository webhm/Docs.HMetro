<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\models\hm\tasks;

use app\models\hm\tasks as Model;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo SMSEmpresarial
 */

class SMSEmpresarial extends Models implements IModels
{
    # Variables de clase
    private $vgnumerr    = '';
    private $vgerror     = '';
    private $vgdest      = '';
    private $parServicio = 'CONTACTOSMS';
    private $parEmisor   = 'HOSPMET';
    private $parLogin    = 'admin';
    private $parPwd      = 'hosmt@csms';
    private $parRef      = '';
    private $parPc       = 'SMSEmpresarialAutomatico';
    private $parMsg      = 'demo demo texto';
    private $parDest     = '0996387644';
    private $min         = "00:01:00";

    public function enviarSMSToImagen($texto, $telefono)
    {

        # variables
        $parServicio = $this->parServicio;
        $parEmisor   = $this->parEmisor;
        $parLogin    = $this->parLogin;
        $parPwd      = $this->parPwd;
        $parRef      = time();
        $parFechaEnv = date('m/d/Y');
        $parHoraEnv  = $this->sumaTiempo(date("H:i:s"), $this->min);
        $parPc       = $this->parPc;
        $parMsg      = $texto;
        $parDest     = $telefono;

        $envio = "N";
        $key   = md5($parServicio . ";csms@auto;" . $parEmisor . ";" . $parLogin . ";" . $parPwd . ";" . $parRef);

        //Xml
        $xml = '<?xml version="1.0" encoding="ISO-8859-1"?>';
        $xml .= '<enviar Servicio="' . $parServicio . '" Emisor="' . $parEmisor . '" Login="' . $parLogin . '" Pwd="' . $parPwd . '" NumDest="1" Referencia="' . $parRef . '" FechaEnv="' . $parFechaEnv . '" HoraEnv="' . $parHoraEnv . '" NombrePC="' . $parPc . '" Key="' . $key . '">';
        $xml .= '<Mensaje>' . $parMsg . '</Mensaje>';
        $xml .= '<Dest>' . $parDest . '</Dest>';
        $xml .= '</enviar>';

        $xmlres = $this->HTTPrequest("POST", "157.100.84.58", "/contactosms/wclsContactoSMS.aspx", $xml);

        $posdata = strpos($xmlres, "\r\n\r\n");

        if ($posdata === false) {

            return $this->vgerror = "Documento XML Invalido: " . $xmlres;

        } else {

            $xmlres = substr($xmlres, $posdata + 2);

            $xml  = simplexml_load_string($xmlres);
            $json = json_encode($xml);
            $logs = json_decode($json, true);

            return array(
                'status' => $this->XMLParse($xmlres),
                'logs'   => $logs,
            );
        }

    }

    public function HTTPrequest($method, $host, $usepath, $postdata = "")
    {

        if (is_array($postdata)) {
            foreach ($postdata as $key => $val) {
                if (!is_integer($key)) {$data .= "$key=" . urlencode($val) . "&";}
            }
        } else {
            $data = $postdata;
        }

        $fp = pfsockopen($host, 80, $errno, $errstr, 120);
        //$fp = fsockopen( $host, 17980, &$errno, &$errstr, 120);

        if (!$fp) {
            $output = '<resenviar Errores="1"><Error Dest="0">' . $errstr . '(' . $errno . ')</Error></resenviar>';
        } else {
            if (strtoupper($method) == "GET") {
                fputs($fp, "GET $usepath HTTP/1.0\n");
            } else if (strtoupper($method) == "POST") {
                fputs($fp, "POST $usepath HTTP/1.0\n");
            }

            fputs($fp, "Accept: */*\n");
            fputs($fp, "Accept: image/gif\n");
            fputs($fp, "Accept: image/x-xbitmap\n");
            fputs($fp, "Accept: image/jpeg\n");

            if (strtoupper($method) == "POST") {
                $strlength = strlen($postdata);
                fputs($fp, "Content-type: text/xml\n");
                fputs($fp, "Content-length: " . $strlength . "\n\n");
                fputs($fp, $postdata . "\n");
            }

            fputs($fp, "\n", 1);
            $output = "";
            stream_set_timeout($fp, 60);
            while (!feof($fp)) {
                $output .= fgets($fp, 1024);
            }
            $info = stream_get_meta_data($fp);
            fclose($fp);
            if ($info['timed_out']) {
                $output = '<resenviar Errores="1"><Error Dest="0">Tiempo de espera agotado.</Error></resenviar>';
            }

        }
        return $output;
    }

    public function XMLParse($xml)
    {

        $xml_parser = xml_parser_create();
        xml_parse_into_struct($xml_parser, $xml, $vals, $idxs);

        if (array_key_exists('ENVIO', $idxs)) {
            if (array_key_exists(0, $idxs['ENVIO'])) {
                if (array_key_exists('attributes', $vals[$idxs['ENVIO'][0]])) {
                    if (array_key_exists('ERRORES', $vals[$idxs['ENVIO'][0]]['attributes'])) {
                        return $this->vgnumerr = $vals[$idxs['ENVIO'][0]]['attributes']['ERRORES'];
                    }
                }
            }
        } else {
            if (array_key_exists('RESENVIAR', $idxs)) {
                if (array_key_exists(0, $idxs['RESENVIAR'])) {
                    if (array_key_exists('attributes', $vals[$idxs['RESENVIAR'][0]])) {
                        if (array_key_exists('ERRORES', $vals[$idxs['RESENVIAR'][0]]['attributes'])) {
                            return $this->vgnumerr = $vals[$idxs['RESENVIAR'][0]]['attributes']['ERRORES'];
                        }
                    }
                }
            }
        }

        if (array_key_exists('ERROR', $idxs)) {
            if (array_key_exists(0, $idxs['ERROR'])) {
                if (array_key_exists('attributes', $vals[$idxs['ERROR'][0]])) {
                    if (array_key_exists('DEST', $vals[$idxs['ERROR'][0]]['attributes'])) {
                        return $vgdest = $vals[$idxs['ERROR'][0]]['attributes']['DEST'];
                    }
                }
                if (array_key_exists('value', $vals[$idxs['ERROR'][0]])) {
                    return $this->vgerror = $vals[$idxs['ERROR'][0]]['value'];
                }
            }
        } else {
            if (array_key_exists('OK', $idxs)) {
                $this->vgnumerr = "0";
                if (array_key_exists(0, $idxs['OK'])) {
                    if (array_key_exists('attributes', $vals[$idxs['OK'][0]])) {
                        if (array_key_exists('DEST', $vals[$idxs['OK'][0]]['attributes'])) {
                            return $vgdest = $vals[$idxs['OK'][0]]['attributes']['DEST'];
                        }
                    }
                    if (array_key_exists('value', $vals[$idxs['OK'][0]])) {
                        return $this->vgerror = $vals[$idxs['OK'][0]]['value'];
                    }
                }
            }
        }

        return xml_parser_free($xml_parser);
    }

    public function respuestasms($numerr, $dest, $error, $xml)
    {

        $numerr = $this->vgnumerr;
        $dest   = $this->vgdest;
        $error  = $this->vgerror;
        //$xml = $vgxml;

    }

    public function sumaTiempo($time1, $time2)
    {
        list($hour1, $min1, $sec1) = $this->parteHora($time1);
        list($hour2, $min2, $sec2) = $this->parteHora($time2);
        return date('H:i', mktime($hour1 + $hour2, $min1 + $min2));
    }

    public function parteHora($hora)
    {
        $horaSplit = explode(":", $hora);
        if (count($horaSplit) < 3) {
            $horaSplit[2] = 0;
        }
        return $horaSplit;
    }

    public function recibirsms($parXML, $parDest, $parMsg)
    {
        $xml_parser = xml_parser_create();
        $posdata    = strpos($parXML, "\r\n\r\n");
        if ($posdata != 0) {
            $parXML = substr($parXML, $posdata + 2);
        }
        return $parXML;
        $parDest = $vals[$idxs['TELEFONO'][0]]['value'];
        $parMsg  = $vals[$idxs['TEXTO'][0]]['value'];
        return xml_parser_free($xml_parser);

    }

    public function confirmarsms($parCodigo, $parMsg, $parXml)
    {
//Xml
        $xml = '<?xml version="1.0" encoding="ISO-8859-1"?>';
        $xml .= '<TransmitirClienteResp>';
        $xml .= '<Codigo>' . $parCodigo . '</Codigo>';
        $xml .= '<Mensaje>' . $parMsg . '</Mensaje>';
        $xml .= '</TransmitirClienteResp>';
        $parXml = $xml;
        return $parXml;

    }

/**
 * __construct()
 */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);
    }
}
