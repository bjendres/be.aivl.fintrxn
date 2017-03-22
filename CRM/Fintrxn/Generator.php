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
  protected $_oldContributionData = NULL;
  protected $_newContributionData = NULL;
  protected $_changes = NULL;

  /**
   * CRM_Fintrxn_Generator constructor, storing the old values of the contribution
   * 
   * @param $operation
   * @param $contributionId
   * @param $oldValues
   */
  public function __construct($operation, $contributionId, $oldValues) {
    $this->_config = CRM_Fintrxn_Configuration::singleton();
    $this->_contributionId = $contributionId;
    $this->_operation = $operation;
    if ($this->_operation == 'create') {
      $this->_oldContributionData = array();
    } else {
      $this->_oldContributionData = $oldValues;  
    }
  }

  /**
   * create a new generator, overwriting an existing one if there is one
   * (expects to be called from a civicrm_pre hook, receiving the old values of the contribution before
   *  the operation is saved in the database)
   * 
   * @param $operation
   * @param $oldValues
   * @param $contributionId
   */
  public static function create($operation, $oldValues, $contributionId) {
    // error_log("CREATE $operation/$contributionId: " . json_encode($oldValues));
    self::$_singleton = new CRM_Fintrxn_Generator($operation, $contributionId, $oldValues);
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
    // todo if the class has not initialized itself, error should be logged?
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
    // error_log("GENERATE $operation/$contributionId: " . json_encode($newValues));

    // first some security checks to make sure we are actually checking the same contribution and comparing the 
    // correct old values and new values
    if ($operation != $this->_operation 
        || ($this->_contributionId && ($this->_contributionId != $contributionId))) {
      // something's gone wrong here because operation or contributionId is not the same as during construct
      // TODO : more meaningfull message
      error_log("FINTRXN ERROR: interleaved calls in ".__METHOD__.", this shouldn't happen!");
      return;
    }

    // will calculate the changes between the new values from the post hook and the old values from the pre hook, stored
    // in the class instance
    $this->calculateChanges($newValues);

    // switch based on case, which is determined by comparing the old and new values and the changes
    $cases = $this->calculateCases();
    CRM_Core_Error::debug('cases', $cases);
    CRM_Core_Error::debug('this', $this);
    exit();

    foreach ($cases as $case) {
      switch ($case) {
        case 'incoming':
          $trxData = $this->createTransactionData($this->_newContributionData);
          $trxData['from_financial_account_id'] = $this->getIncomingFinancialAccountID($this->_newContributionData);
          $trxData['to_financial_account_id'] = $this->getFinancialAccountID($this->_newContributionData);
          $this->writeFinancialTrxn($trxData);
          break;

        case 'rebooking':
          $trxData = $this->createTransactionData($this->_newContributionData);
          $fromAccount = $this->getFinancialAccountID($this->_oldContributionData);
          $toAccount = $this->getFinancialAccountID($this->_newContributionData);

          // create first double entry booking
          $trxData['to_financial_account_id'] = $toAccount;
          $trxData['from_financial_account_id'] = $fromAccount;
          $this->writeFinancialTrxn($trxData);

          // create second double entry booking
          $trxData['to_financial_account_id'] = $fromAccount;
          $trxData['from_financial_account_id'] = $toAccount;
          $trxData['amount'] = -$trxData['amount'];
          $this->writeFinancialTrxn($trxData);
          break;

        case 'amount correction':
          // TODO:
          break;

        case 'receive date correction':
          // TODO:
          break;

        case 'refund date correction':
          // TODO:
          break;

        case 'outgoing':
          $trxData = $this->createTransactionData($this->_newContributionData);
          $trxData['from_financial_account_id'] = $this->getFinancialAccountID($this->_oldContributionData);
          $trxData['to_financial_account_id'] = $this->getOutgoingFinancialAccountID($this->_newContributionData);
          $this->writeFinancialTrxn($trxData);
          break;
        
        default:
          error_log("FINTRXN ERROR: unknown case $case");
          break;
      }
    }
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
  protected function createTransactionData($contributionData, $date) {
    return array(
      'trxn_date'             => $date,
      'total_amount'          => $contributionData['total_amount'],
      'fee_amount'            => $contributionData['fee_amount'],
      'net_amount'            => $contributionData['net_amount'],
      'currency'              => $contributionData['currency'],
      'trxn_id'               => $contributionData['trxn_id'],
      'trxn_result_code'      => '',
      'status_id'             => $contributionData['status_id'],
      'payment_processor_id'  => $contributionData['payment_processor_id'],
      'payment_instrument_id' => $contributionData['payment_instrument_id'],
      'check_number'          => $contributionData['check_number'],
    );
  }

  /**
   * will create a given financial transaction in the DB
   * 
   * @param $data
   */
  protected function writeFinancialTrxn($data) {
    // TODO - write financial trxn AND entity financial trxn
    // TODO for incoming : from account is the fin account linked to the Amnesty IBAN's
    // TODO for refunds : to account is the refunding account of Amnesty
    // TODO each contribution will have custom fields for incoming and refund account



    error_log("WOULD WRITE TO civicrm_financial_trxn: " . json_encode($data));

    // TODO: write to entity_financial_trxn
  }
  
  /**
   * Calculate the accounting case here based on the comparison between the old values from the pre hook (stored
   * in the class instance) and the new values from the post hook (also stored in the class instance)
   *
   * @return string 'incoming', 'outgoing', 'rebooking' or 'ignored'
   */
  protected function calculateCases() {
    $cases = array();
    if (in_array('contribution_status_id', $this->_changes)) {
      // contribution status change -> this
      $oldStatus = CRM_Utils_Array::value('contribution_status_id', $this->_oldContributionData);
      $newStatus = CRM_Utils_Array::value('contribution_status_id', $this->_newContributionData);

      // todo: check with Björn
      // if the status was completed in the pre hook and in the post hook we are dealing with a new
      // contribution and incoming financial transaction
      if (!$this->_config->isCompleted($oldStatus) && $this->_config->isCompleted($newStatus)) {
        $cases[] = 'incoming';
      // if the old status was completed and the new status is NOT completed, we are dealing with a refund, change or
      // cancel so an outgoing financial transaction
      } elseif ($this->_config->isCompleted($oldStatus) && !$this->_config->isCompleted($newStatus)) {
        $cases[] = 'outgoing';
      }
    }

    if ($this->_config->isAccountRelevant($this->_changes)) {
      $cases[] = 'rebooking';
    }

    if ($this->_config->isAmountChange($this->_changes)) {
      $cases[] = 'amount correction';
    }

    if (in_array('receive_date', $this->_changes) && !$this->_config->isHypothetical($newStatus)) {
      $cases[] = 'receive date correction';
    }    

    if (in_array('refund_date', $this->_changes) && $this->_config->isReturned($newStatus)) {
      $cases[] = 'refund date correction';
    }    
  }

  /**
   * populate the $this->new_contribution_data and $this->changes data sets
   * 
   * @param $newValues
   */
  protected function calculateChanges($newValues) {
    // FIXME: neither data sets are properly filtered contribution data,
    //   but let's see how far we get without having to reload a contribution
    $this->_newContributionData = $newValues;
    $this->_changes = array();
    foreach ($newValues as $key => $value) {
      if ($newValues[$key] != CRM_Utils_Array::value($key, $this->_oldContributionData)) {
        $this->_changes[] = $key;
      }
    }
  }

  /**
   * calculate the (target) financial account ID of the given contribution
   * 
   * @param $contributionData
   * @return mixed
   */
  protected function getFinancialAccountID($contributionData) {
    // TODO: check with accounting/databeheer
    if (empty($contributionData['campaign_id'])) {
      // TODO: there SHOULD be a fallback account
      error_log("FINTRXN ERROR: contribution has no campaign!");
      $accountingCode = '0000';
    } else {
      // get the COCOA codes from the campaign
      $campaign = $this->cachedLookup('Campaign', array(
        'id' => $contributionData['campaign_id'],
        'return' => $this->_config->getCocoaFieldList()));

      // if the contribution year is the acquisition year, use custom_85, otherwise custom_86
      // TODO: check with Ilja what the new situation is to be, expect always use custom_85
      $accountingCode = $this->_config->getCocoaValue($campaign, $contributionData['receive_date']);
    }

    // lookup account id
    $account = $this->cachedLookup('FinancialAccount',array(
      'accounting_code' => $accountingCode,
      'return' => 'id'));

    if (empty($account['id'])) {
      // TODO: account not found, issue a warning
      return NULL;
    } else {
      return $account['id'];
    }
  }

  /**
   * Cached API lookup
   * 
   * @param $entity
   * @param $selector
   * @return mixed
   */
  protected function cachedLookup($entity, $selector) {
    error_log("LOOKUP: $entity " . json_encode($selector));
    $cacheKey = sha1($entity.json_encode($selector));
    if (array_key_exists($cacheKey, self::$_lookupCache)) {
      return self::$_lookupCache[$cacheKey];
    } else {
      try {
        $result = civicrm_api3($entity, 'getsingle', $selector);
        self::$_lookupCache[$cacheKey] = $result;
        error_log("RESULT: " . json_encode($result));
        return $result;
      } catch (Exception $e) {
        // not uniquely identified
        self::$_lookupCache[$cacheKey] = NULL;
        return NULL;
      }
    }
  }
}