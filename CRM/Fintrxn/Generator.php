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
 * @license AGPL-3.0
 */
class CRM_Fintrxn_Generator {

  // there can only be zero or one generator at any time
  protected static $_singleton = NULL;
  protected static $_lookup_cache = array();

  // variables
  protected $contribution_id       = NULL;
  protected $operation             = NULL;
  protected $old_contribution_data = NULL;
  protected $new_contribution_data = NULL;
  protected $changes               = NULL;

  /**
   * basic constructor
   */
  public function __construct($operation, $contribution_id, $old_values) {
    $this->contribution_id = $contribution_id;
    $this->operation = $operation;
    $this->old_contribution_data = $old_values;
  }

  /**
   * create a new generator,
   *   overwriting an existing one if there is one
   */
  public static function create($operation, $old_values, $contribution_id) {
    // error_log("CREATE $operation/$contribution_id: " . json_encode($old_values));
    self::$_singleton = new CRM_Fintrxn_Generator($operation, $contribution_id, $old_values);
  }

  /**
   * trigger the calculation of financial transactions
   */
  public static function generate($operation, $contribution_id, $objectRef) {
    if (self::$_singleton != NULL) {
      $new_values = array();
      if ($objectRef) {
        // convert rev object
        foreach ($objectRef as $key => $value) {
          if (substr($key, 0, 1) != '_') {
            $new_values[$key] = $value;
          }
        }
      }
      self::$_singleton->generateFinancialTrxns($operation, $contribution_id, $new_values);
    }
  }

  /**
   * main dispatcher function 
   */
  public function generateFinancialTrxns($operation, $contribution_id, $new_values) {
    // error_log("GENERATE $operation/$contribution_id: " . json_encode($new_values));

    // first some security checks
    if ($operation != $this->operation 
        || ($this->contribution_id && $this->contribution_id != $contribution_id)) {
      // something's gone wrong here
      return;
    }

    // will calculate the changes that happened
    $this->calculateChanges($new_values);

    // operation switch
    if ($operation == 'create') {
      $trx_data = $this->createTransactionData($this->new_contribution_data);
      $trx_data['to_financial_account_id'] = $this->getFinancialAccountID($this->new_contribution_data);
      $this->writeFinancialTrxn($trx_data);
    
    } elseif ($operation == 'edit') {
      // TODO

    }
  }



  /**
   * create a template for a financial transaction based on contribution data
   *  It will be missing the fields:
   *       from_financial_account_id
   *       to_financial_account_id
   */
  protected function createTransactionData($contribution_data, $date) {
    return array(
      'trxn_date'             => $date,
      'total_amount'          => $contribution_data['total_amount'],
      'fee_amount'            => $contribution_data['fee_amount'],
      'net_amount'            => $contribution_data['net_amount'],
      'currency'              => $contribution_data['currency'],
      'trxn_id'               => $contribution_data['trxn_id'],
      'trxn_result_code'      => '',
      'status_id'             => $contribution_data['status_id'],
      'payment_processor_id'  => $contribution_data['payment_processor_id'],
      'payment_instrument_id' => $contribution_data['payment_instrument_id'],
      'check_number'          => $contribution_data['check_number'],
    );
  }

  /**
   * will create a given financial transaction in the DB
   */
  protected function writeFinancialTrxn($data) {
    // TODO

    error_log("WOULD WRITE TO civicrm_financial_trxn: " . json_encode($data));

    // TODO: write to entity_financial_trxn
  }



  /**
   * populate the $this->new_contribution_data and $this->changes data sets
   */
  private function calculateChanges($new_values) {
    // FIXME: neither data sets are properly filtered contribution data,
    //   but let's see how far we get without having to reload a contribution
    $this->new_contribution_data = $new_values;
    $this->changes = array();
    foreach ($new_values as $key => $value) {
      if ($new_values[$key] != CRM_Utils_Array::value($key, $this->old_contribution_data)) {
        $this->changes[] = $key;
      }
    }
  }

  /**
   * calculate the (target) financial account ID of the given contribution
   */
  protected function getFinancialAccountID($contribution_data) {
    // TODO: check with accounting/databeheer
    if (empty($contribution_data['campaign_id'])) {
      // TODO: there SHOULD be a fallback account
      $accounting_code = '0000';
    } else {
      // get the COCOA codes from the campaign
      $campaign = $this->cachedLookup('Campaign', 
          array('id' => $contribution_data['campaign_id'],
                'return' => 'custom_85,custom_86,custom_87'));

      // if the contribution year is the acquisition year, use custom_85, otherwise custom_86
      $year = substr($contribution_data['receive_date'], 0, 4);
      if ($year == $campaign['custom_87']) {
        $accounting_code = $campaign['custom_85'];
      } else {
        $accounting_code = $campaign['custom_86'];
      }
    }

    // lookup account id
    $account = $this->cachedLookup('FinancialAccount',
          array('accounting_code' => $accounting_code,
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
   */
  protected function cachedLookup($entity, $selector) {
    error_log("LOOKUP: $entity " . json_encode($selector));
    $cache_key = sha1($entity.json_encode($selector));
    if (array_key_exists($cache_key, self::$_lookup_cache)) {
      return self::$_lookup_cache[$cache_key];
    } else {
      try {
        $result = civicrm_api3($entity, 'getsingle', $selector);
        self::$_lookup_cache[$cache_key] = $result;
        error_log("RESULT: " . json_encode($result));
        return $result;
      } catch (Exception $e) {
        // not uniquely identified
        self::$_lookup_cache[$cache_key] = NULL;
        return NULL;
      }
    }
  }
}