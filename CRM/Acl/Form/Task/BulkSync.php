<?php

class CRM_Acl_Form_Task_BulkSync extends CRM_Contact_Form_Task {

  public function buildQuickForm() {
    $this->addDefaultButtons('Sync geselecteerde contacten', 'done');
  }

  public function postProcess() {
    $contactIds = $this->_contactIds;
    $count = 0;

    if (!empty($contactIds)) {
      foreach ($contactIds as $cid) {
        if (function_exists('acl_civicrm_configure')) {
          acl_civicrm_configure($cid);
          $count++;
        }
      }
    }

    CRM_Core_Session::setStatus(
      "Succesvol $count contacten gesynchroniseerd.", 
      "Bulk Sync Voltooid", 
      "success"
    );
  }
}