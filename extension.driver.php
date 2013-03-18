<?php
/**
 * (c) 2012
 * Author: Giel Berkers
 * Date: 9-7-12
 * Time: 15:31
 */

Class extension_templates extends Extension
{
	public function getSubscribedDelegates()
	{
		return array(
			array(
				'page' => '/publish/',
				'delegate' => 'EntryPreDelete',
				'callback' => 'entryPreDelete'
			),
			array(
				'page' => '/backend/',
				'delegate' => 'AppendPageAlert',
				'callback' => 'appendPageAlert'
			),
			array(
				'page' => '/backend/',
				'delegate' => 'ExtensionsAddToNavigation',
				'callback' => 'addToNavigation'
			)
		);
	}

	public function install()
	{
		Symphony::Database()->query("
			CREATE TABLE IF NOT EXISTS `tbl_fields_templates` (
				`id` int(11) unsigned NOT NULL auto_increment,
				`field_id` int(11) unsigned NOT NULL,
				`allowed_templates` VARCHAR(255) NULL,
				`migration` int(1) NOT NULL,
				PRIMARY KEY  (`id`),
				KEY `field_id` (`field_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
		");

		return true;
	}

	public function entryPreDelete($context)
	{
		$ids = $context['entry_id'];
		$new_ids = array();
		foreach($ids as $id)
		{
			// Check if this section has a templates field:
			$section_id = intval(Symphony::Database()->fetchVar('section_id', 0,
				sprintf('SELECT `section_id` FROM `tbl_entries` WHERE `id` = %d;', $id)));
			if(Symphony::Database()->fetchVar('total', 0,
				sprintf('SELECT COUNT(*) AS `total` FROM `tbl_fields` WHERE `parent_section` = %d AND `type` = \'templates\';', $section_id)) > 0)
			{
				// Yes!
				// Check if this entry has child pages:
				$fields = Symphony::Database()->fetchRow(0,
					sprintf('SELECT * FROM `tbl_fields` WHERE `parent_section` = %d AND `type` = \'templates\';', $section_id));

				$page_id = Symphony::Database()->fetchVar('page_id', 0,
					sprintf('SELECT `page_id` FROM `tbl_entries_data_%d` WHERE `entry_id` = %d', $fields['id'], $id));

				// Check if this page has children:
				$children = PageManager::fetchChildPages($page_id);
				if(count($children) == 0)
				{
					// No children found, this page can be deleted:
					$new_ids[] = $id;
				} else {
					// This page has children, don't delete it:
					// Administration::instance()->Page->pageAlert(__('Some pages have children and cannot be deleted.'), Alert::ERROR);
					// Administration::instance()->Page->Alert(__('Some pages have children and cannot be deleted.'), Alert::ERROR);
					Session::write('templates_error', __('Some pages have children and cannot be deleted.'));
				}

			} else {
				// Doesn't have templates field, just add as usual:
				$new_ids[] = $id;
			}
		}
		$context['entry_id'] = $new_ids;
	}

	public function appendPageAlert($context)
	{
		if(Session::read('templates_error'))
		{
			Administration::instance()->Page->pageAlert(Session::read('templates_error'), Alert::ERROR);
			Session::destroy('templates_error');
		}
	}

	public function addToNavigation($context)
	{
		// Should be first:
		array_unshift($context['navigation'], array(
			'name' => __('Pages'),
			'type' => 'content',
			'index' => 0,
			'link'    => '/extension/templates/publish/',
			'children' => array(
				array(
					'link'    => '/extension/templates/publish/',
					'name'    => __('Pages'),
					'visible' => 'yes'
				)
			)
		));
	}

	public function uninstall()
	{
		if (parent::uninstall() == true) {
			Symphony::Database()->query("DROP TABLE `tbl_fields_templates`;");
			return true;
		}

		return false;
	}

}
