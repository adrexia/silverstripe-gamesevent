<?php
/**
 * Adds new global settings.
 */

class GamesSiteConfig extends DataExtension {

	private static $has_one = array(
		'CurrentEvent'=>'Event'
	);

	private static $has_many = array(
		'Events'=>'Event'
	);

	public function updateCMSFields(FieldList $fields) {

		if(count(Event::get()) > 0 ){
			$current = new DropdownField('CurrentEventID', 'CurrentEvent', Event::get()->map('ID', 'Title'));
			$current->setEmptyString('');
			$fields->addFieldToTab('Root.Events', $current);
		}

		$events = new GridField(
			'Events',
			'Event',
			Event::get(),
			$config = GridFieldConfig_RecordEditor::create());

			$config->removeComponentsByType('GridFieldDeleteAction');

		$fields->addFieldToTab('Root.Events', $events);
	}

	public function getCurrentEventID(){
		return $this->owner->CurrentEvent()->ID;
	}

}
