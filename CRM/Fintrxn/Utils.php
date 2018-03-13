<?php

/**
 * Class with generic extension helper methods
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 9 March 2017
 * @license AGPL-3.0
 */
class CRM_Fintrxn_Utils {

  /**
   * Method to generate label from name
   *
   * @param $name
   * @return string
   * @access public
   * @static
   */
  public static function buildLabelFromName($name) {
    $nameParts = explode('_', strtolower($name));
    foreach ($nameParts as $key => $value) {
      $nameParts[$key] = ucfirst($value);
    }
    return implode(' ', $nameParts);
  }

  /**
   * Method to get the default COCOA code for Profit and Loss
   *
   * @return array|bool
   */
  public static function getDefaultProfitLossCocoaCode() {
    $config = CRM_Fintrxn_Configuration::singleton();
    try {
      return civicrm_api3('OptionValue', 'getsingle', array(
        'option_group_id' => $config->getCocoaProfitLossOptionGroupId(),
        'is_default' => 1,
      ));
    }
    catch (CiviCRM_API3_Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Method to get the default COCOA code for Acquisition Year
   * @return array|bool
   */
  public static function getDefaultAcquisitionYearCocoaCode() {
    $config = CRM_Fintrxn_Configuration::singleton();
    try {
      return civicrm_api3('OptionValue', 'getsingle', array(
        'option_group_id' => $config->getCocoaCostCentreOptionGroupId(),
        'filter' => $config->getFilterAcquisitionYear(),
        'is_default' => 1,
      ));
    }
    catch (CiviCRM_API3_Exception $ex) {
      return FALSE;
    }
  }
  /**
   * Method to get the default COCOA code for Following Years
   * @return array|bool
   */
  public static function getDefaultFollowingYearsCocoaCode() {
    $config = CRM_Fintrxn_Configuration::singleton();
    try {
      return civicrm_api3('OptionValue', 'getsingle', array(
        'option_group_id' => $config->getCocoaCostCentreOptionGroupId(),
        'filter' => $config->getFilterFollowingYears(),
        'is_default' => 1,
      ));
    }
    catch (CiviCRM_API3_Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Method to check if the financial account is the default Cocoa Account
   * (which is only used when no account could be found for campaign or bank account when
   * generating the financial transaction)
   *
   * @param $financialAccountId
   * @return bool
   */
  public static function isDefaultAccount($financialAccountId) {
    if (!empty($financialAccountId)) {
      $config = CRM_Fintrxn_Configuration::singleton();
      if ($financialAccountId == $config->getDefaultCocoaFinancialAccountId()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * look up financial account id based on bank account
   *
   * @param $bankAccount
   * @return integer
   * @throws Exception when no financial account found
   */
  public static function getFinancialAccountForBankAccount($bankAccount) {
    $config = CRM_Fintrxn_Configuration::singleton();
    if (empty($bankAccount)) {
      return $config->getDefaultCocoaFinancialAccountId();
    }
    if (!empty($bankAccount)) {
      // lookup account id
      $cocoaCode = new CRM_Fintrxn_CocoaCode();
      $account = $cocoaCode->findAccountWithName($bankAccount, $config->getIbanAccountTypeCode());
      if (empty($account)) {
        return $config->getDefaultCocoaFinancialAccountId();
      } else {
        return $account['id'];
      }
    }
  }

  /**
   * look up the financial account id based on campaign id
   *
   * @param $campaignId
   * @param $receiveDate
   * @return mixed
   */
  public static function getFinancialAccountForCampaign($campaignId, $receiveDate) {
    $config = CRM_Fintrxn_Configuration::singleton();
    if (empty($campaignId)) {
      return $config->getDefaultCocoaFinancialAccountId();
    } else {
      // get the COCOA codes from the campaign
      $campaign = civicrm_api3('Campaign', 'getsingle', array(
        'id' => $campaignId,
        'return' => $config->getCocoaFieldList()));
      $accountCode = $config->getCocoaValue($campaign, $receiveDate);
    }
    if (empty($accountCode)) {
      return $config->getDefaultCocoaFinancialAccountId();
    } else {
      // lookup account id if we have an accountCode
      $cocoaCode = new CRM_Fintrxn_CocoaCode();
      $account = $cocoaCode->findAccountWithAccountCode($accountCode, $config->getCampaignAccountTypeCode());
      if (empty($account['id'])) {
        $cocoaCode->createFinancialAccount(array(
          'name' => 'COCAO Code ' . $accountCode,
          'description' => 'AIVL COCOA code ' . $accountCode . ' (niet aankomen!)',
          'accounting_code' => $accountCode,
          'account_type_code' => $config->getCampaignAccountTypeCode(),
          'is_reserved' => 1,
          'is_active' => 1,
        ));
        $account = $cocoaCode->findAccountWithAccountCode($accountCode, $config->getCampaignAccountTypeCode());
      }
    }
    return $account['id'];
  }

  /**
   * Method to get the financial account name
   *
   * @param $financialAccountId
   * @return array|null
   */
  public static function getFinancialAccountName($financialAccountId) {
    try {
      return civicrm_api3('FinancialAccount', 'getvalue', array(
        'id' => $financialAccountId,
        'return' => 'name',
      ));
    }
    catch (CiviCRM_API3_Exception $ex) {
      return NULL;
    }
  }

  /**
   * Method to get the campaign id for a contribution
   *
   * @param $contributionId
   * @return array
   */
  public static function getAdditionalBatchInfoForContribution($contributionId) {
    $result = array();
    try {
      $contribution = civicrm_api3('Contribution', 'getsingle', array(
        'id' => $contributionId,
      ));
      if ($contribution['contribution_campaign_id']) {
        $result['campaign_id'] = $contribution['contribution_campaign_id'];
      } else {
        if ($contribution['campaign_id']) {
          $result['campaign_id'] = $contribution['campaign_id'];
        }
      }
      if ($contribution['financial_type']) {
        $result['financial_type'] = $contribution['financial_type'];
      } else {
        if ($contribution['financial_type_id']) {
          $result['financial_type'] = civicrm_api3('FinancialType', 'getvalue', array(
            'id' => $contribution['financial_type_id'],
            'return' => 'name',
          ));
        }
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
      CRM_Core_Error::debug_log_message('Could not retrieve contribution '.$contributionId
        .' using API Contribution getsingle (extension be.aivl.fintrxn)');
    }
    return $result;
  }

  /**
   * Method om dao in array te stoppen en de 'overbodige' data er uit te slopen
   *
   * @param  $dao
   * @return array
   */
  public static function moveDaoToArray($dao) {
    $ignores = array('N', 'id', 'entity_id');
    $columns = get_object_vars($dao);
    // first remove all columns starting with _
    foreach ($columns as $key => $value) {
      if (substr($key, 0, 1) == '_') {
        unset($columns[$key]);
      }
      if (in_array($key, $ignores)) {
        unset($columns[$key]);
      }
    }
    return $columns;
  }


}