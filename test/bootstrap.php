<?php

/**
 * Prepares a minimalist framework for unit testing.
 *
 * Adapted from [joomla 1.6]/tests/unit/bootstrap
 *
 * @package Attachments_test
 * @subpackage Attachments_config
 *
 * Joomla is assumed to include the /unittest/ directory.
 * eg, /path/to/joomla/unittest/
 */


// Load the custom initialisation file if it exists.
if (file_exists('config.php')) {
    include 'config.php';
}

// Define expected Joomla constants.

define('DS', DIRECTORY_SEPARATOR);



if (!defined('JPATH_BASE')) {
    define('JPATH_BASE', '/var/www/test/joomla25');
}

if (!defined('JPATH_JOOMLA_TESTS')) {
    define('JPATH_JOOMLA_TESTS', JPATH_BASE . '/tests/unit');
}


if (!defined('JPATH_TESTS')) {
    define('JPATH_TESTS', dirname(__FILE__));
}

// Fix magic quotes.
@ini_set('magic_quotes_runtime', 0);

// Maximise error reporting.

@ini_set('zend.ze1_compatibility_mode', '0');
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Include the base test cases.
require_once JPATH_JOOMLA_TESTS . '/JoomlaTestCase.php';
require_once JPATH_JOOMLA_TESTS . '/JoomlaDatabaseTestCase.php';

// Include relative constants, JLoader and the jimport and jexit functions.
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_LIBRARIES . '/import.php';

// Import some other things so the tests will run
jimport('joomla.utilities.string');
jimport('joomla.registry.registry');
jimport('joomla.log.log');
jimport('joomla.session.session');

require_once JPATH_BASE . '/libraries/joomla/plugin/plugin.php';
require_once JPATH_BASE . '/libraries/joomla/plugin/helper.php';

require_once JPATH_BASE . '/libraries/joomla/filter/input.php';

require_once JPATH_BASE . '/libraries/joomla/session/session.php';
require_once JPATH_BASE . '/libraries/joomla/session/storage.php';

require_once JPATH_BASE . '/libraries/joomla/environment/request.php';

require_once JPATH_BASE . '/libraries/joomla/user/user.php';

require_once JPATH_BASE . '/libraries/joomla/cache/cache.php';
require_once JPATH_BASE . '/libraries/joomla/cache/controller.php';

require_once JPATH_BASE . '/libraries/joomla/filesystem/path.php';

require_once JPATH_BASE . '/libraries/joomla/access/access.php';

// Set error handling.
JError::setErrorHandling(E_NOTICE, 'ignore');
JError::setErrorHandling(E_WARNING, 'ignore');
JError::setErrorHandling(E_ERROR, 'ignore');
