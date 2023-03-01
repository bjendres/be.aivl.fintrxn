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


  /**
   * Added more financial campaign fields
   *
   * @see https://issues.civicoop.org/issues/10015
   *
   * @return boolean
   *    TRUE on success
   *
   * @throws Exception
   *    if something goes wrong
   */
  public function upgrade_0120()
  {
    $this->ctx->log->info('Adding more campaign fields.');

    // run install to update fields
    $configItems = new CRM_Fintrxn_ConfigItems_ConfigItems();
    $configItems->install();

    // make sure logging is updated as well
    $logging = new CRM_Logging_Schema();
    $logging->fixSchemaDifferences();

    return true;
  }
}
