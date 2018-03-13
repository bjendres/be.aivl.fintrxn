<?php
use CRM_Fintrxn_ExtensionUtil as E;


/**
 * Cocoa.Migrate API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_cocoa_Migrate($params) {
  set_time_limit(0);
  $customGroupName = 'Groepsbijdrage_campaign_COCOA';
  $cocoaColumnNames = array(
    'cocoa_pl' => 'COCOA_Profit_Lost',
    'cocoa_year' => 'COCOA_Year_of_acquisition',
    'cocoa_cc_year' => 'COCOA_CostCentre_Acquisitionyear',
    'cocoa_cc_later' => 'COCOA_CC_lateryear',
  );
  $returnValues = array();
  $logger = new CRM_Corrections_Logger('cocoa_migrate');
  // get tableName and column names
  try {
    $tableName = civicrm_api3('CustomGroup', 'getvalue', array(
      'name' => $customGroupName,
      'extends' => 'Campaign',
      'return' => 'table_name',
    ));
    foreach ($cocoaColumnNames as $cocoaKey => $customFieldName) {
      $columnName = civicrm_api3('CustomField', 'getvalue', array(
        'custom_group_id' => $customGroupName,
        'name' => $customFieldName,
        'return' => 'column_name',
      ));
      $cocoaColumnNames[$cocoaKey] = $columnName;
    }
    $config = CRM_Fintrxn_Configuration::singleton();
    $campaignTypeId = $config->getFundraisingCampaignType();
    if (!empty($campaignTypeId)) {
      $query = "SELECT oud.* FROM " . $tableName . " AS oud JOIN civicrm_campaign camp ON oud.entity_id = camp.id
        WHERE camp.campaign_type_id = %1";
      $dao = CRM_Core_DAO::executeQuery($query, array(1 => array($campaignTypeId, 'Integer')));
    }
     else {
       $dao = CRM_Core_DAO::executeQuery("SELECT * FROM " . $tableName);
     }
    $newCocoa = new CRM_Fintrxn_CocoaCode();
    while ($dao->fetch()) {
      $data['campaign_id'] = $dao->entity_id;
      foreach ($cocoaColumnNames as $key => $property) {
        $data[$key] = $dao->$property;
      }
      $result = $newCocoa->migrate($data);
      if ($result['is_error'] == TRUE) {
        $logger->logMessage('Error', $result['error_message']);
        $returnValues[] = 'fout bij overzetten cocoa codes voor campagne ' .$dao->entity_id;
      }
      else {
        $returnValues[] = 'cocoa codes overgezet voor campagne ' . $dao->entity_id;
      }
    }
  }
  catch (CiviCRM_API3_Exception $ex) {
    $logger->logMessage('Error', 'Could not find a custom group with name ' . $customGroupName);
    $returnValues = array('No data migrated - check log');
  }
  return civicrm_api3_create_success($returnValues, $params, 'Cocoa', 'Migrate');
}
