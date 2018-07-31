<?php

class CRM_Exportimport_Csv_Import extends CRM_Exportimport_Csv {
  /**
   * @param array $params
   */
  public function processline($rawparams) {
    // var_dump($rawparams);
    $params = $this->parseLine($rawparams);
    $result = civicrm_api($this->_entity, 'Create', $params);
    if ($result['is_error']) {
      echo "\nERROR line " . $this->row . ": " . $result['error_message'] . "\n";
    }
    else {
      echo "\nline " . $this->row . ": created " . $this->_entity . " id: " . $result['id'] . "\n";
    }
  }

}