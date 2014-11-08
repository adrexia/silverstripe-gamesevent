<?php
/**
 *
 */
class SubmitGamePage extends Page {

	private static $hide_ancestor = "MemberProfilePage";

	private static $icon = "gamesevent/images/addgame.png";

	private static $db = array(
		'LoggedOutMessage'=>'HTMLText', 
		'AfterSubmissionContent'=>'HTMLText'
	);
	private static $has_one = array(
		'Game'=> 'Game'
	);

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$fields->insertAfter($submitted = new HtmlEditorField('AfterSubmissionContent'), 'Content');
		$submitted->setRows(20);
		$submitted->setRightTitle('Displayed after a user submits a game for approval');

		$fields->insertAfter($loggedOut = new HtmlEditorField('LoggedOutMessage'), 'Content');
		$loggedOut->setRows(20);

		return $fields;
	}
}

class SubmitGamePage_Controller extends Page_Controller {

	private static $allowed_actions = array (
		'Form' => true,
		'aftersubmission' => true,
		'addgamesubmission' => true,
		'afterediting' => true,
		'edit' => true
	);

	/*
	 * Allow the owners of games to edit games
	 * If page is reached by non owners, redirect back to the submit form
	 */
	public function edit($request) {
		$params = $request->allParams();
		$member = Member::currentUser();
		$game = Game::get()->byID($params['ID']);

		if($game->ID && $member){
			if($game->FacilitatorID === $member->ID || Permission::check('ADMIN')){ 
				$form = $this->Form();
				$fields = $form->Fields();
				$form->loadDataFrom($game);

				if($game->Status){
					$fields->removeByName('Session');
				}

				$data = array (
					'Title' => 'Edit: ' . $game->Title,
					'Content' => $this->obj('ProfileContent'),
					'Form' => $form
				);

				return $this->customise($data)->renderWith(array('SubmitGamePage_edit', 'SubmitGamePage', 'Page'));
			} else {
				$this->redirect($params['URLSegment'].'/');
			}
		} else {
			$this->redirect($params['URLSegment'].'/');
		}
	}

	public function getGamesByFacilitator(){
		$member = Member::currentUser();
		if(!$member){
			return false;
		}
		return Game::get()->filter('FacilitatorID', $member->ID);
	}

	/**
	 * Attempts to save a game 
	 *
	 * @return Member|null
	 */
	protected function addGame($form) {
		$siteConfig = SiteConfig::current_site_config();

		$member = Member::currentUser();
		$params = $this->request->allParams();

		$fields = $form->Fields();
		$id = $fields->dataFieldByName('ID')->Value();

		$game = Game::get()->byID($id);

		if(!$game){
			$game = Game::create();
		}

		$form->saveInto($game);

		$game->FacilitatorID = $game->FacilitatorID ? $game->FacilitatorID : $member->ID;
		$game->ParentID = $siteConfig->CurrentEventID;

		try {
			$game->write();
		} catch(ValidationException $e) {
			$form->sessionMessage($e->getResult()->message(), 'bad');
			return;
		}

		return $game;
	}

	/**
	 * Handles adding new games
	 */
	public function addgamesubmission($data, Form $form) {
		if($game = $this->addGame($form)) {
			$fields = $form->Fields();
			$id = $fields->dataFieldByName('ID')->Value();

			if($id){
				return $this->redirect($this->Link('afterediting'));
			} else {
				return $this->redirect($this->Link('aftersubmission'));
			}
		} else {
			return $this->redirectBack();
		}
	}

	/**
	 * Returns the after submission content to the user.
	 *
	 * @return array
	 */
	public function aftersubmission() {
		return array (
			'Title'   => "Game Submitted!",
			'Content' => $this->obj('AfterSubmissionContent'),
			'Form' => false
		);
	}

	/**
	 * Returns the after submission content to the user.
	 *
	 * @return array
	 */
	public function afterediting() {
		return array (
			'Title'   => "Game Edited",
			'Content' => "Your game has been edited successfully.",
			'Form' => false
		);
	}

	/**
	 * @uses MemberProfilePage_Controller::getProfileFields
	 * @return Form
	 */
	public function Form() {
		$fields = $this->GameFields();

		$form = new Form (
			$this,
			'Form',
			$fields,
			new FieldList(
				new FormAction('addgamesubmission', 'Submit')
			)
		);

		return $form;
	}

	public function getAllTags() {
		return singleton('GameListingPage_Controller')->getAllTags();
	}

	/*
	* Used for autocomplete type functionality when adding genres
	*/
	public function renderGenreList(){
		return $this->customise(array('Name'=>'genre-list'))->renderWith('GenreList');
	}

	public function GameFields(){
		$fields = new FieldList();

		// get current event
		$siteConfig = SiteConfig::current_site_config();
		$current = $siteConfig->getCurrentEventID();
		$event = Event::get()->byID($current);

		$genres = $this->getGroupedGames('Genre');

		$fields->push(new HiddenField('ID'));
		$fields->push(new TextField('Title'));
		$fields->push(new TextField('Restriction', 'Restriction (R18, PG, etc)'));

		// tag input field
		$fields->push($tagfield = new TextField('Genre', 'Genres'));
		$tagfield->addExtraClass('tag-field genre');

		// hidden field for all current genres
		$fields->push(new LiteralField('GenreList', $this->renderGenreList($genres)));

		$briefEditor = new TextAreaField('Brief', 'Abstract');
		$briefEditor->setRows(5);
		$fields->push($briefEditor);

		$detailsEditor = CompositeField::create(
			new LabelField('GameDetails', 'Game Details'),
			$html = new HTMLEditorField('Details'),
			new LiteralField('editorDiv', '<div class="editable"></div>')
		);
		$fields->push($detailsEditor);
		$html->addExtraClass('hide');
		$detailsEditor->addExtraClass('field');

		$costuming = new TextAreaField('Costuming', 'Costuming');
		$costuming->setRows(5);
		$fields->push($costuming);

		$fields->push(new TextField('NumPlayers', 'Number of players'));

		$sessions = array();

		if($event){
			for ($i = 1; $i <= $event->NumberOfSessions; $i++){
				$sessions[$i] = $i;
			}
			$session = new DropdownField('Session', 'Preferred Session', $sessions);
			$session->setEmptyString(' ');

			$fields->push($session);
		}

		return $fields;
	}
}
