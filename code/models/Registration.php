<?php

class Registration extends DataObject {
	private static $db = array(
		'AttendingWholeEvent'=>'Boolean',
		'AttendingTheseSessions'=>'Varchar(255)', //only show these if whole event is not checked
		'PlayWith'=>'Varchar(255)',
		'NotPlayWith'=>'Varchar(255)',
		'Meals'=>"Boolean",
		'SpecialDietryInfo'=>'Text',
		'Accommodation'=>'Int',
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

	private static $many_many = array(
		'HasPlayed'=>'Game'
	);

	private static $summary_fields = array(
		'MemberName'=>'Title',
		'MemberEmail' => 'Email',
		'NumberOfGames' =>'Number of Games',
		'Parent.Title' => 'Event'
	);

	public static $default_sort = 'Sort';



	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->insertBefore(new DropdownField('MemberID', 'Member', Member::get()->map('ID',"FirstName")), 'AttendingWholeEvent');

		$siteConfig = SiteConfig::current_site_config();
		$current = $siteConfig->getCurrentEventID();

		if($this->ParentID < 1) {
			$event = Event::get()->byID($this->ParentID);
		} else {
			$event = Event::get()->byID($current);
		}

		if(!$event) {
			$event = Event::get()->byID($current);
		}

		$fields->removeByName('Sort');

		$fields->insertAfter(HiddenField::create(
			'ParentID',
			'Event',
			$event->ID
		), 'ExtraDetail');

		$fields->removeByName('PublicFieldsRaw');
		$fields->removeByName('Sort');

		if($this->PlayerGames()->Count() > 0) {

			$gridField = new GridField(
				'PlayerGames',
				'Games',
				$this->PlayerGames(),
				$config = GridFieldConfig_RelationEditor::create());
				$gridField->setModelClass('PlayerGame');


			$config->addComponent(new GridFieldOrderableRows());
			$config->removeComponentsByType('GridFieldPaginator');
			$config->removeComponentsByType('GridFieldPageCount');

			$config->addComponent(new GridFieldDeleteAction(false));

			$config->addComponent($export = new GridFieldExportButton('before'));
			$export->setExportColumns(singleton("PlayerGame")->getExportFields());

			$config->getComponentByType('GridFieldDataColumns')->setDisplayFields(
				singleton("PlayerGame")->getPlayerDisplayFields()
			);


			$fields->addFieldToTab('Root.PlayerGames', $gridField);
		}

		return $fields;
	}

	public function getTitle() {
		return $this->getMemberName();
	}

	public function getActiveEventDisplayFields(){
		return array(
			'MemberName' => 'Name',
			'MemberEmail' => 'Email',
			'NumberOfGames' =>'Number of Games'
		);
	}

	public function getEditibleDisplayFields() {
		return $this->getActiveEventDisplayFields();
	}

	public function HasPlayedString() {
		return $this->customise(array('Data'=>$this->HasPlayed()))->renderWith('PlayedList');
	}

	public function getExportFields() {
		$fields = array(
			'MemberName' => 'Player',
			'MemberEmail' => 'Email',
			'AttendingWholeEvent.Nice'=>'Attending whole event',
			'AttendingTheseSessions'=>'Attending sessions', //only show these if whole event is not checked
			'PlayWith'=>'Play with',
			'NotPlayWith'=>'Not play with',
			'Meals'=>"Meals",
			'SpecialDietryInfo'=>'Special dietary info',
			'Accommodation'=>'Accommodation',
			'ExtraDetail'=>'Extra detail',
			'HasPlayedString'=>'Has played',
			'Parent.Title' => 'Event'
		);

		$this->extend('updateExportFields', $fields);

		return $fields;
	}

	public function getExcelExportFields() {
		$fields = $this->getExportFields();

		$this->extend('updateExcelExportFields', $fields);

		return $fields;
	}

	public function NumberOfGames() {
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

	public function getPublicFields() {
		return (array) unserialize($this->owner->getField('PublicFieldsRaw'));
	}

	public function setPublicFields($fields) {
		$this->owner->setField('PublicFieldsRaw', serialize($fields));
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
