<?php

class PlayerGame extends DataObject {
	private static $db = array(
		'Preference'=>'Int',
		'Status'=>'Boolean'
	);

	private static $has_one = array(
		'Game'=>'Game',
		'Parent' => 'Registration'
	);


	private static $summary_fields = array(
		'Game.Title'=>'Game',
		'Preference'=>'Preference',
		'Game.Session'=>'Session'
	);

	public function getTitle() {
		 return $this->Game()->Title;
	}

	public function getMemberName() {
		return $this->Parent()->Member()->FirstName . '' . $this->Parent()->Member()->Surname;
	}

	public function getMemberEmail() {
		return $this->Parent()->Member()->Email;
	}

	public function getCurrentDisplayFields() {
		return array(
			'MemberName' => 'Player',
			'MemberEmail' => 'Email',
			'Preference'=>'Preference Number',
			'Game.Session'=>'Session',
			'Status'=>'Status'
		);
	}

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$siteConfig = SiteConfig::current_site_config();
		$current = $siteConfig->getCurrentEventID();

		if($this->Parent()->ParentID < 1){
			$event = Event::get()->byID($current);
		} else {
			$event = Event::get()->byID($this->Parent()->ParentID);
		}

		if($event){
			$prefNum = $event->PreferencesPerSession ? $event->PreferencesPerSession : 2;
		} else {
			$prefNum = 2;
		}

		$pref = array();
		for ($i = 1; $i <= $prefNum; $i++){ 
			array_push($pref, $i);
		}

		$preference = new DropdownField('Preference', 'Preference', $pref);
		$preference->setEmptyString(' ');
		$fields->insertAfter($preference, 'GameID');

		$status = array(0=>"Pending/Declined", 1=>"Accepted");
		$fields->insertAfter(new OptionsetField('Status', 'Status', $status), 'Preference');

		$reg = Registration::get()->filter(array('ParentID' => $event->ID))->map('ID', "Title");
		$player = new DropdownField('ParentID', 'Player', $reg);
		$player->setEmptyString(' ');
		$fields->insertAfter($player, 'Status');

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
