<?php 

/**
 * base class for doing all command line operations via civicrm
 * used by cli.php
 */
class CRM_Exportimport_Cli {
  // required values that must be passed
  // via the command line
  var $_required_arguments = array('action', 'entity');
  var $_additional_arguments = array();
  var $_entity = NULL;
  var $_action = NULL;
  var $_output = FALSE;
  var $_joblog = FALSE;
  var $_semicolon = FALSE;
  var $_config;

  // optional arguments
  var $_site = 'localhost';
  var $_user = NULL;
  var $_password = NULL;

  // all other arguments populate the parameters
  // array that is passed to civicrm_api
  var $_params = array('version' => 3);

  var $_errors = array();

  /**
   * @return bool
   */
  public function initialize() {
    if (!$this->_accessing_from_cli()) {
      return FALSE;
    }
    if (!$this->_parseOptions()) {
      return FALSE;
    }
    if (!$this->_bootstrap()) {
      return FALSE;
    }
    if (!$this->_validateOptions()) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Ensure function is being run from the cli.
   *
   * @return bool
   */
  public function _accessing_from_cli() {
    if (PHP_SAPI === 'cli') {
      return TRUE;
    }
    else {
      die("cli.php can only be run from command line.");
    }
  }

  /**
   * @return bool
   */
  public function callApi() {
    require_once 'api/api.php';

    CRM_Core_Config::setPermitCacheFlushMode(FALSE);
    //  CRM-9822 -'execute' action always goes thru Job api and always writes to log
    if ($this->_action != 'execute' && $this->_joblog) {
      require_once 'CRM/Core/JobManager.php';
      $facility = new CRM_Core_JobManager();
      $facility->setSingleRunParams($this->_entity, $this->_action, $this->_params, 'From Cli.php');
      $facility->executeJobByAction($this->_entity, $this->_action);
    }
    else {
      // CRM-9822 cli.php calls don't require site-key, so bypass site-key authentication
      $this->_params['auth'] = FALSE;
      $result = civicrm_api($this->_entity, $this->_action, $this->_params);
    }
    CRM_Core_Config::setPermitCacheFlushMode(TRUE);
    CRM_Contact_BAO_Contact_Utils::clearContactCaches();

    if (!empty($result['is_error'])) {
      $this->_log($result['error_message']);
      return FALSE;
    }
    elseif ($this->_output === 'json') {
      echo json_encode($result, defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0);
    }
    elseif ($this->_output) {
      print_r($result['values']);
    }
    return TRUE;
  }

  /**
   * @return bool
   */
  private function _parseOptions() {
    $args = $_SERVER['argv'];
    // remove the first argument, which is the name
    // of this script
    array_shift($args);

    while (list($k, $arg) = each($args)) {
      // sanitize all user input
      $arg = $this->_sanitize($arg);

      // if we're not parsing an option signifier
      // continue to the next one
      if (!preg_match('/^-/', $arg)) {
        continue;
      }

      // find the value of this arg
      if (preg_match('/=/', $arg)) {
        $parts = explode('=', $arg);
        $arg = $parts[0];
        $value = $parts[1];
      }
      else {
        if (isset($args[$k + 1])) {
          $next_arg = $this->_sanitize($args[$k + 1]);
          // if the next argument is not another option
          // it's the value for this argument
          if (!preg_match('/^-/', $next_arg)) {
            $value = $next_arg;
          }
        }
      }

      // parse the special args first
      if ($arg == '--ieentity') {
        $this->_entity = $value;
      }
      elseif ($arg == '--ieaction') {
        $this->_action = $value;
      }
      elseif ($arg == '-s' || $arg == '--site') {
        $this->_site = $value;
      }
      elseif ($arg == '-u' || $arg == '--user') {
        $this->_user = $value;
      }
      elseif ($arg == '-p' || $arg == '--password') {
        $this->_password = $value;
      }
      elseif ($arg == '-o' || $arg == '--output') {
        $this->_output = TRUE;
      }
      elseif ($arg == '-J' || $arg == '--json') {
        $this->_output = 'json';
      }
      elseif ($arg == '-j' || $arg == '--joblog') {
        $this->_joblog = TRUE;
      }
      elseif ($arg == '-sem' || $arg == '--semicolon') {
        $this->_semicolon = TRUE;
      }
      else {
        foreach ($this->_additional_arguments as $short => $long) {
          if ($arg == '-' . $short || $arg == '--' . $long) {
            $property = '_' . $long;
            $this->$property = $value;
            continue;
          }
        }
        // all other arguments are parameters
        $key = ltrim($arg, '--');
        $this->_params[$key] = isset($value) ? $value : NULL;
      }
    }
    return TRUE;
  }

  /**
   * @return bool
   */
  private function _bootstrap() {
    // so the configuration works with php-cli
    $_SERVER['PHP_SELF'] = "/index.php";
    $_SERVER['HTTP_HOST'] = $this->_site;
    $_SERVER['REMOTE_ADDR'] = "127.0.0.1";
    $_SERVER['SERVER_SOFTWARE'] = NULL;
    $_SERVER['REQUEST_METHOD'] = 'GET';

    // SCRIPT_FILENAME needed by CRM_Utils_System::cmsRootPath
    $_SERVER['SCRIPT_FILENAME'] = __FILE__;

    // CRM-8917 - check if script name starts with /, if not - prepend it.
    if (ord($_SERVER['SCRIPT_NAME']) != 47) {
      $_SERVER['SCRIPT_NAME'] = '/' . $_SERVER['SCRIPT_NAME'];
    }

    $civicrm_root = dirname(__DIR__);
    chdir($civicrm_root);
    if (getenv('CIVICRM_SETTINGS')) {
      require_once getenv('CIVICRM_SETTINGS');
    }
    else {
      require_once 'civicrm.config.php';
    }
    // autoload
    if (!class_exists('CRM_Core_ClassLoader')) {
      require_once $civicrm_root . '/CRM/Core/ClassLoader.php';
    }
    CRM_Core_ClassLoader::singleton()->register();

    $this->_config = CRM_Core_Config::singleton();

    // HTTP_HOST will be 'localhost' unless overwritten with the -s argument.
    // Now we have a Config object, we can set it from the Base URL.
    if ($_SERVER['HTTP_HOST'] == 'localhost') {
      $_SERVER['HTTP_HOST'] = preg_replace(
        '!^https?://([^/]+)/$!i',
        '$1',
        $this->_config->userFrameworkBaseURL);
    }

    $class = 'CRM_Utils_System_' . $this->_config->userFramework;

    $cms = new $class();
    if (!CRM_Utils_System::loadBootstrap(array(), FALSE, FALSE, $civicrm_root)) {
      $this->_log(ts("Failed to bootstrap CMS"));
      return FALSE;
    }

    if (strtolower($this->_entity) == 'job') {
      if (!$this->_user) {
        $this->_log(ts("Jobs called from cli.php require valid user as parameter"));
        return FALSE;
      }
    }

    if (!empty($this->_user)) {
      if (!CRM_Utils_System::authenticateScript(TRUE, $this->_user, $this->_password, TRUE, FALSE, FALSE)) {
        $this->_log(ts("Failed to login as %1. Wrong username or password.", array('1' => $this->_user)));
        return FALSE;
      }
      if (($this->_config->userFramework == 'Joomla' && !$cms->loadUser($this->_user, $this->_password)) || !$cms->loadUser($this->_user)) {
        $this->_log(ts("Failed to login as %1", array('1' => $this->_user)));
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * @return bool
   */
  private function _validateOptions() {
    $required = $this->_required_arguments;
    while (list(, $var) = each($required)) {
      $index = '_' . $var;
      if (empty($this->$index)) {
        $missing_arg = '--' . $var;
        $this->_log(ts("The %1 argument is required", array(1 => $missing_arg)));
        $this->_log($this->_getUsage());
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * @param $value
   *
   * @return string
   */
  private function _sanitize($value) {
    // restrict user input - we should not be needing anything
    // other than normal alpha numeric plus - and _.
    return trim(preg_replace('#^[^a-zA-Z0-9\-_=/]$#', '', $value));
  }

  /**
   * @return string
   */
  private function _getUsage() {
    $out = "Usage: cli.php -e entity -a action [-u user] [-s site] [--output|--json] [PARAMS]\n";
    $out .= "  entity is the name of the entity, e.g. Contact, Event, etc.\n";
    $out .= "  action is the name of the action e.g. Get, Create, etc.\n";
    $out .= "  user is an optional username to run the script as\n";
    $out .= "  site is the domain name of the web site (for Drupal multi site installs)\n";
    $out .= "  --output will pretty print the result from the api call\n";
    $out .= "  --json will print the result from the api call as JSON\n";
    $out .= "  PARAMS is one or more --param=value combinations to pass to the api\n";
    return ts($out);
  }

  /**
   * @param $error
   */
  private function _log($error) {
    // fixme, this should call some CRM_Core_Error:: function
    // that properly logs
    print "$error\n";
  }

}