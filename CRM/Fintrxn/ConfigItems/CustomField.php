<?php
/**
 * Class for CustomField configuration
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 9 March 2017
 * @license AGPL-3.0
 */
class CRM_Fintrxn_ConfigItems_CustomField {

  protected $_apiParams = array();

  /**
   * CRM_Fintrxn_ConfigItems_CustomField constructor.
   */
  public function __construct() {
    $this->_apiParams = array();
  }

  /**
   * Method to validate params for create
   *
   * @param array $params
   * @throws Exception when missing mandatory params
   */
  private function validateCreateParams($params) {
    if (!isset($params['name']) || empty($params['name']) || !isset($params['custom_group_id'])
      || empty($params['custom_group_id'])) {
      throw new Exception(ts('When trying to create a Custom Field name and custom_group_id are
      mandatory parameters and can not be empty').' '.ts('in').' '.__METHOD__);
    }
    $this->_apiParams = $params;
    if (isset($this->_apiParams['option_group'])) {
      $this->_apiParams['option_type'] = 0;
      $optionGroup = new CRM_Fintrxn_ConfigItems_OptionGroup();
      $found = $optionGroup->getWithName($this->_apiParams['option_group']);
      if (!empty($found)) {
        $this->_apiParams['option_group_id'] = $found['id'];
      } else {
        $created = $optionGroup->create(array('name' => $this->_apiParams['option_group']));
        $this->_apiParams['option_group_id'] = $created['id'];
      }
      unset($this->_apiParams['option_group']);
    }
  }

  /**
   * Method to create or update custom field
   *
   * @param array $params
   * @throws Exception when error from API CustomField Create
   */
  public function create($params) {
    $this->validateCreateParams($params);
    $existing = $this->getWithNameCustomGroupId($this->_apiParams['name'], $this->_apiParams['custom_group_id']);
    if (isset($existing['id'])) {
      $this->_apiParams['id'] = $existing['id'];
    }
    if (!isset($this->_apiParams['label']) || empty($this->_apiParams['label'])) {
      $this->_apiParams['label'] = CRM_Fintrxn_Utils::buildLabelFromName($this->_apiParams['name']);
    }
    try {
      civicrm_api3('CustomField', 'create', $this->_apiParams);
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception(ts('Could not create or update custom field with name').' '.$this->_apiParams['name']
        .' '.ts('in custom group'). ' '.$this->_apiParams['custom_group_id'].', '.ts('error from API CustomField Create').': '
        .$ex->getMessage());
    }
  }

  /**
   * Method to get custom field with name and custom group id
   *
   * @param string $name
   * @param integer $customGroupId
   * @return array|bool
   */
  public function getWithNameCustomGroupId($name, $customGroupId) {
    try {
      return civicrm_api3('CustomField', 'getsingle', array('name' => $name, 'custom_group_id' => $customGroupId));
    } catch (CiviCRM_API3_Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Method to remove custom fields that are not in the config custom group data
   *
   * @param int $customGroupId
   * @param array $configCustomGroupData
   * @return boolean
   * @access public
   * @static
   */
  public static function removeUnwantedCustomFields($customGroupId, $configCustomGroupData) {
    if (empty($customGroupId)) {
      return FALSE;
    }
    // first get all existing custom fields from the custom group
    try {
      $existingCustomFields = civicrm_api3('CustomField', 'get', array('custom_group_id' => $customGroupId));
      foreach ($existingCustomFields['values'] as $existingId => $existingField) {
        // if existing field not in config custom data, delete custom field
        if (!isset($configCustomGroupData['fields'][$existingField['name']])) {
          civicrm_api3('CustomField', 'delete', array('id' => $existingId));
        }
      }
    } catch (CiviCRM_API3_Exception $ex) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Method to get all custom fields for a custom group id
   *
   * @param int $customGroupId
   * @return array
   */
  public function getAllWithCustomGroupId($customGroupId) {
    try {
      $customFields = civicrm_api3('CustomField', 'get', array('custom_group_id' => $customGroupId));
      return $customFields['values'];
    } catch (CiviCRM_API3_Exception $ex) {
      return array();
    }
  }
}