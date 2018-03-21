<?php
/**
 * Class for specific AIVL Batach processing
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 3 May 2017
 * @license AGPL-3.0
 */
class CRM_Fintrxn_Batch {

  private $_batchId = NULL;

  /**
   * CRM_Fintrxn_Batch constructor.
   *
   * @param $batchId
   * @throws Exception when 0 or more than 1 batches found, or when error from API
   */
  function __construct($batchId) {
    // check if batch actually exists, otherwise throw exception
    try {
      $count = civicrm_api3('Batch', 'getcount', array('id' => $batchId,));
      if ($count != 1) {
        throw new Exception(ts('Could not find a single batch with id '.$batchId.' in '.__METHOD__.'Found '.$count
          .' batches. Contact your system administrator'));
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
      throw new Exception(ts('Could not find a valid accounting batch in '.__METHOD__
        .', contact your system administrator. Error from API Batch getcount: '.$ex->getMessage()));
    }
    $this->_batchId = $batchId;
  }

  /**
   * Method to get the financial transactions in the batch
   *
   * @return array
   */
  public function getFinancialTransactionIds() {
    $result = array();
    if (!empty($this->_batchId)) {
      try {
        $entityBatches = civicrm_api3('EntityBatch', 'get', array(
          'batch_id' => $this->_batchId,
          'options' => array('limit' => 0,),));
        foreach ($entityBatches['values'] as $entityBatch) {
          if ($entityBatch['entity_table'] == 'civicrm_financial_trxn') {
            $result[] = $entityBatch['entity_id'];
          }
        }
      } catch (CiviCRM_API3_Exception $ex) {
        // do nothing
      }
    }
    return $result;
  }

  /**
   * Method to validate all financial transactions in a batch
   *
   * @return null|string
   */
  public function validateBatch() {
    $financialTransactionIds = $this->getFinancialTransactionIds();
    if (empty($financialTransactionIds)) {
      return 'nofintrxn';
    }
    foreach ($financialTransactionIds as $financialTransactionId) {
      $financialTransaction = new CRM_Fintrxn_FinancialTransaction($financialTransactionId);
      $errorMessage = $financialTransaction->validateTransaction();
      if (!empty($errorMessage)) {
        return 'notvalid';
      }
    }
    return NULL;
  }

  /**
   * Static method to process hook_civicrm_links
   *
   * @param $objectId
   * @param $links
   * @param $values
   */
  public static function links($objectId, &$links, &$values) {
    // only for open batches
    if ($values['status'] == 1) {
      // remove export and close
      foreach ($links as $linkKey => $linkValues) {
        if ($linkValues['name'] == 'Export' || $linkValues['name'] == 'Close') {
          unset($links[$linkKey]);
        }
      }
      // add validate, assign latest open and remove all
      //$links[] = array(
        //'name' => ts('Validate'),
        //'url' => 'civicrm/fintrxn/page/batchvalidate',
        //'title' => 'Validate Batch',
        //'qs' => 'reset=1&bid=%%bid%%',
        //'bit' => 'validate',
      //);
      $links[] = array(
        'name' => ts('Assign Latest Open'),
        'url' => 'civicrm/fintrxn/batch/assignopen',
        'title' => 'Assign Latest Open Transactions',
        'qs' => 'bid=%%bid%%',
        'bit' => 'assign open',
      );
      $links[] = array(
        'name' => ts('Remove All Transactions'),
        'url' => 'civicrm/fintrxn/batch/removeall',
        'title' => 'Remove All Transactions',
        'qs' => 'bid=%%bid%%',
        'bit' => 'remove all',
      );
      $values['bid'] = $objectId;
    }
  }

  /**
   * Implements hook_civicrm_batchItems
   *
   * @param $results
   * @param $items
   * @throws Exception
   */
  public static function batchItems ($results, &$items) {
    // clean out items array
    $items = array();
    // get mapping from JSON file
    $mapping = self::getExportMapping();
    // go through all results
    foreach ($results as $result) {
      // create item array based on mapping
      $item = $mapping;
      // retrieve additional info
      $additionalInfo = CRM_Fintrxn_Utils::getAdditionalBatchInfoForContribution($result['contribution_id']);
      foreach ($item as $itemKey => $itemValue) {
        switch ($itemKey) {
          case 'Campaign ID':
            if ($additionalInfo['campaign_id']) {
              $item[$itemKey] = $additionalInfo['campaign_id'];
            }
            break;
          case 'Financial Type':
            if ($additionalInfo['financial_type']) {
              $item[$itemKey] = $additionalInfo['financial_type'];
            }
            break;
          default:
            if (isset($result[$itemValue])) {
              $item[$itemKey] = $result[$itemValue];
            }
            break;
        }
      }
      $items[] = $item;
    }
  }

  /**
   * Method to get export mapping for batch export
   *
   * @return mixed
   * @throws Exception
   */
  private static function getExportMapping() {
    $resourcesPath = CRM_Fintrxn_Configuration::getDefaultResourcesPath();
    $mappingJsonFile = $resourcesPath.'batch_export_mapping.json';
    if (!file_exists($mappingJsonFile)) {
      throw new Exception(ts('Could not load export mapping for batch export in '.__METHOD__
        .', contact your system administrator!'));
    }
    $mappingJson = file_get_contents($mappingJsonFile);
    return json_decode($mappingJson, true);
  }

  /**
   * Method to remove all financial transactions from an accounting batch
   */
  public static function removeAllTrxn() {
    // retrieve batch id from request, only process if it is available and not empty
    $requestValues = CRM_Utils_Request::exportValues();
    if (isset($requestValues['bid']) && !empty($requestValues['bid'])) {
      try {
        $batchTransactions = civicrm_api3('EntityBatch', 'get', array(
          'batch_id' => $requestValues['bid'],
          'options' => array('limit' => 0),
        ));
        foreach ($batchTransactions['values'] as $batchTransaction) {
          civicrm_api3('EntityBatch', 'delete', array(
            'id' => $batchTransaction['id'],
          ));
        }
      }
      catch (CiviCRM_API3_Exception $ex) {
      }
    }
    $url = CRM_Utils_System::url('civicrm/financial/financialbatches', 'reset=1&batchStatus=1', true);
    CRM_Utils_System::redirect($url);
  }

  /**
   * Method to add all unassigned financial transactions after the last assigned financial transaction to an
   * accounting batch
   */
  public static function assignLatestOpen() {
    // retrieve batch id from request, only process if it is available and not empty
    $requestValues = CRM_Utils_Request::exportValues();
    if (isset($requestValues['bid']) && !empty($requestValues['bid'])) {
      // retrieve date of the latest assigned financial transaction
      $latestAssignedDate = self::getLatestAssignedTransactionDate();
      // now select all financial transactions that are not assigned yet and are later than the latest assigned
      $query = "SELECT a.id 
        FROM civicrm_financial_trxn a LEFT JOIN civicrm_entity_batch b ON a.id = b.entity_id
        WHERE trxn_date > %1 AND batch_id IS NULL";
      $params = array(
        1 => array($latestAssignedDate, 'String'),
      );
      $dao = CRM_Core_DAO::executeQuery($query, $params);
      while ($dao->fetch()) {
        try {
          civicrm_api3('EntityBatch', 'create', array(
            'entity_table' => 'civicrm_financial_trxn',
            'entity_id' => $dao->id,
            'batch_id' => $requestValues['bid']
          ));
        }
        catch (CiviCRM_API3_Exception $ex) {
        }
      }
    }
    $url = CRM_Utils_System::url('civicrm/batchtransaction', 'reset=1&bid='.$requestValues['bid'], true);
    CRM_Utils_System::redirect($url);
  }

  /**
   * Method to get the latest transaction date assigned to a batch
   *
   * @return null|string
   */
  private static function getLatestAssignedTransactionDate() {
    $query = "SELECT MAX(trxn_date)
      FROM civicrm_entity_batch a JOIN civicrm_financial_trxn b on a.entity_id = b.id
      WHERE a.entity_table = %1";
    return CRM_Core_DAO::singleValueQuery($query, array(
      1 => array('civicrm_financial_trxn', 'String'),
    ));
  }
}