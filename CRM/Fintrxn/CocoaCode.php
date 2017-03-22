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

  /**
   * CRM_Fintrxn_CocoaCode constructor.
   */
  function __construct() {
    $this->_campaignAccountTypeCode = 'AIVLCAMPAIGNCOCOA';
  }

  /**
   * Getter for campaign account type code
   *
   * @return null|string
   */
  public function getCampaignAccountTypeCode() {
    return $this->_campaignAccountTypeCode;
  }

  /**
   * Method to import cocoa code
   * Expects array sourceData with campaign_id, cocoa P&L, cocoa acquisition year, cocoa following years, acquisition year
   *
   * @param $sourceData
   */
  public function import($sourceData) {
    // todo implement
    $config = CRM_Fintrxn_Configuration::singleton();
  }

  /**
   * Method to create option value for cocoa code
   *
   * @param $cocoaCode
   * @return mixed
   * @throws Exception when error from API
   */
  private function createOptionValue($cocoaCode) {
    $config = CRM_Fintrxn_Configuration::singleton();
    try {
      $created = civicrm_api3('OptionValue', 'create', array(
        'option_group_id' => $config->getCocoaOptionGroupId,
        'name' => 'aivl_cocoa_code_'.$cocoaCode,
        'value' => $cocoaCode,
        'label' => $cocoaCode,
        'is_active' => 1,
        'is_reserved' => 1
      ));
      return $created['values'];
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception(ts('Could not create an option value in').' '.__METHOD__.' '.ts('for COCOA Code').' '
        .$cocoaCode.' .'.ts('Contact your system administrator').', '.ts('error from API OptionValue create').': '
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