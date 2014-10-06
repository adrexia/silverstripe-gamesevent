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
		'Brief'=>'Text',
		'Details'=>'HTMLText'
	);

	private static $has_one = array(
		'Parent' => 'Event'
	);

	private static $many_many = array(
		'Players'=>'Member'
	);

	private static $summary_fields = array(
		'MemberName' => 'Facilitator',
		'MemberEmail' => 'Email',
		'Title'=>'Title',
		'Session'=>'Session',
		'Brief'=>'Brief',
		'Details'=>'Details',
		'Status'=>'Status',
		'Parent.Title' => 'Event'
	);

	private static $plural_name = 'Games';

	private static $defaults = array("FacilitatorID" => 0);

	public function getCurrentDisplayFields(){
		return array(
			'MemberName' => 'Facilitator',
			'MemberEmail' => 'Email',
			'Title'=>'Title',
			'Session'=>'Session',
			'Brief'=>'Brief',
			'Details.NoHTML'=>'Details',
			'Status'=>'Status'
		);
	}

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$siteConfig = SiteConfig::current_site_config();
		$current = $siteConfig->getCurrentEventID();

		if($this->ParentID < 1){
			$event = Event::get()->byID($current);
		}else{
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
			Member::get()->map('ID', 'FirstName')),
		'Session');

		$member->setEmptyString(' ');

		$status = array(0=>"Pending", 1=>"Accepted");

		$fields->insertBefore(new OptionsetField('Status', 'Status', $status), 'Title');

		return $fields;
	}

	public function getMemberName(){
		if($this->FacilitatorID < 1){
			return 'No name';
		}
		$fac = Member::get()->byID($this->FacilitatorID);
		return $fac->FirstName . '' . $fac->Surname;
	}

	public function getMemberEmail(){
		if($this->FacilitatorID < 1){
			return 'No email';
		}
		return Member::get()->byID($this->FacilitatorID)->Email;
	}

	/*
	 * Returns the url suffix to append to teh current controllors url
	 */
	public function Link($action = 'show') {
		return Controller::join_links($action, $this->ID);
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
