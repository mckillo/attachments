<?php

/**
 * Attachment model definition
 *
 * @package Attachments
 * @subpackage Attachments_Component
 *
 * @copyright Copyright (C) 2007-2025 Jonathan M. Cameron, All Rights Reserved
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @link https://github.com/jmcameron/attachments
 * @author Jonathan M. Cameron
 */

namespace JMCameron\Component\Attachments\Site\Model;

use JMCameron\Plugin\AttachmentsPluginFramework\AttachmentsPluginManager;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


/**
 * Attachment Model
 *
 * @package Attachments
 */
class AttachmentModel extends BaseDatabaseModel
{
    /**
     * Attachment ID
     */
    protected $id = null;


    /**
     * Attachment object/data
     *
     */
    protected $attachment = null;


    /**
     * Constructor, build object and determines its ID
     */
    public function __construct()
    {
        parent::__construct();

        // Get the cid array from the request
        $input = Factory::getApplication()->getInput();
        $cid = $input->get('cid', 'DEFAULT', 'array');

        if ($cid) {
            // Accept only the first id from the array
            $id = $cid[0];
        } else {
            $id = $input->getInt('id', 0);
        }

        $this->setId($id);
    }


    /**
     * Reset the model ID and data
     */
    public function setId($id = 0)
    {
        $this->id = $id;
        $this->attachment = null;
    }


    /**
     * Load the attachment data
     *
     * @return true if loaded successfully
     */
    private function loadAttachment()
    {
        if ($this->id == 0) {
            return false;
        }

        if (empty($this->attachment)) {
            $user   = Factory::getApplication()->getIdentity();
            $user_levels = $user->getAuthorisedViewLevels();

            // If the user is not logged in, add extra view levels (if configured)
            if ($user->get('username') == '') {
                // Get the component parameters
                $params = ComponentHelper::getParams('com_attachments');

                // Add the specified access levels
                $guest_levels = $params->get('show_guest_access_levels', array('1'));
                if (is_array($guest_levels)) {
                    foreach ($guest_levels as $glevel) {
                        $user_levels[] = $glevel;
                    }
                } else {
                    $user_levels[] = $guest_levels;
                }
            }
            $user_levels = implode(',', array_unique($user_levels));

            // Load the attachment data and make sure this user has access
            /** @var \Joomla\Database\DatabaseDriver $db */
            $db     = Factory::getContainer()->get('DatabaseDriver');
            $query  = $db->getQuery(true);
            $query->select('a.*, a.id as id');
            $query->from('#__attachments as a');
            $query->where('a.id = ' . (int)$this->id);
            if (!$user->authorise('core.admin')) {
                $query->where('a.access in (' . $user_levels . ')');
            }
            $db->setQuery($query, 0, 1);
            $this->attachment = $db->loadObject();
            if (empty($this->attachment)) {
                return false;
            }

            // Retrieve the information about the parent
            $parent_type = $this->attachment->parent_type;
            $parent_entity = $this->attachment->parent_entity;
            PluginHelper::importPlugin('attachments');
            $apm = AttachmentsPluginManager::getAttachmentsPluginManager();
            if (!$apm->attachmentsPluginInstalled($parent_type)) {
                $this->attachment->parent_type = false;
                return false;
            }
            $parent = $apm->getAttachmentsPlugin($parent_type);

            // Set up the parent info
            $parent_id = $this->attachment->parent_id;
            $this->attachment->parent_title = $parent->getTitle($parent_id, $parent_entity);
            $this->attachment->parent_published =
                $parent->isParentPublished($parent_id, $parent_entity);
        }

        return true;
    }


    /**
     * Create a new Attachment object
     */
    private function initAttachment()
    {
        echo "_initData not implemented yet <br />";
        return null;
    }


    /**
     * Get the data
     *
     * @return object
     */
    public function getAttachment()
    {
        if (!$this->loadAttachment()) {
            // If the load fails, create a new one
            $this->initAttachment();
        }

        return $this->attachment;
    }


    /**
     * Save the attachment
     *
     * @param object $data mixed object or associative array of data to save
     *
     * @return Boolean true on success
     */
    public function save($data)
    {
        // Get the table
        $table = $this->getTable('Attachments');

        // Save the data
        if (!$table->save($data)) {
            // An error occured, save the model error message
            $this->setError($table->getError());
            return false;
        }

        return true;
    }


    /**
     * Increment the download count
     *
     * @param int $attachment_id the attachment ID
     */
    public function incrementDownloadCount()
    {
        // Update the download count
        /** @var \Joomla\Database\DatabaseDriver $db */
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->update('#__attachments')->set('download_count = (download_count + 1)');
        $query->where('id = ' . (int)$this->id);
        $db->setQuery($query);
        try {
            $db->execute();
        } catch (\RuntimeException $e) {
            $errmsg = $e->getMessage() . ' (ERR 49)';
            throw new \Exception($errmsg, 500);
        }
    }
}
