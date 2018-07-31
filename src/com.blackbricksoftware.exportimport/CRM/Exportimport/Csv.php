<?php

/**
 * base class used by both civicrm_cli_csv_import
 * and civicrm_cli_csv_deleter to add or delete
 * records based on those found in a csv file
 * passed to the script.
 */
class CRM_Exportimport_Csv extends CRM_Exportimport_Cli {
  var $header;
  var $separator = ',';

  /**
   */
  public function __construct() {
    $this->_required_arguments = array('entity', 'file');
    $this->_additional_arguments = array('f' => 'file');
    parent::initialize();
  }

  /**
   * Run CLI function.
   */
  public function run() {
    $this->row = 1;
    $handle = fopen($this->_file, "r");

    if (!$handle) {
      die("Could not open file: " . $this->_file . ". Please provide an absolute path.\n");
    }

    //header
    $header = fgetcsv($handle, 0, $this->separator);
    // In case fgetcsv couldn't parse the header and dumped the whole line in 1 array element
    // Try a different separator char
    if (count($header) == 1) {
      $this->separator = ";";
      rewind($handle);
      $header = fgetcsv($handle, 0, $this->separator);
    }

    $this->header = $header;
    while (($data = fgetcsv($handle, 0, $this->separator)) !== FALSE) {
      // skip blank lines
      if (count($data) == 1 && is_null($data[0])) {
        continue;
      }
      $this->row++;
      if ($this->row % 1000 == 0) {
        // Reset PEAR_DB_DATAOBJECT cache to prevent memory leak
        CRM_Core_DAO::freeResult();
      }
      $params = $this->convertLine($data);
      $this->processLine($params);
    }
    fclose($handle);
  }

  /* return a params as expected */
  /**
   * @param $data
   *
   * @return array
   */
  public function convertLine($data) {
    $params = array();
    foreach ($this->header as $i => $field) {
      //split any multiselect data, denoted with CRM_Core_DAO::VALUE_SEPARATOR
      if (strpos($data[$i], CRM_Core_DAO::VALUE_SEPARATOR) !== FALSE) {
        $data[$i] = explode(CRM_Core_DAO::VALUE_SEPARATOR, $data[$i]);
        $data[$i] = array_combine($data[$i], $data[$i]);
      }
      $params[$field] = $data[$i];
    }
    $params['version'] = 3;
    return $params;
  }

  protected function parseLine($line) {

    if (empty($line)) return $line;

    $newline = [];
    foreach ($line as $key => $val) {

      if (substr($key,0,8) !== 'options.') {
        $newline[$key] = $val;
        continue;
      }

      if (empty($newline['options'])) $newline['options'] = [];

      $option = substr($key,8);
      if ($option === 'match') {
        $vals = explode(',', $val);
        $newline['options']['match'] = $vals;
      } else {
        $newline['options'][$option] = $val;
      }

    }

    return $newline;
  }

}