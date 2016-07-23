<?php

class Game extends DataObject {
	private static $db = array(
		'Title'=>'Varchar(255)',
		'Session'=>'Int',
		'NumPlayers'=>'Varchar(255)',
		'Restriction'=>'Varchar(255)',
		'Genre'=>'Varchar(255)',
		'Costuming'=>'Text',
		'Status'=>'Boolean',
		'FacilitatorID' => 'Int',
		'FacilitatorText'=>'Text',
		'Brief'=>'Text',
		'Details'=>'HTMLText',
		'Sort'=>'Int'
	);

	private static $has_one = array(
		'Parent' => 'Event'
	);

	private static $has_many = array(
		'Players'=>'PlayerGame'
	);

	private static $summary_fields = array(
		'Title'=>'Title',
		'Brief.FirstSentence'=>'Brief',
		'MemberName' => 'Facilitator',
		'Session'=>'Session',
		'Status.Nice'=>'Accepted',
		'CountCurrentPlayers' => 'Number of Players',
		'Parent.Title' => 'Event'
	);

	private static $plural_name = 'Games';

	private static $defaults = array("FacilitatorID" => 0);

	private static $default_sort = "Sort ASC, Title ASC";


	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$siteConfig = SiteConfig::current_site_config();
		$current = $siteConfig->getCurrentEventID();

		if($this->ParentID < 1){
			$event = Event::get()->byID($current);
		} else {
			$event = Event::get()->byID($this->ParentID);
		}

		if(!$event) {
			$event = Event::get()->byID($current);
		}


		$parent = HiddenField::create(
			'ParentID',
			'Event',
			$event->ID
		);

		$fields->insertAfter($parent, 'Details');

		$sessions = array();
		if($event){
			for ($i = 1; $i <= $event->NumberOfSessions; $i++){
				$sessions[$i] = $i;
			}
		}

		$session = new DropdownField('Session', 'Session', $sessions);
		$session->setEmptyString(' ');
		$fields->insertAfter($session, 'Title');

		$fields->insertAfter(
		$member = new DropdownField(
			'FacilitatorID',
			'Facilitator',
			Member::get()->map('ID', 'Name')),
		'Session');
		$member->setEmptyString(' ');

		$fields->insertAfter(
			new TextField('FacilitatorText', 'Or enter facilitator name(s)'),
			'FacilitatorID'
		);

		$status = array(0=>"Pending", 1=>"Accepted");
		$fields->insertBefore(new OptionsetField('Status', 'Status', $status), 'Title');

		$gridField = $fields->dataFieldByName("Players");

		if ($gridField) {
			$config = $gridField->getConfig();
			$config->getComponentByType('GridFieldDataColumns')->setDisplayFields(
				singleton("PlayerGame")->getGameDisplayFields()
			);
			$config->addComponent(new GridFieldOrderableRows());
			$config->removeComponentsByType('GridFieldPaginator');
			$config->removeComponentsByType('GridFieldPageCount');

			$config->addComponent($export = new GridFieldExportButton('before'));
			$export->setExportColumns(singleton("PlayerGame")->getExportFields());
		}

		return $fields;
	}

	public function getActiveEventDisplayFields(){
		return array(
			'Title'=>'Title',
			'Brief.FirstSentence'=>'Brief',
			'MemberName' => 'Facilitator',
			'Session'=>'Session',
			'Status.Nice'=>'Accepted',
			'CountCurrentPlayers' => 'Number of Players'
		);
	}


	public function getExportFields() {
		return array(
			'Title'=>'Title',
			'Session'=>'Session',
			'NumPlayers'=>'Number of players (max)',
			'Restriction'=>'Restriction',
			'Genre'=>'Genre',
			'Costuming'=>'Costuming',
			'Status.Nice'=>'Accepted',
			'MemberName' => 'Facilitator (owner)',
			'FacilitatorText'=>'Facilitator (Multiple)',
			'Brief'=>'Brief',
			'Details'=>'Details',
			'Parent.Title' => 'Event'
		);
	}



	public function onBeforeWrite() {
		parent::onBeforeWrite();
		$this->Genre = strtolower($this->Genre);
	}

	public function getMemberName(){
		if($this->FacilitatorID < 1){
			return 'No name';
		}
		$fac = Member::get()->byID($this->FacilitatorID);
		return $fac->FirstName . ' ' . $fac->Surname;
	}

	/*
	 * Produces an ArrayList of all genres on this object
	 */
	public function getGenresList() {
		$genres = preg_split("/\s/", $this->Genre); // turn into array

		if(count($genres) > 0){
			$result = new ArrayList();
			foreach($genres as $genre) {
				$result->push(new ArrayData(array(
					'Title' => $genre
				)));
			}
			return $result;
		}

		return false;
	}

	public function getGenresArray() {
		$genres = preg_split("/\s/", $this->Genre); // turn into array

		$result = array();
		if(count($genres) > 0){
			foreach($genres as $genre) {
				$result[] = $genre;
			}
			return $result;
		}

		return false;
	}

	public function showEditLink() {
		$member = Member::currentUser();

		if(!$member){
			return false;
		}

		if($this->FacilitatorID === $member->ID || Permission::check('ADMIN')){
			return true;
		}

		return false;
	}

	public function getEditLink() {
		$submit = SubmitGamePage::get()->First();
		return $submit->URLSegment . '/edit/' . $this->ID;
	}

	public function getMemberEmail(){
		if($this->FacilitatorID < 1){
			return 'No email';
		}
		return Member::get()->byID($this->FacilitatorID)->Email;
	}

	/*
	 * Returns the url suffix to append to the current controllors url
	 */
	public function URLSegment($action = 'show') {
		return Controller::join_links($action, $this->ID);
	}

	/*
	 * Returns a full viewable link
	 */
	public function Link($action = 'show') {
		$gameListingPage = DataObject::get_one('GameListingPage');
		if($gameListingPage){
			return Controller::join_links($gameListingPage->URLSegment, $action, $this->ID);
		}
	}

	/**
	 * Get all confirmed players
	 * @return PlayerGame
	 */
	public function CountCurrentPlayers() {
		$players = $this->getCurrentPlayers();
		return $players->Count();
	}

	/**
	 * Get all confirmed players
	 * @return PlayerGame
	 */
	public function getCurrentPlayers() {
		return $this->Players()->filter(array('Status'=> "1"));
	}

	/**
	 * Returns the round title.
	 * @return string
	 */
	public function getRoundTitle() {
		if ($this->Session==0){
			return 'unscheduled';
		}
		return 'round-' . $this->Session;
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
