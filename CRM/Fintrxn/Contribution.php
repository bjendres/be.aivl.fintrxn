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
    self::validateRefundAccount($fields, $errors);
    self::validateIncomingAccount($fields, $errors);
    return;
  }

  /**
   * Method to validate if an incoming account has been set
   * @param $fields
   * @param $errors
   */
  private static function validateIncomingAccount($fields, &$errors) {
    $config = CRM_Fintrxn_Configuration::singleton();
    $incomingAccountCustomField = 'custom_' . $config->getIncomingAccountCustomField('id');
    foreach ($fields as $fieldName => $fieldValue) {
      $parts = explode("custom_", $fieldName);
      if (isset($parts[1])) {
        $nextParts = explode('_', $parts[1]);
        $checkName = 'custom_' . $nextParts[0];
        if ($checkName == $incomingAccountCustomField) {
          $incomingFieldName = $fieldName;
        }
      }
    }
    if (empty($incomingFieldName)) {
      $errors['contribution_status_id'] = ts('You have to select an incoming bank account.');
      return;
    }
    if (!isset($fields[$incomingFieldName])) {
      $errors['contribution_status_id'] = ts('You have to select an incoming bank account.');
      return;
    }
    if (empty($fields[$incomingFieldName])) {
      $errors[$incomingFieldName] = ts('You have to select an incoming bank account.');
      return;
    }
    return;
  }

  /**
   * Method to validate if a refund account has been set if relevant
   * @param $fields
   * @param $errors
   */
  private static function validateRefundAccount($fields, &$errors) {
    $config = CRM_Fintrxn_Configuration::singleton();
    if (isset($fields['contribution_status_id']) && $fields['contribution_status_id'] == $config->getRefundContributionStatusId()) {
      $refundAccountCustomField = 'custom_' . $config->getRefundAccountCustomField('id');
      foreach ($fields as $fieldName => $fieldValue) {
        $parts = explode("custom_", $fieldName);
        if (isset($parts[1])) {
          $nextParts = explode('_', $parts[1]);
          $checkName = 'custom_' . $nextParts[0];
          if ($checkName == $refundAccountCustomField) {
            $refundFieldName = $fieldName;
          }
        }
      }
      if (empty($refundFieldName)) {
        $errors['contribution_status_id'] = ts('You have to select an refund bank account if the contribution is going to be refunded.');
        return;
      }
      if (!isset($fields[$refundFieldName])) {
        $errors['contribution_status_id'] = ts('You have to select an refund bank account if the contribution is going to be refunded.');
        return;
      }
      if (empty($fields[$refundFieldName])) {
        $errors[$refundFieldName] = ts('You have to select an refund bank account if the contribution is going to be refunded.');
        return;
      }
    }
    return;
  }
}