<?php
/*
 * Copyright 2005-2010 MERETHIS
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give MERETHIS
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of MERETHIS choice, provided that
 * MERETHIS also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 * SVN : $URL: http://svn.modules.centreon.com/centreon-clapi/trunk/www/modules/centreon-clapi/core/class/centreonHost.class.php $
 * SVN : $Id: centreonHost.class.php 25 2010-03-30 05:52:19Z jmathis $
 *
 */

require_once "centreonObject.class.php";
require_once "centreonACL.class.php";
require_once "centreonHost.class.php";
require_once "Centreon/Object/Host/Group.php";
require_once "Centreon/Object/Host/Host.php";
require_once "Centreon/Object/Service/Group.php";
require_once "Centreon/Object/Service/Service.php";
require_once "Centreon/Object/Relation/Host/Service.php";
require_once "Centreon/Object/Relation/Host/Group/Host.php";
require_once "Centreon/Object/Relation/Host/Group/Service/Service.php";
require_once "Centreon/Object/Relation/Host/Group/Service/Group.php";

/**
 * Class for managing host groups
 *
 * @author sylvestre
 */
class CentreonHostGroup extends CentreonObject
{
    const ORDER_UNIQUENAME        = 0;
    const ORDER_ALIAS             = 1;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->object = new Centreon_Object_Host_Group();
        $this->params = array('hg_snmp_community'           => 'public',
                              'hg_snmp_version'             => '2c',
                              'hg_activate'                 => '1');
        $this->insertParams = array('hg_name', 'hg_alias');
        $this->exportExcludedParams = array_merge($this->insertParams, array($this->object->getPrimaryKey()));
        $this->action = "HG";
        $this->nbOfCompulsoryParams = count($this->insertParams);
        $this->activateField = "hg_activate";
    }

    /**
     * Display all Host Groups
     *
     * @param string $parameters
     * @return void
     */
    public function show($parameters = null)
    {
        $filters = array();
        if (isset($parameters)) {
            $filters = array($this->object->getUniqueLabelField() => "%".$parameters."%");
        }
        $params = array('hg_id', 'hg_name', 'hg_alias');
        $paramString = str_replace("hg_", "", implode($this->delim, $params));
        echo $paramString . "\n";
        $elements = $this->object->getList($params, -1, 0, null, null, $filters);
        foreach ($elements as $tab) {
            echo implode($this->delim, $tab) . "\n";
        }
    }

    /**
     * Add action
     *
     * @param string $parameters
     * @return void
     * @throws CentreonClapiException
     */
    public function add($parameters = null)
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < $this->nbOfCompulsoryParams) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $addParams = array();
        $addParams[$this->object->getUniqueLabelField()] = $params[self::ORDER_UNIQUENAME];
        $addParams['hg_alias'] = $params[self::ORDER_ALIAS];
        $this->params = array_merge($this->params, $addParams);
        $this->checkParameters();
        parent::add();
    }

	/**
     * Del Action
     * Must delete services as well
     *
     * @param string $objectName
     * @return void
     * @throws CentreonClapiException
     */
    public function del($objectName)
    {
        parent::del($objectName);
        $this->db->query("DELETE FROM service WHERE service_register = '1' AND service_id NOT IN (SELECT service_service_id FROM host_service_relation)");
    }


    /**
     * Set params
     *
     * @param string $parameters
     * @return void
     * @throws CenrtreonClapiException
     */
    public function setparam($parameters = null)
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < self::NB_UPDATE_PARAMS) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        if (($objectId = $this->getObjectId($params[self::ORDER_UNIQUENAME])) != 0) {
            if (!preg_match("/^hg_/", $params[1])) {
                $params[1] = "hg_".$params[1];
            }
            $updateParams = array($params[1] => $params[2]);
            parent::setparam($objectId, $updateParams);
        } else {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND.":".$params[self::ORDER_UNIQUENAME]);
        }
    }

    /**
     * Magic method
     *
     * @param string $name
     * @param array $args
     * @return void
     * @throws CentreonClapiException
     */
    public function __call($name, $arg)
    {
        $name = strtolower($name);
        if (!isset($arg[0])) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $args = explode($this->delim, $arg[0]);
        $hgIds = $this->object->getIdByParameter($this->object->getUniqueLabelField(), array($args[0]));
        if (!count($hgIds)) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND .":".$args[0]);
        }
        $groupId = $hgIds[0];
        if (preg_match("/^(get|set|add|del)(member|host|servicegroup)$/", $name, $matches)) {
            if ($matches[2] == "host" || $matches[2] == "member") {
                $relobj = new Centreon_Object_Relation_Host_Group_Host();
                $obj = new Centreon_Object_Host();
            } elseif ($matches[2] == "servicegroup") {
                $relobj = new Centreon_Object_Relation_Host_Group_Service_Group();
                $obj = new Centreon_Object_Service_Group();
            }
            if ($matches[1] == "get") {
                $tab = $relobj->getTargetIdFromSourceId($relobj->getSecondKey(), $relobj->getFirstKey(), $hgIds);
                echo "id".$this->delim."name"."\n";
                foreach($tab as $value) {
                    $tmp = $obj->getParameters($value, array($obj->getUniqueLabelField()));
                    echo $value . $this->delim . $tmp[$obj->getUniqueLabelField()] . "\n";
                }
            } else {
                if (!isset($args[1])) {
                    throw new CentreonClapiException(self::MISSINGPARAMETER);
                }
                $relation = $args[1];
                $relations = explode("|", $relation);
                $relationTable = array();
                foreach($relations as $rel) {
                    $tab = $obj->getIdByParameter($obj->getUniqueLabelField(), array($rel));
                    if (!count($tab)) {
                        throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":".$rel);
                    }
                    $relationTable[] = $tab[0];
                }
                if ($matches[1] == "set") {
                    $relobj->delete($groupId);
                }
                $existingRelationIds = $relobj->getTargetIdFromSourceId($relobj->getSecondKey(), $relobj->getFirstKey(), array($groupId));
                foreach($relationTable as $relationId) {
                    if ($matches[1] == "del") {
                        $relobj->delete($groupId, $relationId);
                    } elseif ($matches[1] == "set" || $matches[1] == "add") {
                        if (!in_array($relationId, $existingRelationIds)) {
                            $relobj->insert($groupId, $relationId);
                        }
                    }
                }
                $acl = new CentreonACL();
                $acl->reload(true);
            }
        } else {
            throw new CentreonClapiException(self::UNKNOWN_METHOD);
        }
    }

    /**
     * Export
     *
     * @return void
     */
    public function export()
    {
        parent::export();
        $relObj = new Centreon_Object_Relation_Host_Group_Host();
        $hostObj = new Centreon_Object_Host();
        $hgFieldName = $this->object->getUniqueLabelField();
        $hFieldName = $hostObj->getUniqueLabelField();
        $elements = $relObj->getMergedParameters(array($hgFieldName), array($hFieldName), -1, 0, $hgFieldName, null);
        foreach ($elements as $element) {
            echo $this->action.$this->delim."addhost".$this->delim.$element[$hgFieldName].$this->delim.$element[$hFieldName]."\n";
        }
    }
}
?>