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
  private $_plTypeCode = NULL;
  private $_defaultCocoaAcquisition = NULL;
  private $_defaultCocoaLater = NULL;
  private $_defaultCocoaPl = NULL;

  /**
   * CRM_Fintrxn_CocoaCode constructor.
   */
  function __construct() {
    $config = CRM_Fintrxn_Configuration::singleton();
    $this->_campaignAccountTypeCode = $config->getCampaignAccountTypeCode();
    $this->_ibanAccountTypeCode = $config->getIbanAccountTypeCode();
    $this->_plTypeCode = $config->getPlTypeCode();
  }

  /**
   * Method to retrieve the AIVL financial accounts linked to COCOA with the COCOA code and the account type code
   *
   * @param $accountName
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
   * Method to load cocoa code into the option group
   *
   * @param $cocoaId
   * @param $cocoaData
   * @throws Exception when no type in $cocoaData
   */
  public function load($cocoaId, $cocoaData) {
    if (!isset($cocoaData['type'])) {
      throw new Exception('No required element type found in parameter $cocoaData in '.__METHOD__);
    }
    $config = CRM_Fintrxn_Configuration::singleton();
    /* process based on type, could be:
     * - the one record used for bankaccounts
     * - records used for cocoa cost centres
     * - records used for cocoa profit loss
     */
    switch ($cocoaData['type']) {
      case $config->getIbanAccountTypeCode():
        $this->loadCocoaBankAccount($cocoaId);
        break;
      case $config->getCampaignAccountTypeCode():
        $this->loadCocoaCostCentre($cocoaId, $cocoaData);
        break;
      case $config->getPlTypeCode():
        $this->loadCocoaProfitLoss($cocoaId, $cocoaData);
        break;
    }
  }

  /**
   * Method to create option value for cocoa cost centre
   *
   * @param $cocoaId
   * @param $cocoaData
   * @throws Exception
   */
  private function loadCocoaCostCentre($cocoaId, $cocoaData) {
    $config = CRM_Fintrxn_Configuration::singleton();
    // first try to get option value and create if not found
    try {
      civicrm_api3('OptionValue', 'getsingle', array(
        'option_group_id' => $config->getCocoaCostCentreOptionGroupId(),
        'value' => $cocoaId,
        'name' => 'aivl_cost_centre_'.$cocoaId
      ));
    }
    catch (CiviCRM_API3_Exception $ex) {
      try {
        civicrm_api3('OptionValue', 'create', array(
          'option_group_id' => $config->getCocoaCostCentreOptionGroupId(),
          'name' => 'aivl_cost_centre_'.$cocoaId,
          'label' => $cocoaId.' ('.$cocoaData['description'].')',
          'value' => $cocoaId,
          'is_active' => 1,
          'is_reserved' => 1
        ));
      }
      catch (CiviCRM_API3_Exception $ex) {
        throw new Exception('Could not create an option value for cocoa code '.$cocoaId.' in '.__METHOD__
          .', contact your system administrator. Error from API OptionValue create: '.$ex->getMessage());
      }
    }
  }

  /**
   * @param $cocoaId
   * @param $cocoaData
   * @throws Exception
   */
  private function loadCocoaProfitLoss($cocoaId, $cocoaData) {
    $config = CRM_Fintrxn_Configuration::singleton();
    // first try to get option value and create if not found
    try {
      civicrm_api3('OptionValue', 'getsingle', array(
        'option_group_id' => $config->getCocoaProfitLossOptionGroupId(),
        'value' => $cocoaId,
        'name' => 'aivl_profit_loss_'.$cocoaId
      ));
    }
    catch (CiviCRM_API3_Exception $ex) {
      try {
        civicrm_api3('OptionValue', 'create', array(
          'option_group_id' => $config->getCocoaProfitLossOptionGroupId(),
          'name' => 'aivl_profit_loss_'.$cocoaId,
          'label' => $cocoaId.' ('.$cocoaData['description'].')',
          'value' => $cocoaId,
          'is_active' => 1,
          'is_reserved' => 1
        ));
      }
      catch (CiviCRM_API3_Exception $ex) {
        throw new Exception('Could not create an option value for cocoa code '.$cocoaId.' in '.__METHOD__
          .', contact your system administrator. Error from API OptionValue create: '.$ex->getMessage());
      }
    }
  }

  /**
   * Method to load the correct cocoa code for bank accounts.
   *
   * @param $cocoaId
   * @throws Exception when error from api to update financial account
   */
  private function loadCocoaBankAccount($cocoaId) {
    $config = CRM_Fintrxn_Configuration::singleton();
    // find all relevant bank accounts from option group
    $optionGroupId = $config->getIncomingAccountCustomField('option_group_id');
    if (!empty($optionGroupId)) {
      $optionValues = civicrm_api3('OptionValue', 'get', array(
        'option_group_id' => $optionGroupId,
        'options' => array('limit' => 0),
      ));
      foreach ($optionValues['values'] as $optionValue) {
        // update all related financial accounts and create if they do not exist
        $financialAccount = $this->findAccountWithName($optionValue['value'], $config->getIbanAccountTypeCode());
        if (!empty($financialAccount)) {
          try {
            civicrm_api3('FinancialAccount', 'create', array(
              'id' => $financialAccount['id'],
              'accounting_code' => $cocoaId
            ));
          }
          catch (CiviCRM_API3_Exception $ex) {
            throw new Exception('Could not update financial account with new accounting code '.$cocoaId
              .' in '.__METHOD__.', contact your system administrator. Error from API FinancialAccount create: '.$ex->getMessage());
          }
        } else {
          $this->createFinancialAccount(array(
            'name' => $optionValue['value'],
            'description' => 'AIVL COCOA code for account '.$optionValue['label'].' (niet aankomen!)',
            'accounting_code' => $cocoaId,
            'account_type_code' => $config->getIbanAccountTypeCode(),
            'is_reserved' => 1,
            'is_active' => 1,));
        }
      }
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
   * Method to create a new financial account for a COCOA campaign code
   *
   * @param $finAccountData
   * @return mixed
   * @throws Exception when error from API
   */
  public function createFinancialAccount($finAccountData) {
    try {
      $created = civicrm_api3('FinancialAccount', 'create', $finAccountData);
      return $created['values'];
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception(ts('Could not create a financial acccount in').' '.__METHOD__.' '
        .ts(', contact your system administrator').', '.ts('error from API FinancialAccount create').': '.$ex->getMessage());
    }
  }

  /**
   * Method to clean the cocoa option groups of their existing values
   */
  public function cleanOptionValues() {
    $relevantGroups = array('aivl_cocoa_cost_centre', 'aivl_cocoa_profit_loss');
    foreach ($relevantGroups as $relevantName) {
      try {
        $optionValues = civicrm_api3('OptionValue', 'get', array(
          'option_group_id' => $relevantName,
          'options' => array('limit' => 0,),
        ));
        foreach ($optionValues['values'] as $optionValue) {
          civicrm_api3('OptionValue', 'delete', array(
            'id' => $optionValue['id'],
          ));
        }
      }
      catch (CiviCRM_API3_Exception $ex) {
      }
    }
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
            $finAccountParams = array(
              'name' => 'COCAO Code '.$param['value'],
              'description' => 'AIVL COCOA code '.$param['value'].' (niet aankomen!)',
              'accounting_code' => $param['value'],
              'account_type_code' => $config->getCampaignAccountTypeCode(),
              'is_reserved' => 1,
              'is_active' => 1,
            );
            $cocoaCode->createFinancialAccount($finAccountParams);
          }
        }
      }
    }
  }

  /**
   * Method to migrate old cocoa data into the new ones
   *
   * @param $data
   * @return array
   */
  public function migrate($data) {
    $this->setDefaultCocoaCodes();
    if ($this->isValidCocoaYear($data['cocoa_year']) == FALSE) {
      return array(
        'is_error' => 1,
        'error_message' => ts('Invalid year of acquisition : ' . $data['cocoa_year']),
        );
    }
    $data['cocoa_cc_year'] = $this->findNewCocoaCode($data['cocoa_cc_year'], 'acquisition');
    $data['cocoa_cc_later'] = $this->findNewCocoaCode($data['cocoa_cc_later'], 'later');
    $data['cocoa_pl'] = $this->findNewCocoaPl($data['cocoa_pl']);
    if ($this->cocoaCustomDataAlreadyExists($data['campaign_id']) == FALSE) {
      $this->writeNewCocoaData($data);
      return array(
        'is_error' => 0,
      );
    }
    else {
      return array(
        'is_error' => 1,
        'error_message' => ts('New Cocoa Codes already there for campaign  ' . $data->campaign_id),
      );
    }
  }

  /**
   * Method to write the new cocoa custom data from the old one
   *
   * @param $data
   */
  private function writeNewCocoaData($data) {
    $config = CRM_Fintrxn_Configuration::singleton();
    $queryFields = array(
      $config->getCocoaAcquisitionYearCustomField('column_name'),
      $config->getCocoaCodeFollowCustomField('column_name'),
      $config->getCocoaProfitLossCustomField('column_name'),
      $config->getCocoaCodeAcquisitionCustomField('column_name'),
    );
    $queryParams = array(
      1 => array($data['campaign_id'], 'Integer'),
      2 => array($data['cocoa_year'], 'String'),
      3 => array($data['cocoa_cc_later'], 'String'),
      4 => array($data['cocoa_pl'], 'String'),
      5 => array($data['cocoa_cc_year'], 'String'),
    );
    $query = 'INSERT INTO '. $config->getCocoaCustomGroup('table_name') . ' (entity_id, ' . implode(",", $queryFields)
      . ') VALUES(%1, %2, %3, %4, %5)';
    CRM_Core_DAO::executeQuery($query, $queryParams);
  }

  /**
   * Method to check if the new custom data already exist
   *
   * @param $campaignId
   * @return bool
   */
  private function cocoaCustomDataAlreadyExists($campaignId) {
    $config = CRM_Fintrxn_Configuration::singleton();
    $query = "SELECT COUNT(*) FROM " . $config->getCocoaCustomGroup('table_name') . " WHERE entity_id = %1";
    $count = CRM_Core_DAO::singleValueQuery($query, array(1 => array($campaignId, 'Integer')));
    if ($count > 0) {
      return TRUE;
    }
    else {
      return FALSE;
    }

  }

  /**
   * Method to set the default cocoa codes for cost centre and profit and loss
   */
  private function setDefaultCocoaCodes() {
    $config = CRM_Fintrxn_Configuration::singleton();
    try {
      $this->_defaultCocoaPl = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => $config->getCocoaProfitLossOptionGroupId(),
        'is_default' => 1,
        'return' => 'value',
      ));
      $this->_defaultCocoaAcquisition = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => $config->getCocoaCostCentreOptionGroupId(),
        'filter' => $config->getFilterAcquisitionYear(),
        'is_default' => 1,
        'return' => 'value'
      ));
      $this->_defaultCocoaLater = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => $config->getCocoaCostCentreOptionGroupId(),
        'filter' => $config->getFilterFollowingYears(),
        'is_default' => 1,
        'return' => 'value'
      ));
    }
    catch (CiviCRM_API3_Exception $ex) {
    }
  }

  /**
   * Method to find the new cocoa code or use the default one
   *
   * @param $oldCode
   * @param $type
   * @return null
   */
  private function findNewCocoaCode($oldCode, $type) {
    $config = CRM_Fintrxn_Configuration::singleton();
    try {
      $count = civicrm_api3('OptionValue', 'getcount', array(
        'option_group_id' => $config->getCocoaCostCentreOptionGroupId(),
        'value' => $oldCode,
      ));
      if ($count > 0) {
        return $oldCode;
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
    }
    if ($type == 'acquisition') {
      return $this->_defaultCocoaAcquisition;
    }
    else {
      return $this->_defaultCocoaLater;
    }
  }

  /**
   * Method to find the new cocoa code or use the default one
   *
   * @param $oldCode
   * @return null
   */
  private function findNewCocoaPl($oldCode) {
    $config = CRM_Fintrxn_Configuration::singleton();
    try {
      $count = civicrm_api3('OptionValue', 'getcount', array(
        'option_group_id' => $config->getCocoaProfitLossOptionGroupId(),
        'value' => $oldCode,
      ));
      if ($count > 0) {
        return $oldCode;
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
    }
    return $this->_defaultCocoaPl;
  }

  /**
   * Method to test the imported cocoa year
   *
   * @param $cocoaYear
   * @return bool
   */
  private function isValidCocoaYear($cocoaYear) {
    // has to be numeric
    if (!is_numeric($cocoaYear)) {
      return FALSE;
    }
    // should start with 19 or 20
    if (substr($cocoaYear,0,2) != "19" && substr($cocoaYear, 0, 2) != "20") {
      return FALSE;
    }
    return TRUE;
  }
}