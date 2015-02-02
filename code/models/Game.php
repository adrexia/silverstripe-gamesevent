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
		'Parent.Title' => 'Event'
	);

	private static $plural_name = 'Games';

	private static $defaults = array("FacilitatorID" => 0);

	private static $default_sort = "Sort ASC, Title ASC";

	public function getCurrentDisplayFields(){
		return array(
			'Title'=>'Title',
			'Brief.FirstSentence'=>'Brief',
			'MemberName' => 'Facilitator',
			'Session'=>'Session',
			'Status.Nice'=>'Accepted'
		);
	}

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$siteConfig = SiteConfig::current_site_config();
		$current = $siteConfig->getCurrentEventID();

		if($this->ParentID < 1){
			$event = Event::get()->byID($current);
		} else {
			$event = Event::get()->byID($this->ParentID);
		}

		$parent = new DropdownField(
			'ParentID',
			'Event',
			Event::get()->map('ID','Title'),
			$current
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
				singleton("PlayerGame")->getCurrentDisplayFields()
			);
			$config->addComponent(new GridFieldOrderableRows());
			$config->removeComponentsByType('GridFieldPaginator');
			$config->removeComponentsByType('GridFieldPageCount');

			$config->addComponent($export = new GridFieldExportButton('before'));
			$export->setExportColumns(singleton("PlayerGame")->getExportFields());
		}
		
		return $fields;
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

	/*
	 * Produces an ArrayList of all genres on this object, with underscores and dashes replaced with spaces
	 */
	public function getGenresListNice() {
		$genres = preg_split("/\s/", $this->Genre); // turn into array

		if(count($genres) > 0){
			$result = new ArrayList();
			foreach($genres as $genre) {
				$genre = str_replace("_",  " ", $genre);
				$genre = str_replace("-",  " ", $genre);

				$result->push(new ArrayData(array(
					'Title' => $genre
				)));
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
	 * Returns the round title. 
	 * @return string
	 */
	public function getRoundTitle() {
		return 'Round ' . $this->Session;
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
