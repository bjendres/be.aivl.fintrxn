<?php
/**
 * Class for specific AIVL COCOA Code processing
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 21 March 2017
 * @license AGPL-3.0
 */
class CRM_Fintrxn_CocoaCode {

  private $_campaignAccountTypeCode = NULL;
  private $_ibanAccountTypeCode = NULL;

  /**
   * CRM_Fintrxn_CocoaCode constructor.
   */
  function __construct() {
    $config = CRM_Fintrxn_Configuration::singleton();
    $this->_campaignAccountTypeCode = $config->getCampaignAccountTypeCode();
    $this->_ibanAccountTypeCode = $config->getIbanAccountTypeCode();
  }

  /**
   * Method to retrieve the AIVL financial accounts linked to COCOA with the COCOA code and the account type code
   *
   * @param $cocoaCode
   * @param $accountTypeCode
   * @return array|bool
   */
  public function findAccountWithName($accountName, $accountTypeCode) {
    $result = array();
    try {
      $result = civicrm_api3('FinancialAccount', 'getsingle', array(
        'name' => $accountName,
        'account_type_code' => $accountTypeCode
      ));
    } catch (CiviCRM_API3_Exception $ex) {
      // todo log error
    }
    return $result;
  }

  /**
   * Method to retrieve the AIVL financial accounts linked to COCOA with the COCOA code and the account type code
   *
   * @param $cocoaCode
   * @param $accountTypeCode
   * @return array|bool
   */
  public function findAccountWithAccountCode($cocoaCode, $accountTypeCode) {
    $result = array();
    try {
      $result = civicrm_api3('FinancialAccount', 'getsingle', array(
        'accounting_code' => $cocoaCode,
        'account_type_code' => $accountTypeCode
      ));
    } catch (CiviCRM_API3_Exception $ex) {
      // todo log error
    }
    return $result;
  }

  /**
   * Method to get the custom field id for the cocoa custom field with type
   *
   * @param $type
   * @return bool|mixed
   */
  private function getCustomFieldId($type) {
    // return false if no valid types
    $validTypes = array('profit_loss', 'acquisition', 'following');
    if (!in_array($type, $validTypes)) {
      return FALSE;
    }
    $config = CRM_Fintrxn_Configuration::singleton();
    switch ($type) {
      case 'profit_loss':
        return $config->getCocoaProfitLossCustomField('id');
        break;
      case 'acquisition':
        return $config->getCocoaCodeAcquisitionCustomField('id');
        break;
      case 'following':
        return $config->getCocoaCodeFollowCustomField('id');
        break;
    }
  }

  /**
   * Method to load cocoa code into the option group
   *
   * @param $sourceRecord
   * @param $logger
   */
  public function load($sourceRecord, $logger) {
    if ($this->validSourceRecord($sourceRecord, $logger) == TRUE) {
      $config = CRM_Fintrxn_Configuration::singleton();
      $count = civicrm_api3('OptionValue', 'getcount', array(
        'option_group_id' => $config->getCocoaCodeOptionGroupId(),
        'value' => $sourceRecord['cocoa_value']
      ));
      if ($count == 0) {
        $created = $this->createOptionValue($sourceRecord['cocoa_value'], $sourceRecord['cocoa_label']);
        $logger->logMessage('Info', 'Created option value '.$created['value']
          .' with label '.$created['label']);
      } else {
        $logger->logMessage('Info', 'Option value found for cocoa code '.$sourceRecord['cocoa_value']
          .' with label '.$sourceRecord['cocoa_label']);
      }
    }
  }

  /**
   * Private method to validate sourceRecord for load of COCOA codes
   *
   * @param $sourceRecord
   * @param $logger
   * @return bool
   */
  private function validSourceRecord($sourceRecord, $logger) {
    $message = NULL;
    if (!is_array($sourceRecord)) {
      $message = 'Line ignored, could not recognize sourceRecord as an array';
    } else {
      $expectedElements = array('cocoa_value', 'cocoa_label', 'id', 'source');
      foreach ($expectedElements as $expectedElement) {
        if (!array_key_exists($expectedElement, $sourceRecord)) {
          $message = 'Line ignored, could not find ' . $expectedElement . ' in source record';
        }
      }
      if (isset($sourceRecord['cocoa_value']) && empty($sourceRecord['cocoa_value'])) {
        $message = 'Line ignored, empty COCOA code in source record';
      }
    }
    if (!empty($message)) {
      if (isset($sourceRecord['id'])) {
        $message .= ' in line ' . $sourceRecord['id'];
      }
      $logger->logMessage('Warning', $message);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Method to create option value for cocoa code
   *
   * @param $value
   * @param $label
   * @return mixed
   * @throws Exception when error from API
   */
  private function createOptionValue($value, $label) {
    if (empty($label)) {
      $label = 'AIVL COCOA code '.$value;
    }
    $config = CRM_Fintrxn_Configuration::singleton();
    try {
      $created = civicrm_api3('OptionValue', 'create', array(
        'option_group_id' => $config->getCocoaCodeOptionGroupId(),
        'name' => 'aivl_cocoa_code_'.$value,
        'value' => $value,
        'label' => $label,
        'is_active' => 1,
        'is_reserved' => 1
      ));
      return $created['values'][$created['id']];
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception(ts('Could not create an option value in').' '.__METHOD__.' '.ts('for COCOA Code').' '
        .$value.' '.ts('with label').' '.$label.' .'.ts('Contact your system administrator').', '
        .ts('error from API OptionValue create').': '
        .$ex->getMessage());
    }
  }

  /**
   * Method to check if the cocoa code has a corresponding financial account
   *
   * @param $cocoaCode
   * @return bool
   */
  public function cocoaCodeFinancialAccountExists($cocoaCode) {
    try {
      $count = civicrm_api3('FinancialAccount', 'getcount', array(
        'account_type_code' => $this->_campaignAccountTypeCode,
        'accounting_code' => $cocoaCode
      ));
      if ($count > 0) {
        return TRUE;
      }
    } catch (CiviCRM_API3_Exception $ex) {}
    return FALSE;
  }

  /**
   * Method to create a new financial account for a cocao code
   *
   * @param $cocoaCode
   * @param $accountTypeCode
   * @return mixed
   * @throws Exception when error from API
   */
  public function createFinancialAccount($cocoaCode, $accountTypeCode) {
    try {
      $created = civicrm_api3('FinancialAccount', 'create', array(
        'name' => 'COCAO Code '.$cocoaCode,
        'description' => 'AIVL COCOA code '.$cocoaCode.' (niet aankomen!)',
        'accounting_code' => $cocoaCode,
        'account_type_code' => $accountTypeCode,
        'is_reserved' => 1,
        'is_active' => 1
      ));
      return $created['values'];
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception(ts('Could not create a financial acccount in').' '.__METHOD__.' '.ts('for COCOA Code').' '
        .$cocoaCode.' .'.ts('Contact your system administrator').', '.ts('error from API FinancialAccount create').': '
        .$ex->getMessage());
    }
  }

  /**
   * Method to find the mapping for the COCOA Load
   *
   * @return array|bool
   */
  public static function getLoadMapping() {
    $config = CRM_Fintrxn_Configuration::singleton();
    $jsonFile = $config->getResourcesPath().'cocoa_mapping.json';
    if (file_exists($jsonFile)) {
      $mappingJson = file_get_contents($jsonFile);
      return json_decode($mappingJson, true);
    }
    return FALSE;
  }

  /**
   * Method to process the hook_civicrm_custom for the campaign COCOA fields
   * Whenever a COCOA account is created or updated, we need to make sure the financial account exists
   * and create it if it does not
   *
   * @param $op
   * @param $groupId
   * @param $entityId
   * @param $params
   */
  public static function custom($op, $groupId, $entityId, &$params ) {
    $config = CRM_Fintrxn_Configuration::singleton();
    // if custom group id is the cocoa custom group id
    if ($groupId == $config->getCocoaCustomGroup('id')) {
      $relevantCustomFields = array(
        $config->getCocoaProfitLossCustomField('column_name'),
        $config->getCocoaCodeAcquisitionCustomField('column_name'),
        $config->getCocoaCodeFollowCustomField('column_name')
      );
      foreach ($params as $paramKey => $param) {
        if (in_array($param['column_name'], $relevantCustomFields)) {
          $cocoaCode = new CRM_Fintrxn_CocoaCode();
          if ($cocoaCode->cocoaCodeFinancialAccountExists($param['value']) == FALSE) {
            $cocoaCode->createFinancialAccount($param['value'], $cocoaCode->getCampaignAccountTypeCode());
          }
        }
      }
    }
  }
}