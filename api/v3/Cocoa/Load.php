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
  $spec['file_name'] = array(
    'name' => 'file_name',
    'title' => 'file_name',
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

  $cocoaCode = new CRM_Fintrxn_CocoaCode();
  $account = $cocoaCode->findAccountWithTypeAndCampaign(1251, 'acquisition');
  CRM_Core_Error::debug('account', $account);
  exit();


  $returnValues = array();
  // check file exists
  try {
    $setting = civicrm_api3('Setting', 'get', array('return' => "uploadDir"));
    $uploadFolder = $setting['values'][$setting['id']]['uploadDir'];
    // exception if not folder
    if (!is_dir($uploadFolder)) {
      throw new API_Exception(ts('The upload Dir ').$uploadFolder.ts(' is not a valid folder or you have no access rights.'), 1000);
    }
    // only if file exists
    $fileName = $uploadFolder.$params['file_name'];
    // initialize logger and load mapping for cocoa import
    $logger = new CRM_Fintrxn_Logger('cocoa_load');
    $logger->logMessage('Info','Starting to load COCOA codes from file '.$fileName);
    $mapping = CRM_Fintrxn_CocoaCode::getLoadMapping();
    // process csv file with class from Streetimport, exception if not found
    if (class_exists('CRM_Streetimport_FileCsvDataSource')) {
      $dataSource = new CRM_Streetimport_FileCsvDataSource($fileName, $logger, $mapping);
      $dataSource->reset();
      $cocoaCode = new CRM_Fintrxn_CocoaCode();
      while ($dataSource->hasNext()) {
        $cocoaCode->load($dataSource->next(), $logger);
      }
    } else {
      throw new API_Exception(ts('Could not find class CRM_Streetimport_FileCsvDataSource, this is part of the extension be.aivl.streetimport. 
        You probably have either disabled or uninstalled this extension. Contact your system administrator'), 1001);
    }
  } catch (CiviCRM_API3_Exception $ex) {
    throw new API_Exception(ts('Could not retrieve the setting for uploadDir in ').__METHOD__, 1002);
  }
  return civicrm_api3_create_success($returnValues, $params, 'Cocoa', 'Load');
}
