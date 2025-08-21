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

namespace JMCameron\Component\Attachments\Administrator\Helper;

use JMCameron\Component\Attachments\Site\Helper\AttachmentsDefines;
use JMCameron\Component\Attachments\Site\Helper\AttachmentsFileTypes;
use JMCameron\Component\Attachments\Site\Helper\AttachmentsHelper;
use Joomla\CMS\Access\Rules;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;
use Joomla\String\StringHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


/**
 * A class for update functions
 *
 * @package Attachments
 */
class AttachmentsUpdate
{
    /**
     * Access check
     */
    public static function checkAccess()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if ($user === null or !$user->authorise('core.admin', 'com_attachments')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR') . ' (ERR 67)', 404);
            return;
        }
    }

    /**
     * Add icon filenames for all attachments missing an icon
     */
    public static function addIconFilenames()
    {
        static::checkAccess();

        // Get all the attachment IDs
        /** @var \Joomla\Database\DatabaseDriver $db */
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('id, filename, file_type, icon_filename')->from('#__attachments');
        $query->where('file_type IS NULL');
        $db->setQuery($query);
        try {
            $attachments = $db->loadObjectList();
        } catch (\Exception $e) {
            $errmsg = $e->getMessage() . ' (ERR 68)';
            throw new \Exception($errmsg, 500);
        }
        if (count($attachments) == 0) {
            return Text::_('ATTACH_NO_FILE_TYPE_FIELDS_NEED_UPDATING');
        }
        $IDs = array();
        foreach ($attachments as $attachment) {
            $IDs[] = $attachment->id;
        }

        // Update the icon file_types all the attachments (that do not have one already)
        /** @var \Joomla\CMS\MVC\Factory\MVCFactory $mvc */
        $mvc = Factory::getApplication()
            ->bootComponent("com_attachments")
            ->getMVCFactory();
        $attachment = $mvc->createTable('Attachment', 'Administrator');
        $numUpdated = 0;
        foreach ($IDs as $id) {
            $attachment->load($id);

            // Only update those attachment records that don't already have an icon_filename
            if (StringHelper::strlen($attachment->icon_filename) == 0) {
                $new_icon_filename  = AttachmentsFileTypes::iconFilename(
                    $attachment->filename,
                    $attachment->file_type
                );
                if (StringHelper::strlen($new_icon_filename) > 0) {
                    $attachment->icon_filename = $new_icon_filename ;
                    if (!$attachment->store()) {
                        $errmsg = Text::sprintf(
                            'ATTACH_ERROR_ADDING_ICON_FILENAME_FOR_ATTACHMENT_S',
                            $attachment->filename
                        ) .
                        ' ' .
                        $attachment->getError() . ' (ERR 69)';
                        throw new \Exception($errmsg, 500);
                    }
                    $numUpdated++;
                }
            }
        }

        return Text::sprintf('ATTACH_ADDED_ICON_FILENAMES_TO_N_ATTACHMENTS', $numUpdated);
    }


    /**
     * Update dates for all attachments with null dates
     */
    public static function updateNullDates()
    {
        static::checkAccess();

        // Get all the attachment IDs
        /** @var \Joomla\Database\DatabaseDriver $db */
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('*')->from('#__attachments');
        $db->setQuery($query);
        try {
            $attachments = $db->loadObjectList();
        } catch (\Exception $e) {
            $errmsg = $e->getMessage() . ' (ERR 70)';
            throw new \Exception($errmsg, 500);
        }
        if (count($attachments) == 0) {
            return 0;
        }

        // Update the dates for all the attachments
        $numUpdated = 0;
        foreach ($attachments as $attachment) {
            // Update the new create and update dates if they are null
            $updated = false;
            $created = $attachment->created;
            if (is_null($created) || ($created == '')) {
                $cdate = Factory::getDate(filemtime($attachment->filename_sys));
                $created = $cdate->toSql();
                $updated = true;
            }
            $mod_date = $attachment->modified;
            if (is_null($mod_date) || ($mod_date == '')) {
                $mdate = Factory::getDate(filemtime($attachment->filename_sys));
                $mod_date = $mdate->toSql();
                $updated = true;
            }

            // Update the record
            if ($updated) {
                $query = $db->getQuery(true);
                $query->update('#__attachments');
                $query->set('modified=' . $db->quote($mod_date) . ', created=' . $db->quote($created));
                $query->where('id = ' . (int)$attachment->id);
                $db->setQuery($query);
                try {
                    $db->execute();
                } catch (\RuntimeException $e) {
                    $errmsg = Text::sprintf(
                        'ATTACH_ERROR_UPDATING_NULL_DATE_FOR_ATTACHMENT_FILE_S',
                        $attachment->filename
                    );
                    throw new \Exception($errmsg . $e->getMessage() . ' (ERR 71)', 500);
                }
                $numUpdated++;
            }
        }

        return $numUpdated;
    }


    /**
     * Disable uninstallation of attachments when the Attachments component is
     * uninstalled.
     *
     * This is accomplished by modifying the uninstall.mysql.sql table to
     * comment out the line that deletes the attachments table.  Note that
     * this only affects the table, not the attachments files.
     */
    public static function disableSqlUninstall($dbtype = 'mysql')
    {
        static::checkAccess();

        // Construct the filenames
        if ($dbtype == 'mysqli') {
            // Use the same MYSQL installation file for mysqli
            $dbtype = 'mysql';
        }
        $filename = JPATH_ADMINISTRATOR . "/components/com_attachments/sql/uninstall.$dbtype.sql";
        $tempfilename = $filename . '.tmp';
        $msg = '';

        // Read the content of the old version of the uninstall sql file
        $contents = file_get_contents($filename);
        $lines = explode("\n", $contents);
        $new_lines = array();
        for ($i = 0; $i < count($lines); $i++) {
            $line = StringHelper::trim($lines[$i]);
            if (StringHelper::strlen($line) != 0) {
                if ($line[0] != '#') {
                    $line = '# ' . $line;
                }
                $new_lines[] = $line;
            }
        }

        // Overwrite the old file with a commented out version
        $new_contents = implode("\n", $new_lines) . "\n";
        File::write($tempfilename, $new_contents);
        if (! File::copy($tempfilename, $filename)) {
            $msg = Text::_('ATTACH_ERROR_UPDATING_FILE') . ": $filename!";
        }

        // Let the user know what happened
        if ($msg == '') {
            $msg = Text::_('ATTACH_DISABLED_UNINSTALLING_MYSQL_ATTACHMENTS_TABLE');
        }

        return $msg;
    }


    /**
     * Check an attachment filename and return relevant info
     */
    private static function checkFilename($filename)
    {
        static::checkAccess();

        $finfo = new \StdClass();

        // If it is a windows filename, convert to Linux format for analysis
        $winfile = false;
        $win_file_re = "|^(?P<drive>[a-zA-Z]{1}):(?P<filename>.*)$|";
        if (preg_match($win_file_re, $filename, $match)) {
            $winfile = true;
            $filename = str_replace("\\", '/', $match['drive'] . ':' . $match['filename']);
        }
        $finfo->winfile = $winfile;

        // Split the file into parts
        $parts = explode('/', $filename);
        $finfo->parts = $parts;

        // See if it is old-style (pre Attachments 2.0)
        $finfo->oldstyle = false;
        $attachments_dir_name = 'attachments';
        if ($parts[count($parts) - 2] == $attachments_dir_name) {
            $finfo->oldstyle = true;
        }

        // Get the path info
        $pathinfo = pathinfo($filename);
        $finfo->basename = $pathinfo['basename'];
        $finfo->extension = $pathinfo['extension'];

        // Construct the relative path for the current OS
        $start = array_search($attachments_dir_name, $parts);
        $relfile = '';
        for ($i = $start; $i < count($parts); $i++) {
            $relfile .= $parts[$i];
            if ($i < count($parts) - 1) {
                $relfile .= '/';
            }
        }
        $finfo->relfile = $relfile;

        // Construct the non-prefix version of the filename (if oldstyle)
        if ($finfo->oldstyle) {
            $finfo->prefix = false;
            if (preg_match('|^[0-9]{3}_(?P<filename>.+\..+$)|', $finfo->basename, $match)) {
                $finfo->prefix = true;
                $finfo->basename_no_prefix = $match['filename'];
            }
        }

        return $finfo;
    }



    /**
     * Regenerate the system filenames for all attachments.
     *
     * This function may need to run if the admin has moved the attachments
     * from one computer to another and the actual file paths need to be
     * updated.
     */
    public static function regenerateSystemFilenames()
    {
        static::checkAccess();

        // Define where the attachments go
        $upload_url = AttachmentsDefines::$ATTACHMENTS_SUBDIR;
        $upload_dir = JPATH_SITE . '/' . $upload_url;

        // Get all the attachment IDs
        /** @var \Joomla\Database\DatabaseDriver $db */
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('id')->from('#__attachments')->where('uri_type=' . $db->quote('file'));
        $db->setQuery($query);
        try {
            $attachments = $db->loadObjectList();
        } catch (\Exception $e) {
            $errmsg = $e->getMessage() . ' (ERR 72)';
            throw new \Exception($errmsg, 500);
        }
        if (count($attachments) == 0) {
            return Text::_('ATTACH_NO_ATTACHMENTS_WITH_FILES');
        }
        $IDs = array();
        foreach ($attachments as $attachment) {
            $IDs[] = $attachment->id;
        }

        // Get the parent plugin manager
        PluginHelper::importPlugin('attachments');
        $apm = \JMCameron\Plugin\AttachmentsPluginFramework\AttachmentsPluginManager::getAttachmentsPluginManager();

        /** @var \Joomla\CMS\MVC\Factory\MVCFactory $mvc */
        $mvc = Factory::getApplication()
            ->bootComponent("com_attachments")
            ->getMVCFactory();
        $attachment = $mvc->createTable('Attachment', 'Administrator');

        $msg = '';

        $numUpdated = 0;
        $numMissing = 0;
        foreach ($IDs as $id) {
            $attachment->load($id);

            // Get the actual parent id for this attachment
            // (Needed because orphaned parent_id is null, which the Table loads as 1)
            $query = $db->getQuery(true);
            $query->select('parent_id')->from('#__attachments')->where('id = ' . (int)$id);
            $db->setQuery($query, 0, 1);
            try {
                $parent_id = $db->loadResult();
            } catch (\Exception $e) {
                $errmsg = Text::sprintf(
                    'ATTACH_ERROR_INVALID_PARENT_S_ID_N',
                    $attachment->parent_entity,
                    $parent_id
                ) . ' (ERR 73)';
                throw new \Exception($errmsg, 500);
            }

            // Construct the updated system filename
            $old_filename_sys = $attachment->filename_sys;

            // Get info about the system filename
            $finfo = AttachmentsUpdate::checkFilename($old_filename_sys);
            $basename = $finfo->basename;

            // Reconstruct the current system filename (in case of migrations)
            $current_filename_sys = JPATH_SITE . '/' . $finfo->relfile;

            // Get the parent object
            $parent = $apm->getAttachmentsPlugin($attachment->parent_type);

            if (!File::exists($current_filename_sys)) {
                $msg .= Text::sprintf(
                    'ATTACH_ERROR_MISSING_ATTACHMENT_FILE_S',
                    $current_filename_sys
                ) . "<br/>";
                $numMissing++;
            } elseif (
                !is_numeric($parent_id) ||
                     !$parent->parentExists($attachment->parent_id, $attachment->parent_entity)
            ) {
                $msg .= Text::sprintf(
                    'ATTACH_ERROR_MISSING_PARENT_FOR_ATTACHMENT_S',
                    $current_filename_sys
                ) . "<br/>";
                $numMissing++;
            } else {
                // Construct the new system filename and url (based on entities, etc)
                $newdir = $parent->getAttachmentPath($attachment->parent_entity, $attachment->parent_id, null);
                $new_path = $upload_dir . '/' . $newdir;

                if ($finfo->oldstyle && $finfo->prefix) {
                    $new_filename_sys = $new_path . $finfo->basename_no_prefix;
                    $attachment->filename = $finfo->basename_no_prefix;
                    $new_url = str_replace(
                        DIRECTORY_SEPARATOR,
                        '/',
                        $upload_url . '/' . $newdir . $finfo->basename_no_prefix
                    );
                } else {
                    $new_filename_sys = $new_path . $basename;
                    $new_url = str_replace(DIRECTORY_SEPARATOR, '/', $upload_url . '/' . $newdir . $basename);
                }


                // If we are on windows, fix the filename and URL
                if (DIRECTORY_SEPARATOR != '/') {
                    $new_filename_sys = str_replace('/', DIRECTORY_SEPARATOR, $new_filename_sys);
                    $new_url = str_replace(DIRECTORY_SEPARATOR, '/', $new_url);
                }

                // Make sure the target directory exists
                if (!File::exists($new_path)) {
                    if (!Folder::create($new_path)) {
                        $errmsg = Text::sprintf('ATTACH_ERROR_UNABLE_TO_SETUP_UPLOAD_DIR_S', $new_path) . ' (ERR 74)';
                        throw new \Exception($errmsg, 500);
                    }
                    AttachmentsHelper::writeEmptyIndex($new_path);
                }

                // Move the file!
                if (!File::move($current_filename_sys, $new_filename_sys)) {
                    $errmsg = Text::sprintf(
                        'ATTACH_ERROR_RENAMING_FILE_S_TO_S',
                        $old_filename_sys,
                        $new_filename_sys
                    ) . ' (ERR 75)';
                    throw new \Exception($errmsg, 500);
                }

                // Verify the new system filename exists!
                if (!File::exists($new_filename_sys)) {
                    $errmsg = Text::sprintf(
                        'ATTACH_ERROR_NEW_SYSTEM_FILENAME_S_NOT_FOUND',
                        $new_filename_sys
                    ) . ' (ERR 76)';
                    throw new \Exception($errmsg, 500);
                }

                // Update the record
                $attachment->filename_sys = $new_filename_sys;
                $attachment->url = $new_url;
                if (!$attachment->store()) {
                    $errmsg = $attachment->getError() . ' (ERR 77)';
                    throw new \Exception($errmsg, 500);
                }

                $numUpdated++;
            }
        }

        // Add warning if there are problem files
        if ($numMissing > 0) {
            $msg = Text::sprintf('ATTACH_ERROR_N_FILES_MISSING', $numMissing) . "<br/>" . $msg . "&nbsp;<br/>";
        }

        return $msg . Text::sprintf(
            'ATTACH_REGENERATED_SYSTEM_FILENAMES_FOR_N_ATTACHMENTS',
            $numUpdated
        );
    }


    /**
     * Remove spaces from the system filenames for all attachments
     *
     * The spaces are replaces with underscores '_'
     */
    public static function removeSpacesFromSystemFilenames()
    {
        static::checkAccess();

        // Define where the attachments go
        $upload_dir = JPATH_SITE . '/' . AttachmentsDefines::$ATTACHMENTS_SUBDIR;

        // Get all the attachment IDs
        /** @var \Joomla\Database\DatabaseDriver $db */
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('id')->from('#__attachments')->where('uri_type=' . $db->quote('file'));
        $db->setQuery($query);
        try {
            $attachments = $db->loadObjectList();
        } catch (\Exception $e) {
            $errmsg = $e->getMessage() . ' (ERR 78)';
            throw new \Exception($errmsg, 500);
        }
        if (count($attachments) == 0) {
            return Text::_('ATTACH_NO_ATTACHMENTS_WITH_FILES');
        }
        $IDs = array();
        foreach ($attachments as $attachment) {
            $IDs[] = $attachment->id;
        }

        // Update the system filenames for all the attachments
        /** @var \Joomla\CMS\MVC\Factory\MVCFactory $mvc */
        $mvc = Factory::getApplication()
            ->bootComponent("com_attachments")
            ->getMVCFactory();
        $attachment = $mvc->createTable('Attachment', 'Administrator');
        $numUpdated = 0;

        foreach ($IDs as $id) {
            $attachment->load($id);

            // Make sure the file exists
            $old_filename_sys = $attachment->filename_sys;
            if (!File::exists($old_filename_sys)) {
                echo Text::sprintf('ATTACH_ERROR_FILE_S_NOT_FOUND_ON_SERVER', $old_filename_sys);
                exit();
            }

            // Construct the new system filename
            $filename_info = pathinfo($old_filename_sys);
            $basename = $filename_info['basename'];
            $filename_sys = $upload_dir . '/' . $basename;
            $new_basename = str_replace(' ', '_', $basename);
            $new_filename_sys = $filename_info['dirname'] . '/' . $new_basename;

            // If the filename has not changed, do not change anything
            if ($new_filename_sys == $old_filename_sys) {
                continue;
            }

            // Rename the file
            if (!File::move($old_filename_sys, $new_filename_sys)) {
                echo Text::sprintf(
                    'ATTACH_ERROR_RENAMING_FILE_S_TO_S',
                    $old_filename_sys,
                    $new_filename_sys
                );
                exit();
            }

            // Construct the new URL (figure it out from the system filename)
            $attachments_dir = str_replace(JPATH_SITE, '', $filename_info['dirname']);
            $dirend_chars = DIRECTORY_SEPARATOR . '/';
            $attachments_dir = StringHelper::trim($attachments_dir, $dirend_chars);
            $attachments_dir = str_replace(DIRECTORY_SEPARATOR, '/', $attachments_dir);
            $new_url = $attachments_dir . '/' . $new_basename;

            // Update the record
            $attachment->filename_sys = $new_filename_sys;
            $attachment->filename = $new_basename;
            $attachment->url = $new_url;

            if (!$attachment->store()) {
                $errmsg = $attachment->getError() . ' (ERR 79)';
                throw new \Exception($errmsg, 500);
            }

            $numUpdated++;
        }

        return Text::sprintf('ATTACH_UPDATED_N_ATTACHMENTS', $numUpdated);
    }


    /**
     * Update the file sizes for all attachments (only applies to files)
     */
    public static function updateFileSizes()
    {
        static::checkAccess();

        // Get the component parameters
        $params = ComponentHelper::getParams('com_attachments');

        // Define where the attachments go
        $upload_dir = JPATH_SITE . '/' . AttachmentsDefines::$ATTACHMENTS_SUBDIR;

        // Get all the attachment IDs
        /** @var \Joomla\Database\DatabaseDriver $db */
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('id')->from('#__attachments')->where('uri_type=' . $db->quote('file'));
        $db->setQuery($query);
        try {
            $attachments = $db->loadObjectList();
        } catch (\Exception $e) {
            $errmsg = $e->getMessage() . ' (ERR 80)';
            throw new \Exception($errmsg, 500);
        }
        if (count($attachments) == 0) {
            return Text::_('ATTACH_NO_ATTACHMENTS_WITH_FILES');
        }
        $IDs = array();
        foreach ($attachments as $attachment) {
            $IDs[] = $attachment->id;
        }

        // Update the system filenames for all the attachments
        /** @var \Joomla\CMS\MVC\Factory\MVCFactory $mvc */
        $mvc = Factory::getApplication()
            ->bootComponent("com_attachments")
            ->getMVCFactory();
        $attachment = $mvc->createTable('Attachment', 'Administrator');
        $numUpdated = 0;
        foreach ($IDs as $id) {
            $attachment->load($id);

            // Update the file size
            $attachment->file_size = filesize($attachment->filename_sys);

            // Update the record
            if (!$attachment->store()) {
                $errmsg = $attachment->getError() . ' (ERR 81)';
                throw new \Exception($errmsg, 500);
            }

            $numUpdated++;
        }

        return Text::sprintf('ATTACH_UPDATED_FILE_SIZES_FOR_N_ATTACHMENTS', $numUpdated);
    }

    /**
     * Check all files and make sure they exist
     */
    public static function checkFilesExistence()
    {
        static::checkAccess();

        $msg = '';

        // Get the component parameters
        $params = ComponentHelper::getParams('com_attachments');

        // Get all the attachment IDs
        /** @var \Joomla\Database\DatabaseDriver $db */
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('id')->from('#__attachments')->where('uri_type=' . $db->quote('file'));
        $db->setQuery($query);
        try {
            $attachments = $db->loadObjectList();
        } catch (\Exception $e) {
            $errmsg = $e->getMessage() . ' (ERR 82)';
            throw new \Exception($errmsg, 500);
        }
        if (count($attachments) == 0) {
            return Text::_('ATTACH_NO_ATTACHMENTS_WITH_FILES');
        }
        $IDs = array();
        foreach ($attachments as $attachment) {
            $IDs[] = $attachment->id;
        }

        // Update the system filenames for all the attachments
        /** @var \Joomla\CMS\MVC\Factory\MVCFactory $mvc */
        $mvc = Factory::getApplication()
            ->bootComponent("com_attachments")
            ->getMVCFactory();
        $attachment = $mvc->createTable('Attachment', 'Administrator');
        $numMissing = 0;
        $numChecked = 0;
        foreach ($IDs as $id) {
            $attachment->load($id);

            if (!File::exists($attachment->filename_sys)) {
                $msg .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' .
                    $attachment->filename_sys . '<br >';
                $numMissing++;
            }

            $numChecked++;
        }

        if ($msg) {
            $msg = ':<br />' . $msg;
        }
        $msg = Text::sprintf('ATTACH_CHECKED_N_ATTACHMENT_FILES_M_MISSING', $numChecked, $numMissing) . $msg;

        return $msg;
    }



    /**
     * Validate all URLS and update their "valid" status
     */
    public static function validateUrls()
    {
        static::checkAccess();

        // Get the component parameters
        $params = ComponentHelper::getParams('com_attachments');

        // Get all the attachment IDs
        /** @var \Joomla\Database\DatabaseDriver $db */
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('id')->from('#__attachments')->where('uri_type=' . $db->quote('url'));
        $db->setQuery($query);
        try {
            $attachments = $db->loadObjectList();
        } catch (\Exception $e) {
            $errmsg = $e->getMessage() . ' (ERR 83)';
            throw new \Exception($errmsg, 500);
        }
        if (count($attachments) == 0) {
            return Text::_('ATTACH_NO_ATTACHMENTS_WITH_URLS');
        }
        $IDs = array();
        foreach ($attachments as $attachment) {
            $IDs[] = $attachment->id;
        }

        // Update the system filenames for all the attachments
        /** @var \Joomla\CMS\MVC\Factory\MVCFactory $mvc */
        $mvc = Factory::getApplication()
            ->bootComponent("com_attachments")
            ->getMVCFactory();
        $attachment = $mvc->createTable('Attachment', 'Administrator');
        $numUpdated = 0;
        $numChecked = 0;
        foreach ($IDs as $id) {
            $attachment->load($id);

            $a = new \stdClass();

            AttachmentsHelper::getUrlInfo($attachment->url, $a, false, false);

            if ($attachment->url_valid != $a->url_valid) {
                $attachment->url_valid = $a->url_valid;

                // Maybe update the file info with fresh info
                if ($a->url_valid) {
                    $attachment->file_size = $a->file_size;
                    $attachment->file_type = $a->file_type;
                }

                // Update the record
                if (!$attachment->store()) {
                    $errmsg = $attachment->getError() . ' (ERR 84)';
                    throw new \Exception($errmsg, 500);
                }
                $numUpdated++;
            }
            $numChecked++;
        }

        return Text::sprintf('ATTACH_VALIDATED_N_URL_ATTACHMENTS_M_CHANGED', $numChecked, $numUpdated);
    }


    /**
     * Validate all URLS and update their "valid" status
     */
    public static function installAttachmentsPermissions($verbose = true)
    {
        static::checkAccess();

        $app = Factory::getApplication();

        // Get the root rules
        $root = Table::getInstance('asset');
        $root->loadByName('root.1');
        $root_rules = new Rules($root->rules);

        // Define the new rules
        $new_rules = new Rules(AttachmentsDefines::$DEFAULT_ATTACHMENTS_ACL_PERMISSIONS);

        // Merge the rules into default rules and save it
        $root_rules->merge($new_rules);
        $root->rules = (string)$root_rules;
        if ($root->store()) {
            if ($verbose) {
                $app->enqueueMessage(Text::_('ATTACH_INSTALLED_DEFAULT_ATTACHMENTS_ASSET_RULES'), 'message');
            }
        } else {
            if ($verbose) {
                $app->enqueueMessage(Text::_('ATTACH_INSTALLING_DEFAULT_ATTACHMENTS_ASSET_RULES_FAILED'), 'message');
            }
        }
    }
}
