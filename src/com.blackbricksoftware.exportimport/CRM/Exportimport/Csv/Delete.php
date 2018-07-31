<?php

class CRM_Exportimport_Csv_Delete extends CRM_Exportimport_Csv {
  use CRM_Exportimport_Helper;
  /**
   * @param array $params
   */
  public function processline($rawparams) {

    $params = $this->parseLine($rawparams);
    $result = civicrm_api($this->_entity, 'Delete', $params);
    if ($result['is_error']) {
      echo "\nERROR line " . $this->row . ": " . $result['error_message'] . "\n";
    }
    else {
      echo "\nline " . $this->row . ": deleted\n";
    }
  }

}