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
  private $_fundraisingCampaignType = NULL;
  private $_resourcesPath = NULL;
  private $_cocoaCostCentreOptionGroupId = NULL;
  private $_cocoaProfitLossOptionGroupId = NULL;
  private $_campaignAccountTypeCode = NULL;
  private $_ibanAccountTypeCode = NULL;
  private $_plTypeCode = NULL;
  private $_cancelContributionStatusId = NULL;
  private $_completedContributionStatusId = NULL;
  private $_pendingContributionStatusId = NULL;
  private $_refundContributionStatusId = NULL;
  private $_failedContributionStatusId = NULL;
  private $_validContributionStatus = array();
  private $_filterAcquisitionYear = NULL;
  private $_filterFollowingYears = NULL;
  private $_defaultCocoaFinancialAccountId = NULL;
  private $_civicrmVersion = NULL;

  /**
   * CRM_Fintrxn_Configuration constructor.
   */
  function __construct() {
    try {
      $this->_civicrmVersion = civicrm_api3('Domain', 'getvalue', array(
        'return' => "version",
      ));
    }
    catch (CiviCRM_API3_Exception $ex) {
      CRM_Core_Error::createError('Could not verify the CiviCRM version using API Domain getvalue (extension be.aivl.fintrxn assumes only 1 domain)');
    }
    $this->setCocoaCustomGroup();
    $this->setContributionCustomGroup();
    $this->setCocoaCustomFields();
    $this->setResourcesPath();
    $this->setAccountTypeCodes();
    $this->setContributionStatus();
    $this->setDefaultCocoaFinancialAccount();
    $this->_filterAcquisitionYear = 1;
    $this->_filterFollowingYears = 2;
    try {
      $this->_cocoaCostCentreOptionGroupId = civicrm_api3('OptionGroup', 'getvalue', array(
        'name' => 'aivl_cocoa_cost_centre',
        'return' => 'id'
      ));
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception(ts('Could not find option group with name aivl_cocoa_cost_centre in').' '.__METHOD__.', '.
      ts('contact your system administrator .Error from API OptionGroup getvalue').': '.$ex->getMessage());
    }
    try {
      $this->_cocoaProfitLossOptionGroupId = civicrm_api3('OptionGroup', 'getvalue', array(
        'name' => 'aivl_cocoa_profit_loss',
        'return' => 'id'
      ));
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception(ts('Could not find option group with name aivl_cocoa_profit_loss in').' '.__METHOD__.', '.
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
   * Getter for filter for default cocoa financial account
   *
   * @return int|null
   */
  public function getDefaultCocoaFinancialAccountId() {
    return $this->_defaultCocoaFinancialAccountId;
  }

  /**
   * Getter for filter for following years option values
   *
   * @return int|null
   */
  public function getFilterFollowingYears() {
    return $this->_filterFollowingYears;
  }

  /**
   * Getter for filter for acquisition year option values
   *
   * @return int|null
   */
  public function getFilterAcquisitionYear() {
    return $this->_filterAcquisitionYear;
  }

  /**
   * Getter for pending contribution status
   *
   * @return array
   */
  public function getPendingContributionStatusId() {
    return $this->_pendingContributionStatusId;
  }

  /**
   * Getter for failed contribution status
   *
   * @return array
   */
  public function getFailedContributionStatusId() {
    return $this->_failedContributionStatusId;
  }

  /**
   * Getter for valid contribution status for fintrxn processing
   *
   * @return array
   */
  public function getValidContributionStatus() {
    return $this->_validContributionStatus;
  }

  /**
   * Getter for completed contribution status
   *
   * @return array
   */
  public function getCompletedContributionStatusId() {
    return $this->_completedContributionStatusId;
  }

  /**
   * Getter for cancelled contribution status
   *
   * @return array
   */
  public function getCancelContributionStatusId() {
    return $this->_cancelContributionStatusId;
  }

  /**
   * Getter for refund contribution status
   *
   * @return array
   */
  public function getRefundContributionStatusId() {
    return $this->_refundContributionStatusId;
  }

  /**
   * Getter for profit loss cocoa code
   *
   * @return null
   */
  public function getPlTypeCode() {
    return $this->_plTypeCode;
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
   * Getter for cocoa cost centre option group id
   *
   * @return array|null
   */
  public function getCocoaCostCentreOptionGroupId() {
    return $this->_cocoaCostCentreOptionGroupId;
  }

  /**
   * Getter for cocoa profit loss option group id
   *
   * @return array|null
   */
  public function getCocoaProfitLossOptionGroupId() {
    return $this->_cocoaProfitLossOptionGroupId;
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
   * Getter for contribution custom fields
   *
   * @return null
   */
  public function getContributionCustomFields() {
    return $this->_contributionCustomFields;
  }

  /**
   * Getter for the contribution custom field for incoming amount
   *
   * @param $key
   * @return mixed|null
   */
  public function getIncomingAccountCustomField($key = 'id') {
    foreach ($this->_contributionCustomFields as $field) {
      if ($field['name'] == 'Incoming_Bank_Account') {
        return $field[$key];
      }
    }
    return NULL;
  }

  /**
   * Getter for the contribution custom field for refund amount
   *
   * @param $key
   * @return mixed|NULL
   */
  public function getRefundAccountCustomField($key) {
    foreach ($this->_contributionCustomFields as $field) {
      if ($field['name'] == 'Refund_Bank_Account') {
        return $field[$key];
      }
    }
    return NULL;
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

  /**
   * Method to get the default resources path
   *
   * @return string
   * @throws CiviCRM_API3_Exception
   * @throws Exception
   */
  public static function getDefaultResourcesPath() {
    try {
      $civicrmVersion = civicrm_api3('Domain', 'getvalue', array(
        'return' => "version",
      ));
    }
    catch (CiviCRM_API3_Exception $ex) {
      CRM_Core_Error::createError('Could not verify the CiviCRM version using API Domain getvalue (extension be.aivl.fintrxn assumes only 1 domain)');
    }

    // retrieve resources path 4.7 or 4.6 and earlier
    if ($civicrmVersion > 4.6) {
      $container = CRM_Extension_System::singleton()->getFullContainer();
      $resourcesPath = $container->getPath('be.aivl.fintrxn') .DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR;
    } else {
      $settings = civicrm_api3('Setting', 'Getsingle', array());
      $resourcesPath = $settings['extensionsDir'].DIRECTORY_SEPARATOR.'be.aivl.fintrxn'.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR;
    }
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
    if ($this->_civicrmVersion > 4.6) {
      $container = CRM_Extension_System::singleton()->getFullContainer();
      $resourcesPath = $container->getPath('be.aivl.fintrxn') .DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR;
    } else {
      $settings = civicrm_api3('Setting', 'Getsingle', array());
      $resourcesPath = $settings['extensionsDir'] . DIRECTORY_SEPARATOR . 'be.aivl.fintrxn' . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR;
    }
    if (!is_dir($resourcesPath) || !file_exists($resourcesPath)) {
      throw new Exception(ts('Could not find the folder '.$resourcesPath
        .' which is required for extension be.aivl.fintrxn in '.__METHOD__
        .'.It does not exist, is not a folder or you do not have access rights. Contact your system administrator'));
    }
    $this->_resourcesPath = $resourcesPath;
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
    return $fields;
  }

  /**
   * Method to set the account type codes for the COCOA financial accounts
   */
  private function setAccountTypeCodes() {
    $this->_campaignAccountTypeCode = 'AIVLCAMPAIGNCOCOA';
    $this->_ibanAccountTypeCode = 'AIVLINC';
    $this->_plTypeCode = 'AIVLPL';
  }

  /**
   * Method to set or create default financial account for campaigns
   */
  private function setDefaultCocoaFinancialAccount() {
    $defaultFinancialAccountName = 'aivl_cocoa_default';
    $accountTypeCode = 'AIVLDEFAULT';
    try {
      $this->_defaultCocoaFinancialAccountId = civicrm_api3('FinancialAccount', 'getvalue', array(
        'name' => $defaultFinancialAccountName,
        'account_type_code' => $accountTypeCode,
        'return' => 'id',
      ));
    }
    catch (CiviCRM_API3_Exception $ex) {
      $financialAccount = civicrm_api3('FinancialAccount', 'create', array(
        'name' => $defaultFinancialAccountName,
        'account_type_code' => $accountTypeCode,
        'description' => 'Amnesty International Vlaanderen default COCOA code (niet aankomen!)',
        'is_reserved' => 1,
        'is_active' => 1,
        'accounting_code'=> 99999999
      ));
      $this->_defaultCocoaFinancialAccountId = $financialAccount['id'];
    }
  }

  /**
   * Method to set the required contribution status properties
   */
  private function setContributionStatus() {
    try {
      $optionValues = civicrm_api3('OptionValue', 'get', array(
        'option_group_id' => 'contribution_status',
        'options' => array('limit' => 0),
      ));
      foreach ($optionValues['values'] as $optionValue) {
        switch ($optionValue['name']) {
          case 'Cancelled':
            $this->_cancelContributionStatusId = $optionValue['value'];
            $this->_validContributionStatus[] = $optionValue['value'];
            break;
          case 'Refunded':
            $this->_refundContributionStatusId = $optionValue['value'];
            $this->_validContributionStatus[] = $optionValue['value'];
            break;
          case 'Completed':
            $this->_completedContributionStatusId = $optionValue['value'];
            $this->_validContributionStatus[] = $optionValue['value'];
            break;
          case 'Pending':
            $this->_pendingContributionStatusId = $optionValue['value'];
            break;
          case 'Failed':
            $this->_failedContributionStatusId = $optionValue['value'];
            $this->_validContributionStatus[] = $optionValue['value'];
            break;
        }
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
    }
  }

  /**
   * Method to determine if status has changed and is either cancel or refund
   *
   * @param $newData
   * @return bool
   */
  public function isCancelOrRefund($newData) {
    if (isset($newData['contribution_status_id'])) {
      if ($newData['contribution_status_id'] == $this->getCancelContributionStatusId()
        || $newData['contribution_status_id'] == $this->getRefundContributionStatusId()
        || $newData['contribution_status_id'] == $this->getFailedContributionStatusId()) {
        return TRUE;
      }
    }
    return FALSE;
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