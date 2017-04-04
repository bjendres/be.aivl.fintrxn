<?php
/*-------------------------------------------------------+
| Custom Financial Transaction Generator                 |
| Copyright (C) 2017 AIVL                                |
| Author: B. Endres (endres@systopia.de)                 |
|         E. Hommel (erik.hommel@civicoop.org)           |
+--------------------------------------------------------+
| License: AGPLv3, see LICENSE file                      |
+--------------------------------------------------------*/
/**
 * Configuration for CRM_Fintrxn_Generator
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license AGPL-3.0
 */
class CRM_Fintrxn_Configuration {

  private static $_singleton;

  private $_cocoaAcquisitionYearCustomField = NULL;
  private $_cocoaCodeAcquisitionCustomField = NULL;
  private $_cocoaCodeFollowCustomField = NULL;
  private $_cocoaProfitLossCustomField = NULL;
  private $_cocoaCustomGroup = NULL;
  private $_contributionCustomGroup = NULL;
  private $_contributionCustomFields = NULL;
  private $_completedContributionStatusId = NULL;
  private $_fundraisingCampaignType = NULL;
  private $_resourcesPath = NULL;
  private $_cocoaCodeOptionGroupId = NULL;
  private $_campaignAccountTypeCode = NULL;
  private $_ibanAccountTypeCode = NULL;


  /**
   * CRM_Fintrxn_Configuration constructor.
   */
  function __construct() {
    $this->setCocoaCustomGroup();
    $this->setContributionCustomGroup();
    $this->setCocoaCustomFields();
    $this->setResourcesPath();
    $this->setAccountTypeCodes();
    try {
      $this->_completedContributionStatusId = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => 'contribution_status',
        'name' => 'Completed',
        'return' => 'value'
      ));
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception(ts('Could not find contribution status with name Completed in').' '.__METHOD__.', '.
      ts('contact your system administrator .Error from API OptionValue getvalue').': '.$ex->getMessage());
    }
    try {
      $this->_cocoaCodeOptionGroupId = civicrm_api3('OptionGroup', 'getvalue', array(
        'name' => 'aivl_cocoa_codes',
        'return' => 'id'
      ));
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception(ts('Could not find option group with name aivl_cocoa_codes in').' '.__METHOD__.', '.
      ts('contact your system administrator .Error from API OptionGroup getvalue').': '.$ex->getMessage());
    }
    try {
      $this->_fundraisingCampaignType = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => 'campaign_type',
        'name' => 'Fundraising campaign',
        'return' => 'value'
      ));
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception(ts('Could not find campaign type with name Fundraising campaign in').' '.__METHOD__.', '.
      ts('contact your system administrator .Error from API OptionValue getvalue').': '.$ex->getMessage());
    }
  }

  /**
   * Getter for campaign financial account code (COCOA)
   *
   * @return null
   */
  public function getCampaignAccountTypeCode() {
    return $this->_campaignAccountTypeCode;
  }

  /**
   * Getter for IBAN (incoming) financial account code (COCOA)
   *
   * @return null
   */
  public function getIbanAccountTypeCode() {
    return $this->_ibanAccountTypeCode;
  }
  /**
   * Getter for resources path
   *
   * @return null
   */
  public function getResourcesPath() {
    return $this->_resourcesPath;
  }

  /**
   * Getter for cocoa code option group id
   *
   * @return array|null
   */
  public function getCocoaCodeOptionGroupId() {
    return $this->_cocoaCodeOptionGroupId;
  }
  /**
   * Getter for cocoa cost centre acquisition year custom field
   *
   * @param string $key (custom field column to return)
   * @return mixed
   */
  public function getCocoaCodeAcquisitionCustomField($key) {
    return $this->_cocoaCodeAcquisitionCustomField[$key];
  }

  /**
   * Getter for cocoa cost centre acquisition year custom field
   *
   * @param string $key (custom field column to return)
   * @return mixed
   */
  public function getCocoaCodeFollowCustomField($key) {
    return $this->_cocoaCodeFollowCustomField[$key];
  }

  /**
   * Getter for cocoa profit loss custom field
   *
   * @param string $key (custom field column to return)
   * @return mixed
   */
  public function getCocoaProfitLossCustomField($key) {
    return $this->_cocoaProfitLossCustomField[$key];
  }

  /**
   * Getter for cocoa acquisition year custom field
   *
   * @param string $key (custom field column to return)
   * @return mixed
   */
  public function getCocoaAcquisitionYearCustomField($key) {
    return $this->_cocoaAcquisitionYearCustomField[$key];
  }

  /**
   * Getter for completed contribution status
   *
   * @return string
   */
  public function getCompletedContributionStatusId() {
    return $this->_completedContributionStatusId;
  }

  /**
   * Getter for fundraisingCampaignType
   *
   * @return string
   */
  public function getFundraisingCampaignType() {
    return $this->_fundraisingCampaignType;
  }

  /**
   * Getter for cocoaCustomGroup
   *
   * @param $key (custom group column to return)
   * @return mixed
   */
  public function getCocoaCustomGroup($key = 'id') {
    return $this->_cocoaCustomGroup[$key];
  }

  /**
   * Method to find the cocoa custom group and set the class property
   */
  private function setCocoaCustomGroup() {
    $cocoaCustomGroupName = 'aivl_campaign_cocoa';
    try {
      $this->_cocoaCustomGroup = civicrm_api3('CustomGroup', 'getsingle', array(
        'name' => $cocoaCustomGroupName,
        'extends' => 'Campaign'
      ));
    } catch (CiviCRM_API3_Exception $ex) {
      error_log("ERROR: Couldn't identify custom group '{$cocoaCustomGroupName}'!");
    }
  }

  /**
   * Method to set the cocoa custom fields and set the class properties
   */
  private function setCocoaCustomFields() {
    $cocoaCustomFieldNames = array(
      'acquisition_year' => 'AcquisitionYear',
      'code_acquisition' => 'CodeAcquisition',
      'code_following' => 'CodeFollow',
      'profit_loss' => 'ProfitLoss');
    foreach ($cocoaCustomFieldNames as $cocoaCustomFieldName => $classPropertyName) {
      $property = '_cocoa'.$classPropertyName.'CustomField';
      try {
        $this->$property = civicrm_api3('CustomField', 'getsingle', array(
          'custom_group_id' => $this->_cocoaCustomGroup['id'],
          'name' => $cocoaCustomFieldName,
        ));
      } catch (CiviCRM_API3_Exception $ex) {}
    }
  }

  /**
   * load the contribution custom group storing
   *  - incoming bank account
   *  - refund bank account
   *  - cancellation fees
   */
  private function setContributionCustomGroup() {
    try {
      $this->_contributionCustomGroup = civicrm_api3('CustomGroup', 'getsingle', array(
        'name'    => 'Contribution_Info',
        'extends' => 'Contribution'
      ));

      // load the fields as well
      $fields = civicrm_api3('CustomField', 'get', array(
        'custom_group_id' => $this->_contributionCustomGroup['id']));
      $this->_contributionCustomFields = $fields['values'];
    } catch (CiviCRM_API3_Exception $ex) {
      error_log("ERROR: Couldn't identify custom group 'Contribution_Info'!");
    }
  }

  /**
   * get the contribution custom field by name
   */
  private function getContributionCustomField($field_name) {
    foreach ($this->_contributionCustomFields as $field) {
      if ($field['name'] == $field_name) {
        return $field;
      }
    }
    return NULL;
  }

  /**
   * check if a change to the given attributes could potentially trigger
   * the financial transaction processing
   *
   * @param array $changes
   * @return bool
   */
  public function isRelevant($changes) {
    return   in_array('campaign_id', $changes)
          || in_array('contribution_status_id', $changes)
          || in_array('amount', $changes)
          || in_array('', $changes);
  }

  /**
   * check if the given status counts as completed
   *
   * @param $contributionStatusId
   * @return bool
   */
  public function isCompleted($contributionStatusId) {
    // TODO: look up status
    return $contributionStatusId == 1;
  }

  /**
   * calculate the right accounting code from the cocoa data
   *
   * @param $cocoaData
   * @param $receiveDate
   * @return string
   */
  public function getCocoaValue($cocoaData, $receiveDate) {
    $year = substr($receiveDate, 0, 4);
    if ($year == $cocoaData['custom_'.$this->_cocoaAcquisitionYearCustomField['id']]) {
      return $cocoaData['custom_'.$this->_cocoaCodeAcquisitionCustomField['id']];
    } else {
      return $cocoaData['custom_'.$this->_cocoaCodeFollowCustomField['id']];
    }
  }

  public static function getDefaultResourcesPath() {
    // TODO: erik: find a way to get the path w/o init config (for configitems)
    $settings = civicrm_api3('Setting', 'Getsingle', array());
    $resourcesPath = $settings['extensionsDir'].DIRECTORY_SEPARATOR.'be.aivl.fintrxn'.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR;
    if (!is_dir($resourcesPath) || !file_exists($resourcesPath)) {
      throw new Exception(ts('Could not find the folder '.$resourcesPath
        .' which is required for extension be.aivl.fintrxn in '.__METHOD__
        .'.It does not exist, is not a folder or you do not have access rights. Contact your system administrator'));
    }
    return $resourcesPath;
  }

  /**
   * Method to set the resources path
   *
   * @throws Exception
   */
  private function setResourcesPath() {
    $settings = civicrm_api3('Setting', 'Getsingle', array());
    $resourcesPath = $settings['extensionsDir'].DIRECTORY_SEPARATOR.'be.aivl.fintrxn'.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR;
    if (!is_dir($resourcesPath) || !file_exists($resourcesPath)) {
      throw new Exception(ts('Could not find the folder '.$resourcesPath
        .' which is required for extension be.aivl.fintrxn in '.__METHOD__
        .'.It does not exist, is not a folder or you do not have access rights. Contact your system administrator'));
    }
    $this->_resourcesPath = $resourcesPath;
  }

  /**
   * check whether the amount has changed.
   * for AIVL it's easy: fee_amount/net_amount aren't used
   */
  public function isAmountChange($changes) {
    return in_array('total_amount', $changes);
  }

  /**
   * check whether the changes indicate that it's a newly created
   * contribution
   */
  public function isNew($changes) {
    return in_array('id', $changes);
  }

  /**
   * Check whether the contribution should have transactions
   * This should not be the case if it's in status 'pending' or 'in progress',
   * and you shouldn't be able to later go back to that status either
   * -> it's a simple check for status
   */
  public function hasTransactions($contribution) {
    if (isset($contribution['contribution_status_id'])) {
      $contribution_status_id = $contribution['contribution_status_id'];
      // TODO: look up status
      if (  $contribution_status_id == 2 /* pending */
         || $contribution_status_id == 5 /* in progress */) {
        return FALSE;

      } else {
        // even if we can't be sure, we have to assume:
        return TRUE;
      }
    } else {
      // no status ID? that's not good.
      throw new Exception("Calling Configuration::hasTransactions with incomplete contribution. Maybe we've missed something here.");
    }
  }

  /**
   * calculate whether the changes (list of changed contribution attributes)
   * are potentially relevant for rebooking.
   */
  public function isAccountRelevant($changes) {
    return    in_array('campaign_id', $changes)
           || in_array($this->getIncomingBankAccountKey(), $changes)
           || in_array($this->getRefundBankAccountKey(), $changes);
  }

  /**
   * get key of the custom field for the incoming bank account
   */
  public function getIncomingBankAccountKey() {
    $field = $this->getContributionCustomField('Incoming_Bank_Account');
    if ($field) {
      return $field['id'];
    } else {
      return NULL;
    }
  }

  /**
   * get key of the custom field for the refund bank account
   */
  public function getRefundBankAccountKey() {
    $field = $this->getContributionCustomField('Refund_Bank_Account');
    if ($field) {
      return "custom_{$field['id']}";
    } else {
      return NULL;
    }
  }

  /**
   * get a list of the cocoa relevant fields
   */
  public function getCocoaFieldList() {
    $fields = array(
      'custom_' . $this->getCocoaAcquisitionYearCustomField('id'),
      'custom_' . $this->getCocoaCodeFollowCustomField('id'),
      'custom_' . $this->getCocoaCodeAcquisitionCustomField('id'),
      'custom_' . $this->getCocoaProfitLossCustomField('id'));

    return implode(',', $fields);
  }

  /**
   * Method to set the account type codes for the COCOA financial accounts
   */
  public function setAccountTypeCodes() {
    $this->_campaignAccountTypeCode = 'AIVLCAMPAIGNCOCOA';
    $this->_ibanAccountTypeCode = 'AIVLINC';
  }

  /**
   * Singleton method
   *
   * @return CRM_Fintrxn_Configuration
   * @access public
   * @static
   */
  public static function singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Fintrxn_Configuration();
    }
    return self::$_singleton;
  }
}