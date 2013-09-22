<?php

class PlayerGame extends DataObject {
	private static $db = array(
		'Preference'=>'Int',
		'CharacterPreference'=>'Text'
	);

	private static $has_one = array(
		'Game'=>'Game',
		'Parent' => 'Registration'
		
	);

	private static $has_many = array(
		
	);

	private static $summary_fields = array(
		'Game.Title'=>'Game',
		'Preference'=>'Int',
		'CharacterPreference'=>'Character',
		'Game.Session'=>'Session'
	);

	public function getTitle(){
		 return $this->Game()->Title;
	}

	function getMemberName(){
		return $this->Member()->FirstName . '' . $this->Member()->Surname;
	}

	function getMemberEmail(){
		return $this->Member()->Email;
	}

	function getCMSFields() {
		$fields = parent::getCMSFields();

		$fields->removeByName('ParentID');

		if($this->ParentID < 1){
			$event = Event::get()->byID($current);
		}else{
			$event = Event::get()->byID($this->ParentID);
		}

		$pref = array();
		for ($i = 1; $i <= $event->PreferencesPerSession; $i++){ 
			array_push($pref, $i);
		}

		$preference = new DropdownField('Preference', 'Preference', $pref);
		$preference->setEmptyString(' ');
		$fields->insertAfter($preference, 'GameID');

		$fields->insertAfter(new TextareaField('CharacterPreference','Character Preference'), 'Preference');

		
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
