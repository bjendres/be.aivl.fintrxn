<?php
/**
 * Class for specific Financial Transaction processing
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 3 May 2017
 * @license AGPL-3.0
 */
class CRM_Fintrxn_FinancialTransaction {

  private $_financialTransactionId = NULL;

  /**
   * CRM_Fintrxn_FinancialTransaction constructor.
   *
   * @param $financialTransactionId
   * @throws Exception when 0 or more financial transactions found for id or when error from API
   */
  function __construct($financialTransactionId) {
    // check if financial transaction actually exists, otherwise throw exception
    try {
      $count = civicrm_api3('FinancialTrxn', 'getcount', array('id' => $financialTransactionId,));
      if ($count != 1) {
        throw new Exception(ts('Could not find a single financial transaction with id '.$financialTransactionId.' in '.__METHOD__.'Found '.$count
          .' financial transactions. Contact your system administrator'));
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
      throw new Exception(ts('Could not find a valid financial transaction '.__METHOD__
        .', contact your system administrator. Error from API FinancialTrxn getcount: '.$ex->getMessage()));
    }
    $this->_financialTransactionId = $financialTransactionId;
  }

  /**
   * Method to validate a financial transaction (before it is exported)
   * - does the contribution still exist
   * - does the contact still exist
   * - does the campaign still exist
   * - if the default financial account for AIVL is used, re-set the financial accounts and error if they remain default
   * - do the financial accounts still exist
   *
   * @return null or error message with specific text for the actual error so the user can fix the problem
   */
  public function validateTransaction() {
    if (!empty($this->_financialTransactionId)) {
      // get the financial transaction/entity financial transaction and process only if entity table = civicrm_contribution
      $sql = "SELECT t.id AS financial_transaction_id, t.from_financial_account_id, t.to_financial_account_id, e.entity_id AS contribution_id 
        FROM civicrm_financial_trxn t JOIN civicrm_entity_financial_trxn e ON t.id = e.financial_trxn_id
        WHERE t.id = %1 AND e.entity_table = %2";
      $daoTransaction = CRM_Core_DAO::executeQuery($sql, array(
        1 => array($this->_financialTransactionId, 'Integer',),
        2 => array('civicrm_contribution', 'String',),
      ));
      // check if contribution still exists
      if ($daoTransaction->fetch()) {
        if ($this->entityExists('Contribution', $daoTransaction->contribution_id) == FALSE) {
          return ts('Could not find a contribution with id '.$daoTransaction->contribution_id);
        }
        // we now know contribution exists so get it and check if contact and campaign still exist
        $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $daoTransaction->contribution_id));
        if ($this->entityExists('Contact', $contribution['contact_id']) == FALSE) {
          return ts('Could not find contact with id '.$contribution['contact_id'].' as specified in contribution with id '
            .$contribution['id']);
        }
        if (!isset($contribution['contribution_campaign_id']) || empty($contribution['contribution_campaign_id'])) {
          return ts('Contribution with id '.$contribution['id'].' is not linked to a campaign');
        }
        if ($this->entityExists('Campaign', $contribution['contribution_campaign_id']) == FALSE) {
          return ts('Could not find campaign with id '.$contribution['contribution_campaign_id']
            .' as specified in contribution with id '.$contribution['id']);
        }
        // we now know the vital entities all exists. Now check if to or from financial account are the default one
        if (CRM_Fintrxn_Utils::isDefaultAccount($daoTransaction->from_financial_account_id)) {
          return ts('The FROM account for the transaction of contribution with id '.$contribution['id']
            .' is  the default AIVL financial account because no valid COCOA code could be found for campaign '
            .$contribution['contribution_campaign_id']);
        }
        if (CRM_Fintrxn_Utils::isDefaultAccount($daoTransaction->to_financial_account_id)) {
          return ts('The TO account for the transaction of contribution with id '.$contribution['id']
            .' is  the default AIVL financial account because no valid COCOA code could be found for campaign '
            .$contribution['contribution_campaign_id']);
        }
        // finally check if the financial accounts exist
        if ($this->entityExists('FinancialAccount', $daoTransaction->from_financial_account_id)) {
          return ts('The FROM account for the transaction of contribution with id '.$contribution['id']
            .' does not exist.');
        }
        if ($this->entityExists('FinancialAccount', $daoTransaction->to_financial_account_id)) {
          return ts('The TO account for the transaction of contribution with id '.$contribution['id']
            .' does not exist.');
        }
      } else {
        return ts('Could not find a financial transaction linked to a contribution with financial transaction id '
          .$this->_financialTransactionId);
      }
    }
    return NULL;
  }

  /**
   * Method to check if entity still exists
   * @param $entity
   * @param $entityId
   * @return bool
   */
  private function entityExists($entity, $entityId) {
    try {
      $count = civicrm_api3(ucfirst(strtolower($entity)), 'getcount', array('id' => $entityId,));
      if ($count == 1) {
        return TRUE;
      }
    } catch (CiviCRM_API3_Exception $ex) {
    }
    return FALSE;
  }

  /**
   * Method to process the civicrm post hook
   *
   * @param $op
   * @param $objectId
   */
  public static function post($op, $objectId) {
    // if contribution is deleted, make sure all financial transactions with check_number 'AIVL fintrxn' are also deleted
    if ($op == 'delete') {

    }
  }

}