<?php
/**
 * (c) 2012
 * Author: Giel Berkers
 * Date: 10-7-12
 * Time: 12:16
 */

require_once(TOOLKIT . '/class.administrationpage.php');
require_once(TOOLKIT . '/class.entrymanager.php');
require_once(TOOLKIT . '/class.sectionmanager.php');
require_once(CONTENT . '/content.publish.php');

class contentExtensionTemplatesPublish extends contentPublish
{
	public function __switchboard($type='view'){

		if($type == 'view')
		{

		$this->_context['section_handle'] = $this->_context[0];

		$this->addStylesheetToHead(URL . '/extensions/templates/assets/templates.css', 'screen');
		$this->addScriptToHead(URL . '/extensions/templates/assets/templates.js');

		// Reorder:
		if(isset($_GET['up']) || isset($_GET['down']))
		{
			$pages = PageManager::fetch(true, array('title'), array(), null, true);
			$new_pages = $this->sortPages($pages);

			// Switch sortorder:
			$can_proceed = false;
			if(isset($_GET['up']))
			{
				$count = 0;
				foreach($new_pages as $page)
				{
					if($page['id'] == $_GET['up'])
					{
						// Switch with the next item on this level:
						for($i=$count-1; $i>=0; $i--)
						{
							if($new_pages[$i]['indent'] == $page['indent'])
							{
								$page1 = PageManager::fetchPageByID($page['id']);
								$page2 = PageManager::fetchPageByID($new_pages[$i]['id']);

								$sortorder1 = $page1['sortorder'];
								$sortorder2 = $page2['sortorder'];

								PageManager::edit($page1['id'], array('sortorder'=>$sortorder2));
								PageManager::edit($page2['id'], array('sortorder'=>$sortorder1));

								redirect(SYMPHONY_URL . '/extension/templates/publish/');

								break;
							}
						}
					}
					$count++;
				}
			} elseif(isset($_GET['down'])) {
				$count = 0;
				foreach($new_pages as $page)
				{
					if($page['id'] == $_GET['down'])
					{
						// Switch with the next item on this level:
						for($i=$count + 1; $i<count($new_pages); $i++)
						{
							if($new_pages[$i]['indent'] == $page['indent'])
							{
								$page1 = PageManager::fetchPageByID($page['id']);
								$page2 = PageManager::fetchPageByID($new_pages[$i]['id']);

								$sortorder1 = $page1['sortorder'];
								$sortorder2 = $page2['sortorder'];

								PageManager::edit($page1['id'], array('sortorder'=>$sortorder2));
								PageManager::edit($page2['id'], array('sortorder'=>$sortorder1));

								redirect(SYMPHONY_URL . '/extension/templates/publish/');

								break;
							}
						}
					}
					$count++;
				}
			}
		}

		$this->__viewIndex();

		} elseif($type == 'action') {
			$this->__actionIndex();
		}
	}

	public function __viewIndex()
	{
		$this->setPageType('table');
		$this->setTitle(__('%1$s &ndash; %2$s', array(__('Pages'), __('Symphony'))));

		$this->Form->setAttribute('action', SYMPHONY_URL . '/extension/templates/publish/');

		$create_new_options = array(
			array(
				0, true, __('Create a new page')
			)
		);
		$fields = FieldManager::fetch(null, null, 'ASC', 'sortorder', 'templates');
		$field_ids = array_keys($fields);
		$all_used_templates = array();
		$displayColumns = array();
		foreach($field_ids as $field_id)
		{
			$field = FieldManager::fetch($field_id);
			// print_r($field->get('parent_section'));
			$ids = explode(',', $field->get('allowed_templates'));
			// $all_used_templates = array_merge($all_used_templates, $ids);
			$section = SectionManager::fetch($field->get('parent_section'));
			$ids = array_filter($ids);
			foreach($ids as $page_id)
			{
				$page = PageManager::fetchPageByID($page_id);

				$all_used_templates[] = array(
					'page' => $page_id,
					'name' => $page['title'],
					'section' => $section->get('handle')
				);
			}

			$a = FieldManager::fetch(null, $section->get('id'), 'ASC', 'sortorder', null, null, 'AND `show_column` = \'yes\'');
			foreach($a as $showColumn)
			{
				if($showColumn->get('type') != 'templates') {
					$displayColumns[] = $showColumn;
				}
			}

		}

		foreach($all_used_templates as $used_template)
		{
			$create_new_options[] = array(
				SYMPHONY_URL.'/publish/'.$used_template['section'].'/new/?t='.$used_template['page'], 
				false, 
				$used_template['name']
			);
		}

		$subheading_buttons = array(
			Widget::Select('templates_new', $create_new_options)
		);

		$this->appendSubheading(__('Pages'), $subheading_buttons);

		$aTableHead = array(
			array(__('Page'), 'col')
		);

		// Add the fields that need to be displayed:
		foreach($displayColumns as $column)
		{
			$aTableHead[] = array($column->get('label'), 'col');
		}

		$aTableHead[] = array(__('Template'), 'col');
		$aTableHead[] = array(__('Actions'), 'col', array('class'=>'templates-actions'));

		// Table Body
		$aTableBody = array();

		$pages = PageManager::fetch(true, array('title'), array(), null, true);
		$new_pages = $this->sortPages($pages);

		// Provide pages with links to their proper sections/entries:
		// Find sections which use the templates-field:
		$fieldIDs = Symphony::Database()->fetch('SELECT `id`, `parent_section` FROM `tbl_fields` WHERE `type` = \'templates\';');
		$pageIDs  = array();
		foreach($fieldIDs as $row)
		{
			$results = Symphony::Database()->fetch(
				sprintf('SELECT `entry_id`, `template`, `page_id` FROM `tbl_entries_data_%d`;', $row['id'])
			);
			$section = SectionManager::fetch($row['parent_section']);

			foreach($results as $result)
			{
				$result['section_handle'] = $section->get('handle');
				$pageIDs[$result['page_id']] = $result;
			}
		}

		$count = 0;

		foreach($new_pages as $page)
		{
			// Ignore 'template' and 'template_hide':
			if(in_array('template', $page['type']) || in_array('template_hide', $page['type'])) { continue; }

			$upLink   = false;
			$downLink = false;

			$className = null;

			if(isset($pageIDs[$page['id']]))
			{
				// There exists an entry related to this page:
				$templatePage = PageManager::fetchPageByID($pageIDs[$page['id']]['template']);
				$tableData = array(
					Widget::TableData(Widget::Anchor($page['title'], sprintf('%s/publish/%s/edit/%d/',
						SYMPHONY_URL, $pageIDs[$page['id']]['section_handle'], $pageIDs[$page['id']]['entry_id']))->generate()));

				foreach($displayColumns as $column)
				{
					$entry = EntryManager::fetch($pageIDs[$page['id']]['entry_id']);
					$field = FieldManager::fetch($column->get('id'));
					/* @var $field Field */
					$value = $field->prepareTableValue($entry[0]->getData($column->get('id')));
					$tableData[] = Widget::TableData($value);
				}

				$tableData[] = Widget::TableData($templatePage['title']);
				// Add a checkbox:
				$tableData[count($tableData) - 1]->appendChild(Widget::Input('items['.$page['id'].']', NULL, 'checkbox'));
			} else {
				// There doesn't exist an entry related to this page:
				$tableData = array(
					Widget::TableData($page['title']),
					Widget::TableData('-')
				);
				$className = 'non-editable';
			}

			// Check according to the indention:
			for($i = $count + 1; $i < count($new_pages); $i++)
			{
				if($new_pages[$i]['indent'] < $page['indent']) {
					break; // break the for-loop.
				}

				if($new_pages[$i]['indent'] == $page['indent']) {
					// Page can move down.
					$downLink = true;
				}
			}
			for($i = $count - 1; $i >= 0; $i--)
			{
				if($new_pages[$i]['indent'] < $page['indent']) {
					break; // break the for-loop.
				}
				if($new_pages[$i]['indent'] == $page['indent']) {
					// Page can move up.
					$upLink = true;
				}
			}

			// Up link:
			if($upLink)
			{
				$upLink = Widget::Anchor('<span>'.__('up').'</span>', Administration::instance()->getCurrentPageURL().'?up='.$page['id'],
					__('move up'), 'up')->generate().' ';
			} else {
				$upLink = '<span class="up"></span> ';
			}

			// Down link:
			if($downLink)
			{
				$downLink = Widget::Anchor('<span>'.__('down').'</span>', Administration::instance()->getCurrentPageURL().'?down='.$page['id'],
					__('move down'), 'down')->generate().' ';
			} else {
				$downLink = '<span class="down"></span> ';
			}

			$tableData[] = Widget::TableData($upLink.$downLink.
				Widget::Anchor('<span>'.__('view').'</span>', URL . '/' . PageManager::resolvePagePath($page['id']) . '/', __('view'),
					'view', null, array('target'=>'_blank'))->generate(), 'templates-actions');

			$aTableBody[] = Widget::TableRow($tableData, $className);

			$count++;
		}

		$table = Widget::Table(
			Widget::TableHead($aTableHead),
			NULL,
			Widget::TableBody($aTableBody),
			'selectable'
		);

		$this->Form->appendChild($table);

		$tableActions = new XMLElement('div');
		$tableActions->setAttribute('class', 'actions');

		$options = array(
			array(NULL, false, __('With Selected...')),
			array('delete', false, __('Delete'), 'confirm', null, array(
				'data-message' => __('Are you sure you want to delete the selected entries?')
			))
		);

		$tableActions->appendChild(Widget::Apply($options));
		$this->Form->appendChild($tableActions);
	}

	private function sortEntries($new_entries, $entries, $pages, $templatesFieldID, $indent = 0)
	{
		foreach($pages as $page)
		{
			$matchFound = false;
			foreach($entries as $entry)
			{
				$data = $entry->getData($templatesFieldID);
				if($data['page_id'] == $page['id'])
				{
					// match!
					$prefix = '';
					for($i = 0; $i < $indent; $i++) { $prefix .= '&emsp;'; }
					if($indent != 0) { $prefix.='›&emsp;'; }
					$data['title'] = $prefix.$data['title'];
					$entry->setData($templatesFieldID, $data);
					$entry->set('indent', $indent);
					$new_entries[] = $entry;
					$matchFound = true;
				}
			}
			if(!$matchFound)
			{

			}
			if(count($page['children']) > 0)
			{
				$new_entries = $this->sortEntries($new_entries, $entries, $page['children'], $templatesFieldID, $indent + 1);
			}
		}
		return $new_entries;
	}

	private function sortPages($pages, $indent = 0, $new_pages = array())
	{
		foreach($pages as $page)
		{
			if(in_array('template', $page['type']) || in_array('template_hide', $page['type'])) { continue; }
			$prefix = '';
			for($i = 0; $i < $indent; $i++) { $prefix .= '&emsp;'; }
			if($indent != 0) { $prefix.='›&emsp;'; }
			$page['title'] = $prefix.$page['title'];
			$page['indent'] = $indent;
			$new_pages[] = $page;
			if(count($page['children']) > 0)
			{
				$new_pages = $this->sortPages($page['children'], $indent + 1, $new_pages);
			}
		}
		return $new_pages;
	}

}
