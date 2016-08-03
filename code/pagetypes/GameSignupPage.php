<?php

/*
* This page allows users to register game choices, writing to the PlayerGame dataobject
*/
class GameSignupPage extends Page {

	private static $icon = "gamesevent/images/gamesignup.png";
	private static $hide_preview_panel = true;

	private static $db = array(
		'OpenGameReg'=>'Boolean',
		'GameLiveContent'=>'HTMLText'
	);

	private static $many_many = array(
		'OpenGameRegForGroups'=>'Group'
	);

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$fields->insertAfter($gameFormContent = new HTMLEditorField('GameLiveContent', 'Game selection form detail'),'Content');
		$gameFormContent->setRows(20);

		$regOpen = new SwitchField('OpenGameReg', 'Open game selection (to all)');
		$fields->insertBefore($regOpen, 'Content');

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
		if($this->getCurrentRegistration() && $this->getCurrentRegistration()->PlayerGames()->Count() > 0){
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
	 *
	 * If game reg is open, return true
	 * @return Boolean
	 */
	public function userGameRegOpen() {
		if($this->OpenGameReg){
			return true;
		}
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
		if(!$reg) {
			if($register) {
				$this->redirect($register->AbsoluteLink());
				return;
			} else {
				$this->redirect($this->baseURL);
				return;
			}
		}

		if(!$this->userGameRegOpen()) {
			return false;
		}

		// If the user has already added games, redirect them to after submission
		// @todo: allow users to edit submitted game choices if option enabled, and within 10 minutes
		if($reg->PlayerGames()->Count() > 0) {
			$this->redirect($this->Link('yourgames'));
		}

		$fields = $this->GameSignupFields($reg);

		$form = Form::create(
			$this,
			'Form',
			$fields,
			FieldList::create(
				FormAction::create('addplayergames', 'Submit')
			)
		);

		$form->enableSpamProtection();

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

		$fields->push(HiddenField::create('RegistrationID', 'Reg', $reg->ID));

		if (!$this->DisableFavourite()) {
			$fields->push($fav = HiddenField::create('FavouriteID', 'Favourite'));
			$fav->addExtraClass('favourite-id');
		}

		$fields->push(LiteralField::create('no-js','<p class="js-hide">This page works better with javascript. If you can\'t use javascript, rank the items below in your order of preference. 1 for your first choice, 2 for your second. Note, only the top ' . $prefNum . ' will be recorded</p>'));

		for ($session = 1; $session <= $event->NumberOfSessions; $session++){
			$evenOdd = $session % 2 == 0 ? 'even': 'odd';
			$fieldset = '<fieldset id="round'.$session.'" class="preference-group preference-'.$prefNum.' data-preference-select="preference-select-group">';

			$fields->push(LiteralField::create('Heading_'.$session, '<h5>Round '.$session.' preferences</h5>'. $fieldset));

			$games = Game::get()->filter(array(
				'Session' => $session,
				'ParentID' =>$currentID,
				'Status' => true
			))->sort('RAND()');

			$i = 1;
			foreach ($games as $game){

				$gameOptions = NumericField::create("GameID_".$game->ID, '');
				$gameOptions->setValue($i)
						->setRightTitle($game->Title)
						->setAttribute('type','number')
						->setAttribute('data-id',$game->ID)
						->addExtraClass('small-input js-hide-input');

				$fields->push($gameOptions);

				$i++;

			}

			if($this->EnableLuckyDip()) {
				// Add not playing option
				$gameOptions = NumericField::create("LuckyDip_".$session, '');
				$gameOptions->setValue($i)
						->setRightTitle("Lucky dip")
						->setAttribute('type','number')
						->addExtraClass('small-input js-hide-input isfinal');

				$fields->push($gameOptions);

				$i++;
			}

			// Add not playing option
			$gameOptions = NumericField::create("NotPlaying_".$session, '');
			$gameOptions->setValue($i)
					->setRightTitle("No game (or Facilitating)")
					->setAttribute('type','number')
					->addExtraClass('small-input js-hide-input not-playing isfinal');

			$fields->push($gameOptions);

			$fields->push(LiteralField::create('fieldset', '</fieldset>'));

		}

		$allgames = Game::get()->filter(array(
			'ParentID' => $currentID,
			'Status' => true
		));

		$allgamesMap = $allgames->map("ID", "Title")->toArray();
		asort($allgamesMap);

		// tag input field
		$fields->push($tagfield = new Listboxfield(
			'HasPlayed',
			'I have already played these games'
		));

		$tagfield->setMultiple(true)
				->setSource($allgamesMap)
				->setAttribute(
					'data-placeholder',
					'Select games'
				);

		$tagfield->addExtraClass('js-select2 ptl');

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
		if($reg = $this->addPlayerGame($data, $form)) {

			$this->handleAppEmails($reg);
			$this->redirect($this->Link('yourgames'));
			return;
		} else {
			return $this->redirectBack();
		}
	}

	/**
	 * Send an email with two csv attachments:
	 * * the HasPlayed Game list for this registration
	 * * the Player game selections for this player
	 *
	 * @param Registration | $reg
	 */
	public function handleAppEmails($reg) {

		$siteConfig = SiteConfig::current_site_config();
		$address = $reg->Parent()->AppEmail;
		$currentID = $reg->Parent()->ID;

		if(!$address) {
			return;
		}

		$hasPlayedData = $this->getHasPlayed($reg);
		$hasPlayedFile = $this->handleFile($hasPlayedData, "playedgames.csv", $reg);

		$playerGames = PlayerGame::get()->filter(
			'ParentID', $reg->ID
		);

		$formatter = new CsvDataFormatter();
		$playerGameData = $formatter->convertDataObjectSet($playerGames);
		$playerGamesFile = $this->handleFile($playerGameData, "gameselections.csv", $reg);

		$email = Email::create();
		$email->attachFile($playerGamesFile);
		$email->attachFile($hasPlayedFile);
		$email->setTo($address);
		$email->setFrom('it@nzlarps.org');
		$email->setSubject("Chimera games for " . $reg->getMemberName());
		$email->setBody('Game details are attached');
		$email->send();
	}

	/**
	 * Compile custom hasplayed records as a csv formatted string
	 *
	 * @param Registration | $reg
	 * @return String
	 */
	public function getHasPlayed($reg) {
		$hasPlayed = $reg->HasPlayed();

		$result = '"RegID", "MemberEmail", "GameID", "GameTitle"';

		$id = $reg->ID;
		$email = $reg->getMemberEmail();

		foreach($hasPlayed as $game) {
			$gID = $game->ID;
			$title = $game->Title;

			$result .= PHP_EOL .'"'. $id . '","' . $email . '","' . $gID . '","'. $title .'"';
		}

		return $result;
	}

	/**
	 * Generate the export and return the filepath if successful
	 *
	 * @param Data | $fileData csv formatted string
	 * @param String | $filename
	 * @param Registraton | $reg user registration
	 * @return filepath
	 */
	public function handleFile($fileData, $fileName, $reg) {
		$ID = $reg->ID;
		$email = $reg->Email;

		$folder = "/tmp/$ID-$email/";
		$filepath = $folder . $fileName;

		try {
			if(!file_exists($folder)) {
				if (!mkdir($folder, 0700)){
					die('Failed to create export folder');
				}
			}

			$file = fopen($filepath, "w");
			fwrite($file, $fileData);
			fclose($file);

			return $filepath;

		} catch(Exception $e) {
			return false;
		}
	}

	public function handleExistingGames($reg) {
		// @todo - handle a proper 'change game' case
		if($reg->PlayerGames()->Count() > 0) {
			foreach($reg->PlayerGames() as $playerGame) {
				$reg->PlayerGames()->removeByID($playerGame->ID);
				$playerGame->delete();
			}
		}
	}

	/**
	 * Attempts to save a game
	 *
	 * @return Game|null
	 */
	protected function addPlayerGame($data,$form) {

		$event = $this->getCurrentEvent();
		$currentID = $event->ID;

		$regID = $data['RegistrationID'];
		$reg = Registration::get()->byID($regID);

		if (!$reg) {
			return false;
		}

		$this->handleExistingGames($reg);

		$favouriteID = false;

		if (!$this->DisableFavourite()) {
			$favouriteID = $data["FavouriteID"];
		}

		for ($session = 1; $session <= $event->NumberOfSessions; $session++) {

			$notPlay = (int)$data["NotPlaying_".$session];

			$luckyDip = false;

			if($this->EnableLuckyDip()) {
				$luckyDip = (int)$data["LuckyDip_".$session]; //gets position/value of luckydip entry
			}

			$prefNum = $event->PreferencesPerSession;

			$games = Game::get()->filter(array(
				'Session' => $session,
				'ParentID' => $currentID,
				'Status' => 1
			));

			// Alter prefnumber so it stops when it encounters "Not Playing"
			if($notPlay != 0 && $notPlay < $prefNum) {
				$prefNum = $notPlay;
			}

			// if first choice is to not play, don't create any games this session
			if($notPlay === 1) {
				continue;
			}

			if($luckyDip) {
				$this->writeLuckyDip($luckyDip, $prefNum, $regID, $session, $form);

				// Alter prefnumber so it stops when it encounters our lucky dip
				if($luckyDip < $prefNum) {
					$prefNum = $luckyDip;
				}
			}

			$this->writeSessionChoices($games, $data, $prefNum, $regID, $session, $favouriteID, $form);
		}

		$this->writeHasPlayed($reg, $form);

		return $reg;
	}


	/**
	 * Write HasPlayed data for this registration
	 *
	 * @param Registration | $reg - the id of the registration this choice belongs to
	 * @param Form | $form
	 */
	public function writeHasPlayed($reg, $form) {

		$form->saveInto($reg);

		try {
			$reg->write();
		} catch(ValidationException $e) {
			$form->sessionMessage($e->getResult()->message(), 'bad');
		}
	}

	/**
	 * Write all games for this session that are less than our current number of preferences to write
	 *
	 * @param DataList | $games - A list of Games for this session
	 * @param Array | $data - submitted form data
	 * @param Int | $prefNum - the number of preferences allowed
	 * @param Int | $regID - the id of the registration this choice belongs to
	 * @param Int | $session - the session number of the lucky dip option
	 * @param Int | $favouriteID
	 * @param Form | $form
	 */
	public function writeSessionChoices($games, $data, $prefNum, $regID, $session, $favouriteID, $form) {
		foreach ($games as $game) {
			$gamePref = (int)$data["GameID_".$game->ID]; //get value of field, this should be our preference number

			// only store games with a preference higher than our threshold
			if ($gamePref > $prefNum || !isset($gamePref)) {
				continue;
			}

			$playerGame = PlayerGame::create();

			$form->saveInto($playerGame);

			$playerGame->GameID = $game->ID;
			$playerGame->ParentID = $regID;
			$playerGame->Preference = $gamePref;
			$playerGame->Session = $session;

			if($favouriteID == $game->ID) {
				$playerGame->Favourite = true;
			}

			try {
				$playerGame->write();
			} catch(ValidationException $e) {
				$form->sessionMessage($e->getResult()->message(), 'bad');
			}
		}
	}

	/**
	 * A Lucky dip is a player game where the game itself hasn't been chosen.
	 * The player would like a game, but is happy to have an organiser vhoose for them.
	 * This means we need to write a player game that has a registration, but no game assigned
	 *
	 * @param Int | $luckyDipPref - the value of the lucky dip option (choice number),
	 * @param Int | $prefNum - the number of preferences allowed
	 * @param Int | $regID - the id of the registration this choice belongs to
	 * @param Int | $session - the session number of the lucky dip option
	 * @param Form | $form
	 */
	public function writeLuckyDip($luckyDipPref, $prefNum, $regID, $session, $form) {

		// only store luckydip with a preference higher than our threshold
		if ($luckyDipPref > $prefNum) {
			return;
		}

		$playerGame = PlayerGame::create();

		$form->saveInto($playerGame);

		$playerGame->ParentID = $regID;
		$playerGame->Preference = $luckyDipPref;
		$playerGame->Session = $session;

		try {
			$playerGame->write();
		} catch(ValidationException $e) {
			$form->sessionMessage($e->getResult()->message(), 'bad');
		}
	}

	/**
	 * Returns data for the your games view (after game selection)
	 *
	 * @return array
	 */
	public function yourgames() {
		$reg = $this->getCurrentRegistration();
		if(!$reg || !$reg->PlayerGames()->Count() > 0) {
			$this->redirect($this->Link());
			return;
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
	public function getGroupedPlayerGames() {
		$reg = $this->getCurrentRegistration();

		if(!$reg){
			return false;
		}

		$playergames = $reg->PlayerGames();
		$games = new ArrayList();

		foreach($playergames as $playergame) {

			$data = array(
				"GameTitle" => $playergame->getTitle(),
				"Preference" => $playergame->Preference,
				"Favourite" => $playergame->Favourite,
				"Status" => $playergame->Status,
				"Session" => $playergame->GameSession()
			);

			if ($this->DisableFavourite()) {
				unset($data['Favourite']);
			}

			$games->push(new ArrayData($data));
		}

		$result = $games->sort(array('Session' => 'ASC', 'Preference' => 'ASC'));

		return GroupedList::create($result);
	}

	public function DisableFavourite() {
		return $this->getCurrentEvent()->DisableFavourite;
	}

	public function EnableLuckyDip() {
		return $this->getCurrentEvent()->EnableLuckyDip;
	}
}
