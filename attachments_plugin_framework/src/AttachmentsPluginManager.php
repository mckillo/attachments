<?php

/**
 * Manager for plugins for Attachments
 *
 * @package     Attachments
 * @subpackage  Attachments_Plugin_Framework
 *
 * @author      Jonathan M. Cameron <jmcameron@jmcameron.net>
 * @copyright   Copyright (C) 2009-2025 Jonathan M. Cameron, All Rights Reserved
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @link https://github.com/jmcameron/attachments
 */

namespace JMCameron\Plugin\AttachmentsPluginFramework;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\String\Normalise;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


/**
 * The class for the manager for attachments plugins
 *
 * AttachmentsPluginManager manages plugins for Attachments.
 * It knows how to create handlers for plugins for all
 * supported extensions.
 *
 * @package  Attachments
 * @since    3.0
 */
class AttachmentsPluginManager
{
    /** A list of known parent_type names
     */
    private $parent_types = array();

    /** An array of info about the installed entities.
     *  Each item in the array is an associative array with the following entries:
     *    'id' - the unique name of entity as stored in the jos_attachments table (all lower case)
     *    'name' - the translated name of the entity
     *    'name_plural' - the translated plural name of the entity
     *    'parent_type' - the parent type for the entity
     */
    private $entity_info = array();

    /** An associative array of attachment plugins
     */
    private $plugin = array();

    /** Flag indicating if the language file haas been loaded
     */
    private $language_loaded = false;

    private static ?AttachmentsPluginManager $instance = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->loadLanguage();
    }

    /**
     * Get the singleton plugin manager for attachments
     *
     * @return AttachmentsPluginManager the plugin manager singleton object
     */
    public static function &getAttachmentsPluginManager(): AttachmentsPluginManager
    {
        if (!is_object(static::$instance)) {
            static::$instance = new AttachmentsPluginManager();
        }

        return static::$instance;
    }

    /**
     * See if a particular plugin is installed (available)
     *
     * @param   string  $parent_type  the name of the parent extension (eg, com_content)
     *
     * @return Boolean true if the plugin is available (false if not)
     */
    public function attachmentsPluginInstalled($parent_type)
    {
        return in_array($parent_type, $this->parent_types);
    }

    /**
     * Check to see if an attachments plugin is enabled
     *
     * @param   string  $parent_type  the name of the parent extension (eg, com_content)
     *
     * @return true if the attachment is enabled (false if disabled)
     */
    public function attachmentsPluginEnabled($parent_type)
    {
        // Extract the component name (the part after 'com_')
        if (strpos($parent_type, 'com_') == 0) {
            $name = substr($parent_type, 4);

            return PluginHelper::isEnabled('attachments', "attachments_for_$name");
        }

        // If the parent type does not conform to the naming convention, assume it is not enabled
        return false;
    }

    /**
     * Add a new parent type
     *
     * @param   string  $new_parent_type  the name of the new parent extension (eg, com_content)
     *
     * @return nothing
     */
    public function addParentType($new_parent_type)
    {
        if (in_array($new_parent_type, $this->parent_types)) {
            return;
        } else {
            $this->parent_types[] = $new_parent_type;
        }
    }

    /**
     * Return the list of installed parent types
     *
     * @return array an array of the installed parent types
     */
    public function &getInstalledParentTypes()
    {
        return $this->parent_types;
    }

    /**
     * Return the list of installed parent entities
     *
     * @return array of entity info (see var $_entity_info definition above)
     */
    public function &getInstalledEntityInfo()
    {
        if (count($this->entity_info) == 0) {
            // Add an option for each entity
            PluginHelper::importPlugin('attachments');
            $apm = $this->getAttachmentsPluginManager();

            // Process all the parent types
            foreach ($this->parent_types as $parent_type) {
                $parent   = $apm->getAttachmentsPlugin($parent_type);
                $entities = $parent->getEntities();

                // Process each entity for this parent type
                foreach ($entities as $entity) {
                    $centity             = $parent->getCanonicalEntityId($entity);
                    $this->entity_info[] = array(
                        'id' => $centity,
                        'name' => Text::_('ATTACH_' . $centity),
                        'name_plural' => Text::_('ATTACH_' . $centity . 's'),
                        'parent_type' => $parent_type
                    );
                }
            }
        }

        return $this->entity_info;
    }

    /**
     * Load the language for this parent type
     *
     * @return true of the language was loaded successfully
     */
    public function loadLanguage()
    {
        if ($this->language_loaded) {
            return true;
        }

        $lang = Factory::getApplication()->getLanguage();

        $this->language_loaded = $lang->load(
            'plg_attachments_plugin_framework',
            JPATH_PLUGINS . '/attachments/framework'
        );

        return $this->language_loaded;
    }

    /**
     * Get the plugin (attachments parent handler object)
     *
     * @param   string  $parent_type  the name of the parent extension (eg, com_content)
     *
     * @return the parent handler object
     */
    public function getAttachmentsPlugin($parent_type)
    {
        // Make sure the parent type is valid
        if (!in_array($parent_type, $this->parent_types)) {
            $errmsg = Text::sprintf('ATTACH_ERROR_UNKNOWN_PARENT_TYPE_S', $parent_type) . ' (ERR 303)';
            throw new \Exception($errmsg, 406);
        }

        // Instantiate the plugin object, if we have not already done it
        if (!array_key_exists($parent_type, $this->plugin)) {
            $this->installPlugin($parent_type);
        }

        return $this->plugin[$parent_type];
    }

    /**
     * Install the specified plugin
     *
     * @param   string  $parent_type  the name of the parent extension (eg, com_content)
     *
     * @return true if successful (false if not)
     */
    private function installPlugin($parent_type)
    {
        // Do nothing if the plugin is already installed
        if (array_key_exists($parent_type, $this->plugin)) {
            return true;
        }

        $classNamePostfix = str_replace('com_', 'for_', $parent_type);
        $className = 'Attachments' . Normalise::toCamelCase($classNamePostfix);
        $namespace = "JMCameron\\Plugin\\Attachments\\$className\\Extension\\";
        if (!class_exists($namespace . $className)) {
            // Try the old style class name
            $className = 'PlgAttachmentsAttachments_' . $classNamePostfix;
            $namespace = '';

            if (!class_exists($className)) {
                // If the class does not exist, we cannot install the plugin
                $errmsg = Text::sprintf('ATTACH_ERROR_UNKNOWN_PARENT_TYPE_S', $parent_type) . ' (ERR 305)';
                Log::add($errmsg, Log::ERROR, 'jerror');
                throw new \Exception($errmsg, 406);
            }
        }

        // Install the plugin
        $dispatcher                 = Factory::getApplication()->getDispatcher();
        $full_class_name = $namespace . $className;
        $this->plugin[$parent_type] = new $full_class_name($dispatcher);

        return is_object($this->plugin[$parent_type]);
    }
}
