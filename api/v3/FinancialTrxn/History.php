<?php
use CRM_Fintrxn_ExtensionUtil as E;

/**
 * FinancialTrxn.History API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_financial_trxn_History_spec(&$spec) {
  $spec['start_date'] = array(
    'name' => 'start_date',
    'title' => 'start_date',
    'description' => ts("Date from when the historic financial transactions have to be built"),
    'api.required' => 1,
    'type' => CRM_Utils_Type::T_DATE,
  );
}

/**
 * FinancialTrxn.History API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_financial_trxn_History($params) {
  if (!CRM_Core_DAO::checkTableExists('hist_fintrnx_contri')) {
    // create history table if not exists
    CRM_Fintrxn_FinancialTransaction::historicTable($params);
    $returnValues[] = 'Tabel voor historische contributies aangemaakt, draai job opnieuw om transacties te genereren';
  }
  else {
    $returnValues = CRM_Fintrxn_FinancialTransaction::createHistory($params);
  }
  return civicrm_api3_create_success($returnValues, $params, 'FinancialTrxn', 'History');
}
