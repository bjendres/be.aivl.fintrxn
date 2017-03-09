<?php
/**
 * Class to create or update configuration items from
 * JSON files in resources folder
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 9 March 2017
 * @license AGPL-3.0
 */
class CRM_Fintrxn_ConfigItems_ConfigItems {

  protected $_resourcesPath;
  protected $_customDataDir;

  /**
   * CRM_Fintrxn_ConfigItems_ConfigItems constructor.
   */
  function __construct() {
    $settings = civicrm_api3('Setting', 'Getsingle', array());
    $resourcesPath = $settings['extensionsDir'].DIRECTORY_SEPARATOR.'be.aivl.fintrxn'.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR;
    if (!is_dir($resourcesPath) || !file_exists($resourcesPath)) {
      throw new Exception(ts('Could not find the folder '.$resourcesPath
        .' which is required for extension be.aivl.fintrxn in '.__METHOD__
        .'.It does not exist or is not a folder, contact your system administrator'));
    }
    $this->_resourcesPath = $resourcesPath;
    $this->_customDataDir = $resourcesPath.'custom_data';
  }

  /**
   * Method to install config items
   */
  public function install() {
    $this->installOptionGroups();
    $this->installCustomData();
  }

  /**
   * Method to remove config items on uninstall of extension
   */
  public function uninstall() {
    $this->uninstallCustomData();
    $this->uninstallOptionGroups();
  }

  private function uninstallCustomData() {
    // read all json files from custom_data dir
    if (file_exists($this->_customDataDir) && is_dir($this->_customDataDir)) {
      // get all json files from dir
      $jsonFiles = glob($this->_customDataDir.DIRECTORY_SEPARATOR."*.json");
      foreach ($jsonFiles as $customDataFile) {
        $customDataJson = file_get_contents($customDataFile);
        $customData = json_decode($customDataJson, true);
        foreach ($customData as $customGroupName => $customGroupData) {
          $customGroup = new CRM_Fintrxn_ConfigItems_CustomGroup();
          $customGroup->uninstall($customGroupName);
        }
      }
    }
  }

  /**
   * Method to remove custom groups
   *
   * @throws Exception
   */
  private function installCustomData() {
    if (file_exists($this->_customDataDir) && is_dir($this->_customDataDir)) {
      // get all json files from dir
      $jsonFiles = glob($this->_customDataDir.DIRECTORY_SEPARATOR. "*.json");
      foreach ($jsonFiles as $customDataFile) {
        $customDataJson = file_get_contents($customDataFile);
        $customData = json_decode($customDataJson, true);
        foreach ($customData as $customGroupName => $customGroupData) {
          $customGroup = new CRM_Fintrxn_ConfigItems_CustomGroup();
          $created = $customGroup->create($customGroupData);
          foreach ($customGroupData['fields'] as $customFieldName => $customFieldData) {
            $customFieldData['custom_group_id'] = $created['id'];
            $customField = new CRM_Fintrxn_ConfigItems_CustomField();
            $customField->create($customFieldData);
          }
          // remove custom fields that are still on install but no longer in config
          CRM_Fintrxn_ConfigItems_CustomField::removeUnwantedCustomFields($created['id'], $customGroupData);
        }
      }
    }
  }

  /**
   * Method to remove option groups
   *
   * @throws Exception
   */
  private function uninstallOptionGroups() {
    $jsonFile = $this->_resourcesPath.'option_groups.json';
    if (file_exists($jsonFile)) {
      $optionGroupsJson = file_get_contents($jsonFile);
      $optionGroups = json_decode($optionGroupsJson, true);
      foreach ($optionGroups as $name => $optionGroupParams) {
        $optionGroup = new CRM_Fintrxn_ConfigItems_OptionGroup();
        $optionGroup->uninstall($optionGroupParams);
      }
    }
  }

  /**
   * Method to create option groups
   *
   * @throws Exception when resource file not found
   * @access protected
   */
  protected function installOptionGroups() {
    $jsonFile = $this->_resourcesPath.'option_groups.json';
    if (!file_exists($jsonFile)) {
      throw new Exception(ts('Could not load option_groups configuration file for extension,
      contact your system administrator!'));
    }
    $optionGroupsJson = file_get_contents($jsonFile);
    $optionGroups = json_decode($optionGroupsJson, true);
    foreach ($optionGroups as $name => $optionGroupParams) {
      $optionGroup = new CRM_Fintrxn_ConfigItems_OptionGroup();
      $optionGroup->create($optionGroupParams);
    }
  }
}