<?php

/**
 * Attachment list model definition
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
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


/**
 * Attachment List Model for all attachments belonging to one
 * content article (or other content-related entity)
 *
 * @package Attachments
 */
class AttachmentsModel extends BaseDatabaseModel
{
    /**
     * ID of parent of the list of attachments
     */
    protected $parent_id = null;

    /**
     * type of parent
     */
    protected $parent_type = null;

    /**
     * type of parent entity (each parent_type can support several)
     */
    protected $parent_entity = null;

    /**
     * Parent class object (an Attachments extension plugin object)
     */
    protected $parent = null;

    /**
     * Parent title
     */
    protected $parent_title = null;

    /**
     * Parent entity name
     */
    protected $parent_entity_name = null;

    /**
     * Whether some of the attachments should be visible to the user
     */
    protected $some_visible = null;

    /**
     * Whether some of the attachments should be modifiable to the user
     */
    protected $some_modifiable = null;

    /**
     * The desired sort order
     */
    protected $sort_order;


    /**
     * The list of attachments for the specified article/content entity
     */
    protected $list = null;

    /**
     * Number of attachments
     *
     * NOTE: After the list of attachments has been retrieved, if it is empty, this is set to zero.
     *       But _list remains null.   You can use this to check to see if the list has been loaded.
     */
    protected $num_attachments = null;

    /**
     * Set the parent id (and optionally the parent type)
     *
     * NOTE: If the $id is null, it will get both $id and $parent_id from JRequest
     *
     * @param int $id the id of the parent
     * @param string $parent_type the parent type (defaults to 'com_content')
     * @param string $parent_entity the parent entity (defaults to 'default')
     */
    public function setParentId($id = null, $parent_type = 'com_content', $parent_entity = 'default')
    {
        // Get the parent id and type
        if (is_numeric($id)) {
            $parent_id = (int)$id;
        } else {
            $input = Factory::getApplication()->getInput();
            // It was not an argument, so get parent id and type from the JRequest
            $parent_id   = $input->getInt('article_id', null);

            // Deal with special case of editing from the front end
            if ($parent_id == null) {
                if (
                    ($input->getCmd('view') == 'article') &&
                     ($input->getCmd('task') == 'edit' )
                ) {
                    $parent_id = $input->getInt('id', null);
                }
            }

            // If article_id is not specified, get the general parent id/type
            if ($parent_id == null) {
                $parent_id = $input->getInt('parent_id', null);
                if ($parent_id == null) {
                    $errmsg = Text::_('ATTACH_ERROR_NO_PARENT_ID_SPECIFIED') . ' (ERR 50)';
                    throw new \Exception($errmsg, 500);
                }
            }
        }

        // Reset instance variables
        $this->parent_id = $parent_id;
        $this->parent_type = $parent_type;
        $this->parent_entity = $parent_entity;

        $this->parent = null;
        $this->parent_class = null;
        $this->parent_title = null;
        $this->parent_entity_name = null;

        $this->list = null;
        $this->sort_order = null;
        $this->some_visible = null;
        $this->some_modifiable = null;
        $this->num_attachments = null;
    }



    /**
     * Get the parent id
     *
     * @return the parent id
     */
    public function getParentId()
    {
        if ($this->parent_id === null) {
            $errmsg = Text::_('ATTACH_ERROR_NO_PARENT_ID_SPECIFIED') . ' (ERR 51)';
            throw new \Exception($errmsg, 500);
        }
        return $this->parent_id;
    }


    /**
     * Get the parent type
     *
     * @return the parent type
     */
    public function getParentType()
    {
        if ($this->parent_type == null) {
            $errmsg = Text::_('ATTACH_ERROR_NO_PARENT_TYPE_SPECIFIED') . ' (ERR 52)';
            throw new \Exception($errmsg, 500);
        }
        return $this->parent_type;
    }


    /**
     * Get the parent entity
     *
     * @return the parent entity
     */
    public function getParentEntity()
    {
        if ($this->parent_entity == null) {
            $errmsg = Text::_('ATTACH_ERROR_NO_PARENT_ENTITY_SPECIFIED') . ' (ERR 53)';
            throw new \Exception($errmsg, 500);
        }

        // Make sure we have a good parent_entity value
        if ($this->parent_entity == 'default') {
            $parent = $this->getParentClass();
            $this->parent_entity = $parent->getDefaultEntity();
        }

        return $this->parent_entity;
    }


    /**
     * Get the parent class object
     *
     * @return the parent class object
     */
    public function &getParentClass()
    {
        if ($this->parent_type == null) {
            $errmsg = Text::_('ATTACH_ERROR_NO_PARENT_TYPE_SPECIFIED') . ' (ERR 54)';
            throw new \Exception($errmsg, 500);
        }

        if ($this->parent_class == null) {
            // Get the parent handler
            PluginHelper::importPlugin('attachments');
            $apm = AttachmentsPluginManager::getAttachmentsPluginManager();
            if (!$apm->attachmentsPluginInstalled($this->parent_type)) {
                $errmsg = Text::sprintf('ATTACH_ERROR_INVALID_PARENT_TYPE_S', $this->parent_type) . ' (ERR 55)';
                throw new \Exception($errmsg, 500);
            }
            $this->parent_class = $apm->getAttachmentsPlugin($this->parent_type);
        }

        return $this->parent_class;
    }


    /**
     * Get the title for the parent
     *
     * @return the title for the parent
     */
    public function getParentTitle()
    {
        // Get the title if we have not done it before
        if ($this->parent_title == null) {
            $parent = $this->getParentClass();

            // Make sure we have an article ID
            if ($this->parent_id === null) {
                $errmsg = Text::_('ATTACH_ERROR_UNKNOWN_PARENT_ID') . ' (ERR 56)';
                throw new \Exception($errmsg, 500);
            }

            $this->parent_title = $parent->getTitle($this->parent_id, $this->parent_entity);
        }

        return $this->parent_title;
    }


    /**
     * Get the EntityName for the parent
     *
     * @return the entity name for the parent
     */
    public function getParentEntityName()
    {
        // Get the parent entity name if we have not done it before
        if ($this->parent_entity_name == null) {
            // Make sure we have an article ID
            if ($this->parent_id === null) {
                $errmsg = Text::_('ATTACH_ERROR_NO_PARENT_ID_SPECIFIED') . ' (ERR 57)';
                throw new \Exception($errmsg, 500);
            }

            $this->parent_entity_name = Text::_('ATTACH_' . $this->getParentEntity());
        }

        return $this->parent_entity_name;
    }


    /**
     * Set the sort order (do this before doing getAttachmentsList)
     *
     * @param string $new_sort_order name of the new sort order
     */
    public function setSortOrder($new_sort_order)
    {
        if ($new_sort_order == 'filename') {
            $order_by = 'filename';
        } elseif ($new_sort_order == 'filename_desc') {
            $order_by = 'filename DESC';
        } elseif ($new_sort_order == 'file_size') {
            $order_by = 'file_size';
        } elseif ($new_sort_order == 'file_size_desc') {
            $order_by = 'file_size DESC';
        } elseif ($new_sort_order == 'description') {
            $order_by = 'description';
        } elseif ($new_sort_order == 'description_desc') {
            $order_by = 'description DESC';
        } elseif ($new_sort_order == 'display_name') {
            $order_by = 'display_name, filename';
        } elseif ($new_sort_order == 'display_name_desc') {
            $order_by = 'display_name DESC, filename';
        } elseif ($new_sort_order == 'created') {
            $order_by = 'created';
        } elseif ($new_sort_order == 'created_desc') {
            $order_by = 'created DESC';
        } elseif ($new_sort_order == 'modified') {
            $order_by = 'modified';
        } elseif ($new_sort_order == 'modified_desc') {
            $order_by = 'modified DESC';
        } elseif ($new_sort_order == 'user_field_1') {
            $order_by = 'user_field_1';
        } elseif ($new_sort_order == 'user_field_1_desc') {
            $order_by = 'user_field_1 DESC';
        } elseif ($new_sort_order == 'user_field_2') {
            $order_by = 'user_field_2';
        } elseif ($new_sort_order == 'user_field_2_desc') {
            $order_by = 'user_field_2 DESC';
        } elseif ($new_sort_order == 'user_field_3') {
            $order_by = 'user_field_3';
        } elseif ($new_sort_order == 'user_field_3_desc') {
            $order_by = 'user_field_3 DESC';
        } elseif ($new_sort_order == 'id') {
            $order_by = 'id';
        } else {
            $order_by = 'filename';
        }

        $this->sort_order = $order_by;
    }



    /**
     * Get or build the list of attachments
     *
     * @return the list of attachments for this parent
     */
    public function &getAttachmentsList($attachmentid = null)
    {
        // Just return it if it has already been created
        if ($this->list != null) {
            return $this->list;
        }

        // Get the component parameters
        $params = ComponentHelper::getParams('com_attachments');

        // Create the list

        // Get the parent id and type
        $parent_id     = $this->getParentId();
        $parent_type   = $this->getParentType();
        $parent_entity = $this->getParentEntity();

        // Use parent entity corresponding to values saved in the attachments table
        $parent = $this->getParentClass();

        // Define the list order
        if (! $this->sort_order) {
            $this->sort_order = 'filename';
        }

        // Determine allowed access levels
        $app = Factory::getApplication();
        /** @var \Joomla\Database\DatabaseDriver $db */
        $db = Factory::getContainer()->get('DatabaseDriver');
        $user = $app->getIdentity();
        $user_levels = $user->getAuthorisedViewLevels();

        // If the user is not logged in, add extra view levels (if configured)
        $secure = $params->get('secure', false);
        $logged_in = $user->get('username') <> '';
        if (!$logged_in and $secure) {
            $user_levels = array('1'); // Override the authorised levels
            // NOTE: Public attachments are ALWAYS visible
            $guest_levels = $params->get('show_guest_access_levels', array());
            if (is_array($guest_levels)) {
                foreach ($guest_levels as $glevel) {
                    $user_levels[] = $glevel;
                }
            } else {
                $user_levels[] = $guest_levels;
            }
        }
        $user_levels = implode(',', array_unique($user_levels));

        // Create the query
        $query = $db->getQuery(true);
        $query->select('a.*, u.name as creator_name')->from('#__attachments AS a');
        $query->leftJoin('#__users AS u ON u.id = a.created_by');
        if ($attachmentid && join(',', $attachmentid)) {
            $query->where('a.id in (' . join(',', $attachmentid) . ')');
        }
        if ($parent_id == 0) {
            // If the parent ID is zero, the parent is being created so we have
            // do the query differently
            $user_id = $user->get('id');
            $query->where('a.parent_id IS NULL AND u.id=' . (int)$user_id);
        } else {
            $query->where('a.parent_id=' . (int)$parent_id);

            // Handle the state part of the query
            if ($user->authorise('core.edit.state', 'com_attachments')) {
                // Do not filter on state since this user can change the state of any attachment
            } elseif ($user->authorise('attachments.edit.state.own', 'com_attachments')) {
                $query->where('((a.created_by = ' . (int)$user->id . ') OR (a.state = 1))');
            } elseif ($user->authorise('attachments.edit.state.ownparent', 'com_attachments')) {
                // The user can edit the state of any attachment if they created the article/parent
                $parent_creator_id = $parent->getParentCreatorId($parent_id, $parent_entity);
                if ((int)$parent_creator_id == (int)$user->get('id')) {
                    // Do not filter on state since this user can change
                    // the state of any attachment on this article/parent
                } else {
                    // Since the user is not the creator, they should only see published attachments
                    $query->where('a.state = 1');
                }
            } else {
                // For everyone else only show published attachments
                $query->where('a.state = 1');
            }
        }

        $query->where(
            'a.parent_type=' . $db->quote($parent_type) . ' AND a.parent_entity=' . $db->quote($parent_entity)
        );
        if (!$user->authorise('core.admin')) {
            $query->where('a.access IN (' . $user_levels . ')');
        }
        $query->order($this->sort_order);

        // Do the query
        try {
            $db->setQuery($query);
            $attachments = $db->loadObjectList();
        } catch (\RuntimeException $e) {
            $errmsg = $e->getMessage() . ' (ERR 58)';
            throw new \Exception($errmsg, 500);
        }

        $this->some_visible = false;
        $this->some_modifiable = false;

        // Install the list of attachments in this object
        $this->num_attachments = count($attachments);

        // The query only returns items that are visible/accessible for
        // the user, so if it contains anything, they will be visible
        $this->some_visible = $this->num_attachments > 0;

        // Add permissions for each attachment in the list
        if ($this->num_attachments > 0) {
            $this->list = $attachments;

            // Add the permissions to each row
            $parent = $this->getParentClass();

            // Add permissions
            foreach ($attachments as $attachment) {
                $attachment->user_may_delete = $parent->userMayDeleteAttachment($attachment);
                $attachment->user_may_edit = $parent->userMayEditAttachment($attachment);
                if ($attachment->user_may_edit) {
                    $this->some_modifiable = true;
                }
            }

            // Fix relative URLs
            foreach ($attachments as $attachment) {
                if ($attachment->uri_type == 'url') {
                    $url = $attachment->url;
                    if (strpos($url, '://') === false) {
                        $uri = Uri::getInstance();
                        $attachment->url = $uri->base(true) . '/' . $url;
                    }
                }
            }
        }

        // Finally, return the list!
        return $this->list;
    }


    /**
     * Get the number of attachments
     *
     * @return the number of attachments for this parent
     */
    public function numAttachments()
    {
        return $this->num_attachments;
    }


    /**
     * Are some of the attachments be visible?
     *
     * @return true if there are attachments and some should be visible
     */
    public function someVisible($attachmentid = null)
    {
        // See if the attachments list has been loaded
        if ($this->list == null) {
            // See if we have already loaded the attachments list
            if ($this->num_attachments === 0) {
                return false;
            }

            // Since the attachments have not been loaded, load them now
            $this->getAttachmentsList($attachmentid);
        }

        return $this->some_visible;
    }


    /**
     * Should some of the attachments be modifiable?
     *
     * @return true if there are attachments and some should be modifiable
     */
    public function someModifiable()
    {
        // See if the attachments list has been loaded
        if ($this->list == null) {
            // See if we have already loaded the attachments list
            if ($this->num_attachments === 0) {
                return false;
            }

            // Since the attachments have not been loaded, load them now
            $this->getAttachmentsList();
        }

        return $this->some_modifiable;
    }



    /**
     * Returns the types of attachments
     *
     * @return 'file', 'url', 'both', or false (if no attachments)
     */
    public function types()
    {
        // Make sure the attachments are loaded
        if ($this->list == null) {
            // See if we have already loaded the attachments list
            if ($this->num_attachments === 0) {
                return false;
            }

            // Since the attachments have not been loaded, load them now
            $this->getAttachmentsList();
        }

        // Scan the attachments
        $types = false;
        foreach ($this->list as $attachment) {
            if ($types) {
                if ($attachment->uri_type != $types) {
                    return 'both';
                }
            } else {
                $types = $attachment->uri_type;
            }
        }

        return $types;
    }
}
