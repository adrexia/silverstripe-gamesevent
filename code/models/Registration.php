<?php

class Registration extends DataObject {
	private static $db = array(
		'AttendingWholeEvent'=>'Boolean', 
		'AttendingTheseSessions'=>'Varchar(255)', //only show these if whole event is not checked
		'PlayWith'=>'Varchar(255)',
		'NotPlayWith'=>'Varchar(255)',
		'Meals'=>"Boolean",
		'SpecialDietryInfo'=>'Text',
		'Accommodation'=>'Boolean',
		'ExtraDetail'=>'Text',
		'PublicFieldsRaw' => 'Text',
		'Sort' => 'Int'
	);

	private static $has_one = array(
		'Member' => 'Member',
		'Parent' => 'Event'
	);

	private static $has_many = array(
		'PlayerGames'=>'PlayerGame'
	);

	private static $summary_fields = array(
		'MemberName'=>'Title',
		'MemberName' => 'Name',
		'MemberEmail' => 'Email',
		'NumberOfGames' =>'Number of Games',
		'Parent.Title' => 'Event'
	);

	public static $default_sort = 'Sort';

	public function getTitle(){
		return $this->getMemberName();
	}

	public function getCurrentDisplayFields(){
		return array(
			'MemberName' => 'Name',
			'MemberEmail' => 'Email',
			'NumberOfGames' =>'Number of Games'
		);
	}

	public function NumberOfGames(){
		if(PlayerGame::get()){

			return PlayerGame::get()->filter("ParentID", $this->ID)->Count();
		} else {
			return "none";
		}
	}

	public function getMemberName(){
		return $this->Member()->FirstName . ' ' . $this->Member()->Surname;
	}

	public function getMemberEmail(){
		return $this->Member()->Email;
	}


	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->insertBefore(new DropdownField('MemberID', 'Member', Member::get()->map('ID',"FirstName")), 'AttendingWholeEvent');
		$fields->insertAfter(new DropdownField('ParentID', 'Event', Event::get()->map('ID',"Title")), 'ExtraDetail');
		$fields->removeByName('PublicFieldsRaw');
		$fields->removeByName('Sort');

		// Carousel tab
		$gridField = new GridField(
			'PlayerGames',
			'Games',
			$this->PlayerGames(),
			$conf =GridFieldConfig_RelationEditor::create());

		$conf->addComponent(new GridFieldOrderableRows());

		$gridField->setModelClass('PlayerGame');
		$fields->addFieldToTab('Root.PlayerGames', $gridField);
		$conf->addComponent(new GridFieldDeleteAction(false));
		
		return $fields;
	}


	public function getPublicFields() {
		return (array) unserialize($this->owner->getField('PublicFieldsRaw'));
	}

	public function setPublicFields($fields) {
		$this->owner->setField('PublicFieldsRaw', serialize($fields));
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
