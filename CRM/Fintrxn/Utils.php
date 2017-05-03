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

}