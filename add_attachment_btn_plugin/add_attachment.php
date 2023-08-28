<?php
/**
 * Add Attachments Button plugin
 *
 * @package Attachments
 * @subpackage Add_Attachment_Button_Plugin
 *
 * @copyright Copyright (C) 2007-2018 Jonathan M. Cameron, All Rights Reserved
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @link http://joomlacode.org/gf/project/attachments/frs/
 * @author Jonathan M. Cameron
 */

use JMCameron\Component\Attachments\Site\Helper\AttachmentsJavascript;
use JMCameron\Plugin\AttachmentsPluginFramework\AttachmentsPluginManager;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

// no direct access
defined( '_JEXEC' ) or die('Restricted access');

/**
 * Button that allows you to add attachments from the editor
 *
 * @package Attachments
 */
class plgEditorsXtdAdd_attachment extends CMSPlugin implements SubscriberInterface
{
	/**
	 * $db and $app are loaded on instantiation
	 */
	protected ?DatabaseDriver $db = null;
	protected ?CMSApplication $app = null;

	/**
	 * Load the language file on instantiation
	 *
	 * @var    boolean
	 */
	protected $autoloadLanguage = true;

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onDisplay' => 'onDisplay',
		];
	}

	/**
	 * Add Attachment button
	 *
	 * @param Event $event The event object
	 *
	 * @return a button
	 */
	public function onDisplay(Event $event)
	{
		$input = $this->app->getInput();

		// Avoid displaying the button for anything except for registered parents
		$parent_type = $input->getCmd('option');
		if (!$parent_type) {
			return;
			}
		$parent_entity = 'default';
		$editor = 'article';

		// Handle categories specially (since they are really com_content)
		if ($parent_type == 'com_categories') {
			$parent_type = 'com_content';
			$parent_entity = 'category';
			$editor = 'category';
			}

		// Get the parent ID (id or first of cid array)
		//	   NOTE: $parent_id=0 means no id (usually means creating a new entity)
		$cid = $input->get('cid', array(0), 'array');
		$parent_id = 0;
		if ( count($cid) > 0 ) {
			$parent_id = (int)$cid[0];
			}
		if ( $parent_id == 0) {
			$a_id = $input->getInt('a_id');
			if ( !is_null($a_id) ) {
				$parent_id = (int)$a_id;
				}
			}
		if ( $parent_id == 0) {
			$nid = $input->getInt('id');
			if ( !is_null($nid) ) {
				$parent_id = (int)$nid;
				}
			}

		// Check for the special case where we are creating an article from a category list
		$item_id = $input->getInt('Itemid');
		$menu = $this->app->getMenu();
		$menu_item = $menu->getItem($item_id);
		if ( $menu_item AND ($menu_item->query['view'] == 'category') AND empty($a_id) ) {
			$parent_entity = 'article';
			$parent_id = NULL;
			}

		// Get the article/parent handler
		PluginHelper::importPlugin('attachments');
		$apm = AttachmentsPluginManager::getAttachmentsPluginManager();
		if ( !$apm->attachmentsPluginInstalled($parent_type) ) {
			// Exit if there is no Attachments plugin to handle this parent_type
			return;
			}
		// Figure out where we are and construct the right link and set
		$base_url = Uri::root(true);
		if ( $this->app->isClient('administrator') ) {
			$base_url = str_replace('/administrator','', $base_url);
			}

		// Set up the Javascript framework
		AttachmentsJavascript::setupJavascript();

		// Get the parent handler
		$parent = $apm->getAttachmentsPlugin($parent_type);
		$parent_entity = $parent->getCanonicalEntityId($parent_entity);

		if ( $parent_id == 0 ) {
			# Last chance to get the id in extension editors
			$view = $input->getWord('view');
			$layout = $input->getWord('layout');
			$parent_id = $parent->getParentIdInEditor($parent_entity, $view, $layout);
			}

		// Make sure we have permissions to add attachments to this article or category
		if ( !$parent->userMayAddAttachment($parent_id, $parent_entity, $parent_id == 0) ) {
			return;
			}

		// NOTE: I cannot find anything about AttachmentsRemapper class.
		// Could it be old unnecessary code that needs deletion?
		// ------------------------------------------------------
		// Allow remapping of parent ID (eg, for Joomfish)
		if (jimport('attachments_remapper.remapper'))
		{
			$parent_id = AttachmentsRemapper::remapParentID($parent_id, $parent_type, $parent_entity);
		}

		// Add the regular css file
		HTMLHelper::stylesheet('media/com_attachments/css/attachments_list.css');
		HTMLHelper::stylesheet('media/com_attachments/css/add_attachment_button.css');

		// Handle RTL styling (if necessary)
		$lang = $this->app->getLanguage();
		if ( $lang->isRTL() ) {
			HTMLHelper::stylesheet('media/com_attachments/css/attachments_list_rtl.css');
			HTMLHelper::stylesheet('media/com_attachments/css/add_attachment_button_rtl.css');
			}

		// Load the language file from the frontend
		$lang->load('com_attachments', JPATH_ADMINISTRATOR.'/components/com_attachments');

		// Create the [Add Attachment] button object
		$button = new CMSObject();

		$link = $parent->getEntityAddUrl($parent_id, $parent_entity, 'closeme');
		$link .= '&amp;editor=' . $editor;

		// Finalize the [Add Attachment] button info
		$button->modal = true;
		$button->class = 'btn';
		$button->text = Text::_('ATTACH_ADD_ATTACHMENT');
		$button->name = 'paperclip';
		$button->link = $link;
		$button->options = "{handler: 'iframe', size: {x: 920, y: 530}}";

		return $button;
	}
}
