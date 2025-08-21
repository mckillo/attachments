<?php

/**
 * Attachments component
 *
 * @package Attachments
 * @subpackage Attachments_Component
 *
 * @copyright Copyright (C) 2007-2025 Jonathan M. Cameron, All Rights Reserved
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @link https://github.com/jmcameron/attachments
 * @author Jonathan M. Cameron
 */

namespace JMCameron\Component\Attachments\Administrator\Controller;

use JMCameron\Plugin\AttachmentsPluginFramework\AttachmentsPluginManager;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Input\Input;
use Joomla\String\StringHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The controller for special requests
 * (adapted from administrator/components/com_config/controllers/component.php)
 *
 * @package Attachments
 */
class SpecialController extends BaseController
{
    /**
     * Constructor.
     *
     * @return  BaseController
     */
    public function __construct(
        $config = array('default_task' => 'noop'),
        MVCFactoryInterface $factory = null,
        ?CMSApplication $app = null,
        ?Input $input = null
    ) {
        $config['default_task'] = 'noop';
        parent::__construct($config, $factory, $app, $input);

        // Access check.
        $user = $this->app->getIdentity();
        if ($user === null || !$user->authorise('core.admin', 'com_attachments')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR') . ' (ERR 147)', 404);
        }
    }


    /**
     * A noop function so this controller does not have a usable default
     */
    public function noop()
    {
        echo "<h1>" . Text::_('ATTACH_ERROR_NO_SPECIAL_FUNCTION_SPECIFIED') . "</h1>";
        exit();
    }


    /**
     * Show the current SEF mode
     *
     * This is for system testing purposes only
     */
    public function showSEF()
    {
        echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "https://www.w3.org/TR/html4/loose.dtd">';
        echo "<html><head><title>SEF Status</title></head><body>";
        echo "SEF: " . $this->app->getCfg('sef') . "<br />";
        echo "</body></html>";
        exit();
    }


    /**
     * Show a list of all attachment IDs
     *
     * This is for system testing purposes only
     */
    public function listAttachmentIDs()
    {
        // Get the article IDs
        /** @var \Joomla\Database\DatabaseDriver $db */
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('att.id,parent_id,parent_type,parent_entity,art.catid');
        $query->from('#__attachments as att');
        $query->leftJoin('#__content as art ON att.parent_id = art.id');
        $query->where('att.parent_entity=' . $db->quote('article'));
        $query->order('art.id');
        try {
            $db->setQuery($query);
            $attachments = $db->loadObjectList();
        } catch (\RuntimeException $e) {
            $errmsg = $e->getMessage() . ' (ERR 148)';
            throw new \Exception($errmsg, 500);
        }

        // Get the category IDs
        $query = $db->getQuery(true);
        $query->select('att.id,att.parent_id,parent_type,parent_entity');
        $query->from('#__attachments as att');
        $query->leftJoin('#__categories as c ON att.parent_id = c.id');
        $query->where('att.parent_entity=' . $db->quote('category'));
        $query->order('c.id');
        try {
            $db->setQuery($query);
            $crows = $db->loadObjectList();
        } catch (\RuntimeException $e) {
            $errmsg = $e->getMessage() . ' (ERR 149)';
            throw new \Exception($errmsg, 500);
        }

        echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "https://www.w3.org/TR/html4/loose.dtd">';
        echo '<html><head><title>Attachment IDs</title></head><body>';
        echo 'Attachment IDs:<br/>';

        // Do the article attachments
        foreach ($attachments as $attachment) {
            if (empty($attachment->id)) {
                $attachment->id = '0';
            }
            if (empty($attachment->catid)) {
                $attachment->catid = '0';
            }
            $parent_entity = StringHelper::strtolower($attachment->parent_entity);
            echo ' ' . $attachment->id . '/' . $attachment->parent_id . '/' .
                $attachment->parent_type . '/' . $parent_entity . '/' . $attachment->catid . '<br/>';
        }
        foreach ($crows as $attachment) {
            if (empty($attachment->id)) {
                $attachment->id = '0';
            }
            $parent_entity = StringHelper::strtolower($attachment->parent_entity);
            echo ' ' . $attachment->id . '/' . $attachment->parent_id . '/' .
                    $attachment->parent_type . '/' . $parent_entity . '/' . $attachment->parent_id . '<br/>';
        }
        echo '</body></html>';
        exit();
    }


    /**
     * Show a list of all attachment IDs
     *
     * This is for system testing purposes only
     */
    public function listKnownParentTypes()
    {
        // Get the article/parent handler
        PluginHelper::importPlugin('attachments');
        $apm = AttachmentsPluginManager::getAttachmentsPluginManager();

        $ptypes = $apm->getInstalledParentTypes();
        echo implode('<br/>', $ptypes);
    }
}
