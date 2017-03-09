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
  private $_completedContributionStatusId = NULL;
  private $_fundraisingCampaignType = NULL;

  /**
   * CRM_Fintrxn_Configuration constructor.
   */
  function __construct() {
    $this->setCocoaCustomGroup();
    $this->setCocoaCustomFields();
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
    } catch (CiviCRM_API3_Exception $ex) {}
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