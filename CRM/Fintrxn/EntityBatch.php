<?php
/**
 * Class for specific Financial Transaction processing
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 21 March 2018
 * @license AGPL-3.0
 */
class CRM_Fintrxn_EntityBatch {

  private $_quarter = NULL;
  private $_batchId = NULL;
  private $_startDate = NULL;
  private $_endDate = NULL;

  /**
   * CRM_Fintrxn_EntityBatch constructor.
   *
   * @param array $params
   */
  public function __construct($params) {
    $this->_quarter = strtoupper($params['quarter']);
    $validQuarters = array('Q1', 'Q2', 'Q3', 'Q4');
    if (!in_array($this->_quarter, $validQuarters)) {
      CRM_Core_Error::createError('Invalid quarter passed to ' . __METHOD__ . ', valid is Q1, Q2, Q3 or Q4');
    }
    switch ($this->_quarter) {
      case 'Q3':
        $this->_startDate = new DateTime('2017-07-01');
        $this->_endDate = new DateTime('2017-09-30');
        break;
      case 'Q4':
        $this->_startDate = new DateTime('2017-10-01');
        $this->_endDate = new DateTime('2017-12-31');
        break;
      case 'Q1':
        $this->_startDate = new DateTime('2018-01-01');
        $this->_endDate = new DateTime('2018-03-31');
        break;
      case 'Q2':
        $this->_startDate = new DateTime('2018-04-01');
        $this->_endDate = new DateTime('2018-06-30');
        break;
    }
    // now create new batch
    try {
      $created = civicrm_api3('Batch', 'create', array(
        'title' => "Hist Batch " . $this->_quarter . " ". date('Ymdhis'),
        'status_id' => "Open",
        'description' => "Batch voor historische financiële transacties in " . $this->_quarter . ' tussen ' .
          $this->_startDate->format('d-m-Y') . ' en ' . $this->_endDate->format('d-m-Y'),
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
   * Method to add financial transactions from quarter to entity batch
   */
  public function addHistoricQuarter() {
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