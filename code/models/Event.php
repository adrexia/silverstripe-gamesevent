<?php

class Event extends DataObject {

	private static $db = array(
		'Title' => 'Varchar(255)',
		'NumberOfSessions'=>'Int',
		'PreferencesPerSession'=>'Int',
		'MealOption'=>'Boolean',
		'Accommodation'=>'Int'

	);

	private static $has_one = array(
		'Parent' => 'SiteConfig'
	);

	private static $has_many = array(
		'Games'=>'Game',
		'Registrations'=>'Registration'
	);

	private static $summary_fields = array(
		'Title' => 'Title',
		'NumberOfSessions' => 'Number Of Sessions',
	);

	private static $defaults = array(
		"NumberOfSessions" => 1,
		"PreferencesPerSession" => 1
	);

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->removeByName('ParentID');
		$accom = $fields->dataFieldByName('Accommodation');
		$accom->setRightTitle('Number of nights accommodation offered, or 0 if not applicable');
		$accom->setTitle("Accommodation (Num. nights)");
		return $fields;
	}

	public function canCreate($member = null) {
		return $this->Parent()->canCreate($member);
	}

	public function canEdit($member = null) {
		return $this->Parent()->canEdit($member);
	}

	public function canDelete($member = null) {
		return $this->Parent()->canDelete($member);
	}

	public function canView($member = null) {
		return $this->Parent()->canView($member);
	}
}
