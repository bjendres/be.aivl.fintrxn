<?php
use CRM_Fintrxn_ExtensionUtil as E;

/**
 * EntityBatch.History API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_entity_batch_History_spec(&$spec) {
  $spec['year'] = array(
    'name' => 'year',
    'title' => 'Jaar',
    'description' => ts("Which year to select and add to the batch"),
    'api.required' => 1,
    'type' => CRM_Utils_Type::T_INT,
  );
  $spec['month'] = array(
    'name' => 'month',
    'title' => 'month',
    'description' => ts("Which month (numbers only, so for example april = 4) to select and add to the batch"),
    'api.required' => 1,
    'type' => CRM_Utils_Type::T_INT,
  );
}

/**
 * EntityBatch.History API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_entity_batch_History($params) {
  $entityBatch = new CRM_Fintrxn_EntityBatch($params);
  $returnValues = $entityBatch->addMonth();
  return civicrm_api3_create_success($returnValues, $params, 'EntityBatch', 'History');
}
