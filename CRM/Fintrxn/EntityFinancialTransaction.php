<?php
/**
 * Class for specific Entity Financial Transaction processing
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 21 March 2017
 * @license AGPL-3.0
 */
class CRM_Fintrxn_EntityFinancialTransaction {
  /**
   * CRM_Fintrxn_Entity_FinancialTransaction constructor.
   */
  function __construct() {
  }

  /**
   * Method to create a financial transaction with the API
   * 
   * @param $data
   * @return array|bool
   */
  function create($data) {
    try {
      $createdEntityFinTrxn = civicrm_api3('EntityFinancialTrxn', 'create', $data);
      return $createdEntityFinTrxn;
    } catch (CiviCRM_API3_Exception $ex) {
      // todo logging or error message
      return FALSE;
    }
  }
}