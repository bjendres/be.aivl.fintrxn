<?php
/*-------------------------------------------------------+
| Custom Financial Transaction Generator                 |
| Copyright (C) 2017 AIVL                                |
| Author: B. Endres (endres@systopia.de)                 |
|         E. Hommel (erik.hommel@civicoop.org)           |
+--------------------------------------------------------+
| License: AGPLv3, see LICENSE file                      |
+--------------------------------------------------------*/

require_once 'fintrxn.civix.php';


/**
 * inform the generator of an upcoming change
 */
function fintrxn_civicrm_pre($op, $objectName, $id, &$params) {
  if ($objectName == 'Contribution') {
    CRM_Fintrxn_Generator::create($op, $params, $id);
  }
}

/**
 * inform the generator of an performed change
 */
function fintrxn_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  if ($objectName == 'Contribution') {
    CRM_Fintrxn_Generator::generate($op, $objectId, $objectRef);
  }
}

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function fintrxn_civicrm_config(&$config) {
  _fintrxn_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @param array $files
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function fintrxn_civicrm_xmlMenu(&$files) {
  _fintrxn_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function fintrxn_civicrm_install() {
  _fintrxn_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function fintrxn_civicrm_uninstall() {
  _fintrxn_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function fintrxn_civicrm_enable() {
  _fintrxn_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function fintrxn_civicrm_disable() {
  _fintrxn_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed
 *   Based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function fintrxn_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _fintrxn_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function fintrxn_civicrm_managed(&$entities) {
  _fintrxn_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * @param array $caseTypes
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function fintrxn_civicrm_caseTypes(&$caseTypes) {
  _fintrxn_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function fintrxn_civicrm_angularModules(&$angularModules) {
_fintrxn_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function fintrxn_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _fintrxn_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

