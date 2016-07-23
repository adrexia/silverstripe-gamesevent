<?php

class PlayerGame extends DataObject {
	private static $db = array(
		'Preference'=>'Int',
		'Status'=>'Boolean',
		'Favourite'=>'Boolean',
		'Sort'=>'Int',
		'Session'=>'Int'
	);

	private static $has_one = array(
		'Parent' => 'Registration',
		'Game'=>'Game',
		'Event'=>"Event"
	);


	private static $summary_fields = array(
		'getTitle'=>'Game',
		'Preference'=>'Preference',
		'GameSession'=>'Session',
		'Favourite.Nice'=>'Favourite',
		'NiceStatus'=>'Status'
	);

	public static $plural_name = "Player Games";

	public static $default_sort = 'Sort, Session, Preference, Status DESC';

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->removeByName('Sort');

		$event = $this->getEvent();

		// Only show session and event fields unless we have a saved session
		// Games need to be filtered by the session number
		if($this->GameSession()) {

			$fields->replaceField('Session', ReadonlyField::create('Session'));

			// Filter the object by the saved session ID
			$fields->replaceField('GameID', DropdownField::create(
				'GameID',
				'Game',
				Game::get()->filter('Session', $this->GameSession())->map('ID', 'Title')
			));


			if($event->EnableLuckyDip) {
				$game = $fields->dataFieldByName('GameID');
				$game->setEmptyString('Lucky dip');
			}

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
			$fields->insertAfter($player, 'Session');

			if (!$event->DisableFavourite) {
				$fields->insertAfter($fields->dataFieldByName('Favourite'), 'Status');
			} else {
				$fields->removeByName('Favourite');
			}

			$event = HiddenField::create(
				'EventID',
				'Event',
				$event->ID
			);

			$fields->insertAfter($event, 'GameID');

		} else {

			$fields->insertBefore(LiteralField::create('Unsaved',
				'<p class="message">Save this record with a session number to edit other fields</p>'),
				'Session'
			);

			$fields->removeByName('Preference');
			$fields->removeByName('Status');
			$fields->removeByName('Favourite');
			$fields->removeByName('GameID');
			$fields->removeByName('ParentID');
		}

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

		if(!$this->Session && $this->Game()) {
			$this->Session = $this->Game()->Session;
		}

	}

	public function getExportFields() {

		$fieldsArray = array(
			'getTitle'=>'Game',
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

	/**
	 * CMS readable title
	 * If Lucky dip is enabled and the Game field is empty, then this is a lucky dip record
	 * @return String
	 */
	public function getTitle() {
		if ($this->Game()->ID) {
			return $this->Game()->Title;
		}

		if($this->getEvent()->EnableLuckyDip) {
			return 'Lucky dip';
		}

		return 'No game';
	}

	/**
	 * If set, return the Session value, otherwise get session from a game
	 *
	 * Note: getting the session from the game is mostly for backwards compatibility reasons
	 * with older saved records.
	 *
	 * @return String | Boolean - false if no session and no game
	 */
	public function GameSession() {
		if($this->Session) {
			return $this->Session;
		} else if($this->Game()) {
			return $this->Game()->Session;
		}

		return false;
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
			'getTitle'=>'Game',
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
			'getTitle'=>'Game',
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
