<?php
/**
 * Class for CustomGroup configuration
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 9 March 2017
 * @license AGPL-3.0
 */
class CRM_Fintrxn_ConfigItems_CustomGroup {

  protected $_apiParams = array();

  /**
   * CRM_Fintrxn_ConfigItems_CustomGroup constructor.
   */
  public function __construct() {
    $this->_apiParams = array();
  }

  /**
   * Method to validate params for create
   *
   * @param $params
   * @throws Exception
   */
  private function validateCreateParams($params) {
    if (!isset($params['name']) || empty($params['name']) || !isset($params['extends']) ||
      empty($params['extends'])) {
      throw new Exception(ts('When trying to create a Custom Group name and extends are mandatory parameters
      and can not be empty').ts(' in ').__METHOD__);
    }
    $this->buildApiParams($params);
  }

  /**
   * Method to create custom group
   *
   * @param array $params
   * @return array
   * @throws Exception when error from API CustomGroup Create
   */
  public function create($params) {
    $this->validateCreateParams($params);
    $existing = $this->getWithName($this->_apiParams['name']);
    if (isset($existing['id'])) {
      $this->_apiParams['id'] = $existing['id'];
    }
    if (!isset($this->_apiParams['title']) || empty($this->_apiParams['title'])) {
      $this->_apiParams['title'] = CRM_Fintrxn_Utils::buildLabelFromName($this->_apiParams['name']);
    }
    try {
      $customGroup = civicrm_api3('CustomGroup', 'create', $this->_apiParams);
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception(ts('Could not create or update custom group with name').' ' . $this->_apiParams['name']
        . ts(' to extend ') . $this->_apiParams['extends'] . ', '.ts('in').' '.__METHOD__
        .ts('error from API CustomGroup create: ') .$ex->getMessage() . ", ".ts("parameters")." : "
        . implode(";", $this->_apiParams));
    }
    return $customGroup['values'][$customGroup['id']];
  }

  /**
   * Method to get custom group with name
   *
   * @param string $name
   * @return array|bool
   */
  public function getWithName($name) {
    try {
      return civicrm_api3('CustomGroup', 'getsingle', array('name' => $name));
    } catch (CiviCRM_API3_Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Method to get custom group table name with name
   *
   * @param string $name
   * @return array|bool
   */
  public function getTableNameWithName($name) {
    try {
      return civicrm_api3('CustomGroup', 'getvalue', array('name' => $name, 'return' => 'table_name'));
    } catch (CiviCRM_API3_Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Method to build api param list
   *
   * @param array $params
   */
  protected function buildApiParams($params) {
    $this->_apiParams = array();
    foreach ($params as $name => $value) {
      if ($name != 'fields') {
        $this->_apiParams[$name] = $value;
      }
    }
    if ($this->_apiParams['extends'] == "Campaign") {
      if (isset($this->_apiParams['extends_entity_column_value']) && !empty($this->_apiParams['extends_entity_column_value'])) {
        if (is_array($this->_apiParams['extends_entity_column_value'])) {
          foreach ($this->_apiParams['extends_entity_column_value'] as $extendsValue) {
            $campaignType = new CRM_Fintrxn_ConfigItems_OptionValue();
            $found = $campaignType->getWithNameAndOptionGroupId($extendsValue, 'campaign_type');
            if (isset($found['value'])) {
              $this->_apiParams['extends_entity_column_value'][] = $found['value'];
            }
            unset ($campaignType);
          }
        } else {
          $campaignType = new CRM_Fintrxn_ConfigItems_OptionValue();
          $found = $campaignType->getWithNameAndOptionGroupId($this->_apiParams['extends_entity_column_value'], 'campaign_type');
          if (isset($found['value'])) {
            $this->_apiParams['extends_entity_column_value'] = $found['value'];
          }
        }
      }
    }
  }

  /**
   * Method to remove custom groups and fields when extension is uninstalled
   *
   * @param string $customGroupName
   */
  public function uninstall($customGroupName) {
    //try {
      $customGroupId = civicrm_api3('CustomGroup', 'getvalue', array('name' => $customGroupName, 'return' => 'id'));
      civicrm_api3('CustomGroup', 'delete', array('id' => $customGroupId));
    //} catch (CiviCRM_API3_Exception $ex) {}
  }

}