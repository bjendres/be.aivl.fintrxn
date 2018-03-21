<?php
/**
 * Class for specific Financial Transaction processing
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 21 March 2018
 * @license AGPL-3.0
 */
class CRM_Fintrxn_EntityBatch {

  private $_year = NULL;
  private $_month = NULL;
  private $_batchId = NULL;
  private $_startDate = NULL;
  private $_endDate = NULL;

  /**
   * CRM_Fintrxn_EntityBatch constructor.
   *
   * @param array $params
   */
  public function __construct($params) {
    if (!is_numeric($params['year']) || empty($params['year'])) {
      CRM_Core_Error::createError(ts('Year has to contain 4 digits (like 2018) in ' . __METHOD__ . ', it is getting the value : ' . $params['year']));
    }
    if (strlen($params['month']) != 4) {
      CRM_Core_Error::createError(ts('Year has to contain 4 digits (like 2018) in ' . __METHOD__ . ', it is getting the value : ' . $params['year']));
    }
    if (!is_numeric($params['month']) || empty($params['month'])) {
      CRM_Core_Error::createError(ts('Month has to contain 2 digits (like 11) in ' . __METHOD__ . ', it is getting the value : ' . $params['month']));
    }
    if (strlen($params['month']) > 2) {
      CRM_Core_Error::createError(ts('Month has to contain 2 digits (like 11) in ' . __METHOD__ . ', it is getting the value : ' . $params['month']));
    }
    $this->_year = $params['year'];
    $this->_month = $params['month'];
    $this->_startDate = new DateTime($this->_year . '-' . $this->_month . '-01');
    $this->_endDate = new DateTime($this->_year . '-' . $this->_month . '-31');
    // now create new batch
    try {
      $created = civicrm_api3('Batch', 'create', array(
        'title' => "Hist Batch " . $this->_year . "-" . $this->_month . " ". date('Ymdhis'),
        'status_id' => "Open",
        'description' => "Batch voor hist. fin. transacties tussen " . $this->_startDate->format('d-m-Y') . " en " . $this->_endDate->format('d-m-Y'),
        'created_id' => 1,
        'created_date' => date('Y-m-d h:i:s'),
        'mode_id' => 2,
      ));
      $this->_batchId = $created['id'];
    }
    catch (CiviCRM_API3_Exception $ex) {
      CRM_Core_Error::createError('Could not create batch in ' . __METHOD__ . ', error from Batch Create : ' . $ex->getMessage());
    }
  }

  /**
   * Method to add financial transactions from month to entity batch
   */
  public function addMonth() {
    $result = array();
    $query = "SELECT * FROM civicrm_financial_trxn WHERE check_number = %1 AND (trxn_date BETWEEN %2 AND %3)";
    $queryParams = array(
      1 => array('AIVL fintrxn', 'String'),
      2 => array($this->_startDate->format('Y-m-d'), 'String'),
      3 => array($this->_endDate->format('Y-m-d'), 'String'),
    );
    $dao = CRM_Core_DAO::executeQuery($query, $queryParams);
    while ($dao->fetch()) {
      try {
        $created = civicrm_api3('EntityBatch', 'create', array(
          'batch_id' => $this->_batchId,
          'entity_table' => 'civicrm_financial_trxn',
          'entity_id' => $dao->id,
        ));
        $result[] = 'Transactie ' . $dao->id . ' in batch ' . $this->_batchId . 'geplaatst';
      }
      catch (CiviCRM_API3_Exception $ex) {
        $result[] = 'FOUT!!!! Transactie niet meegenomen!';
      }
    }
    return $result;
  }
}