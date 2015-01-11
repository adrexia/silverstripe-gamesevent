<?php

/*
* This page will hold user game choices, writing to the PlayerGame dataobject
*/
class GameSignupPage extends Page {

	private static $icon = "gamesevent/images/gamesignup.png";

	private static $db = array(
		'OpenGameReg'=>'Boolean'
	);

	private static $many_many = array(
		'OpenGameRegForGroups'=>'Group'
	);

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$regOpen = new CheckboxField('OpenGameReg', '');
		$fields->insertBefore($cField = new CompositeField(array(
			$label = new LabelField('OpenGameRegLabel','Open game selection (all)'),
			$regOpen
		)),'Content');

		$cField->addExtraClass('field');
		$regOpen->addExtraClass('mts'); 
		$label->addExtraClass('left');

		$groupsMap = array();
		foreach(Group::get() as $group) {
			// Listboxfield values are escaped, use ASCII char instead of &raquo;
			$groupsMap[$group->ID] = $group->getBreadcrumbs(' > ');
		}
		asort($groupsMap);

		$fields->insertBefore(
			ListboxField::create('OpenGameRegForGroups', "Open game selection for group (limited)")
				->setMultiple(true)
				->setSource($groupsMap)
				->setAttribute(
					'data-placeholder', 
					_t('Member.ADDGROUP', 'Add group', 'Placeholder text for a dropdown')
				),'Content'
		);

		return $fields;
	}

}

class GameSignupPage_Controller extends Page_Controller {

	private static $allowed_actions = array (
		'Form' => true,
		'aftersubmission' => true,
		'addplayergames' => true,
		'gamesessionasjson' => true
	);

	/* 
	 * Check if there are any groups added to OpenGameRegForGroups
	 * return true if the current member is in one of these groups
	 * @return Boolean
	 */
	public function userGameRegOpen(){
		if($this->OpenGameRegForGroups()) {
			foreach ($this->OpenGameRegForGroups() as $group){
				if (Member::currentUser()->inGroup($group->ID)){
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @return Form
	 */
	public function Form() {

		// Check that a user has a current registration and that 
		// game selection is open before showing form
		$siteConfig = SiteConfig::current_site_config();
		$currentID = $siteConfig->getCurrentEventID();

		$member = Member::currentUser();

		if(!$member || !$currentID || (!$this->userGameRegOpen() && !$this->OpenGameReg)){
			return false;
		}

		// Attempt to retrieve a current registration for the logged in member
		$reg = Registration::get('Registration')->filter(array(
			'MemberID' => $member->ID,
			'ParentID' => $currentID,
		))->First();

		// If the user has no registration, redirect them to the registration page
		if(!$reg) {
			$register = RegistrationPage::get()->First();
			if($register){
				$this->redirect($register->URLSegment.'/');
			} else {
				return false;
			}
		}

		// If the user has already added games, redirect them to after submission
		// @todo: allow users to edit submitted game choices
		// @todo: show users what choices they made, sorted by round and then preference
		if($reg->Games()->Count() > 0){
			$this->redirect($this->Link('aftersubmission'));
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

		$form->addExtraClass('preference-select');

		return $form;
	}

	/*
	* Used for autocomplete type functionality when adding genres
	*/
	public function renderGamesList($games){
		return $this->customise(array('Games'=>$games))->renderWith('GamesList');
	}

	/*
	 * Fields for game signup
	 * Note: this is strongly tied to the hydra sites need for sortable games lists.
	 * The frontend uses jquery sortable to allow users to order games by preference order
	 */
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

		$fields->push(new LiteralField('no-js','<p class="js-hide">This page works better with javascript. If you can\'t use javascript, rank the items below in your order of preference. 1 for your first choice, 2 for your second. Note, only the top ' . $prefNum . ' will be recorded</p>'));

		for ($session = 1; $session <= $event->NumberOfSessions; $session++){
			$evenOdd = $session % 2 == 0 ? 'even': 'odd';
			$fieldset = '<fieldset id="round'.$session.'" class="preference-group preference-'.$prefNum.' data-preference-select="preference-select-group">';

			$fields->push(new LiteralField('Heading_'.$session, '<h5>Round '.$session.'</h5>'. $fieldset));

			$games = Game::get()->filter(array('Session'=> $session, 'ParentID'=>$currentID))->sort('RAND()');

			$i = 1;
			foreach ($games as $game){

				$gameOptions = new NumericField("GameID_".$game->ID, '');
				$gameOptions->setValue($i)
						->setRightTitle($game->Title)
						->setAttribute('type','number')
						->addExtraClass('small-input js-hide-input');

				$fields->push($gameOptions);

				$i++;

			}

			// Add not playing option
			$gameOptions = new NumericField("NotPlaying_".$session, '');
			$gameOptions->setValue($i)
					->setRightTitle("Not playing")
					->setAttribute('type','number')
					->addExtraClass('small-input js-hide-input');

			$fields->push($gameOptions);

			$fields->push(new LiteralField('fieldset', '</fieldset>'));

		}

		return $fields;
	}

	/*
	 * Generate a json object of games for a session. 
	 * URL format: $this->Link/gamesessionasjson/$i
	 * @return Json | Games per session
	 */
	public function gamesessionasjson($request){
		$session = $request->param('ID') ? $request->param('ID') : 1;
		$games =  Game::get()->filter('Session', $session)->map('ID', 'Title')->toArray();
		return Convert::array2json($games);
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

		if($reg->Games()->Count() > 0){
			foreach($reg->Games() as $playerGame){
				$reg->Games()->removeByID($playerGame->ID);
				$playerGame->delete();
			}
		}

		for ($session = 1; $session <= $event->NumberOfSessions; $session++){

			$notPlay = $data["NotPlaying_".$session];

			$games = Game::get()->filter(array('Session'=> $session, 'ParentID'=>$currentID));

			// Alter prefnumber so it stops when it encounters "not Playing"
			if($notPlay != 0 && $notPlay < $prefNum){
				$prefNum = $notPlay;
			}

			// if first choice is to not play, don't create any games
			if($notPlay === 1){
				continue;
			}

			foreach ($games as $game){
				$gamePref = $data["GameID_".$game->ID];

				// only store games with a preference higher than our threshold
				if ($gamePref > $prefNum) {
					continue;
				}

				$playerGame = PlayerGame::create();

				$form->saveInto($playerGame);

				$playerGame->GameID = $game->ID;
				$playerGame->ParentID = $regID;
				$playerGame->Preference = $gamePref;

				try {
					$playerGame->write();
				} catch(ValidationException $e) {
					$form->sessionMessage($e->getResult()->message(), 'bad');
					return;
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
			'Content' => "Your game choices have been submitted. If you wish to change your games, please contact us.",
			'Form' => false
		);
	}

}