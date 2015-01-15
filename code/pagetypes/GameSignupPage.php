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

	/*
	 * @return Registration
	 */
	public function getCurrentRegistration(){
		$member = Member::currentUser();
		$currentID = $this->getCurrentEvent()->ID;

		if(!$currentID || !$member){
			return false;
		}
		
		$reg = Registration::get()->filter(array(
			'MemberID' => $member->ID,
			'ParentID' => $this->getCurrentEvent()->ID,
		));

		if(!$reg){
			return false;
		}

		return $reg->First();
	}

	/*
	 * @return Event
	 */
	public function getCurrentEvent() {
		return SiteConfig::current_site_config()->CurrentEvent();
	}

	/*
	 * @return String
	 */
	public function MenuTitle(){
		if($this->getCurrentRegistration()->PlayerGames()->Count() > 0){
			return "Your Games";
		}
		return $this->MenuTitle;
	}

}

class GameSignupPage_Controller extends Page_Controller {

	private static $allowed_actions = array (
		'Form' => true,
		'yourgames' => true,
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

		// Attempt to retrieve a current registration for the logged in member
		$reg = $this->getCurrentRegistration();
		$register = RegistrationPage::get()->First();

		// If the user has no registration, redirect them to the registration page
		if(!$reg){
			if($register){
				return $this->redirect($register->AbsoluteLink());
			} else {
				return $this->redirect($this->baseURL);
			}
		}

		if(!$this->userGameRegOpen() && !$this->OpenGameReg) {
			return false;
		}

		// If the user has already added games, redirect them to after submission
		// @todo: allow users to edit submitted game choices
		if($reg->PlayerGames()->Count() > 0){
			return $this->redirect($this->Link('yourgames'));
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

		$currentID = $this->getCurrentEvent()->ID;
		$event = Event::get()->byID($currentID);

		$prefNum =  $event->PreferencesPerSession;

		$reg = $this->getCurrentRegistration();

		$fields->push(new HiddenField('RegistrationID', 'Reg', $reg->ID));
		$fields->push($fav = new HiddenField('FavouriteID', 'Favourite'));
		$fav->addExtraClass('favourite-id');
		$fields->push(new LiteralField('no-js','<p class="js-hide">This page works better with javascript. If you can\'t use javascript, rank the items below in your order of preference. 1 for your first choice, 2 for your second. Note, only the top ' . $prefNum . ' will be recorded</p>'));

		for ($session = 1; $session <= $event->NumberOfSessions; $session++){
			$evenOdd = $session % 2 == 0 ? 'even': 'odd';
			$fieldset = '<fieldset id="round'.$session.'" class="preference-group preference-'.$prefNum.' data-preference-select="preference-select-group">';

			$fields->push(new LiteralField('Heading_'.$session, '<h5>Round '.$session.' preferences</h5>'. $fieldset));

			$games = Game::get()->filter(array(
				'Session' => $session, 
				'ParentID' =>$currentID,
				'Status' => true
			))->sort('RAND()');

			$i = 1;
			foreach ($games as $game){

				$gameOptions = new NumericField("GameID_".$game->ID, '');
				$gameOptions->setValue($i)
						->setRightTitle($game->Title)
						->setAttribute('type','number')
						->setAttribute('data-id',$game->ID)
						->addExtraClass('small-input js-hide-input');

				$fields->push($gameOptions);

				$i++;

			}

			// Add not playing option
			$gameOptions = new NumericField("NotPlaying_".$session, '');
			$gameOptions->setValue($i)
					->setRightTitle("Not playing")
					->setAttribute('type','number')
					->addExtraClass('small-input js-hide-input not-playing');

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

		$games =  Game::get()->filter(array('Session' => $session, 'Status' => true))->map('ID', 'Title')->toArray();
		return Convert::array2json($games);
	}

	/**
	 * Handles adding new games
	 */
	public function addplayergames($data, Form $form) {
		if($this->addPlayerGame($data, $form)) {
			return $this->redirect($this->Link('yourgames'));
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
		$event = $this->getCurrentEvent();
		$currentID = $event->ID;

		$prefNum =  $event->PreferencesPerSession;

		$regID = $data['RegistrationID'];
		$reg = Registration::get()->byID($regID);

		if (!$reg){
			return false;
		}

		// @todo - handle a proper 'change game' case
		if($reg->PlayerGames()->Count() > 0){
			foreach($reg->PlayerGames() as $playerGame){
				$reg->PlayerGames()->removeByID($playerGame->ID);
				$playerGame->delete();
			}
		}

		$favouriteID = $data["FavouriteID"];

		for ($session = 1; $session <= $event->NumberOfSessions; $session++){

			$notPlay = $data["NotPlaying_".$session];

			$games = Game::get()->filter(array(
				'Session'=> $session, 
				'ParentID'=>$currentID, 
				'Status' => true
			));

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
				if ($gamePref > $prefNum || !isset($gamePref)) {
					continue;
				}



				$playerGame = PlayerGame::create();

				$form->saveInto($playerGame);

				$playerGame->GameID = $game->ID;
				$playerGame->ParentID = $regID;
				$playerGame->Preference = $gamePref;

				if($favouriteID == $game->ID){
					$playerGame->Favourite = true;
				}

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
	 * Returns data for the your games view (after game selection)
	 *
	 * @return array
	 */
	public function yourgames() {
		$reg = $this->getCurrentRegistration();
		if(!$reg->PlayerGames()->Count() > 0){
			return $this->redirect($this->Link());
		}
		return array (
			'Title'   => "Your Games",
			'Content' => "Your game choices are listed below. If any of this is incorrect, or if you wish to change your game choices, please contact us.",
			'Form' => false,
			'SubmittedGames' => true
		);
	}

	/*
	 * @return GroupedList
	 */
	public function getGroupedPlayerGames(){
		$reg = $this->getCurrentRegistration();

		if(!$reg){
			return false;
		}

		$playergames = $reg->PlayerGames();
		$games = new ArrayList();

		foreach($playergames as $playergame){
			$games->push(new ArrayData(array(
				"Game" => $playergame->Game(),
				"Preference" => $playergame->Preference,
				"Favourite" => $playergame->Favourite,
				"Status" => $playergame->Status,
				"Session" => $playergame->Game()->Session
			)));
		}

		$result = $games->sort(array('Session' => 'ASC', 'Preference' => 'ASC'));

		return GroupedList::create($result);
	}
}