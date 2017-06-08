<?php
/**
 * Class for specific AIVL Contribution processing
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 8 May 2017
 * @license AGPL-3.0
 */
class CRM_Fintrxn_Contribution {
  /**
   * Method to process validateForm hook
   *
   * @param $fields
   * @param & $errors
   */
  public static function validateForm($fields, &$errors) {
    $config = CRM_Fintrxn_Configuration::singleton();
    // if contribution_status_id = refund, validate refund bank account is set
    if (isset($fields['contribution_status_id']) && $fields['contribution_status_id'] == $config->getRefundContributionStatusId()) {
      $refundAccountCustomField = 'custom_'.$config->getRefundAccountCustomField('id');
      foreach ($fields as $fieldName => $fieldValue) {
        $parts = explode("custom_", $fieldName);
        if (isset($parts[1])) {
          $nextParts = explode('_', $parts[1]);
          $checkName = 'custom_'.$nextParts[0];
          if ($checkName == $refundAccountCustomField) {
            $refundFieldName = $fieldName;
          }
        }
      }
      if (!isset($refundFieldName) || empty($refundFieldName)) {
        $errors['contribution_status_id'] = ts('You have to select a refund bank account if the contribution is going to be refunded.');
        return;
      }
      if (!isset($fields[$refundFieldName])) {
        $errors[$refundFieldName] = ts('You have to select a refund bank account if the contribution is going to be refunded.');
        return;
      }
      if (empty($fields[$refundFieldName])) {
        $errors[$refundFieldName] = ts('You have to select a refund bank account if the contribution is going to be refunded.');
        return;
      }
    }
    return;
  }
}