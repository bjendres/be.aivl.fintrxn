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
        if ($this->entityExists('FinancialAccount', $daoTransaction->from_financial_account_id) == FALSE) {
          return ts('The FROM account for the transaction of contribution with id '.$contribution['id']
            .' does not exist.');
        }
        if ($this->entityExists('FinancialAccount', $daoTransaction->to_financial_account_id) == FALSE) {
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
   * Method to fix accounts for a financial trxn id if possible
   *
   * @param object $dao with financial transaction columns id, status_id, to_financial_account_id and from_financial_account_id
   * @return bool
   */
  public static function fixAccounts($dao) {
    // can not fix if not passed object
    if (!is_object($dao)) {
      return FALSE;
    }
    // can not fix if required properties not in dao
    if (!isset($dao->id) || !isset($dao->status_id) || !isset($dao->to_financial_account_id)
      || !isset($dao->from_financial_account_id)) {
      return FALSE;
    }
    // find contribution id from civicrm_entity_financial_transaction
    $sql = "SELECT entity_id FROM civicrm_entity_financial_trxn WHERE entity_table = %1 AND financial_trxn_id = %2";
    $sqlParams = array(
      1 => array('civicrm_contribution', 'String',),
      2 => array($dao->id, 'Integer',),
    );
    $contributionId = CRM_Core_DAO::singleValueQuery($sql, $sqlParams);
    if ($contributionId) {
      // get contribution data
      try {
        $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $contributionId,));
        $fromFinancialAccountId = self::setFromFinancialAccount($dao->status_id, $contribution);
        $toFinancialAccountId = self::setToFinancialAccount($dao->status_id, $contribution);
        // if any changes, update and return fix is true
        if ($fromFinancialAccountId != $dao->from_financial_account_id || $toFinancialAccountId != $dao->to_financial_account_id) {
          $update = "UPDATE civicrm_financial_trxn SET from_financial_account_id = %1, to_financial_account_id = %2 WHERE id = %3";
          $updateParams = array(
            1 => array($fromFinancialAccountId, 'Integer',),
            2 => array($toFinancialAccountId, 'Integer',),
            3 => array($dao->id, 'Integer',),
          );
          CRM_Core_DAO::executeQuery($update, $updateParams);
          return TRUE;
        }
      }
      catch (CiviCRM_API3_Exception $ex) {
      }
    }
    return FALSE;
  }

  /**
   * Method to determine from financial account
   *
   * @param $statusId
   * @param $contribution
   * @return int|mixed|null
   */
  private static function setFromFinancialAccount($statusId, $contribution) {
    $config = CRM_Fintrxn_Configuration::singleton();
    // if refund or cancel, from account is based on campaign
    if ($statusId == $config->getCancelContributionStatusId() || $statusId == $config->getRefundContributionStatusId()) {
      return CRM_Fintrxn_Utils::getFinancialAccountForCampaign($contribution['contribution_campaign_id'], $contribution['receive_date']);
    } else {
      // in all other cases, from account is based on custom field incoming account
      $incomingCustomFieldId = 'custom_'.$config->getIncomingAccountCustomField('id');
      if (isset($contribution[$incomingCustomFieldId]) && !empty($contribution[$incomingCustomFieldId])) {
        return CRM_Fintrxn_Utils::getFinancialAccountForBankAccount($contribution[$incomingCustomFieldId]);
      } else {
        return $config->getDefaultCocoaFinancialAccountId();
      }
    }
  }

  /**
   * Method to determine to financial account id
   *
   * @param $statusId
   * @param $contribution
   * @return int|mixed|null
   */
  private static function setToFinancialAccount($statusId, $contribution) {
    $config = CRM_Fintrxn_Configuration::singleton();
    // if refund or cancel, to account is based on custom field for refund account
    if ($statusId == $config->getCancelContributionStatusId() || $statusId == $config->getRefundContributionStatusId()) {
      $refundCustomFieldId = 'custom_'.$config->getRefundAccountCustomField('id');
      if (isset($contribution[$refundCustomFieldId]) && !empty($contribution[$refundCustomFieldId])) {
        return CRM_Fintrxn_Utils::getFinancialAccountForBankAccount($contribution[$refundCustomFieldId]);
      } else {
        return $config->getDefaultCocoaFinancialAccountId();
      }
    } else {
      return CRM_Fintrxn_Utils::getFinancialAccountForCampaign($contribution['contribution_campaign_id'], $contribution['receive_date']);
    }
  }

  /**
   * Method to rebuild financial transactions for contributions from a start date
   *
   * @param $params
   * @return array
   */
  public static function createHistory($params) {
    $extConfig = CRM_Fintrxn_Configuration::singleton();
    $returnValues = array();
    // error if no start_date
    if (!isset($params['start_date']) || empty($params['start_date'])) {
      $returnValues[] = ts('You did not specify a start date, no historic financial transactions created');
      return $returnValues;
    }
    // retrieve all completed, refunded, cancelled or failed contributions from the start date that do not have financial transactions
    // for each contribution, generate financial transactions into a temporary table
    $count = 0;
    $dao = CRM_Core_DAO::executeQuery("SELECT * FROM hist_fintrnx_contri LIMIT 2500");
    while ($dao->fetch()) {
      if (self::alreadyHasFinTrxn($dao->id) == FALSE) {
        $generatedObjectRef = self::generateObjectRefHistory($dao, 'create');
        $createValues = self::generateCreateValues($dao);
        CRM_Fintrxn_Generator::create('create', $createValues, $dao->id);
        CRM_Fintrxn_Generator::generate('create', $dao->id, $generatedObjectRef);
        // if status is not completed, to an edit with same data
        if ($dao->contribution_status_id != $extConfig->getCompletedContributionStatusId()) {
          $generatedObjectRef = self::generateObjectRefHistory($dao, 'edit');
          CRM_Fintrxn_Generator::generate('edit', $dao->id, $generatedObjectRef);
        }
        $count++;
      }
      // delete hist record
      CRM_Core_DAO::executeQuery("DELETE FROM hist_fintrnx_contri WHERE id = %1", array(1 => array($dao->id, 'Integer')));
    }
    $returnValues[] = $count . ' financial transactions generated';
    // finally check if there are still historical ones to create
    $stillToGo = CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM hist_fintrnx_contri");
    if ($stillToGo > 0) {
      $returnValues[] = 'Still ' .$stillToGo . ' contributions to process, run job again!';
    }
    else {
      // drop table
      CRM_Core_DAO::executeQuery("DROP TABLE hist_fintrnx_contri");
      $returnValues[] = "All contributions processed.";
    }
    return $returnValues;
  }

  /**
   * Method to generate the create values (pretending it is an array as per civicrm_pre hook, including the custom fields
   *
   * @param CRM_Core_DAO $dao
   * @return array
   */
  private static function generateCreateValues($dao) {
    $result = CRM_Fintrxn_Utils::moveDaoToArray($dao);
    // add custom fields with the correct pattern
    $extConfig = CRM_Fintrxn_Configuration::singleton();
    $customGroup = $extConfig->getContributionCustomGroup();
    if ($customGroup) {
      $query = "SELECT * FROM " . $customGroup['table_name'] . " WHERE entity_id = %1";
      $customData = CRM_Core_DAO::executeQuery($query, array(
        1 => array($dao->id, 'Integer'),
      ));
      if ($customData->fetch()) {
        $customFields = $extConfig->getContributionCustomFields();
        $rows = array();
        foreach ($customFields as $customFieldId => $customField) {
          $property = $customField['column_name'];
          if (isset($customData->$property)) {
            $rows[$customFieldId][$customData->id]['value'] = $customData->$property;
          }
        }
      }
    }
    if ($rows) {
      $result['custom'] = $rows;
    }
    return $result;
  }

  /**
   * Method to generate the object ref object for the fintrxn generator for historic creation
   * - if contribution_status_id = completed, dao object is good enough
   * - if contribution_status_id != completed:
   *   - if operation is create, change the status to completed so an initial fin trxn is created as if it was a new one
   *   - if operation is edit, keep the status so the generator processes as if it was a UI edit
   *
   * @param $dao
   * @param $operation
   * @return CRM_Core_DAO
   */
  private static function generateObjectRefHistory($dao, $operation) {
    $extConfig = CRM_Fintrxn_Configuration::singleton();
    if ($dao->contribution_status_id == $extConfig->getCompletedContributionStatusId()) {
      return $dao;
    }
    else {
      if ($operation == 'edit') {
        return $dao;
      }
      else {
        $result = $dao;
        $result-> contribution_status_id = $extConfig->getCompletedContributionStatusId();
        return $result;
      }
    }
  }

  /**
   * Method to check if a contribution already has a new financial transaction
   *
   * @param $contributionId
   * @return bool
   */
  private static function alreadyHasFinTrxn($contributionId) {
    $query = "SELECT COUNT(*)
      FROM civicrm_entity_financial_trxn ent
      JOIN civicrm_financial_trxn trx ON trx.id = ent.financial_trxn_id
      WHERE ent.entity_id = %1 AND ent.entity_table = %2 AND trx.check_number = %3";
    $countTrxn = CRM_Core_DAO::singleValueQuery($query, array(
      1 => array($contributionId, 'Integer'),
      2 => array('civicrm_contribution', 'String'),
      3 => array('AIVL fintrxn', 'String'),
    ));
    if ($countTrxn > 0) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Method to create and populate table for historic financial transactions
   *
   * @param array $params
   */
  public static function historicTable($params) {
    if (!CRM_Core_DAO::checkTableExists('hist_fintrnx_contri')) {
      CRM_Core_DAO::executeQuery("CREATE TABLE hist_fintrnx_contri (
        id int(10) UNSIGNED NOT NULL ,
        contact_id int(10) UNSIGNED NOT NULL,
        financial_type_id int(10) UNSIGNED DEFAULT NULL,
        contribution_page_id int(10) UNSIGNED DEFAULT NULL,
        payment_instrument_id int(10) UNSIGNED DEFAULT NULL,
        receive_date datetime DEFAULT NULL,
        non_deductible_amount decimal(20,2) DEFAULT '0.00',
        total_amount decimal(20,2) NOT NULL,
        fee_amount decimal(20,2) DEFAULT NULL,
        net_amount decimal(20,2) DEFAULT NULL,
        trxn_id varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        invoice_id varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        currency varchar(3) COLLATE utf8_unicode_ci DEFAULT NULL,
        cancel_date datetime DEFAULT NULL,
        cancel_reason text COLLATE utf8_unicode_ci,
        receipt_date datetime DEFAULT NULL,
        thankyou_date datetime DEFAULT NULL,
        source varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        amount_level text COLLATE utf8_unicode_ci,
        contribution_recur_id int(10) UNSIGNED DEFAULT NULL,
        is_test tinyint(4) DEFAULT '0',
        is_pay_later tinyint(4) DEFAULT '0',
        contribution_status_id int(10) UNSIGNED DEFAULT '1',
        address_id int(10) UNSIGNED DEFAULT NULL,
        check_number varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        campaign_id int(10) UNSIGNED DEFAULT NULL,
        tax_amount decimal(20,2) DEFAULT NULL,
        creditnote_id varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
      // now populate table
      $extConfig = CRM_Fintrxn_Configuration::singleton();
      $startDate = new DateTime($params['start_date']);
      if (!$startDate instanceof DateTime) {
        $startDate = new DateTime($startDate);
      }
      $query = "INSERT INTO hist_fintrnx_contri (SELECT id, contact_id, financial_type_id, contribution_page_id, payment_instrument_id, 
        receive_date, non_deductible_amount, total_amount, fee_amount, net_amount, trxn_id, invoice_id, currency, cancel_date,
        cancel_reason, receipt_date, thankyou_date, source, amount_level, contribution_recur_id, is_test, is_pay_later, contribution_status_id,
        address_id, check_number, campaign_id, tax_amount, creditnote_id 
        FROM civicrm_contribution WHERE receive_date >= %1 AND contribution_status_id IN (%2, %3, %4, %5))";
      CRM_Core_DAO::executeQuery($query, array(
        1 => array($startDate->format('Y-m-d') . ' 00:00:00', 'String'),
        2 => array($extConfig->getCancelContributionStatusId(), 'Integer'),
        3 => array($extConfig->getCompletedContributionStatusId(), 'Integer'),
        4 => array($extConfig->getFailedContributionStatusId(), 'Integer'),
        5 => array($extConfig->getRefundContributionStatusId(), 'Integer'),
      ));
    }
  }
}