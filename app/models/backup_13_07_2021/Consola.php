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
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Models\Traits\DBModel;
use Ocrend\Kernel\Router\IRouter;

/**
 * Modelo Consola
 */
class Consola extends Models implements IModels
{

    use DBModel;

    /**
     * Log de respuestas HTTP
     *
     * @var array
     */

    private $logs = array();

    /**
     * Registro Track
     *
     * @var
     */

    private $track = null;

    /**
     * Get Auth
     *
     * @var
     */

    private function getAuthorization()
    {

        try {

            global $http;

            $token = $http->headers->get("Authorization");

            $auth = new Model\Auth;
            $data = $auth->GetData($token);

            $this->id_user = $data;

        } catch (ModelsException $e) {
            return array('status' => false, 'message' => $e->getMessage());
        }
    }

    /**
     * Obtener los paceitnes de un medico
     *
     * @return array : Con información de éxito/falla.
     */

    public function setConsola()
    {

        try {

            global $config, $http;

            $this->track = $http->request->get('C');

            # Verificar que no están vacíos
            if (Helper\Functions::e($this->track)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion.');
            }

            # Get Auth
            $this->getAuthorization();

            switch ($this->track) {
                // OBTENER INFO INICIAL DE CONSOLA
                case 1:

                    $atenciones = new Model\Atenciones;
                    $inCall = $atenciones->verificarAtencion();

                    if ($inCall['status']) {

                        if ($inCall['data']['formatoAtencion'] == 'T') {

                            return array(
                                'status' => true,
                                'idMedico' => (int) $this->id_user->codMedico,
                                'modeConsola' => (int) $this->id_user->modeConsola,
                                'rolConsola' => (int) $this->id_user->rol,
                                'idPacienteActivo' => false,
                                'inCall' => true,
                                'dataCall' => $inCall['data'],
                            );

                        } else {

                            return array(
                                'status' => true,
                                'idMedico' => (int) $this->id_user->codMedico,
                                'modeConsola' => (int) $this->id_user->modeConsola,
                                'rolConsola' => (int) $this->id_user->rol,
                                'idPacienteActivo' => true,
                                'inCall' => true,
                                'dataCall' => $inCall['data'],
                            );

                        }

                    } else {

                        return array(
                            'status' => true,
                            'idMedico' => (int) $this->id_user->codMedico,
                            'modeConsola' => (int) $this->id_user->modeConsola,
                            'rolConsola' => (int) $this->id_user->rol,
                            'idPacienteActivo' => false,
                            'inCall' => false,
                            'dataCall' => [],
                        );

                    }

                    break;

                // OBTENER CITAS AGENDADAS DEL MEDICO
                case 2:
                    $citas = new Model\Citas;
                    return $citas->obtenerCitasAgendadasMedico();
                    break;

                // OBTENER DATOS PERSONALES DE UN PACIENTE
                case 3:
                    $pacientes = new Model\Pacientes;
                    return $pacientes->obtenerDatosPersonales();
                    break;

                // OBTENER DATOS PERSONALES DE UN PACIENTE
                case 4:
                    $pacientes = new Model\Pacientes;
                    return $pacientes->obtenerAtencionesHM();
                    break;

                // verificar stats licencia de Medico
                case 5:
                    $licencias = new Model\Licencias;
                    return $licencias->getLicencia();
                    break;

                // Nueva Atencion presencial
                case 6:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->nuevaAtencion();
                    break;

                // Send Data HC atenciones
                case 7:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->updateDataHC();
                    break;

                // Send Data HC atenciones
                case 8:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->cancelarAtencion();
                    break;

                // Cerrar atencion HM
                case 9:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->cerrarAtencionHM();
                    break;

                // Cerrar atencion
                case 10:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->cerrarAtencion();
                    break;

                // Obtener citas disponibles de un edico
                case 11:
                    $citas = new Model\Citas;
                    return $citas->obtenerCitasDisponiblesMedico();
                    break;

                // Obtener citas disponibles de un edico
                case 12:
                    $pacientes = new Model\Pacientes;
                    return $pacientes->consultarAfiliacion();
                    break;

                // nUEVA CITA hm
                case 13:
                    $citas = new Model\Citas;
                    return $citas->consultarAfiliacion();
                    break;

                case 14:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->nuevaAtencionHM();
                    break;

                case 15:
                    $teleconsulta = new Model\Teleconsulta;
                    return $teleconsulta->setCallZoom();
                    break;

                case 16:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->nuevaTeleconsulta();
                    break;

                case 17:
                    $teleconsulta = new Model\Teleconsulta;
                    return $teleconsulta->deleteCallZoom();
                    break;

                case 18:
                    $pacientes = new Model\Pacientes;
                    return $pacientes->getFormulario002();
                    break;

                case 19:
                    $pacientes = new Model\Pacientes;
                    return $pacientes->getPacientesAnteriores();
                    break;

                case 20:
                    $certificado = new Model\Certificados;
                    return $certificado->nuevoCertificado();
                    break;

                case 21:
                    $notificaciones = new Model\Notificaciones;
                    return $notificaciones->sendCertificado();
                    break;

                case 22:
                    $res = new Model\Laboratorio;
                    return $res->obtenerResultadosHM();
                    break;

                case 23:
                    $lab = new Model\Recetas;
                    return $lab->nuevaReceta();
                    break;

                case 24:
                    $notificaciones = new Model\Notificaciones;
                    return $notificaciones->sendReceta();
                    break;

                case 25:
                    $notificaciones = new Model\Notificaciones;
                    return $notificaciones->sendTeleconsulta();
                    break;

                case 26:
                    $antecedentes = new Model\Pacientes;
                    return $antecedentes->getAntecedentesFamiliares();
                    break;

                case 27:
                    $evoluciones = new Model\Pacientes;
                    return $evoluciones->getEvoluciones();
                    break;

                case 28:
                    $certificado = new Model\Certificados;
                    return $certificado->processCertificado();
                    break;

                case 29:
                    $recetas = new Model\Recetas;
                    return $recetas->processReceta();
                    break;

                // OBTENER RESULTADSO DEL PACIENTE
                case 30:
                    $res = new Model\Laboratorio;
                    return $res->obtenerResultadosHM_Post();
                    break;

                // OBTENER RESULTADO DEL PACIENTE
                case 31:
                    $res = new Model\Lis;
                    return $res->obtenerResultadoLabHM();
                    break;

                // OBTENER RESULTADO DEL PACIENTE
                case 32:
                    $res = new Model\Pacientes;
                    return $res->getExamenesLab();
                    break;

                // nuevo pedido de lboratoro
                case 33:
                    $res = new Model\Pedidos;
                    return $res->nuevoPedidoLab();
                    break;

                case 34:
                    $res = new Model\Pedidos;
                    return $res->processPedidoLab();
                    break;

                case 35:
                    $res = new Model\Recetas;
                    return $res->getRecetasHM();
                    break;

                case 36:
                    $res = new Model\Atenciones;
                    return $res->cancelarAtencionHM();
                    break;

                case 37:
                    $res = new Model\Certificados;
                    return $res->getCertificadosHM();
                    break;

                # Pedidos de laboratorio
                case 38:
                    $res = new Model\Pedidos;
                    return $res->getPedidosLabHM();
                    break;

                # Verificar si paciente por documento esta registrado en nuestra bdd
                case 39:
                    $res = new Model\Pacientes;
                    return $res->validacionBDDGEMA();
                    break;

                # Agregar correo al paciente
                case 40:
                    $res = new Model\Laboratorio;
                    return $res->agregarCorreoElectrónicoPaciente();
                    break;

                // nuevo pedido de lboratoro
                case 41:
                    $res = new Model\Pedidos;
                    return $res->nuevoPedidoImg();
                    break;

                case 42:
                    $res = new Model\Pedidos;
                    return $res->processPedidoImg();
                    break;

                case 43:
                    $res = new Model\Pacientes;
                    return $res->getExamenesImg();
                    break;

                case 44:
                    $res = new Model\Pedidos;
                    return $res->getPedidosImgHM();
                    break;

                case 45:
                    $res = new Model\Pedidos;
                    return $res->nuevoPedidoImg();
                    break;

                case 46:
                    $res = new Model\Teleconsulta;
                    return $res->getParticipantesCall();
                    break;

                case 47:
                    $res = new Model\Pacientes;
                    return $res->registroNuevoPaciente();
                    break;

                case 48:
                    $res = new Model\Notificaciones;
                    return $res->sendResultadoLab();
                    break;

                case 49:
                    $res = new Model\Pacientes;
                    return $res->getPacientes();
                    break;

                case 50:
                    $res = new Model\Notificaciones;
                    return $res->sendNuevaCitaAgendadaa();
                    break;

                case 51:
                    $res = new Model\Pedidos;
                    return $res->nuevoFormulario();
                    break;

                case 52:
                    $res = new Model\Pedidos;
                    return $res->processFormulario();
                    break;

                case 53:
                    $res = new Model\Pacientes;
                    return $res->getBuscaCitasNombres();
                    break;

                case 54:
                    $res = new Model\Pacientes;
                    return $res->getBuscaPacienteNombres();
                    break;

                case 55:
                    $res = new Model\Pacientes;
                    return $res->getCitasFechas();
                    break;

                default:
                    throw new ModelsException('No existe una petición track definida.');
                    break;
            }

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => array(),
                'message' => $e->getMessage(),
                'logs' => $this->logs,
            );

        }

    }

    /**
     * Obtener los paceitnes de un medico
     *
     * @return array : Con información de éxito/falla.
     */

    public function setConsolaLab()
    {

        try {

            global $config, $http;

            $this->track = $http->request->get('C');

            # Verificar que no están vacíos
            if (Helper\Functions::e($this->track)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion.');
            }

            # Get Auth
            $this->getAuthorization();

            switch ($this->track) {
                // oBTENER INFO INICIAL DE CONSOLA
                case 1:
                    return array(
                        'status' => true,
                        'modeConsola' => (int) $this->id_user->modeConsola,
                        'rolConsola' => (int) $this->id_user->rol,
                        'idPacienteActivo' => false,
                        'inCall' => false,
                    );
                    break;

                // OBTENER TODOS LOS RESULTADOS DEL MEDICO
                case 2:
                    $res = new Model\Laboratorio;
                    return $res->obtenerResultadosMedicoHM();
                    break;

                // OBTENER DATOS PERSONALES DE UN PACIENTE
                case 3:
                    $pacientes = new Model\Pacientes;
                    return $pacientes->obtenerDatosPersonales();
                    break;

                // OBTENER DATOS PERSONALES DE UN PACIENTE
                case 4:
                    $pacientes = new Model\Pacientes;
                    return $pacientes->obtenerAtencionesHM();
                    break;

                // verificar stats licencia de Medico
                case 5:
                    $licencias = new Model\Licencias;
                    return $licencias->getLicencia();
                    break;

                // Nueva Atencion presencial
                case 6:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->nuevaAtencion();
                    break;

                // Send Data HC atenciones
                case 7:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->updateDataHC();
                    break;

                // Send Data HC atenciones
                case 8:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->cancelarAtencion();
                    break;

                // Cerrar atencion HM
                case 9:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->cerrarAtencionHM();
                    break;

                // Cerrar atencion
                case 10:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->cerrarAtencion();
                    break;

                // Obtener citas disponibles de un edico
                case 11:
                    $citas = new Model\Citas;
                    return $citas->obtenerCitasDisponiblesMedico();
                    break;

                // Obtener citas disponibles de un edico
                case 12:
                    $pacientes = new Model\Pacientes;
                    return $pacientes->consultarAfiliacion();
                    break;

                // nUEVA CITA hm
                case 13:
                    $citas = new Model\Citas;
                    return $citas->consultarAfiliacion();
                    break;

                case 14:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->nuevaAtencionHM();
                    break;

                case 15:
                    $teleconsulta = new Model\Teleconsulta;
                    return $teleconsulta->setCallZoom();
                    break;

                case 16:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->nuevaTeleconsulta();
                    break;

                case 17:
                    $teleconsulta = new Model\Teleconsulta;
                    return $teleconsulta->deleteCallZoom();
                    break;

                case 18:
                    $pacientes = new Model\Pacientes;
                    return $pacientes->getFormulario002();
                    break;

                case 19:
                    $pacientes = new Model\Pacientes;
                    return $pacientes->getPacientesAnteriores();
                    break;

                case 20:
                    $certificado = new Model\Certificados;
                    return $certificado->nuevoCertificado();
                    break;

                case 21:
                    $notificaciones = new Model\Notificaciones;
                    return $notificaciones->sendCertificado();
                    break;

                case 22:
                    $res = new Model\Laboratorio;
                    return $res->obtenerResultadosHM();
                    break;

                case 23:
                    $lab = new Model\Recetas;
                    return $lab->nuevaReceta();
                    break;

                case 24:
                    $notificaciones = new Model\Notificaciones;
                    return $notificaciones->sendReceta();
                    break;

                case 25:
                    $notificaciones = new Model\Notificaciones;
                    return $notificaciones->sendTeleconsulta();
                    break;

                case 26:
                    $antecedentes = new Model\Pacientes;
                    return $antecedentes->getAntecedentesFamiliares();
                    break;

                case 27:
                    $evoluciones = new Model\Pacientes;
                    return $evoluciones->getEvoluciones();
                    break;

                case 28:
                    $certificado = new Model\Certificados;
                    return $certificado->processCertificado();
                    break;

                case 29:
                    $recetas = new Model\Recetas;
                    return $recetas->processReceta();
                    break;

                // OBTENER RESULTADSO DEL PACIENTE
                case 30:
                    $res = new Model\Laboratorio;
                    return $res->obtenerResultadosHM();
                    break;

                // OBTENER RESULTADO DEL PACIENTE
                case 31:
                    $res = new Model\Laboratorio;
                    return $res->obtenerResultadoLabHM();
                    break;

                // OBTENER RESULTADO DEL PACIENTE
                case 32:
                    $res = new Model\Pacientes;
                    return $res->getExamenesLab();
                    break;

                // nuevo pedido de lboratoro
                case 33:
                    $res = new Model\Pedidos;
                    return $res->nuevoPedidoLab();
                    break;

                case 34:
                    $res = new Model\Pedidos;
                    return $res->processPedidoLab();
                    break;

                case 35:
                    $res = new Model\Recetas;
                    return $res->getRecetasHM();
                    break;

                case 36:
                    $res = new Model\Atenciones;
                    return $res->cancelarAtencionHM();
                    break;

                case 37:
                    $res = new Model\Certificados;
                    return $res->getCertificadosHM();
                    break;

                # Pedidos de laboratorio
                case 38:
                    $res = new Model\Pedidos;
                    return $res->getPedidosLabHM();
                    break;

                # Verificar si paciente por documento esta registrado en nuestra bdd
                case 39:
                    $res = new Model\Pacientes;
                    return $res->validacionBDDGEMA();
                    break;

                # Agregar correo al paciente
                case 40:
                    $res = new Model\Laboratorio;
                    return $res->agregarCorreoElectrónicoPaciente();
                    break;

                // nuevo pedido de lboratoro
                case 41:
                    $res = new Model\Pedidos;
                    return $res->nuevoPedidoImg();
                    break;

                case 42:
                    $res = new Model\Pedidos;
                    return $res->processPedidoImg();
                    break;

                case 43:
                    $res = new Model\Pacientes;
                    return $res->getExamenesImg();
                    break;

                case 44:
                    $res = new Model\Pedidos;
                    return $res->getPedidosImgHM();
                    break;

                case 45:
                    $res = new Model\Pedidos;
                    return $res->nuevoPedidoImg();
                    break;

                case 46:
                    $res = new Model\Teleconsulta;
                    return $res->getParticipantesCall();
                    break;

                default:
                    throw new ModelsException('No existe una petición track definida.');
                    break;
            }

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => array(),
                'message' => $e->getMessage(),
                'logs' => $this->logs,
            );

        }

    }

    /**
     * setConsolaDatos
     *
     * @return array : Con información de éxito/falla.
     */

    public function setConsolaDatos()
    {

        try {

            global $config, $http;

            $this->track = $http->request->get('C');

            # Verificar que no están vacíos
            if (Helper\Functions::e($this->track)) {
                throw new ModelsException('Parámetros insuficientes para esta peticion.');
            }

            switch ($this->track) {
                // oBTENER INFO INICIAL DE CONSOLA
                case 1:
                    return array(
                        'status' => true,
                        'modeConsola' => (int) $this->id_user->modeConsola,
                        'rolConsola' => (int) $this->id_user->rol,
                        'idPacienteActivo' => false,
                        'inCall' => false,
                    );
                    break;

                // OBTENER TODOS LOS RESULTADOS DEL MEDICO
                case 2:
                    $res = new Model\Laboratorio;
                    return $res->obtenerResultadosMedicoHM();
                    break;

                // OBTENER DATOS PERSONALES DE UN PACIENTE
                case 3:
                    $pacientes = new Model\Pacientes;
                    return $pacientes->getDatosPersonales();
                    break;

                // OBTENER DATOS PERSONALES DE UN PACIENTE
                case 4:
                    $pacientes = new Model\Pacientes;
                    return $pacientes->obtenerAtencionesHM();
                    break;

                // verificar stats licencia de Medico
                case 5:
                    $licencias = new Model\Licencias;
                    return $licencias->getLicencia();
                    break;

                // Nueva Atencion presencial
                case 6:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->nuevaAtencion();
                    break;

                // Send Data HC atenciones
                case 7:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->updateDataHC();
                    break;

                // Send Data HC atenciones
                case 8:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->cancelarAtencion();
                    break;

                // Cerrar atencion HM
                case 9:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->cerrarAtencionHM();
                    break;

                // Cerrar atencion
                case 10:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->cerrarAtencion();
                    break;

                // Obtener citas disponibles de un edico
                case 11:
                    $citas = new Model\Citas;
                    return $citas->obtenerCitasDisponiblesMedico();
                    break;

                // Obtener citas disponibles de un edico
                case 12:
                    $pacientes = new Model\Pacientes;
                    return $pacientes->consultarAfiliacion();
                    break;

                // nUEVA CITA hm
                case 13:
                    $citas = new Model\Citas;
                    return $citas->consultarAfiliacion();
                    break;

                case 14:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->nuevaAtencionHM();
                    break;

                case 15:
                    $teleconsulta = new Model\Teleconsulta;
                    return $teleconsulta->setCallZoom();
                    break;

                case 16:
                    $atenciones = new Model\Atenciones;
                    return $atenciones->nuevaTeleconsulta();
                    break;

                case 17:
                    $teleconsulta = new Model\Teleconsulta;
                    return $teleconsulta->deleteCallZoom();
                    break;

                case 18:
                    $pacientes = new Model\Pacientes;
                    return $pacientes->getFormulario002();
                    break;

                case 19:
                    $pacientes = new Model\Pacientes;
                    return $pacientes->getPacientesAnteriores();
                    break;

                case 20:
                    $certificado = new Model\Certificados;
                    return $certificado->nuevoCertificado();
                    break;

                case 21:
                    $notificaciones = new Model\Notificaciones;
                    return $notificaciones->sendCertificado();
                    break;

                case 22:
                    $res = new Model\Laboratorio;
                    return $res->obtenerResultadosHM();
                    break;

                case 23:
                    $lab = new Model\Recetas;
                    return $lab->nuevaReceta();
                    break;

                case 24:
                    $notificaciones = new Model\Notificaciones;
                    return $notificaciones->sendReceta();
                    break;

                case 25:
                    $notificaciones = new Model\Notificaciones;
                    return $notificaciones->sendTeleconsulta();
                    break;

                case 26:
                    $antecedentes = new Model\Pacientes;
                    return $antecedentes->getAntecedentesFamiliares();
                    break;

                case 27:
                    $evoluciones = new Model\Pacientes;
                    return $evoluciones->getEvoluciones();
                    break;

                case 28:
                    $certificado = new Model\Certificados;
                    return $certificado->processCertificado();
                    break;

                case 29:
                    $recetas = new Model\Recetas;
                    return $recetas->processReceta();
                    break;

                // OBTENER RESULTADSO DEL PACIENTE
                case 30:
                    $res = new Model\Laboratorio;
                    return $res->obtenerResultadosHM();
                    break;

                // OBTENER RESULTADO DEL PACIENTE
                case 31:
                    $res = new Model\Laboratorio;
                    return $res->obtenerResultadoLabHM();
                    break;

                // OBTENER RESULTADO DEL PACIENTE
                case 32:
                    $res = new Model\Pacientes;
                    return $res->getExamenesLab();
                    break;

                // nuevo pedido de lboratoro
                case 33:
                    $res = new Model\Pedidos;
                    return $res->nuevoPedidoLab();
                    break;

                case 34:
                    $res = new Model\Pedidos;
                    return $res->processPedidoLab();
                    break;

                case 35:
                    $res = new Model\Recetas;
                    return $res->getRecetasHM();
                    break;

                case 36:
                    $res = new Model\Atenciones;
                    return $res->cancelarAtencionHM();
                    break;

                case 37:
                    $res = new Model\Certificados;
                    return $res->getCertificadosHM();
                    break;

                # Pedidos de laboratorio
                case 38:
                    $res = new Model\Pedidos;
                    return $res->getPedidosLabHM();
                    break;

                # Verificar si paciente por documento esta registrado en nuestra bdd
                case 39:
                    $res = new Model\Pacientes;
                    return $res->validacionBDDGEMA();
                    break;

                # Agregar correo al paciente
                case 40:
                    $res = new Model\Laboratorio;
                    return $res->agregarCorreoElectrónicoPaciente();
                    break;

                // nuevo pedido de lboratoro
                case 41:
                    $res = new Model\Pedidos;
                    return $res->nuevoPedidoImg();
                    break;

                case 42:
                    $res = new Model\Pedidos;
                    return $res->processPedidoImg();
                    break;

                case 43:
                    $res = new Model\Pacientes;
                    return $res->getExamenesImg();
                    break;

                case 44:
                    $res = new Model\Pedidos;
                    return $res->getPedidosImgHM();
                    break;

                case 45:
                    $res = new Model\Pedidos;
                    return $res->nuevoPedidoImg();
                    break;

                case 46:
                    $res = new Model\Teleconsulta;
                    return $res->getParticipantesCall();
                    break;

                default:
                    throw new ModelsException('No existe una petición track definida.');
                    break;
            }

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => array(),
                'message' => $e->getMessage(),
                'logs' => $this->logs,
            );

        }

    }

    /**
     * Obtener los paceitnes de un medico
     *
     * @return array : Con información de éxito/falla.
     */

    public function rtf()
    {

        try {

            global $config, $http;

            $s1 = '{\rtf1\ansi\ansicpg1252\uc1\deff0\stshfdbch0\stshfloch0\stshfhich0\stshfbi0\deflang1033\deflangfe1033{\fonttbl{\f0\froman\fcharset0\fprq2{\*\panose 02020603050405020304}Times New Roman;}
            {\f2\fmodern\fcharset0\fprq1{\*\panose 02070309020205020404}Courier New;}{\f36\froman\fcharset238\fprq2 Times New Roman CE;}{\f37\froman\fcharset204\fprq2 Times New Roman Cyr;}{\f39\froman\fcharset161\fprq2 Times New Roman Greek;}
            {\f40\froman\fcharset162\fprq2 Times New Roman Tur;}{\f41\froman\fcharset177\fprq2 Times New Roman (Hebrew);}{\f42\froman\fcharset178\fprq2 Times New Roman (Arabic);}{\f43\froman\fcharset186\fprq2 Times New Roman Baltic;}
            {\f44\froman\fcharset163\fprq2 Times New Roman (Vietnamese);}{\f56\fmodern\fcharset238\fprq1 Courier New CE;}{\f57\fmodern\fcharset204\fprq1 Courier New Cyr;}{\f59\fmodern\fcharset161\fprq1 Courier New Greek;}
            {\f60\fmodern\fcharset162\fprq1 Courier New Tur;}{\f61\fmodern\fcharset177\fprq1 Courier New (Hebrew);}{\f62\fmodern\fcharset178\fprq1 Courier New (Arabic);}{\f63\fmodern\fcharset186\fprq1 Courier New Baltic;}
            {\f64\fmodern\fcharset163\fprq1 Courier New (Vietnamese);}}{\colortbl;\red0\green0\blue0;\red0\green0\blue255;\red0\green255\blue255;\red0\green255\blue0;\red255\green0\blue255;\red255\green0\blue0;\red255\green255\blue0;\red255\green255\blue255;
            \red0\green0\blue128;\red0\green128\blue128;\red0\green128\blue0;\red128\green0\blue128;\red128\green0\blue0;\red128\green128\blue0;\red128\green128\blue128;\red192\green192\blue192;}{\stylesheet{
            \ql \li0\ri0\widctlpar\aspalpha\aspnum\faauto\adjustright\rin0\lin0\itap0 \f2\fs20\lang12298\langfe1033\cgrid\langnp12298\langfenp1033 \snext0 \sautoupd \styrsid15158057 Normal;}{\*\cs10 \additive \ssemihidden Default Paragraph Font;}{\*
            \ts11\tsrowd\trftsWidthB3\trpaddl108\trpaddr108\tr';

            $s2 = 'paddfl3\trpaddft3\trpaddfb3\trpaddfr3\tscellwidthfts0\tsvertalt\tsbrdrt\tsbrdrl\tsbrdrb\tsbrdrr\tsbrdrdgl\tsbrdrdgr\tsbrdrh\tsbrdrv
            \ql \li0\ri0\widctlpar\aspalpha\aspnum\faauto\adjustright\rin0\lin0\itap0 \fs20\lang1024\langfe1024\cgrid\langnp1024\langfenp1024 \snext11 \ssemihidden Normal Table;}{\s15\ql \li0\ri0\widctlpar\aspalpha\aspnum\faauto\adjustright\rin0\lin0\itap0
            \f2\fs20\lang12298\langfe1033\cgrid\langnp12298\langfenp1033 \sbasedon0 \snext15 \styrsid15158057 Plain Text;}}{\*\latentstyles\lsdstimax156\lsdlockeddef0}{\*\rsidtbl \rsid15158057}
            \widowctrl\ftnbj\aenddoc\noxlattoyen\expshrtn\noultrlspc\dntblnsbdb\nospaceforul\formshade\horzdoc\dgmargin\dghspace180\dgvspace180\dghorigin1701\dgvorigin1984\dghshow1\dgvshow1
            \jexpand\pgbrdrhead\pgbrdrfoot\splytwnine\ftnlytwnine\htmautsp\nolnhtadjtbl\useltbaln\alntblind\lytcalctblwd\lyttblrtgr\lnbrkrule\nobrkwrptbl\snaptogridincell\allowfieldendsel\wrppunct\asianbrkrule\rsidroot15158057\newtblstyruls\nogrowautofit \fet0\sectd
            \linex0\endnhere\sectdefaultcl\sftnbj {\*\pnseclvl1\pnucrm\pnstart1\pnindent720\pnhang {\pntxta .}}{\*\pnseclvl2\pnucltr\pnstart1\pnindent720\pnhang {\pntxta .}}{\*\pnseclvl3\pndec\pnstart1\pnindent720\pnhang {\pntxta .}}{\*\pnseclvl4
            \pnlcltr\pnstart1\pnindent720\pnhang {\pntxta )}}{\*\pnseclvl5\pndec\pnstart1\pnindent720\pnhang {\pntxtb (}{\pntxta )}}{\*\pnseclvl6\pnlcltr\pnstart1\pnindent720\pnhang {\pntxtb (}{\pntxta )}}{\*\pnseclvl7\pnlcrm\pnstart1\pnindent720\pnhang {\pntxtb (}
            {\pntxta )}}{\*\pnseclvl8\pnlcltr\pnstart1\pnindent720\pnhang {\pntxtb (}{\pntxta )}}{\*\pnseclvl9\pnlcrm\pnstart1\pnindent720\pnhang {\pntxtb (}{\pntxta )}}\pard\plain
            \s15\qj \li0\ri0\widctlpar\aspalpha\aspnum\faauto\adjustright\rin0\lin0\itap0\pararsid15158057 \f2\fs20\lang12298\langfe1033\cgrid\langnp1';

            $s3 = "2298\langfenp1033 {\b\insrsid15158057\charrsid4471293 Standar de t\'f3rax del 3 de mayo de 2010:
                \par }{\insrsid15158057
                \par Peque\'f1o granuloma calcificado localizado en el v\'e9rtice pulmonar derecho. No hay evidencia lesi\'f3n pleuropulmonar activa. Silueta cardiovascular dentro de l\'edmites normales.
                \par
                \par }{\b\insrsid15158057\charrsid4471293 RX AP y lateral de columna cervical del 3 de mayo de 2010:
                \par }{\insrsid15158057
                \par Rectificaci\'f3n de la lordosis. No se observan signos evidentes de lesi\'f3n \'f3sea. El di\'e1metro del canal raqu\'eddeo los espacios discales est\'e1n conservados.}{\insrsid15158057\charrsid5055  }{\insrsid15158057
                \par }{\insrsid15158057\charrsid5055
                \par }}";

            file_put_contents('../v1/demo2.txt', $s1 . $s2 . $s3);

        } catch (ModelsException $e) {

            return array(
                'status' => false,
                'data' => array(),
                'message' => $e->getMessage(),
            );

        }

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
