<?php
/**
 * Interfaz para acceder a las empresas del facturador
 * 
 * Funciones para crear, modificar, y coger información de empresas
 * 
 * PHP version 7.1
 * 
 * @category  Facturacion-electronica
 * @package   Contica\Facturacion
 * @author    Josias Martin <josias@solucionesinduso.com>
 * @copyright 2018 Josias Martin
 * @license   https://opensource.org/licenses/MIT MIT
 * @version   GIT: <git-id>
 * @link     https://github.com/josiasmc/facturador-electronico-cr
 */

namespace Contica\Facturacion;

use \Defuse\Crypto\Crypto;

/**
 * Class providing functions to manage companies
 * 
 * @category Facturacion-electronica
 * @package  Contica\Facturacion\Empresas
 * @author   Josias Martin <josias@solucionesinduso.com>
 * @license  https://opensource.org/licenses/MIT MIT
 * @version  Release: <package-version>
 * @link     https://github.com/josiasmc/facturador-electronico-cr
 */
class Empresas
{
    protected $container;
    protected $log;


    /**
     * Class constructor
     * 
     * @param array $container The Invoicer container
     */
    public function __construct($container)
    {
        $this->container = $container;
        $this->log = $container['log'];
    }

    /**
     * Revisar si ya hay una empresa con cierta cedula
     * 
     * @param int $cedula La cedula de la empresa
     * 
     * @return int Id unico de la empresa si existe
     */
    public function buscarPorCedula($cedula)
    {
        $db = $this->container['db'];
        $client_id = $this->container['client_id'];
        $sql = "SELECT id_empresa FROM fe_empresas
        WHERE cedula='$cedula' AND id_cliente='$client_id'";
        $r = $db->query($sql);
        if ($r->num_rows > 0) {
            $cps = [];
            while ($row = $r->fetch_row()) {
                $cps[] = $row[0];
            }
            return $cps;
        } else {
            return false;
        }
    }

    /**
     * Crear una nueva empresa para el cliete actual
     * 
     * @param array $data Informacion de la empresa
     * 
     * @return int El ID de la empresa creada
     */
    public function add($data)
    {
        $db = $this->container['db'];
        $client_id = $this->container['client_id'];
        $data['id_cliente'] = $client_id;

        $prepedData = $this->_prepData($data);
        $fields = "";
        $values = "";
        foreach ($prepedData as $key => $value) {
            $fields .= $key . ', ';
            $values .= $this->_prepValue($value) . ', ';
        }
        $fields = rtrim($fields, ", ");
        $values = rtrim($values, ", ");
        $sql = "INSERT INTO fe_empresas ($fields) VALUES ($values)";
        if ($db->query($sql) === true) {
            return $db->insert_id;
        } else {
            $this->log->error("Error guardando empresa para el cliente $client_id: $db->error");
            throw new \Exception("Error guardando empresa: " . $db->error);
        }
        return false;
    }

    /**
     * Modificar una empresa existente
     * 
     * @param int   $id   El ID unico de la empresa
     * @param array $data La informacion de la empresa a modificar
     * 
     * @return bool resultado
     */
    public function modify($id, $data)
    {
        $db = $this->container['db'];
        $client_id = $this->container['client_id'];
        $prepedData = $this->_prepData($data);
        $values = '';
        foreach ($prepedData as $key => $value) {
            $values .= $key . '=' . $this->_prepValue($value) . ', ';
        }
        $values = rtrim($values, ', ');
        $sql = "UPDATE fe_empresas SET $values
        WHERE id_empresa='$id' AND id_cliente='$client_id'";
        if ($r = $db->query($sql) === true) {
            if ($db->affected_rows > 0) {
                return true;
            } else {
                return false;
            }
        } else {
            $this->log->error("Error actualizando empresa $id para el cliente $client_id: $db->error");
            throw new \Exception("Error actualizando empresa: " . $db->error);
        }
    }

    /**
     * Get an existing company
     * 
     * @param int $id Id unico de la empresa.
     *                Si no se provee, devuelve todas las del cliente
     * 
     * @return array Toda la informacion de la empresa, sin la llave criptografica
     */
    public function get($id = '')
    {
        $db = $this->container['db'];
        $client_id = $this->container['client_id'];
        $cryptoKey = $this->container['crypto_key'];

        if ($id != '') {
            $stmt = $db->prepare("SELECT id_empresa AS id, cedula, usuario_mh AS usuario,
            contra_mh AS contra, id_ambiente AS ambiente FROM fe_empresas
            WHERE id_cliente=? AND id_empresa=?");
            $stmt->bind_param('si', $client_id, $id);
        } else {
            $stmt = $db->prepare("SELECT id_empresa AS id, cedula FROM fe_empresas
            WHERE id_cliente=?");
            $stmt->bind_param('s', $client_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $return = [];
            while ($data = $result->fetch_assoc()) {
                if ($id) {
                    // Decrypt the encrypted entries
                    foreach (['usuario', 'contra', 'pin'] as $key) {
                        if ($data[$key]) {
                            $data[$key] = Crypto::decrypt($data[$key], $cryptoKey);
                        }
                    }
                }
                $return[] = $data;
            }
            return $id == '' ? $return : $return[0];
        }
        return false;
    }

    /**
     * Get the Ministerio de Hacienda certificate
     * 
     * @param int $id The company's cedula
     * 
     * @return string The certificate
     */
    public function getCert($id)
    {
        $db = $this->container['db'];
        $sql = "SELECT Certificado_mh FROM Empresas WHERE Cedula=$id";
        $result = $db->query($sql);
        if ($result->num_rows > 0) {
            return $result->fetch_assoc()['Certificado_mh'];
        }
        return false;
    }

    /**
     * Prepare company data for database storage
     * 
     * @param array $data The data to prepare
     * 
     * @return array The prepared data
     */
    private function _prepData($data)
    {
        $db = $this->container['db'];
        $cryptoKey = $this->container['crypto_key'];
        // The fields that need sql escaping
        $fields = [
            'id_ambiente' => 'ambiente',
            'llave_criptografica' => 'llave_criptografica',
            'cedula' => 'cedula',
            'id_cliente' => 'id_cliente'
        ];
        $prepd = array();
        foreach ($fields as $key => $value) {
            if (array_key_exists($value, $data)) {
                $prepd[$key] = $db->real_escape_string($data[$value]);
            }
        }
        // The fields that need encryption
        $fields = [
            'usuario_mh' => 'usuario',
            'contra_mh' => 'contra',
            'pin_llave' => 'pin'
        ];
        foreach ($fields as $key => $value) {
            if (array_key_exists($value, $data)) {
                if ($cryptoKey) {
                    $prepd[$key] = Crypto::encrypt((string)$data[$value], $cryptoKey);
                } else {
                    $prepd[$key] = $data[$value];
                }
            }
        }
        return $prepd;
    }

    /** 
     * Quote non integer strings with ''
     * 
     * @param String $value Text needing quotes
     * 
     * @return String
     */
    private function _prepValue($value)
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            $value = "'" . $value . "'";
        }
        return $value;
    }
}