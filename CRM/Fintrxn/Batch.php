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
   * Static method to process validateForm hook
   *
   * @param $fields
   * @param $errors
   * @throws Exception when unable to find batchId in entryURL or when entryURL absent from $fields
   */
  public static function validateForm($fields, &$errors) {
    if (isset($fields['entryURL'])) {
      $entryParts = explode('id=', $fields['entryURL']);
      if (isset($entryParts[1])) {
        $idParts = explode('&amp', $entryParts[1]);
        if (is_numeric($idParts[0])) {
          $batchId = (int) $idParts[0];
        }
      }
      if (isset($batchId) && !empty($batchId)) {
        // retrieve all financial transactions in batch and validate them
        $batch = new CRM_Fintrxn_Batch($batchId);
        $errorType = $batch->validateBatch();
        switch ($errorType) {
          case 'nofintrxn':
            $errors['export_format'] = 'There are no financial transactions in the batch, can not be exported.';
            break;
          case 'notvalid':
            $errors['export_format'] = 'You can not export the batch because there are still invalid transactions. 
            To find out what the problems are, cancel the export and validate the batch from the list of batches';
            break;
        }
        return;
      } else {
        throw new Exception('Could not find a batch id in the entry url in '.__METHOD__.', contact your system administrator!');
      }
    } else {
      throw new Exception('Fields parameter does not contain an element named entryURL in '.__METHOD__.'contact your system administrator!');
    }
  }

  /**
   * Static method to process hook_civicrm_links
   *
   * @param $objectId
   * @param $links
   * @param $values
   */
  public static function links($objectId, &$links, &$values) {
    // add validate and remove export, close for open batches
    if ($values['status'] == 1) {
      foreach ($links as $linkKey => $linkValues) {
        if ($linkValues['name'] == 'Export' || $linkValues['name'] == 'Close') {
          unset($links[$linkKey]);
        }
      }
      $links[] = array(
        'name' => ts('Validate'),
        'url' => 'civicrm/fintrxn/page/batchvalidate',
        'title' => 'Validate Batch',
        'qs' => 'reset=1&bid=%%bid%%',
        'bit' => 'validate',
      );
      $values['bid'] = $objectId;
    }
  }
  public static function batchItems ($results, &$items) {
    // clean out items array
    $items = array();
    // get mapping from JSON file
    $mapping = self::getExportMapping();
    // go through all results
    foreach ($results as $result) {
      // split transaction date in year and month
      $transactionDate = new DateTime($result['trxn_date']);
      $result['trxn_year'] = $transactionDate->format('Y');
      $result['trxn_month'] = $transactionDate->format('n');
      // create item array based on mapping
      $item = $mapping;
      foreach ($item as $itemKey => $itemValue) {
        if (isset($result[$itemValue])) {
          $item[$itemKey] = $result[$itemValue];
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
}