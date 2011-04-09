<?php
/**
 * Attachments component
 *
 * @package Attachments
 * @subpackage Attachments_Component
 *
 * @copyright Copyright (C) 2007-2011 Jonathan M. Cameron, All Rights Reserved
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @link http://joomlacode.org/gf/project/attachments/frs/
 * @author Jonathan M. Cameron
 */

// No direct access
defined('_JEXEC') or die('Restricted Access');

jimport( 'joomla.application.component.view' );

/**
 * View for a list of attachments
 *
 * @package Attachments
 */
class AttachmentsViewAttachments extends JView
{
	/**
	 * Construct the output for the view/template.
	 *
	 * NOTE: This only constructs the output; it does not display it!
	 *		 Use getOutput() to actually display it.
	 *
	 * @param string $tpl template name (optional)
	 *
	 * @return if there are no attachments for this article,
	 *		   if everything is okay, return true
	 *		   if there is an error, return the error code
	 */
	function display($tpl = null)
	{
		$document =& JFactory::getDocument();
		if ( JRequest::getWord('format', '') == 'raw' ) {
			// Choose raw text even though it is actually html
			$document->setMimeEncoding('text/plain');
			}

		// Add javascript
	    $uri = JFactory::getURI();
		$document->addScript( $uri->root(true) . '/plugins/content/attachments/attachments_refresh.js' );

		// Get the model
		$model =& $this->getModel('Attachments');
		if ( !$model ) {
			$errmsg = JText::_('ERROR_UNABLE_TO_FIND_MODEL') . ' (ERR 83)';
			JError::raiseError( 500, $errmsg);
			}

		// See if there are any attachments
		$list = $model->getAttachmentsList();
		if ( ! $list ) {
			return null;
			}

		// Add the default path
		$this->addTemplatePath(JPATH_SITE.DS.'components'.DS.'com_attachments'.DS.
							   'views'.DS.'attachments'.DS.'tmpl');

		// Set up the correct path for template overloads
		// (Do this after previous addTemplatePath so that template overrides actually override)
		$app =& JFactory::getApplication();
		$templateDir = JPATH_SITE . DS. 'templates' . DS . $app->getTemplate() . DS .
					   'html' . DS . 'com_attachments' . DS . 'attachments';
		$this->addTemplatePath($templateDir);

		// Load the language files from the backend
		$lang =&  JFactory::getLanguage();
		$lang->load('plg_content_attachments', JPATH_SITE.DS.'plugins'.DS.'content'.DS.'attachments');

		// Get the component parameters
		jimport('joomla.application.component.helper');
		$params =&	JComponentHelper::getParams('com_attachments');

		// See whether the user-defined fields should be shown
		// User field 1
		$show_user_field_1 = false;
		$user_field_1_name = $params->get('user_field_1_name', '');
		if ( $user_field_1_name != '' ) {
			if ( $user_field_1_name[JString::strlen($user_field_1_name)-1] != '*' ) {
				$show_user_field_1 = true;
				$this->user_field_1_name = $user_field_1_name;
				}
			}
		$this->show_user_field_1 = $show_user_field_1;
		// User field 2
		$show_user_field_2 = false;
		$user_field_2_name = $params->get('user_field_2_name', '');
		if ( $user_field_2_name != '' ) {
			if ( $user_field_2_name[JString::strlen($user_field_2_name)-1] != '*' ) {
				$show_user_field_2 = true;
				$this->user_field_2_name = $user_field_2_name;
				}
			}
		$this->show_user_field_2 = $show_user_field_2;
		// User field 3
		$show_user_field_3 = false;
		$user_field_3_name = $params->get('user_field_3_name', '');
		if ( $user_field_3_name != '' ) {
			if ( $user_field_3_name[JString::strlen($user_field_3_name)-1] != '*' ) {
				$show_user_field_3 = true;
				$this->user_field_3_name = $user_field_3_name;
				}
			}
		$this->show_user_field_3 = $show_user_field_3;
		
		// Set up for the template
		$from = JRequest::getWord('from', 'closeme');
		$parent_id = $model->getParentId();
		$parent_type = $model->getParentType();
		// ??? $parent_entity = $model->getParentEntity();
		$parent_entity = 'article';
		$this->parent_id = $parent_id;
		$this->parent_type = $parent_type;
		$this->parent_entity = $parent_entity;
		$this->parent_title = $model->getParentTitle();
		$this->parent_entity_name = $model->getParentEntityName();

		$this->some_attachments_visible = $model->someVisible();
		$this->some_attachments_modifiable = $model->someModifiable();
		$this->superimpose_link_icons = $params->get('superimpose_url_link_icons', true);

		$this->from = $from;

		$this->list = $list;

		// Get the display options
		$this->style = $params->get('attachments_table_style', 'attachmentsList');
		$this->secure = $params->get('secure', false);
		$this->who_can_see = $params->get('who_can_see', 'logged_in');
		$this->show_column_titles = $params->get('show_column_titles', false);
		$this->show_description = $params->get('show_description', true);
		$this->show_uploader = 	$params->get('show_uploader', false);
		$this->show_file_size = $params->get('show_file_size', true);
		$this->show_downloads = $params->get('show_downloads', false);
		$this->show_mod_date = 	$params->get('show_modification_date', false);
		$this->file_link_open_mode = $params->get('file_link_open_mode', 'in_same_window');
		if ( $this->show_mod_date ) {
			$this->mod_date_format = $params->get('mod_date_format', '%Y-%m-%d %I:%M%P');
			}

		// Get the attachments list title
		$title = $this->title;
		if ( !$title OR (JString::strlen($title) == 0) ) {
			$title = 'ATTACHMENTS_TITLE';
			}
		$parent =& $model->getParentClass();
		$title = $parent->attachmentsListTitle($title, $params, $parent_id, $parent_entity);
		$this->title = $title; // Note: assume it is translated

		// Construct the path for the icons
        $uri = JFactory::getURI();
		$base_url = $uri->root(true) . '/';
		$this->base_url = $base_url;
		$this->icon_url_base = $base_url . 'components/com_attachments/media/icons/';

		// Get the output of the template
		$result = $this->loadTemplate($tpl);
		if (JError::isError($result)) {
			return $result;
			}

		return true;
	}

	/**
	 * Get the output
	 *
	 * @return string the output
	 */
	function getOutput()
	{
		return $this->_output;
	}


}

?>
