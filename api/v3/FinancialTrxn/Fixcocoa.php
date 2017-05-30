<?php

/**
 * FinancialTrxn.FixCocoa API
 * - check all financial transactions where to or from account is the AIVL default and try to fix
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 29 May 2017
 */
function civicrm_api3_financial_trxn_Fixcocoa($params) {
  $returnValues = array();
  $config = CRM_Fintrxn_Configuration::singleton();
  $sql = "SELECT id, status_id, from_financial_account_id, to_financial_account_id FROM civicrm_financial_trxn
    WHERE (to_financial_account_id = %1 OR from_financial_account_id = %1) AND check_number = %2";
  $sqlParams = array(
    1 => array($config->getDefaultCocoaFinancialAccountId(), 'Integer',),
    2 => array('AIVL fintrxn', 'String',),);
  $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
  while ($dao->fetch()) {
    $fixed = CRM_Fintrxn_FinancialTransaction::fixAccounts($dao);
    if ($fixed) {
      $returnValues[] = 'updated financial transaction '.$dao->id;
    } else {
      $returnValues[] = 'could not update financial transaction '.$dao->id;
    }
  }
  return civicrm_api3_create_success($returnValues, $params, 'FinancialTrxn', 'FixCocoa');
}
