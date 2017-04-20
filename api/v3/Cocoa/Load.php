<?php

/**
 * Cocoa.Load API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_cocoa_Load_spec(&$spec) {
  $spec['clean_existing'] = array(
    'name' => 'clean_existing',
    'title' => 'clean_existing',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 1
  );
}

/**
 * Cocoa.Load API is used to load cocoa codes from a csv file (name of the file is passed as param).
 * The file is expected in uploadDir from settings
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_cocoa_Load($params) {
  $returnValues = array();
  $cocoaCode = new CRM_Fintrxn_CocoaCode();
  // clean existing option values if specified
  if ($params['clean_existing'] == "Y" || $params['clean_existing'] == "J") {
    $cocoaCode->cleanOptionValues();
    $returnValues[] = "Existing option groups aivl_cocoa_cost_centre and aivl_cocoa_profit_loss emptied before loading";
  }
  $config = CRM_Fintrxn_Configuration::singleton();
  $jsonFile = $config->getResourcesPath().'cocoa_mapping.json';
  // check file exists
  if (!file_exists($jsonFile)) {
    return civicrm_api3_create_error('Could not find the required file '.$jsonFile);
  }
  // read json file
  $cocoaJson = file_get_contents($jsonFile);
  $cocoaMappings = json_decode($cocoaJson, true);
  foreach ($cocoaMappings as $cocoaId => $cocoaData) {
    // process based on type
    $cocoaCode->load($cocoaId, $cocoaData);
    $returnValues[] = "Cocoa code of type ".$cocoaData['type']." loaded";
  }
  return civicrm_api3_create_success($returnValues, $params, 'Cocoa', 'Load');
}
