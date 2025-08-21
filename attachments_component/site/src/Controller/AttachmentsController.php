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

namespace JMCameron\Component\Attachments\Site\Controller;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Input\Input;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


/**
 * Class for a controller for dealing with lists of attachments
 *
 * @package Attachments
 */
class AttachmentsController extends BaseController
{
    /**
     * Constructor
     *
     * @param array $default : An optional associative array of configuration settings.
     * Recognized key values include 'name', 'default_task', 'model_path', and
     * 'view_path' (this list is not meant to be comprehensive).
     */
    public function __construct(
        $config = array('default_task' => 'noop'),
        MVCFactoryInterface $factory = null,
        ?CMSApplication $app = null,
        ?Input $input = null
    ) {
        parent::__construct($config, $factory, $app, $input);
    }


    /**
     * A noop function so this controller does not have a usable default
     */
    public function noop()
    {
        $errmsg = Text::_('ATTACH_ERROR_NO_FUNCTION_SPECIFIED') . ' (ERR 59)';
        throw new \Exception($errmsg, 500);
    }


    /**
     * Disable the default display function
     */
    public function display($cachable = false, $urlparams = false)
    {
        // Do nothing (not sure why this works...)
    }


    /**
     * Display the attachments list
     *
     * @param int $parent_id the id of the parent
     * @param string $parent_type the type of parent
     * @param string $parent_entity the type entity of the parent
     * @param string $title title to be shown above the list of articles.  If null, use system defaults.
     * @param bool $show_file_links enable showing links for the filenames
     * @param bool $allow_edit enable showing edit/delete links (if permissions are okay)
     * @param bool $echo if true the output will be echoed; otherwise the results are returned.
     * @param string $from The 'from' info
     *
     * @return the string (if $echo is false)
     */
    public function displayString(
        $parent_id,
        $parent_type,
        $parent_entity,
        $title = null,
        $show_file_links = true,
        $allow_edit = true,
        $echo = true,
        $from = null,
        $attachmentid = null
    ) {
        $app = $this->app;
        $document = $app->getDocument();

        // Get an instance of the model
        /** @var \JMCameron\Component\Attachments\Site\Model\AttachmentsModel $model */
        $model = $this->getModel('Attachments', 'Site');
        if (!$model) {
            $errmsg = Text::_('ATTACH_ERROR_UNABLE_TO_FIND_MODEL') . ' (ERR 60)';
            throw new \Exception($errmsg, 500);
        }

        $model->setParentId($parent_id, $parent_type, $parent_entity);

        // Get the component parameters
        $params = ComponentHelper::getParams('com_attachments');

        // Set up to list the attachments for this article/content item
        $sort_order = $params->get('sort_order', 'filename');
        $model->setSortOrder($sort_order);

        // If none of the attachments should be visible, exit now
        if (! $model->someVisible($attachmentid)) {
            return false;
        }

        // Get the view
        $viewType = $document->getType();
        /** @var \JMCameron\Component\Attachments\Site\View\Attachments\HtmlView $view */
        $view = $this->getView('Attachments', $viewType);
        if (!$view) {
            $errmsg = Text::_('ATTACH_ERROR_UNABLE_TO_FIND_VIEW') . ' (ERR 61)';
            throw new \Exception($errmsg, 500);
        }
        $view->setModel($model);

        // Construct the update URL template
        $base_url = Uri::base(false);
        $update_url = $base_url . "index.php?option=com_attachments&task=update&id=%d";
        $update_url .= "&from=$from&tmpl=component";
        $update_url = Route::_($update_url);
        $view->update_url = $update_url;

        // Construct the delete URL template
        $delete_url = $base_url . "index.php?option=com_attachments&task=deleteWarning&id=%d";
        $delete_url .= "&parent_type=$parent_type&parent_entity=$parent_entity&parent_id=" . (int)$parent_id;
        $delete_url .= "&from=$from&tmpl=component";
        $delete_url = Route::_($delete_url);
        $view->delete_url = $delete_url;

        // Set some display settings
        $view->title = $title;
        $view->show_file_links = $show_file_links;
        $view->allow_edit = $allow_edit;
        $view->from = $from;

        // Get the view to generate the display output from the template
        if ($view->display() === true) {
            // Display or return the results
            if ($echo) {
                echo $view->getOutput();
            } else {
                return $view->getOutput();
            }
        }

        return false;
    }
}
