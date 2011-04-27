<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

// Set up a few convenience items
$params = $this->params;
$secure = $params->get('secure',false);
$lists = $this->lists;
$list_for_parents = $lists['list_for_parents'];

$listOrder	= $this->escape($this->state->get('list.ordering'));
$listDirn	= $this->escape($this->state->get('list.direction'));

?>
<tr>
	 <th class="at_published" width="20">
	<input type="checkbox" name="toggle" value=""
		   onclick="checkAll(<?php echo count( $this->items ); ?>);" />
	 </th>
	 <th class="at_published" width="5%" nowrap="nowrap"><?php echo JHTML::_('grid.sort', JText::_('PUBLISHED'),
							 'a.state', $listDirn, $listOrder ) ?></th>
	 <th class="at_filename"><?php echo JHTML::_('grid.sort', JText::_('ATTACHMENT_FILENAME_URL'),
							 'a.filename', $listDirn, $listOrder ) ?></th>
	 <th class="at_description"><?php echo JHTML::_('grid.sort', JText::_('DESCRIPTION'),
							 'a.description', $listDirn, $listOrder ) ?></th>
	 <?php if ($params->get('user_field_1_name')): ?>
	   <th class="at_user_field"><?php echo JHTML::_('grid.sort', $params->get('user_field_1_name', ''),
													 'a.user_field_1', $listDirn, $listOrder ) ?></th>
	 <?php endif; ?>
	 <?php if ($params->get('user_field_2_name')): ?>
	   <th class="at_user_field"><?php echo JHTML::_('grid.sort', $params->get('user_field_2_name', ''),
													 'a.user_field_2', $listDirn, $listOrder ) ?></th>
	 <?php endif; ?>
	 <?php if ($params->get('user_field_3_name')): ?>
	   <th class="at_user_field"><?php echo JHTML::_('grid.sort', $params->get('user_field_3_name', ''),
													 'a.user_field_3', $listDirn, $listOrder ) ?></th>
	 <?php endif; ?>
	 <th class="at_file_type"><?php echo JHTML::_('grid.sort', JText::_('FILE_TYPE'),
							 'a.file_type', $listDirn, $listOrder ) ?></th>
	 <th class="at_file_size"><?php echo JHTML::_('grid.sort', JText::_('FILE_SIZE_KB'),
							 'a.file_size', $listDirn, $listOrder ) ?></th>
	 <th class="at_uploader"><?php echo JHTML::_('grid.sort', JText::_('UPLOADER'),
							 'u.name', $listDirn, $listOrder ) ?></th>
	 <th class="at_created"><?php echo JHTML::_('grid.sort', JText::_('CREATED'),
								'a.created', $listDirn, $listOrder ) ?></th>
	 <th class="at_mod_date"><?php echo JHTML::_('grid.sort', JText::_('LAST_MODIFIED'),
							 'a.modified', $listDirn, $listOrder ) ?></th>
	 <?php if ( $secure ): ?>
	   <th class="at_downloads"><?php echo JHTML::_('grid.sort', JText::_('DOWNLOADS'),
													'a.download_count', $listDirn, $listOrder ) ?></th>
	 <?php endif; ?>
</tr>

   