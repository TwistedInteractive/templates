<?php
/**
 * (c) 2012
 * Author: Giel Berkers
 * Date: 9-7-12
 * Time: 15:34
 */

Class fieldTemplates extends Field
{
	public function __construct()
	{
		parent::__construct();
		$this->_name = __('Templates');
		$this->_required = true;

		// Default settings
		$this->set('show_column', 'yes');
		$this->set('required', 'yes');
	}

	public function canFilter(){
		return true;
	}

	public function canToggle(){
		// Check if this section uses a pagesfield and is in migration mode:
		if($this->get('migration') == 1)
		{
			$fields = FieldManager::fetchFieldsSchema($this->get('parent_section'));
			foreach($fields as $field)
			{
				if($field['type'] == 'pages') {
					return true;
				}
			}
		}
		return false;
	}

	public function getToggleStates()
	{
		$states = array();
		// According to pages field:
		$fields = FieldManager::fetchFieldsSchema($this->get('parent_section'));
		foreach($fields as $field)
		{
			if($field['type'] == 'pages') {
				$states[$field['id']] = __('According to \'%s\'', array($field['element_name']));
			}
		}
		return $states;
	}

	public function toggleFieldData($data, $newState, $entryId)
	{
		// $newState = ID of reference pages field
		$entry = EntryManager::fetch($entryId);
		$pagesData = $entry[0]->getData($newState);

		// Migration, only create the link:
		$page = PageManager::fetchPageByID($pagesData['page_id']);

		$data['page_id'] = $pagesData['page_id'];
		$data['title'] = $pagesData['title'];
		$data['parent'] = $page['parent'];

		return $data;
	}

	public function displaySettingsPanel(XMLElement &$wrapper, $errors = null)
	{
		// Create header
		$location = ($this->get('location') ? $this->get('location') : 'main');
		$header = new XMLElement('header', NULL, array('class' => $location, 'data-name' => $this->name()));
		$label = (($this->get('label')) ? $this->get('label') : __('New Field'));
		$header->appendChild(new XMLElement('h4', '<strong>' . $label . '</strong> <span class="type">' . $this->name() . '</span>'));
		$wrapper->appendChild($header);

		// Create content
		$wrapper->appendChild(Widget::Input('fields['.$this->get('sortorder').'][type]', $this->handle(), 'hidden'));
		if($this->get('id')) $wrapper->appendChild(Widget::Input('fields['.$this->get('sortorder').'][id]', $this->get('id'), 'hidden'));

		// Get used templates:
		$fields = FieldManager::fetch(null, null, 'ASC', 'sortorder', 'templates');
		$field_ids = array_keys($fields);
		$all_used_templates = array();
		foreach($field_ids as $field_id)
		{
			if($field_id != $this->get('id'))
			{
				$field = FieldManager::fetch($field_id);
				$ids = explode(',', $field->get('allowed_templates'));
				$all_used_templates = array_merge($all_used_templates, $ids);
			}
		}

		$wrapper->appendChild($this->buildSummaryBlock($errors));
		$allowed_templates = explode(',', $this->get('allowed_templates'));
		$label = Widget::Label(__('Allowed templates'));
		$options = array();
		$pages = PageManager::fetchPageByType('template');
		if(isset($pages['id'])) { $pages = array($pages); }
		foreach($pages as $page)
		{
			$options[] = array(
				$page['id'], 
				in_array($page['id'], $allowed_templates), 
				General::sanitize($page['title']),
				null,
				null,
				(in_array($page['id'], $all_used_templates) ? array('disabled'=>'disabled') : null)
			);
		}		
		$label->appendChild(Widget::Select('fields['.$this->get('sortorder').'][templates][]', $options, array('multiple'=>'multiple')));

		$wrapper->appendChild($label);

		$div = new XMLElement('div', NULL, array('class' => 'two columns'));
		$div->appendChild(
			new XMLElement('label', __('%s Migration mode (allows you to link already existing entries to pages)',
					array(Widget::Input('fields['.$this->get('sortorder').'][migration]', 'yes', 'checkbox', (
						$this->get('migration') == 1 ? array('checked' => 'checked') : null
					))->generate())
				), array('class' => 'column')
			)
		);
		$wrapper->appendChild($div);

		$div = new XMLElement('div', NULL, array('class' => 'two columns'));
		$this->appendShowColumnCheckbox($div);
		$this->appendRequiredCheckbox($div);
		$wrapper->appendChild($div);
	}

	public function commit()
	{
		parent::commit();
		$id = $this->get('id');
		$templates = !is_array($this->get('templates')) ? '' : implode(',', $this->get('templates'));
		$migrate   = $this->get('migration') ? 1 : 0;
		return FieldManager::saveSettings($id, array('allowed_templates'=>$templates, 'migration'=>$migrate));
	}

	private function populatePages($options, $pages, $parent, $indent = 0)
	{
		foreach($pages as $page){
			if(!in_array('template', $page['type']) && !in_array('template_hide', $page['type']))
			{
				$spaces = '';

				for($i = 0; $i < $indent; $i ++) { $spaces .= '&emsp;'; }
				if($indent > 0) { $spaces .= '›&emsp;'; }
				$options[] = array($page['id'], $page['id'] === $parent, $spaces.General::sanitize($page['title']));
				if(count($page['children']) > 0)
				{
					$options = $this->populatePages($options, $page['children'], $parent, $indent + 1);
				}
			}
		}
		return $options;
	}


	public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null){
		Administration::instance()->Page->addScriptToHead(URL . '/extensions/templates/assets/templates.js');
		if($this->get('migration') == 1)
		{
			// Migration mode:
			// Only link existing entries to pages:
			$wrapper->addClass('migration');
			$options = array(array(null, true, null));

			$pages = PageManager::fetch(true, array('title'), array(), null, true);
			if(isset($pages['id'])) { $pages = array($pages); }

			$options = $this->populatePages($options, $pages, $data['page_id']);

			$fieldname = 'fields'.$fieldnamePrefix.'['.$this->get('element_name').'][pageid]'.$fieldnamePostfix;

			$label = Widget::Label(__('Linked page'));
			$label->appendChild(new XMLElement('strong', __('** MIGRATION MODE **')));
			$label->appendChild(Widget::Select($fieldname, $options));

			$wrapper->appendChild($label);
		} else {
			// The real deal, with overwriting of stuff and everything!
			// Page title:
			$fieldname = 'fields'.$fieldnamePrefix.'['.$this->get('element_name').'][title]'.$fieldnamePostfix;
			$label = Widget::Label(__('Page title'));
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', __('Optional')));
			$label->appendChild(Widget::Input($fieldname, $data['title']));
			$wrapper->appendChild($label);

			// Page parent:
			$options = array(array(null, true, null));

			$pages = PageManager::fetch(true, array('title'), array(), null, true);
			if(isset($pages['id'])) { $pages = array($pages); }

			$options = $this->populatePages($options, $pages, $data['parent']);

			$fieldname = 'fields'.$fieldnamePrefix.'['.$this->get('element_name').'][parent]'.$fieldnamePostfix;

			$label = Widget::Label(__('Parent page'));
			$label->appendChild(Widget::Select($fieldname, $options));

			$wrapper->appendChild($label);
		}

		// Template picker:
		$options = array();

		$allowed_templates = explode(',', $this->get('allowed_templates'));

		$pages = PageManager::fetchPageByID($allowed_templates);
		if(isset($pages['id'])) { $pages = array($pages); }

		foreach($pages as $page){
			$selected = isset($_GET['t']) ? $_GET['t'] == $page['id'] : $page['id'] === $data['template'];
			$options[] = array($page['id'], $selected, General::sanitize($page['title']));
		}

		$fieldname = 'fields'.$fieldnamePrefix.'['.$this->get('element_name').'][template]'.$fieldnamePostfix;

		$label = Widget::Label(__('Template'));
		$label->appendChild(Widget::Select($fieldname, $options));

		$wrapper->appendChild($label);

		// Page ID:
		$fieldname = 'fields'.$fieldnamePrefix.'['.$this->get('element_name').'][page_id]'.$fieldnamePostfix;
		$wrapper->appendChild(Widget::Input($fieldname, $data['page_id'], 'hidden'));
	}

	public function createTable(){
		return Symphony::Database()->query(
			"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
			  `id` int(11) unsigned NOT NULL auto_increment,
			  `entry_id` int(11) unsigned NOT NULL,
			  `title` varchar(255) default NULL,
			  `parent` varchar(255) default NULL,
			  `template` varchar(255) default NULL,
			  `page_id` varchar(255) default NULL,
			  PRIMARY KEY  (`id`),
			  KEY `entry_id` (`entry_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
		);
	}


	public function processRawFieldData($data, &$status, &$message=null, $simulate=false, $entry_id=NULL){
		$status = self::__OK__;

		if(!empty($data))
		{
			if($this->get('migration') == 1)
			{
				// Migration, only create the link:
				$page = PageManager::fetchPageByID($data['pageid']);

				$data['page_id'] = $data['pageid'];
				$data['title'] = $page['title'];
				$data['parent'] = $page['parent'];

				unset($data['pageid']);

				return $data;
			} else {

				// Copy the page:
				$page = PageManager::fetchPageByID($data['template']);

				if(!empty($data['parent']))
				{
					$parent = PageManager::fetchPageByID($data['parent']);
					$path   = (!empty($parent['path']) ? $parent['path'].'/' : '').$parent['handle'];
				} else {
					$path = null;
				}

				$fields = array(
					'parent' => $data['parent'],
					'title'  => General::sanitize($data['title']),
					'handle' => Lang::createHandle($data['title']),
					'data_sources' => $page['data_sources'],
					'events' => $page['events'],
					'params' => $page['params'],
					'path' => $path
				);

				if(empty($data['page_id']))
				{
					// Create new page:

					// Check if there already exists a page with the same title on this path:
					$where = array();
					$where[] = "p.handle = '" . $fields['handle'] . "'";
					$where[] = (is_null($fields['path'])) ? "p.path IS NULL" : "p.path = '" . $fields['path'] . "'";
					$duplicate = PageManager::fetch(false, array('*'), $where);

					if(empty($duplicate))
					{
						$new_page_id = PageManager::add($fields);
					}
				} else {
					$new_page_id = $data['page_id'];
					$old_page = PageManager::fetchPageByID($new_page_id);
					$src_file = PageManager::resolvePageFileLocation($old_page['path'], $old_page['handle']);

					// Check if the page exists:
					$where = array();
					$where[] = "p.id != {$data['page_id']}";
					$where[] = "p.handle = '" . $fields['handle'] . "'";
					$where[] = (is_null($fields['path'])) ? "p.path IS NULL" : "p.path = '" . $fields['path'] . "'";
					$duplicate = PageManager::fetch(false, array('*'), $where);

					if(empty($duplicate))
					{
						// Delete old template file:
						General::deleteFile($src_file);
						PageManager::edit($new_page_id, $fields);
					}
				}

				if(!empty($duplicate))
				{
					// $this->_errors['title'] = __('A page with that title already exists');
					Session::write('templates_error', __('A page with that title already exists.'));
					redirect(Administration::instance()->getCurrentPageURL());
				}

				// Copy the types, only when a new page is created:
				if(empty($data['page_id']))
				{
					$types = PageManager::fetchPageTypes($data['template']);
					$new_types = array();
					foreach($types as $type)
					{
						if($type != 'template' && $type != 'template_hide')
						{
							$new_types[] = $type;
						}
					}
					PageManager::addPageTypesToPage($new_page_id, $new_types);
				}

				// Create the XSL template file:
				$src_file = PageManager::createFilePath($page['path'], $page['handle']).'.xsl';
				$dst_file = PageManager::resolvePageFileLocation($fields['path'], $fields['handle']);

				$xsl = '<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:include href="'.$src_file.'" />
</xsl:stylesheet>';

				file_put_contents($dst_file, $xsl);
				// copy($src_file, $dst_file);

				$data['page_id'] = $new_page_id;

				return $data;
			}
		}
	}

	public function entryDataCleanup($entry_id, $data=NULL)
	{
		if(!is_array($entry_id))
		{
			$ids = array($entry_id);
		} else {
			$ids = $entry_id;
		}

		// Delete the pages:
		$can_proceed = true;
		foreach($ids as $entry_id)
		{
			if($can_proceed)
			{
				$page_id = intval(Symphony::Database()->fetchVar('page_id', 0,
					sprintf('SELECT `page_id` FROM `tbl_entries_data_%d` WHERE `entry_id` = %d;', $this->get('id'), $entry_id)
				));

				$can_proceed = PageManager::delete($page_id);
			}
		}

		if($can_proceed)
		{
			parent::entryDataCleanup($entry_id, $data);
		} else {
			return false;
		}
	}

	public function prepareTableValue($data, XMLElement $link = null, $entry_id = null) {
		$max_length = Symphony::Configuration()->get('cell_truncation_length', 'symphony');
		$max_length = ($max_length ? $max_length : 75);

		$value = strip_tags($data['title']);

		if(function_exists('mb_substr') && function_exists('mb_strlen')) {
			$value = (mb_strlen($value, 'utf-8') <= $max_length ? $value : mb_substr($value, 0, $max_length, 'utf-8') . '�');
		}
		else {
			$value = (strlen($value) <= $max_length ? $value : substr($value, 0, $max_length) . '�');
		}

		if (strlen($value) == 0) $value = __('None');

		// Convert special characters to HTML entities to prevent XSL missing entity errors
		$value = htmlspecialchars($value);

		if ($link) {
			$link->setValue($value);
			return $link->generate();
		}

		return $value;
	}

	public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation = false) {
		$field_id = $this->get('id');

		// REGEX filtering is a special case, and will only work on the first item
		// in the array. You cannot specify multiple filters when REGEX is involved.
		if (self::isFilterRegex($data[0])) {
			$this->buildRegexSQL($data[0], array('value'), $joins, $where);
		}

		// AND operation, iterates over `$data` and uses a new JOIN for
		// every item.
		else if ($andOperation) {
			foreach ($data as $value) {
				$this->_key++;
				$value = $this->cleanValue($value);
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND t{$field_id}_{$this->_key}.page_id = '{$value}'
				";
			}
		}

		// Default logic, this will use a single JOIN statement and collapse
		// `$data` into a string to be used inconjuction with IN
		else {
			foreach ($data as &$value) {
				$value = $this->cleanValue($value);
			}

			$this->_key++;
			$data = implode("', '", $data);
			$joins .= "
				LEFT JOIN
					`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
					ON (e.id = t{$field_id}_{$this->_key}.entry_id)
			";
			$where .= "
				AND t{$field_id}_{$this->_key}.page_id IN ('{$data}')
			";
		}

		return true;
	}

	public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null)
	{
		$wrapper->appendChild(
			new XMLElement($this->get('element_name'),
				($encode ? General::sanitize($this->prepareTableValue($data, null, $entry_id)) : $this->prepareTableValue($data, null, $entry_id)),
				array('id' => $data['page_id'])
			)
		);
	}

}