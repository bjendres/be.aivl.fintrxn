<?php
/*-------------------------------------------------------+
| Custom Financial Transaction Generator                 |
| Copyright (C) 2017 AIVL                                |
| Author: B. Endres (endres@systopia.de)                 |
|         E. Hommel (erik.hommel@civicoop.org)           |
+--------------------------------------------------------+
| License: AGPLv3, see LICENSE file                      |
+--------------------------------------------------------*/

define('COCOA_CREATE_YEAR',  'custom_87');
define('COCOA_CODE_INITIAL', 'custom_85');
define('COCOA_CODE_FOLLOW',  'custom_86');

/**
 * Cnfiguration for CRM_Fintrxn_Generator
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
class CRM_Fintrxn_Configuration {

  /**
   * check if a change to the given attributes could potentially trigger 
   * the 
   */
  public function isRelevant($changes) {
    return   in_array('campaign_id', $changes)
          || in_array('contribution_status_id', $changes)
          || in_array('amount', $changes)
          || in_array('', $changes)
  }

  /**
   * check if the given status counts as completed
   */
  public function isCompleted($contribution_status_id) {
    return $contribution_status_id == 1;
  }

  /** 
   * get the list of cocoa fields
   */
  public function getCocoaFieldList() {
    return COCOA_CREATE_YEAR . ',' . COCOA_CODE_INITIAL . ',' . COCOA_CODE_FOLLOW;
  }

  /**
   * calculate the right accounting code from the cocoa data
   */
  public function getCocoaValue($cocoa_data, $receive_date) {
    $year = substr($receive_date, 0, 4);
    if ($year == $cocoa_data[COCOA_CREATE_YEAR]) {
      return $cocoa_data[COCOA_CODE_INITIAL];
    } else {
      return $cocoa_data[COCOA_CODE_FOLLOW];
    }
  }
}