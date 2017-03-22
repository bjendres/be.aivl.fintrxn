<?php

/**
 * Collection of upgrade steps.
 */
class CRM_Fintrxn_Upgrader extends CRM_Fintrxn_Upgrader_Base {

  /**
   * Create configuration items on install
   *
   */
  public function install() {
    $configItems = new CRM_Fintrxn_ConfigItems_ConfigItems();
    $configItems->install();
  }

  /**
   * Delete configuration items on uninstall
   *
   */
  public function uninstall() {
    $configItems = new CRM_Fintrxn_ConfigItems_ConfigItems();
    $configItems->uninstall();
  }

}
