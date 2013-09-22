<?php

class Registration extends DataObject {
	private static $db = array(

	);

	private static $has_one = array(
		'Member' => 'Member',
		'Parent' => 'Event'
	);

	private static $has_many = array(
		'Games'=>'PlayerGame'

	);

	private static $summary_fields = array(
		'MemberName'=>'Title',
		'MemberName' => 'Name',
		'MemberEmail' => 'Email',
		'NumberOfGames' =>'Number of Games',
		'Parent.Title' => 'Event'
	);

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
		}else{
			return "none";
		}

	}

	function getMemberName(){
		return $this->Member()->FirstName . '' . $this->Member()->Surname;
	}

	function getMemberEmail(){
		return $this->Member()->Email;
	}


	function getCMSFields() {
		$fields = parent::getCMSFields();

		
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
