<?php

/**
 * Attachments component
 *
 * @package Attachments_test
 * @subpackage Attachments_helper
 *
 * @copyright Copyright (C) 2007-2025 Jonathan M. Cameron, All Rights Reserved
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @link https://github.com/jmcameron/attachments
 * @author Jonathan M. Cameron
 */

use JMCameron\Component\Attachments\Site\Helper\AttachmentsHelper;

/** Load the PHPUnit test framework */
require_once 'PHPUnit/Framework/TestCase.php';

/** Load the CSV file iterator class */
require_once JPATH_TESTS . '/utils/CsvFileIterator.php';

/**
 * Work-around class to expose protected method for testing
 *
 * @package Attachments_test
 * @subpackage Attachments_helper
 */
class AttachmentsHelper2 extends AttachmentsHelper
{
    /**
     * Truncate the filename if it is longer than the maxlen
     * Do this by deleting necessary at the end of the base filename (before the extensions)
     *
     * @param string $raw_filename the input filename
     * @param int $maxlen the maximum allowed length (0 means no limit)
     *
     * @return the truncated filename
     */
    public static function truncate_filename($raw_filename, $maxlen)
    {
        return parent::truncate_filename($raw_filename, $maxlen);
    }
}


/**
 * Tests filename trunction
 *
 * @package Attachments_test
 * @subpackage Attachments_helper
 */
class HelperFilenameTruncationTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test filename trunction
     *
     * @dataProvider provider
     *
     * @param string @truncated_filename the expected result of the truncation
     * @param strint @full_filename the filename before truncating
     * @param int the maximum length for the filename
     */
    public function testFilenameTruncation($truncated_filename, $full_filename, $maxlen)
    {
        $maxlen = (int)$maxlen;

        $this->assertEquals(
            $truncated_filename,
            AttachmentsHelper2::truncate_filename($full_filename, $maxlen)
        );
    }

    /**
     * Get the test data from CSV file
     */
    public function provider()
    {
        return new CsvFileIterator(dirname(__FILE__) . '/testHelperFilenameTruncationData.csv');
    }
}
