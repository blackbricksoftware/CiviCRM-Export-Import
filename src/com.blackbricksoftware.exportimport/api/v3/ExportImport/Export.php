<?php
use CRM_Exportimport_ExtensionUtil as E;

/**
 * Exportimport.Export API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_export_import_Export_spec(&$spec) {
  // $spec['magicword']['api.required'] = 1;
}

/**
 * Exportimport.Export API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_export_import_Export($params) {
  try {

    $entityImporter = new CRM_Exportimport_Csv_Export();
    $entityImporter->run();

    // Spec: civicrm_api3_create_success($values = 1, $params = array(), $entity = NULL, $action = NULL)
    return civicrm_api3_create_success($returnValues, $params, 'Exportimport', 'Export');

  } catch (Exception $e) {
    throw new API_Exception('Export error: '.$e->getMessage(), $e->getCode());
  }
}
