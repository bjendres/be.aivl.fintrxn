<?php
// This file declares a managed database record of type "Job".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 => 
  array (
    'name' => 'aivl_fintrxn_fixcocoa',
    'entity' => 'Job',
    'params' => 
    array (
      'version' => 3,
      'name' => 'AIVL Fix Default Account Financial Transactions',
      'description' => 'Fix Accounts for COCOA Financial Transactions (AIVL specific)',
      'run_frequency' => 'Daily',
      'api_entity' => 'FinancialTrxn',
      'api_action' => 'Fixcocoa',
      'parameters' => '',
    ),
  ),
);
