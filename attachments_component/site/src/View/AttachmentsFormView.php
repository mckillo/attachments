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

namespace JMCameron\Component\Attachments\Site\View;

use Joomla\CMS\Document\Renderer\Html\HeadRenderer;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\View\HtmlView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


/**
 * View for the uploads
 *
 * @package Attachments
 */
class AttachmentsFormView extends HtmlView
{
    /**
     * Return the starting HTML for the page
     *
     * Note: When displaying a View directly from user code (not a conroller),
     *       it does not automatically create the HTML <html>, <body> and
     *       <head> tags.  This code fixes that.
     *
     *       There is probably a better way to do this!
     */
    protected function startHTML()
    {
        /** @var \Joomla\CMS\Application\CMSApplication $app */
        $app = Factory::getApplication();
        $document = $app->getDocument();
        $this->document = $document;

        $this->template = $app->getTemplate(true)->template;
        $template_dir = $this->baseurl . '/templates/' . $this->template;

        $file = '/templates/system/css/system.css';
        if (File::exists(JPATH_SITE . $file)) {
            $document->addStyleSheet($this->baseurl . $file);
        }

        // Try to add the typical template stylesheets
        $files = array('template.css', 'position.css', 'layout.css', 'general.css');
        foreach ($files as $file) {
            $path = JPATH_SITE . '/templates/' . $this->template . '/css/' . $file;
            if (File::exists($path)) {
                $document->addStyleSheet($this->baseurl . '/templates/' . $this->template . '/css/' . $file);
            }
        }

        // Add the CSS for the attachments list (whether we need it or not)
        HTMLHelper::stylesheet('media/com_attachments/css/attachments_list.css');
        HTMLHelper::stylesheet('media/com_attachments/css/attachments_list_dark.css');

        $head_renderer = new HeadRenderer($document);

        $html = '';
        $html .= "<html>\n";
        $html .= "<head>\n";
        $html .= $head_renderer->render('header');
        $html .= "</head>\n";
        $html .= "<body id=\"attachments_iframe\">\n";

        return $html;
    }


    /**
     * Return the ending HTML tags for the page
     */
    protected function endHTML()
    {
        $html = "\n";
        $html .= "</body>\n";
        $html .= "</html>\n";

        return $html;
    }
}
