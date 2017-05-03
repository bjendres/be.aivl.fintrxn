<?php
/*-------------------------------------------------------+
| Custom Financial Transaction Generator                 |
| Copyright (C) 2017 AIVL                                |
| Author: B. Endres (endres@systopia.de)                 |
|         E. Hommel (erik.hommel@civicoop.org)           |
+--------------------------------------------------------+
| License: AGPLv3, see LICENSE file                      |
+--------------------------------------------------------*/


/**
 * Generator for custom financial transactions
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license AGPL-3.0
 */
class CRM_Fintrxn_Generator {

  // there can only be zero or one generator at any time
  protected static $_singleton = NULL;
  protected static $_lookupCache = array();

  // variables
  protected $_config = NULL;
  protected $_contributionId = NULL;
  protected $_operation = NULL;
  protected $_preContributionData = NULL;
  protected $_oldContributionData = NULL;
  protected $_newContributionData = NULL;
  protected $_changes = NULL;

  /**
   * CRM_Fintrxn_Generator constructor, storing the old values of the contribution
   *
   * @param $operation
   * @param $contributionId
   * @param $values
   */
  public function __construct($operation, $contributionId, $values) {
    $this->_config = CRM_Fintrxn_Configuration::singleton();
    $this->_contributionId = $contributionId;
    $this->_preContributionData = $values;
    $this->_operation = $operation;
    if ($this->_operation == 'create') {
      $this->_oldContributionData = array();
    } elseif ($this->_operation == 'edit') {
      $this->_oldContributionData = civicrm_api3('Contribution', 'getsingle', array('id' => $values['id']));

      // fixes (don't even ask...)
      if ( empty($this->_oldContributionData['campaign_id'])
        && !empty($this->_oldContributionData['contribution_campaign_id'])) {
        $this->_oldContributionData['campaign_id'] = $this->_oldContributionData['contribution_campaign_id'];
      }
      if (!isset($this->_oldContributionData['tax_amount'])) {
        $this->_oldContributionData['tax_amount'] = NULL;
      }
    } else {
      error_log("OPERATION '{$operation}' was ignored.");
    }
  }

  /**
   * create a new generator, overwriting an existing one if there is one
   * (expects to be called from a civicrm_pre hook, receiving the old values of the contribution before
   *  the operation is saved in the database)
   *
   * @param $operation
   * @param $values
   * @param $contributionId
   */
  public static function create($operation, $values, $contributionId) {
    // error_log("CREATE $operation/$contributionId: " . json_encode($values));
    self::$_singleton = new CRM_Fintrxn_Generator($operation, $contributionId, $values);
  }

  /**
   * trigger the calculation of financial transactions
   * (expects to be called from the civicrm_post hook, receiving the new values in the ref object)
   *
   * @param $operation
   * @param $contributionId
   * @param $objectRef
   */
  public static function generate($operation, $contributionId, $objectRef) {
    if (self::$_singleton != NULL) {
      $newValues = array();
      if ($objectRef) {
        // convert ref object into array newValues
        foreach ($objectRef as $key => $value) {
          if (substr($key, 0, 1) != '_') {
            $newValues[$key] = $value;
          }
        }
      }
      self::$_singleton->generateFinancialTrxns($operation, $contributionId, $newValues);
    }
  }

  /**
   * main dispatcher function
   * will determine what the changes are, based on the incoming new values and the old values in the class
   *
   * @param $operation
   * @param $contributionId
   * @param $newValues
   */
  public function generateFinancialTrxns($operation, $contributionId, $newValues) {
    // first some security checks to make sure we are actually checking the same contribution and comparing the
    // correct old values and new values
    if ($operation != $this->_operation
        || ($this->_contributionId && ($this->_contributionId != $contributionId))) {
      // something's gone wrong here because operation or contributionId is not the same as during construct
      // TODO : more meaningfull message
      // TODO: with Exception!
      error_log("FINTRXN ERROR: interleaved calls (? contributionId is different as in __construct) in ".__METHOD__
        .", this shouldn't happen!");
      return;
    }

    if ($operation != 'delete') {
      $this->calculateChanges($newValues);
      // if operation is create, add new fin trxn
      if ($operation == 'create') {
        $this->processNewContribution();
      }
      // if operation is edit, process cancel, refund or change
      if ($operation == 'edit') {
        if ($this->_config->isCancelOrRefund($this->_newContributionData)) {
          $this->processCancelRefundContribution();
        } else {
          $this->processChangedContribution();
        }
      }
    }
  }

  /**
   * Method to process a cancelled or refunded contribution
   * - from financial account is the account based on the campaign of the contribution
   * - to financial account is the refund account in the custom data
   *
   */
  private function processCancelRefundContribution() {
    // reverse transaction for old contribution
    $this->_oldContributionData['total_amount'] = -$this->_oldContributionData['total_amount'];
    $this->_oldContributionData['net_amount'] = -$this->_oldContributionData['net_amount'];
    $this->_oldContributionData['fee_amount'] = -$this->_oldContributionData['fee_amount'];
    $refundCustomField = 'custom_'.$this->_config->getRefundAccountCustomField();
    $trxData = $this->createTransactionData($this->_oldContributionData);
    $trxData['from_financial_account_id'] = $this->getFinancialAccountForCampaign($this->_oldContributionData['campaign_id'],
      $this->_oldContributionData['receive_date']);
    $trxData['to_financial_account_id'] = $this->getFinancialAccountForBankAccount($this->_oldContributionData[$refundCustomField]);
    $this->writeTransaction($trxData);
  }

  /**
   * Method to process a changed contribution
   * - from financial account is the receiving account in the custom data
   * - to financial account is the account based on the campaign of the contribution
   */
  private function processChangedContribution() {
    // reverse transaction for old contribution
    $this->_oldContributionData['total_amount'] = -$this->_oldContributionData['total_amount'];
    $this->_oldContributionData['net_amount'] = -$this->_oldContributionData['net_amount'];
    $this->_oldContributionData['fee_amount'] = -$this->_oldContributionData['fee_amount'];
    $incomingCustomField = 'custom_'.$this->_config->getIncomingAccountCustomField();
    $oldTrxData = $this->createTransactionData($this->_oldContributionData);
    $oldTrxData['from_financial_account_id'] = $this->getFinancialAccountForBankAccount($this->_oldContributionData[$incomingCustomField]);
    $oldTrxData['to_financial_account_id'] = $this->getFinancialAccountForCampaign($this->_oldContributionData['campaign_id'],
      $this->_oldContributionData['receive_date']);
    $this->writeTransaction($oldTrxData);
    // then create new transaction
    $newTrxData = $this->createTransactionData($this->_newContributionData);
    $newTrxData['from_financial_account_id'] = $this->getFinancialAccountForBankAccount($this->_newContributionData[$incomingCustomField]);
    $newTrxData['to_financial_account_id'] = $this->getFinancialAccountForCampaign($this->_newContributionData['campaign_id'],
      $this->_newContributionData['receive_date']);
    $this->writeTransaction($newTrxData);
  }

  /**
   * Method to process a new contribution
   * - from financial account is the receiving account in the custom data
   * - to financial account is the account based on the campaign of the contribution
   */
  private function processNewContribution() {
    $incomingCustomField = 'custom_'.$this->_config->getIncomingAccountCustomField();
    $trxData = $this->createTransactionData($this->_newContributionData);
    $trxData['from_financial_account_id'] = $this->getFinancialAccountForBankAccount($this->_newContributionData[$incomingCustomField]);
    $trxData['to_financial_account_id'] = $this->getFinancialAccountForCampaign($this->_newContributionData['campaign_id'],
      $this->_newContributionData['receive_date']);
    $this->writeTransaction($trxData);
  }

  /**
   * create a template for a financial transaction based on contribution data
   *  It will be missing the fields:
   *       from_financial_account_id
   *       to_financial_account_id
   *
   * @param $contributionData
   * @param $date
   * @return array
   */
  protected function createTransactionData($contributionData, $date = NULL) {
    if ($date === NULL) {
      $date = date('YmdHis');
    }
    return array(
      'trxn_date'             => $date,
      'total_amount'          => CRM_Utils_Array::value('total_amount', $contributionData),
      'fee_amount'            => CRM_Utils_Array::value('fee_amount', $contributionData),
      'net_amount'            => CRM_Utils_Array::value('net_amount', $contributionData, CRM_Utils_Array::value('total_amount', $contributionData)),
      'currency'              => CRM_Utils_Array::value('currency', $contributionData),
      'trxn_id'               => CRM_Utils_Array::value('trxn_id', $contributionData),
      'trxn_result_code'      => '',
      'status_id'             => CRM_Utils_Array::value('contribution_status_id', $contributionData),
      'payment_processor_id'  => CRM_Utils_Array::value('payment_processor_id', $contributionData),
      'payment_instrument_id' => CRM_Utils_Array::value('payment_instrument_id', $contributionData),
      'check_number'          => 'AIVL fintrxn',
      //'check_number'          => CRM_Utils_Array::value('check_number', $contributionData),
    );
  }

  /**
   * will create a given financial transaction in the DB
   *
   * @param $data
   */
  protected function writeTransaction($data) {
    try {
      $financialTrxn = civicrm_api3('FinancialTrxn', 'create', $data);
      // now add entity
      civicrm_api3('EntityFinancialTrxn', 'create', $this->createEntityTransactionData($financialTrxn));

    } catch (CiviCRM_API3_Exception $ex) {
      error_log('Could not create financial transaction and/or entity financial transaction in '.__METHOD__
        .', error message from API FinancialTrxn Create: '.$ex->getMessage());
    }
  }

  /**
   * Method to write entity financial transaction data
   *
   * @param $financialTrxn
   * @return array
   */
  protected function createEntityTransactionData($financialTrxn) {
    // todo what to do if in case of refunds etc? is _contributionId filled? And should I only use the _newContribution is that
    // todo one is empty?
    return array(
      'entity_table' => 'civicrm_contribution',
      'entity_id' => $this->_newContributionData['id'],
      'financial_trxn_id' => $financialTrxn['id'],
      'amount' => $this->_newContributionData['total_amount']
    );
  }

  /**
   * populate the $this->changes data
   * and fill the $this->new_contribution_data
   */
  protected function calculateChanges($updatedValues) {
    // again....don't even ask
    $ignoreKeys = array('instrument_id', 'N', 'payment_instrument', 'contribution_page_id', 'source', 'creditnote_id');
    // first, update the new contribution data with the values
    foreach ($updatedValues as $key => $value) {
      if ($value !== NULL && !in_array($key, $ignoreKeys)) {
        $this->_newContributionData[$key] = $value;
      }
    }

    // then, copy the old data values to the new ones, if they haven't changed
    foreach ($this->_oldContributionData as $key => $value) {
      if (!isset($this->_newContributionData[$key])) {
        // ignore N and instrument_id
        if (!in_array($key, $ignoreKeys)) {
          $this->_newContributionData[$key] = $value;
        }
      }
    }
    // add custom fields if not set
    $this->addCustomFieldsToNewData();

    // finally calculate the changes compared to old
    $this->_changes = array();
    foreach ($this->_newContributionData as $key => $value) {
      if ($this->isValueChanged($key) == TRUE) {
        $this->_changes[] = $key;
      }
    }
  }

  /**
   * Method to add custom data to new contribution data
   */
  private function addCustomFieldsToNewData() {
    $customFields = $this->_config->getContributionCustomFields();
    foreach ($customFields as $customFieldId => $customField) {
      $customFieldName = 'custom_'.$customFieldId;
      if (!isset($this->_newContributionData[$customFieldName])) {
        $this->_newContributionData[$customFieldName] = $this->retrieveCustomData($customFieldId);
      }
    }
  }

  /**
   * Method to get custom data value from _preContributionData with custom field id
   *
   * @param $customFieldId
   * @return mixed
   */
  private function retrieveCustomData($customFieldId) {
    if (isset($this->_preContributionData['custom'][$customFieldId])) {
      foreach ($this->_preContributionData['custom'][$customFieldId] as $customData) {
        return $customData['value'];
      }
    }
  }

  /**
   * Method to determine if values really changed. This method has some dirty hacks to avoid testing
   * values from newContributionData that made it to the array but are actually irrelevant. It also
   * checks dates with DateTime objects to avoid format conflicts in testing
   *
   * @param $key
   * @return bool
   */

  private function isValueChanged($key) {
    if (!isset($this->_oldContributionData[$key])) {
      return TRUE;
    }
    // not changed if both values are empty
    if (empty($this->_newContributionData[$key]) && empty($this->_oldContributionData[$key])) {
      return FALSE;
    }
    // special case for weird value 'null' getting into new values
    if ($this->_newContributionData[$key] == 'null' && empty($this->_oldContributionData[$key])) {
      return FALSE;
    }
    // compare date values
    $dateValues = array('receive_date', 'cancel_date', 'receipt_date', 'thankyou_date');
    if (in_array($key, $dateValues)) {
      $oldDate = date('YmdHis', strtotime($this->_oldContributionData[$key]));
      $newDate = date('YmdHis', strtotime($this->_newContributionData[$key]));
      if ($oldDate === $newDate) {
        return FALSE;
      }
    }
    if ($this->_oldContributionData[$key] !== $this->_newContributionData[$key]) {
      return TRUE;
    }
    return FALSE;
  }


  /**
   * look up financial account id based on bank account
   *
   * @param $bankAccount
   * @return integer
   * @throws Exception when no financial account found
   */
  protected function getFinancialAccountForBankAccount($bankAccount) {
    if (empty($bankAccount)) {
      return $this->_config->getDefaultCocoaFinancialAccountId();
    }
    if (!empty($bankAccount)) {
      // lookup account id
      $cocoaCode = new CRM_Fintrxn_CocoaCode();
      $account = $cocoaCode->findAccountWithName($bankAccount, $this->_config->getIbanAccountTypeCode());
      if (empty($account)) {
        return $this->_config->getDefaultCocoaFinancialAccountId();
      } else {
        return $account['id'];
      }
    }
  }

  /**
   * look up the financial account id based on campaign id
   *
   * @param $campaignId
   * @param $receiveDate
   * @return mixed
   */
  protected function getFinancialAccountForCampaign($campaignId, $receiveDate) {
    if (empty($campaignId)) {
      return $this->_config->getDefaultCocoaFinancialAccountId();
    } else {
      // get the COCOA codes from the campaign
      $campaign = $this->cachedLookup('Campaign', array(
        'id' => $campaignId,
        'return' => $this->_config->getCocoaFieldList()));
      $accountCode = $this->_config->getCocoaValue($campaign, $receiveDate);
    }
    // lookup account id
    $cocoaCode = new CRM_Fintrxn_CocoaCode();
    $account = $cocoaCode->findAccountWithAccountCode($accountCode, $this->_config->getCampaignAccountTypeCode());
    if (empty($account['id'])) {
      $cocoaCode->createFinancialAccount(array(
        'name' => 'COCAO Code '.$accountCode,
        'description' => 'AIVL COCOA code '.$accountCode.' (niet aankomen!)',
        'accounting_code' => $accountCode,
        'account_type_code' => $this->_config->getCampaignAccountTypeCode(),
        'is_reserved' => 1,
        'is_active' => 1,
      ));
      $account = $cocoaCode->findAccountWithAccountCode($accountCode, $this->_config->getCampaignAccountTypeCode());
    }
    return $account['id'];
  }


  /**
   * Cached API lookup
   *
   * @param $entity
   * @param $selector
   * @return mixed
   */
  protected function cachedLookup($entity, $selector) {
    // error_log("LOOKUP: $entity " . json_encode($selector));
    $cacheKey = sha1($entity.json_encode($selector));
    if (array_key_exists($cacheKey, self::$_lookupCache)) {
      return self::$_lookupCache[$cacheKey];
    } else {
      try {
        $result = civicrm_api3($entity, 'getsingle', $selector);
        self::$_lookupCache[$cacheKey] = $result;
        // error_log("RESULT: " . json_encode($result));
        return $result;
      } catch (Exception $e) {
        // not uniquely identified
        self::$_lookupCache[$cacheKey] = NULL;
        return NULL;
      }
    }
  }
}