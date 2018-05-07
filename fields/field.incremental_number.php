<?php

	Class fieldincremental_number extends Field
	{

		function __construct(){
			parent::__construct();

			$this->_name = 'Incremental Number';
			$this->_required = true;
			$this->set('required', 'yes');
		}

		function isSortable(){
			return true;
		}

		function canFilter(){
			return true;
		}

		function allowDatasourceParamOutput(){
			return true;
		}

		function canPrePopulate(){
			return true;
		}

		public function displaySettingsPanel(XMLElement &$wrapper, $errors = NULL){
			parent::displaySettingsPanel($wrapper, $errors);

			$label = Widget::Label(__('Start Number'));
			$label->appendChild(WIDGET::Input('fields['.$this->get('sortorder').'][start_number]', $this->get('start_number')));
			$wrapper->appendChild($label);
			$this->appendShowColumnCheckbox($wrapper);
		}

		public function displayPublishPanel(XMLElement &$wrapper, $data = NULL, $flagWithError = NULL, $fieldnamePrefix = NULL, $fieldnamePostfix = NULL, $entry_id = NULL){
			$value = $data['value'];
			$label = Widget::Label($this->get('label'));

			$label->appendChild(
				Widget::Input(
					'fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix,
					(string) (strlen($value) != 0 ? $value : $this->getNewNumber()),
					'text',
					array('readonly' => 'readonly')
				)
			);

			if( $flagWithError != NULL ) $wrapper->appendChild(Widget::Error($label, $flagWithError));
			else $wrapper->appendChild($label);
		}

		public function processRawFieldData($data, &$status, &$message = NULL, $simulate = false, $entry_id = NULL){
			if( !$data ) $data = $this->getNewNumber();

			return parent::processRawFieldData($data, $status, $message, $simulate, $entry_id);
		}

		public function getNewNumber(){
			$year = date('Y');
			$next = $year . '-01';

			$last = Symphony::Database()->fetchVar("value", 0, "
				SELECT `value`
				FROM `tbl_entries_data_".$this->get('id')."`
				ORDER BY `value` DESC LIMIT 1
			");

			if(empty($last)) {
				return $next;
			}

			list($last_year, $last_number) = explode('-', $last);

			// Current year
			if($last_year == $year) {
				return $year . '-' . str_pad(intval($last_number) + 1, 2, '0', STR_PAD_LEFT);
			}

			// New year
			else {
				return $next;
			}
		}

		public function commit(){
			if( !parent::commit() ) return false;

			$id = $this->get('id');
			$value = $this->get('start_number');

			if( $id === false ) return false;

			$fields = array();

			$fields['field_id'] = $id;
			$fields['start_number'] = $value;

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");

			return Symphony::Database()->insert($fields, 'tbl_fields_'.$this->handle());

		}

		public function displayDatasourceFilterPanel(XMLElement &$wrapper, $data = NULL, $errors = NULL, $fieldnamePrefix = NULL, $fieldnamePostfix = NULL){
			$wrapper->appendChild(new XMLElement('h4', $this->get('label').' <i>'.$this->Name().'</i>'));
			$label = Widget::Label('Value');
			$label->appendChild(Widget::Input('fields[filter]'.($fieldnamePrefix ? '['.$fieldnamePrefix.']' : '').'['.$this->get('id').']'.($fieldnamePostfix ? '['.$fieldnamePostfix.']' : ''), ($data ? General::sanitize($data) : NULL)));
			$wrapper->appendChild($label);

			$wrapper->appendChild(new XMLElement('p', 'To filter by ranges, add <code>mysql:</code> to the beginning of the filter input. Use <code>value</code> for field name. E.G. <code>mysql: value &gt;= 1.01 AND value &lt;= {$price}</code>', array('class' => 'help')));

		}

		public function checkPostFieldData($data, &$message, $entry_id = NULL){
			$message = NULL;

			if($this->get('required') == 'yes' && strlen($data) == 0) {
				$message = __('This is a required field.');
				return self::__MISSING_FIELDS__;
			}

			if(strlen($data) > 0 && !preg_match('/^[0-9-]+$/', $data)) {
				$message = __('Must be a team ID.');
				return self::__INVALID_FIELDS__;
			}

			return self::__OK__;
		}

		public function createTable(){
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_".$this->get('id')."` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `value` int(11) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `value` (`value`)
				) TYPE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"

			);
		}

		function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation = false){
			if( preg_match('/^mysql:/i', $data[0]) ){

				$field_id = $this->get('id');

				$expression = str_replace(array('mysql:', 'value'), array('', " `t$field_id`.`value` "), $data[0]);

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND $expression ";
			}

			else parent::buildDSRetrievalSQL($data, $joins, $where, $andOperation);

			return true;
		}

	}
