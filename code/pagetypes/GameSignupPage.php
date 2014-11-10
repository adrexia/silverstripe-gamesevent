<?php

/*
* This page will hold user game choices, writing to the PlayerGame dataobject
*/
class GameSignupPage extends Page {

	private static $icon = "gamesevent/images/gamesignup.png";

	private static $db = array(
		'RegistrationOpen'=>'Boolean'
	);

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$regOpen = new CheckboxField('RegistrationOpen', '');
		$fields->insertBefore($cField = new CompositeField(array(
			$label = new LabelField('OpenRegistration','Open Registration'),
			$regOpen
		)),'Content');

		$cField->addExtraClass('field');
		$regOpen->addExtraClass('mts'); 
		$label->addExtraClass('left');

		return $fields;
	}

}

class GameSignupPage_Controller extends Page_Controller {

	private static $allowed_actions = array (
		'Form' => true,
		'aftersubmission' => true,
		'addplayergames' => true
	);

	/**
	 * @return Form
	 */
	public function Form() {

		// Check that a user has a current registration and that 
		// game selection is open before showing form
		$siteConfig = SiteConfig::current_site_config();
		$currentID = $siteConfig->getCurrentEventID();

		$member = Member::currentUser();
		if(!$member || !$currentID || !$this->RegistrationOpen) return false;

		$reg = Registration::get()->filter(array(
			'MemberID' => $member->ID,
			'ParentID' => $currentID,
		));

		if(!$reg || !$reg->First()) {
			$register = RegistrationPage::get()->First();
			if($register){
				$this->redirect($register->URLSegment.'/');
			} else {
				return false;
			}
		}

		$fields = $this->GameSignupFields($reg);

		$form = new Form (
			$this,
			'Form',
			$fields,
			new FieldList(
				new FormAction('addplayergames', 'Submit')
			)
		);

		return $form;
	}

	public function GameSignupFields($reg){
		$fields = new FieldList();

		$siteConfig = SiteConfig::current_site_config();
		$currentID = $siteConfig->getCurrentEventID();
		$event = Event::get()->byID($currentID);

		$member = Member::currentUser();

		$prefNum =  $event->PreferencesPerSession;

		$reg = Registration::get()->filter(array(
			'MemberID' => $member->ID,
			'ParentID' => $currentID,
		));


		$fields->push(new HiddenField('RegistrationID', 'Reg', $reg->First()->ID));

		for ($session = 1; $session <= $event->NumberOfSessions; $session++){
			$evenOdd = $session % 2 == 0 ? 'even': 'odd';
			$fieldset = '<fieldset class="zebra '.$evenOdd.'">';

			$fields->push(new LiteralField('Heading_'.$session, $fieldset.'<h5>Round '.$session.'</h5>'));

			$games = Game::get()->filter('Session', $session);

			// Don't give multiple choices if there aren't multiple choices
			if($games->count() < $prefNum){
				$num = $games->count();
			} else {
				$num = $prefNum;
			}

			for ($i = 1; $i <= $num; $i++){

				$gameDropdown = new DropdownField('GameID_'.$session.'_'.$i, 'Game', $games->map('ID', 'Title'));
				$gameDropdown->setEmptyString("Not Playing");

				$fields->push($fieldgroup = new CompositeField(array(
					new LiteralField('Heading_'.$session.'_'.$i, '<p class="col-heading">Choice '.$i.'</p>'),
					new HiddenField('Preference_'.$session.'_'.$i,'Pref', $i),
					$gameDropdown,
					$textField = new TextField('Character_'.$session.'_'.$i, 'Character preferences')
				)));

				$fieldgroup->addExtraClass('clearfix');
				$gameDropdown->addExtraClass('inline hide-label');
				$textField->addExtraClass('inline hide-label');
				$textField->setAttribute('placeholder', 'Character preferences');
				
				
			}
			$fields->push(new LiteralField('fieldset', '</fieldset>'));

		}

		return $fields;
	}

	/**
	 * Handles adding new games
	 */
	public function addplayergames($data, Form $form) {
		if($this->addPlayerGame($data, $form)) {
			return $this->redirect($this->Link('aftersubmission'));
		} else {
			return $this->redirectBack();
		}
	}

		/**
	 * Attempts to save a game 
	 *
	 * @return Game|null
	 */
	protected function addPlayerGame($data,$form) {
		$fields = $form->Fields();

		$siteConfig = SiteConfig::current_site_config();
		$currentID = $siteConfig->getCurrentEventID();
		$event = Event::get()->byID($currentID);

		$prefNum =  $event->PreferencesPerSession;

		// this will need to be an object saved as the registration relations
		$regID = $data['RegistrationID'];
		$reg = Registration::get()->byID($regID);

		for ($session = 1; $session <= $event->NumberOfSessions; $session++){

			$games = Game::get()->filter('Session', $session);

			// Don't give multiple choices if there aren't multiple choices
			if($games->count() < $prefNum){
				$num = $games->count();
			} else {
				$num = $prefNum;
			}

			for ($i = 1; $i <= $num; $i++){

				$gameID = $data['GameID_'.$session.'_'.$i];

				if ($gameID) {

					$playerGame = PlayerGame::create();
					$form->saveInto($playerGame);

					$preference = $data['Preference_'.$session.'_'.$i];
					$character = $data['Character_'.$session.'_'.$i];

					$playerGame->GameID = $gameID;
					$playerGame->ParentID = $regID;
					$playerGame->Preference = $preference;
					$playerGame->Character = $character;

					try {
						$playerGame->write();
					} catch(ValidationException $e) {
						$form->sessionMessage($e->getResult()->message(), 'bad');
						return;
					}
				}

			}
		}

		return $playerGame;
	}

	/**
	 * Returns the after submission content to the user.
	 *
	 * @return array
	 */
	public function aftersubmission() {
		return array (
			'Title'   => "Games chosen!",
			'Content' => "Your game choices have been noted. We'll be in contact",
			'Form' => false
		);
	}



}