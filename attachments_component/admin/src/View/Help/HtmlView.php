<?php
/**
 * Attachments component
 *
 * @package		Attachments
 * @subpackage	Attachments_Component
 *
 * @author		Jonathan M. Cameron <jmcameron@jmcameron.net>
 * @copyright	Copyright (C) 2007-2018 Jonathan M. Cameron, All Rights Reserved
 * @license		http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @link		http://joomlacode.org/gf/project/attachments/frs/
 */

namespace JMCameron\Component\Attachments\Administrator\View\Help;

use JMCameron\Component\Attachments\Administrator\View\Help\HelpView;
use JMCameron\Component\Attachments\Site\Helper\AttachmentsDefines;
use Joomla\CMS\Date\Date;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die();

/**
 * View for the help page
 *
 * @package	 Attachments
 * @since	 3.1
 */
class HtmlView extends HelpView
{
	/**
	 * Display the view
	 *
	 * @param	string	$tpl  A template file to load. [optional]
	 *
	 * @return nothing
	 */
	public function display($tpl = null)
	{
		$this->logo_img = HTMLHelper::image('com_attachments/attachments_help_logo32.png', '', null, true);
		$this->version = AttachmentsDefines::$ATTACHMENTS_VERSION;
		$rdate = new Date(AttachmentsDefines::$ATTACHMENTS_VERSION_DATE);
		$this->date = HTMLHelper::_('date', $rdate, Text::_('DATE_FORMAT_LC1'));

		parent::display($tpl);
	}

	/**
	 * Add the start of the permissions table including the header
	 *
	 * @param	string	$col1_code	Language token for column 1 (permission name)
	 * @param	string	$col2_code	Language token for column 2 (permission note)
	 * @param	string	$col3_code	Language token for column 3 (permission action)
	 *
	 * @return nothing
	 */
	protected function startPermissionsTable($col1_code, $col2_code, $col3_code)
	{
		echo "<table id=\"permissions\"class=\"permissions docutils\">\n";
		echo "<colgroup>\n";
		echo "  <col class=\"col_perm_name\"/>\n";
		echo "  <col class=\"col_perm_note\"/>\n";
		echo "  <col class=\"col_perm_action\"/>\n";
		echo "</colgroup>\n";
		echo "<thead>\n";
		echo "  <tr>\n";
		echo "     <th class=\"head\">" . Text::_($col1_code) . "</th>\n";
		echo "     <th class=\"head\">" . Text::_($col2_code) . "</th>\n";
		echo "     <th class=\"head\">" . Text::_($col3_code) . "</th>\n";
		echo "  </tr>\n";
		echo "</thead>\n";
		echo "<tbody>\n";
	}

	/**
	 * Add the a row of the permissions table
	 *
	 * @param	string	$col1_code	Language token for column 1 (permission name)
	 * @param	string	$col2_code	Language token for column 2 (permission note)
	 * @param	string	$col3_code	Language token for column 3 (permission action)
	 *
	 * @return nothing
	 */
	protected function addPermissionsTableRow($col1_code, $col2_code, $col3_code)
	{
		echo "  <tr>\n";
		echo "     <td>" . Text::_($col1_code) . "</td>\n";
		echo "     <td>" . Text::_($col2_code) . "</td>\n";
		echo "     <td>" . Text::_($col3_code) . "</td>\n";
		echo "  </tr>\n";
	}

	/**
	 * Add the end of the permissions table
	 *
	 * @return nothing
	 */
	protected function endPermissionsTable()
	{
		echo "</table>\n";
	}
}
