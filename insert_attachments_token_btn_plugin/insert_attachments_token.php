<?php
/**
 * Add Attachments Button plugin
 *
 * @package Attachments
 * @subpackage Insert_Attachments_Token_Button_Plugin
 *
 * @copyright Copyright (C) 2007-2018 Jonathan M. Cameron, All Rights Reserved
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @link http://joomlacode.org/gf/project/attachments/frs/
 * @author Jonathan M. Cameron
 */

use JMCameron\Plugin\AttachmentsPluginFramework\AttachmentsPluginManager;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

// no direct access
defined( '_JEXEC' ) or die('Restricted access');

/**
 * Button that allows you to insert an {attachments} token into the text from the editor
 *
 * @package Attachments
 */
class PlgEditorsXtdInsert_attachments_token extends CMSPlugin implements SubscriberInterface
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
	 * Insert attachments token button
	 *
	 * @param Event $event The event object
	 *
	 * @return a button
	 */
	public function onDisplay(Event $event)
	{
		[$name, $asset, $author] = $event->getArguments();
		// Get the component parameters
		$params = ComponentHelper::getParams('com_attachments');

		// This button should only be displayed in 'custom placement' mode.
		// Check to make sure that is the case
		$placement = $params->get('attachments_placement', 'end');
		if ( $placement != 'custom' ) {
			return false;
			}

		// Avoid displaying the button for anything except for registered parents
		$input = $this->app->getInput();
		$parent_type = $input->getCmd('option');

		// Handle sections and categories specially (since they are really com_content)
		if ($parent_type == 'com_categories') {
			$parent_type = 'com_content';
			}

		// Get the article/parent handler
		PluginHelper::importPlugin('attachments');
		$apm = AttachmentsPluginManager::getAttachmentsPluginManager();
		if ( !$apm->attachmentsPluginInstalled($parent_type) ) {
			// Exit if there is no Attachments plugin to handle this parent_type
			return;
			}

		// Get ready for language things
		$lang =	 $this->app->getLanguage();
		if ( !$lang->load('plg_editors-xtd_insert_attachments_token', dirname(__FILE__)) ) {
			// If the desired translation is not available, at least load the English
			$lang->load('plg_editors-xtd_insert_attachments_token', JPATH_ADMINISTRATOR, 'en-GB');
			}

		// Set up the Javascript to insert the tag
		$present = Text::_('ATTACH_ATTACHMENTS_TOKEN_ALREADY_PRESENT', true) ;
		$js =  "
			function insertAttachmentsToken(editor) {
				var content = Joomla.editors.instances['$name'].getValue();
				if (content.match(/\{\s*attachments/i)) {
					alert('$present');
					return false;
				} else {
					jInsertEditorText('<span class=\"hide_attachments_token\">{attachments}</span>', editor);
				}
			}
			";

		$doc = $this->app->getDocument();

		$doc->addScriptDeclaration($js);

		// Add the regular css file
		HTMLHelper::stylesheet('media/com_attachments/css/attachments_list.css');
		HTMLHelper::stylesheet('media/com_attachments/css/insert_attachments_token_button.css');

		// Handle RTL styling (if necessary)
		if ( $lang->isRTL() ) {
			HTMLHelper::stylesheet('media/com_attachments/css/attachments_list_rtl.css');
			HTMLHelper::stylesheet('media/com_attachments/css/insert_attachments_token_button_rtl.css');
			}

		$button = new Registry();
		$button->set('modal', false);
		$button->set('class', 'btn');
		$button->set('onclick', 'insertAttachmentsToken(\''.$name.'\');return false;');
		$button->set('text', Text::_('ATTACH_ATTACHMENTS_TOKEN'));
		$button->set('title', Text::_('ATTACH_ATTACHMENTS_TOKEN_DESCRIPTION'));

		$button_name = 'paperclip';
		$button->set('name', $button_name);

		// TODO: The button writer needs to take into account the javascript directive
		// $button->set('link', 'javascript:void(0)');
		$button->set('link', '#');

		return $button;
	}
}
