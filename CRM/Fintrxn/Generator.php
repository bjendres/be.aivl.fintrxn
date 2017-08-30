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
    // only if is_test == 0
    if (empty($objectRef->is_test)) {
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
      // when coming from API contribution_status_id can be empty. If this is the case, initialize
      if (!isset($newValues['contribution_status_id'])) {
        $newValues['contribution_status_id'] = $this->initializeContributionStatusId($newValues);
      }
      // ignore if contribution status is NOT a valid one for processing financial transactions
      if (in_array($newValues['contribution_status_id'], $this->_config->getValidContributionStatus())) {
        $this->calculateChanges($newValues);
        // if operation is create, add new fin trxn
        if ($operation == 'create') {
          $this->processNewContribution();
        }
        // if operation is edit and something changed, process cancel, failed, refund or change
        if ($operation == 'edit' && !empty($this->_changes)) {
          if ($this->_config->isCancelOrRefund($this->_newContributionData)) {
            $this->processCancelRefundContribution();
          } else {
            $this->processChangedContribution();
          }
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
    $refundCustomField = 'custom_'.$this->_config->getRefundAccountCustomField('id');
    $trxData = $this->createTransactionData($this->_oldContributionData);
    $trxData['from_financial_account_id'] = CRM_Fintrxn_Utils::getFinancialAccountForCampaign($this->_oldContributionData['campaign_id'],
      $this->_oldContributionData['receive_date']);
    $trxData['to_financial_account_id'] = CRM_Fintrxn_Utils::getFinancialAccountForBankAccount($this->_newContributionData[$refundCustomField]);
    // use new contribution status
    $trxData['status_id'] = $this->_newContributionData['contribution_status_id'];
    $this->writeTransaction($trxData);
  }

  /**
   * Method to process a changed contribution
   * - from financial account is the receiving account in the custom data
   * - to financial account is the account based on the campaign of the contribution
   */
  private function processChangedContribution() {
    $incomingCustomField = 'custom_' . $this->_config->getIncomingAccountCustomField();
    // reverse transaction for old contribution if required
    if ($this->isReversalRequired() == TRUE) {
      $this->_oldContributionData['total_amount'] = -$this->_oldContributionData['total_amount'];
      $this->_oldContributionData['net_amount'] = -$this->_oldContributionData['net_amount'];
      $this->_oldContributionData['fee_amount'] = -$this->_oldContributionData['fee_amount'];
      $oldTrxData = $this->createTransactionData($this->_oldContributionData, new DateTime($this->_oldContributionData['receive_date']));
      $oldTrxData['from_financial_account_id'] = CRM_Fintrxn_Utils::getFinancialAccountForBankAccount($this->_oldContributionData[$incomingCustomField]);
      $oldTrxData['to_financial_account_id'] = CRM_Fintrxn_Utils::getFinancialAccountForCampaign($this->_oldContributionData['campaign_id'],
        $this->_oldContributionData['receive_date']);
      $this->writeTransaction($oldTrxData);
    }
    // then create new transaction
    $newTrxData = $this->createTransactionData($this->_newContributionData, $this->setDateForEdit());
    $newTrxData['from_financial_account_id'] = CRM_Fintrxn_Utils::getFinancialAccountForBankAccount($this->_newContributionData[$incomingCustomField]);
    $newTrxData['to_financial_account_id'] = CRM_Fintrxn_Utils::getFinancialAccountForCampaign($this->_newContributionData['campaign_id'],
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
    $receiveDate = new DateTime($this->_newContributionData['receive_date']);
    $trxData = $this->createTransactionData($this->_newContributionData, $receiveDate);
    $trxData['from_financial_account_id'] = CRM_Fintrxn_Utils::getFinancialAccountForBankAccount($this->_newContributionData[$incomingCustomField]);
    if (!isset($this->_newContributionData['campaign_id']) || empty($this->_newContributionData['campaign_id']) || $this->_newContributionData['campaign_id'] == 'null') {
      $this->_newContributionData['to_financial_account_id'] = $this->_config->getDefaultCocoaFinancialAccountId();
    } else {
      $trxData['to_financial_account_id'] = CRM_Fintrxn_Utils::getFinancialAccountForCampaign($this->_newContributionData['campaign_id'],
        $this->_newContributionData['receive_date']);
    }
    $this->writeTransaction($trxData);
  }

  /**
   * create a template for a financial transaction based on contribution data
   *  It will be missing the fields:
   *       from_financial_account_id
   *       to_financial_account_id
   *
   * @param $contributionData
   * @return array
   */
  protected function createTransactionData($contributionData, $date = NULL) {
    if (!$date) {
      $date = new DateTime();
    }
    return array(
      'trxn_date'             => $date->format('YmdHis'),
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
    );
  }

  /**
   * Method to set the transaction date for edit operation
   *
   * @return DateTime
   */
  protected function setDateForEdit() {
    if ($this->_newContributionData['contribution_status_id'] == $this->_config->getCompletedContributionStatusId() &&
      $this->_oldContributionData['contribution_status_id'] == $this->_config->getPendingContributionStatusId()) {
      return new DateTime($this->_newContributionData['receive_date']);
    }
    if ($this->_oldContributionData['is_test'] == 1 && $this->_newContributionData['is_test'] == 0) {
      return new DateTime($this->_newContributionData['receive_date']);
    }
    if ($this->_oldContributionData['receive_date'] != $this->_newContributionData['receive_date']) {
      return new DateTime($this->_newContributionData['receive_date']);
    }
    return new DateTime();
  }

  /**
   * will create a given financial transaction in the DB
   *
   * @param $data
   */
  protected function writeTransaction($data) {
    // make sure that from and to financial account are present
    if (!isset($data['to_financial_account_id']) || empty($data['to_financial_account_id'])) {
      $data['to_financial_account_id'] = $this->_config->getDefaultCocoaFinancialAccountId();
    }
    if (!isset($data['from_financial_account_id']) || empty($data['from_financial_account_id'])) {
      $data['from_financial_account_id'] = $this->_config->getDefaultCocoaFinancialAccountId();
    }
    // fix for null value in fee and net amount, don't ask.....
    $fixNullStrings = array(
      'fee_amount',
      'net_amount',
    );
    foreach ($fixNullStrings as $fixNullString) {
      if ($data[$fixNullString] == 'null') {
        $data[$fixNullString] = 0;
      }
    }
    try {
      $financialTrxn = civicrm_api3('FinancialTrxn', 'create', $data);
      // now add entity
      civicrm_api3('EntityFinancialTrxn', 'create', $this->createEntityTransactionData($financialTrxn));

    } catch (CiviCRM_API3_Exception $ex) {
      CRM_Core_Error::debug_log_message('Could not create financial transaction and/or entity financial transaction in '.__METHOD__
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
    return array(
      'entity_table' => 'civicrm_contribution',
      'entity_id' => $this->_newContributionData['id'],
      'financial_trxn_id' => $financialTrxn['id'],
      'amount' => $financialTrxn['values'][$financialTrxn['id']]['total_amount'],
    );
  }

  /**
   * populate the $this->changes data
   * and fill the $this->new_contribution_data
   *
   * @param $updatedValues
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
      if (!empty($this->_newContributionData[$key]) && $this->_newContributionData[$key] != 'null') {
        return TRUE;
      }
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
   * Method to initialize contribution status based on new values
   *
   * @param $newValues
   * @return mixed
   * @throws Exception when error from api
   */
  private function initializeContributionStatusId($newValues) {
    // get contribution status id from current contribution if id is set, else completed
    if (isset($newValues['id'])) {
      try {
        return civicrm_api3('Contribution', 'getvalue', array('id' => $newValues['id'], 'return' => 'contribution_status_id'));
      }
      // something is horribly wrong if we try to update an non-existing contribution
      catch (CiviCRM_API3_Exception $ex) {
        throw new Exception('Could not find a contribution with id '.$newValues['id'].' in '.__METHOD__
          .'. Contact your system administrator. Error from API Contribution getvalue: '.$ex->getMessage());
      }
    } else {
      return $this->_config->getCompletedContributionStatusId();
    }
  }

  /**
   * Method to check if reversing the original transaction is required when operation is edit
   * (it is not when the contribution is changing from status pending to status completed or when
   *  changing from is_test = 1 to is_test = 0)
   *
   * @return bool
   */
  private function isReversalRequired() {
    if (!in_array($this->_oldContributionData['contribution_status_id'], $this->_config->getValidContributionStatus())) {
      return FALSE;
    }
    if ($this->_oldContributionData['is_test'] == 1 && $this->_newContributionData['is_test'] == 0) {
      return FALSE;
    }
    return TRUE;
  }


  /**
   * Cached API lookup
   *
   * @param $entity
   * @param $selector
   * @return mixed
   */
  protected function cachedLookup($entity, $selector) {
    $cacheKey = sha1($entity.json_encode($selector));
    if (array_key_exists($cacheKey, self::$_lookupCache)) {
      return self::$_lookupCache[$cacheKey];
    } else {
      try {
        $result = civicrm_api3($entity, 'getsingle', $selector);
        self::$_lookupCache[$cacheKey] = $result;
        return $result;
      } catch (Exception $e) {
        // not uniquely identified
        self::$_lookupCache[$cacheKey] = NULL;
        return NULL;
      }
    }
  }
}