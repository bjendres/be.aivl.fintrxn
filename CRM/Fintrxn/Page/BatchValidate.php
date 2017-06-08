<?php

class CRM_Fintrxn_Page_BatchValidate extends CRM_Core_Page {

  protected $_batchId = NULL;
  protected $_batchErrors = array();
  protected $_validatedBatchStatus = NULL;

  /**
   * Child method to run the page
   */
  public function run() {
    $this->initializePage();
    $this->_batchErrors = array();
    // get all financial transaction in the batch
    $transactionIds = $this->getFinancialTransactions();
    foreach ($transactionIds as $transactionId) {
      $finTrxn = new CRM_Fintrxn_FinancialTransaction($transactionId);
      $errorMessage = $finTrxn->validateTransaction();
      if (!empty($errorMessage)) {
        $this->addBatchError($transactionId, $errorMessage);
      }
    }
    if (!empty($this->_batchErrors)) {
      $this->assign('batchErrors', $this->_batchErrors);
    } else {
      try {
        civicrm_api3('Batch', 'create', array(
          'id' => $this->_batchId,
          'status_id' => $this->_validatedBatchStatus,
        ));
      }
      catch (CiviCRM_API3_Exception $ex) {
      }
    }
    parent::run();
  }

  /**
   * Method to add a contribution error for the batch
   *
   * @param $finTransactionId
   * @param $errorMessage
   */
  private function addBatchError($finTransactionId, $errorMessage) {
    if (!empty($finTransactionId)) {
      $sql = "SELECT tx.from_financial_account_id, tx.to_financial_account_id, tx.total_amount, tx.currency, tx.trxn_date,
        cb.id AS contribution_id, cb.contact_id, cc.display_name, cp.title
        FROM civicrm_financial_trxn tx 
        JOIN civicrm_entity_financial_trxn ex ON tx.id = ex.financial_trxn_id AND ex.entity_table = %1
        JOIN civicrm_contribution cb ON ex.entity_id = cb.id
        JOIN civicrm_contact cc ON cb.contact_id = cc.id
        LEFT JOIN civicrm_campaign cp ON cb.campaign_id = cp.id
        WHERE tx.id = %2";
      $sqlParams = array(
        1 => array('civicrm_contribution', 'String',),
        2 => array($finTransactionId, 'Integer',),
      );
      $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
      if ($dao->fetch()) {
        $batchError = array(
          'contribution_id' => $dao->contribution_id,
          'contact_id' => $dao->contact_id,
          'contact_name' => $dao->display_name,
          'campaign' => $dao->title,
          'total_amount' => $dao->total_amount,
          'transaction_date' => $dao->trxn_date,
          'from_account' =>CRM_Fintrxn_Utils::getFinancialAccountName($dao->from_financial_account_id),
          'to_acccount' => CRM_Fintrxn_Utils::getFinancialAccountName($dao->to_financial_account_id),
          'error_message' => $errorMessage,
          'actions' => $this->addRowActions($dao->contribution_id, $dao->contact_id),
        );
        $this->_batchErrors[] = $batchError;
      }
    }
  }

  /**
   * Method to set the row actions
   *
   * @param $contributionId
   * @param $contactId
   * @return array
   */
  private function addRowActions($contributionId, $contactId) {
    $viewUrl = CRM_Utils_System::url('civicrm/contact/view/contribution', 'reset=1&id='.$contributionId
      .'&cid='.$contactId.'&action=view&contribution=context&selectedChild=contribute', TRUE);
    $editUrl = CRM_Utils_System::url('civicrm/contact/view/contribution', 'reset=1&action=update&id='.$contributionId
    .'&cid='.$contactId.'&context=search', TRUE);
    return array(
      '<a class="action-item" title="View Contribution" href="'.$viewUrl.'">View</a>',
      '<a class="action-item" title="Edit Contribution" href="'.$editUrl.'">Edit</a>',
    );
  }

  /**
   * Method to get the financial transactions of the batch
   *
   * @return bool|array
   */
  private function getFinancialTransactions() {
    $result = array();
    try {
      $entityBatch = civicrm_api3('EntityBatch', 'get', array(
        'entity_table' => 'civicrm_financial_trxn',
        'batch_id' => $this->_batchId,
        'options' => array('limit' => 0),
      ));
      foreach ($entityBatch['values'] as $transaction) {
        $result[] = $transaction['entity_id'];
      }
      return $result;
    }
    catch (CiviCRM_API3_Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Method to initialize the page
   */
  private function initializePage() {
    try {
      $this->_validatedBatchStatus = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => 'batch_status',
        'name' => 'Validated',
        'return' => 'value',));
    }
    catch (CiviCRM_API3_Exception $ex) {
      throw new Exception(ts('Could not find the batch status Validated in '.__METHOD__
        .', contact your system administrator. Error from API OptionValue getvalue: '.$ex->getMessage()));
    }
    $requestValues = CRM_Utils_Request::exportValues();
    if (isset($requestValues['bid'])) {
      $this->_batchId = $requestValues['bid'];
      try {
        $batchName = civicrm_api3('Batch', 'getvalue', array('id' => $this->_batchId, 'return' => 'title'));
        CRM_Utils_System::setTitle(ts('Validate Batch '.$batchName));
      }
      catch (CiviCRM_API3_Exception $ex) {
        CRM_Utils_System::setTitle(ts('Validate Batch '));
      }
    }
  }
}
