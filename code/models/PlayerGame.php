<?php

class PlayerGame extends DataObject {
	private static $db = array(
		'Preference'=>'Int',
		'Status'=>'Boolean',
		'Favourite'=>'Boolean',
		'Sort'=>'Int'
	);

	private static $has_one = array(
		'Game'=>'Game',
		'Parent' => 'Registration'
	);


	private static $summary_fields = array(
		'Title'=>'Game',
		'Preference'=>'Preference',
		'GameSession'=>'Session',
		'Favourite.Nice'=>'Favourite',
		'NiceStatus'=>'Status',
	);

	public static $default_sort = 'Sort, Status DESC, Preference';

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->removeByName('Sort');

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
			$pref[$i] = $i;
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

		$fields->insertAfter($fields->dataFieldByName('Favourite'), 'Status');

		$fields->insertAfter($fields->dataFieldByName('ParentID'), 'GameID');

		return $fields;
	}

	public function getExportFields() {
		return array(
			'Game.Title'=>'Game',
			'MemberName' => 'Player',
			'MemberEmail' => 'Email',
			'Preference'=>'Preference',
			'NiceStatus'=>'Status',
			'Favourite.Nice'=>'Favourite',
			'GameSession'=>'Session',
		);

	}

	public function getTitle() {
		 return $this->Game()->Title;
	}

	public function GameSession() {
		return $this->Game()->Session;
	}


	public function NiceStatus() {
		if($this->Status){
			return 'Accepted';
		} else {
			return 'Pending or Declined';
		}
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
			'GameSession'=>'Session',
			'Favourite.Nice'=>'Favourite',
			'NiceStatus'=>'Status'
		);
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
