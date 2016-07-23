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
		'Parent' => 'Registration',
		'Event'=>"Event"
	);


	private static $summary_fields = array(
		'Title'=>'Game',
		'Preference'=>'Preference',
		'GameSession'=>'Session',
		'Favourite.Nice'=>'Favourite',
		'NiceStatus'=>'Status'
	);

	public static $plural_name = "Player Games";

	public static $default_sort = 'Sort, Status DESC, Preference';

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->removeByName('Sort');

		$event = $this->getEvent();

		if($event) {
			$prefNum = $event->PreferencesPerSession ? $event->PreferencesPerSession : 2;
		} else {
			$prefNum = 2;
		}

		$pref = array();
		for ($i = 1; $i <= $prefNum; $i++) {
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

		if (!$event->DisableFavourite) {
			$fields->insertAfter($fields->dataFieldByName('Favourite'), 'Status');
		} else {
			$fields->removeByName('Favourite');
		}

		$fields->insertAfter($fields->dataFieldByName('ParentID'), 'GameID');

		$event = HiddenField::create(
			'EventID',
			'Event',
			$event->ID
		);

		$fields->insertAfter($event, 'GameID');


		return $fields;
	}

	/**
	 * Set eventID to match parent's event ID or current event (as a backup)
	 * We need this to always stay in sync with our parentID's event, so write everytime
	 */
	public function onBeforeWrite() {
		parent::onBeforeWrite();

		$siteConfig = SiteConfig::current_site_config();
		$current = $siteConfig->getCurrentEventID();

		if($this->Parent()->ParentID > 0) {
			$this->EventID = $this->Parent()->ParentID;
		} else {
			$this->EventID = $current;
		}

	}

	public function getExportFields() {

		$fieldsArray = array(
			'Game.Title'=>'Game',
			'MemberName' => 'Player',
			'MemberEmail' => 'Email',
			'Preference'=>'Preference',
			'NiceStatus'=>'Status',
			'Favourite.Nice'=>'Favourite',
			'GameSession'=>'Session',
		);

		if ($this->getEvent()->DisableFavourite) {
			unset($fieldsArray['Favourite.Nice']);
		}

		return $fieldsArray;
	}

	public function getEvent() {
		$siteConfig = SiteConfig::current_site_config();
		$current = $siteConfig->getCurrentEventID();

		if($this->Event()->ID) {
			return $this->Event();
		} else if($this->Parent()->ParentID > 0) {
			return Event::get()->byID($this->Parent()->ParentID);
		} else {
			return Event::get()->byID($current);
		}
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
		return $this->Parent()->Member()->FirstName . ' ' . $this->Parent()->Member()->Surname;
	}

	public function getMemberEmail() {
		return $this->Parent()->Member()->Email;
	}

	public function getActiveEventDisplayFields() {

		$fieldsArray = array(
			'MemberName' => 'Player',
			'MemberEmail' => 'Email',
			'Title'=>'Game',
			'Preference'=>'Preference Number',
			'GameSession'=>'Session',
			'Favourite.Nice'=>'Favourite',
			'NiceStatus'=>'Status'
		);

		if ($this->getEvent()->DisableFavourite) {
			unset($fieldsArray['Favourite.Nice']);
		}

		return $fieldsArray;
	}

	public function getGameDisplayFields() {

		$fieldsArray = array(
			'MemberName' => 'Player',
			'MemberEmail' => 'Email',
			'Preference'=>'Preference Number',
			'GameSession'=>'Session',
			'Favourite.Nice'=>'Favourite',
			'NiceStatus'=>'Status'
		);

		if ($this->getEvent()->DisableFavourite) {
			unset($fieldsArray['Favourite.Nice']);
		}

		return $fieldsArray;
	}

	public function getPlayerDisplayFields() {
		$fieldsArray = array(
			'Title'=>'Game',
			'Preference'=>'Preference',
			'GameSession'=>'Session',
			'Favourite.Nice'=>'Favourite',
			'NiceStatus'=>'Status'
		);

		if ($this->getEvent()->DisableFavourite) {
			unset($fieldsArray['Favourite.Nice']);
		}

		return $fieldsArray;
	}


	public function canCreate($member = null) {
		return Permission::check('EVENTS_CREATE');
	}

	public function canEdit($member = null) {
		return Permission::check('EVENTS_EDIT');
	}

	public function canDelete($member = null) {
		return Permission::check('EVENTS_DELETE');
	}

	public function canView($member = null) {
		return Permission::check('EVENTS_VIEW');
	}
}
