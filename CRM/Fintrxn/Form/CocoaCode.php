<?php

/**
 * Form controller class
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 20 April 2017
 * @license AGPL-3.0
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Fintrxn_Form_CocoaCode extends CRM_Core_Form {
  private $_cocoaProfitLoss = array();
  private $_cocoaCostCentres = array();

  /**
   * Method to build the QuickForm
   */
  public function buildQuickForm() {
    // add form elements
    $this->add('select', 'default_profit_loss', ts('Default COCOA code for Campaign Profit & Loss'),
      $this->_cocoaProfitLoss, TRUE);
    $this->add('select', 'default_acquisition_year', ts('Default COCOA code for Campaign Acquisition Year'),
      $this->_cocoaCostCentres, TRUE);
    $this->add('select', 'default_following_years', ts('Default COCOA code for Campaign Following Years'),
      $this->_cocoaCostCentres, TRUE);
    // add buttons
    $this->addButtons(array(
      array('type' => 'next', 'name' => ts('Save'), 'isDefault' => true,),
      array('type' => 'cancel', 'name' => ts('Cancel')),
      ));
    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  /**
   * Method to set default values for select lists
   */
  public function setDefaultValues() {
    $config = CRM_Fintrxn_Configuration::singleton();
    $defaults = array();
    try {
      $defaults['default_profit_loss'] = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => $config->getCocoaProfitLossOptionGroupId(),
        'is_default' => 1,
        'return' => 'value',
      ));
      $defaults['default_acquisition_year'] = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => $config->getCocoaCostCentreOptionGroupId(),
        'filter' => $config->getFilterAcquisitionYear(),
        'is_default' => 1,
        'return' => 'value'
      ));
      $defaults['default_following_years'] = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => $config->getCocoaCostCentreOptionGroupId(),
        'filter' => $config->getFilterFollowingYears(),
        'is_default' => 1,
        'return' => 'value'
      ));
    }
    catch (CiviCRM_API3_Exception $ex) {
    }
    if (!empty($defaults)) {
      return $defaults;
    }
  }

  /**
   * Method to get the list of active profit loss option values
   */
  private function getCocoaProfitLoss() {
    $config = CRM_Fintrxn_Configuration::singleton();
    try {
      $optionValues = civicrm_api3('OptionValue', 'get', array(
        'option_group_id' => $config->getCocoaProfitLossOptionGroupId(),
        'is_active' => 1,
        'options' => array('limit' => 0),
      ));
      foreach ($optionValues['values'] as $optionValue) {
        $this->_cocoaProfitLoss[$optionValue['value']] = $optionValue['label'];
      }
      asort($this->_cocoaProfitLoss);
    }
    catch (CiviCRM_API3_Exception $ex) {
    }
  }

  /**
   * Method to get the list of active cost centre option values
   */
  private function getCocoaCostCentres() {
    $config = CRM_Fintrxn_Configuration::singleton();
    try {
      $optionValues = civicrm_api3('OptionValue', 'get', array(
        'option_group_id' => $config->getCocoaCostCentreOptionGroupId(),
        'is_active' => 1,
        'options' => array('limit' => 0),
      ));
      foreach ($optionValues['values'] as $optionValue) {
        $this->_cocoaCostCentres[$optionValue['value']] = $optionValue['label'];
      }
      asort($this->_cocoaCostCentres);
    }
    catch (CiviCRM_API3_Exception $ex) {
    }
  }

  /**
   * Method to process results from the form
   *
   * @throws Exception when error from OptionValue create
   */
  public function postProcess() {
    $fieldNames = array('default_profit_loss', 'default_acquisition_year', 'default_following_years');
    $defaultCocoaParams = array();
    foreach ($fieldNames as $fieldName) {
      $currentDefault = $this->getCurrentDefaultCocoa($fieldName);
      if (!empty($currentDefault)) {
        // update to is_default = 0 if not the same as the one selected
        if ($currentDefault['value'] != $this->_submitValues[$fieldName]) {
          try {
            civicrm_api3('OptionValue', 'create', array(
              'id' => $currentDefault['id'],
              'is_default' => 0,
            ));
          }
          catch (CiviCRM_API3_Exception $ex) {
            throw new Exception('Could not update the current default cocoa code in '.__METHOD__
              .', contact your system administrator. Error from API OptionValue create: '.$ex->getMessage());
          }
        }
      }
      $defaultCocoaParams['value'] = $this->_submitValues[$fieldName];
      $this->saveDefaultCocoa($fieldName, $defaultCocoaParams);
    }
    parent::postProcess();
  }

  /**
   * Method to get the current default cocoa code for campaigns
   *
   * @param $fieldName
   * @return array
   */
  private function getCurrentDefaultCocoa($fieldName) {
    try {
      switch ($fieldName) {
        case 'default_profit_loss':
          return CRM_Fintrxn_Utils::getDefaultProfitLossCocoaCode();
          break;
        case 'default_acquisition_year':
          return CRM_Fintrxn_Utils::getDefaultAcquisitionYearCocoaCode();
          break;
        case 'default_following_years':
          return CRM_Fintrxn_Utils::getDefaultFollowingYearsCocoaCode();
          break;
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
      return array();
    }
  }

  /**
   * Method to update or add the default cocoa code
   * (uses CRM_Core_DAO::executeQuery rather than API because API only allows 1 default per option group
   *  and does not take filter into consideration)
   *
   * @param $fieldName
   * @param $defaultCocoaParams
   * @throws Exception when error in API OptionValue create
   */
  private function saveDefaultCocoa($fieldName, $defaultCocoaParams) {
    $config = CRM_Fintrxn_Configuration::singleton();
    $currentOptionValueId = NULL;
    switch ($fieldName) {
      case 'default_profit_loss':
        $defaultCocoaParams['option_group_id'] = $config->getCocoaProfitLossOptionGroupId();
        $currentOptionValueId = civicrm_api3('OptionValue', 'getvalue', array(
          'option_group_id' => $defaultCocoaParams['option_group_id'],
          'value' => $defaultCocoaParams['value'],
          'return' => 'id',
        ));
        break;
      case 'default_acquisition_year':
        $defaultCocoaParams['option_group_id'] = $config->getCocoaCostCentreOptionGroupId();
        $currentOptionValueId = civicrm_api3('OptionValue', 'getvalue', array(
          'option_group_id' => $defaultCocoaParams['option_group_id'],
          'filter' => $config->getFilterAcquisitionYear(),
          'value' => $defaultCocoaParams['value'],
          'return' => 'id',
        ));
        break;
      case 'default_following_years':
        $defaultCocoaParams['option_group_id'] = $config->getCocoaCostCentreOptionGroupId();
        $currentOptionValue = civicrm_api3('OptionValue', 'getvalue', array(
          'option_group_id' => $defaultCocoaParams['option_group_id'],
          'filter' => $config->getFilterFollowingYears(),
          'value' => $defaultCocoaParams['value'],
          'return' => 'id',
        ));
        break;
    }
    if (!empty($currentOptionValueId)) {
      $sql = 'UPDATE civicrm_option_value SET is_default = %1, is_active = %1 WHERE id = %2';
      CRM_Core_DAO::executeQuery($sql, array(
        1 => array(1, 'Integer',),
        2 => array($currentOptionValueId, 'Integer',),
      ));
    }
  }

  /**
   * Overridden parent method to initiate form
   *
   * @access public
   */
  function preProcess() {
    CRM_Utils_System::setTitle(ts('AIVL Default COCOA codes for Campaigns'));
    $this->getCocoaProfitLoss();
    $this->getCocoaCostCentres();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}
