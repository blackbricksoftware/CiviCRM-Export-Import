<?php

class CRM_Exportimport_Csv_Export extends CRM_Exportimport_Csv {
  /**
   * Run the script.
   */
  public function run() {
    if ($this->_semicolon) {
      $this->separator = ';';
    }

    $out = fopen("php://output", 'w');
    fputcsv($out, $this->columns, $this->separator, '"');

    $this->row = 1;
    $params = $this->parseLine($this->_params);
    $result = civicrm_api($this->_entity, 'Get', $params);
    $first = TRUE;
    foreach ($result['values'] as $row) {
      if ($first) {
        $columns = array_keys($row);
        fputcsv($out, $columns, $this->separator, '"');
        $first = FALSE;
      }
      //handle values returned as arrays (i.e. custom fields that allow multiple selections) by inserting a control character
      foreach ($row as &$field) {
        if (is_array($field)) {
          //convert to string
          $field = implode($field, CRM_Core_DAO::VALUE_SEPARATOR) . CRM_Core_DAO::VALUE_SEPARATOR;
        }
      }
      fputcsv($out, $row, $this->separator, '"');
    }
    fclose($out);
    echo "\n";
  }

}