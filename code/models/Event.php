<?php

class Event extends DataObject implements PermissionProvider {

	private static $db = array(
		'Title' => 'Varchar(255)',
		'NumberOfSessions'=>'Int',
		'PreferencesPerSession'=>'Int',
		'MealOption'=>'Boolean',
		'Accommodation'=>'Int',
		'DisableFavourite'=>'Boolean'
	);

	private static $has_one = array(
		'Parent' => 'SiteConfig'
	);

	private static $has_many = array(
		'Games'=>'Game',
		'Registrations'=>'Registration'
	);

	private static $summary_fields = array(
		'Title' => 'Title',
		'NumberOfSessions' => 'Number Of Sessions',
	);

	private static $defaults = array(
		"NumberOfSessions" => 1,
		"PreferencesPerSession" => 1
	);

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->removeByName('ParentID');
		$accom = $fields->dataFieldByName('Accommodation');
		$accom->setDescription('Number of nights accommodation offered, or 0 if not applicable');
		$accom->setTitle("Accommodation (Num. nights)");

		$fav = $fields->dataFieldByName('DisableFavourite');
		$fav->setDescription('Disable the ability for users to mark a game as their favourite');

		return $fields;
	}

	public function canCreate($member = null) {
		return Permission::check('EVENTS_CREATE');
	}

	public function canEdit($member = null) {
		return Permission::check('EVENTS_EDIT');
	}

	// We want to make sure events can't be deleted if there is related event data.
	public function canDelete($member = null) {
		if(!Permission::check('EVENTS_DELETE')) {
			return false;
		}

		$game = Game::get()->filter('ParentID', $this->ID);
		$reg = Registration::get()->filter('ParentID', $this->ID);
		$pg = PlayerGame::get()->filter('EventID', $this->ID);

		if ($game->count() > 0 || $reg->count() > 0 || $pg->count() > 0) {
			return false;
		}

		return Permission::check('EVENTS_DELETE');
	}

	public function canView($member = null) {
		return Permission::check('EVENTS_VIEW');
	}


	/**
	 * Get an array of {@link Permission} definitions that this object supports
	 *
	 * @return array
	 */
	public function providePermissions() {
		return array(
			'EVENTS_VIEW' => array(
				'name' => 'View event data',
				'category' => 'Events',
			),
			'EVENTS_EDIT' => array(
				'name' => 'Edit event data',
				'category' => 'Events',
			),
			'EVENTS_DELETE' => array(
				'name' => 'Delete event data',
				'category' => 'Events',
			),
			'EVENTS_CREATE' => array(
				'name' => 'Create event data',
				'category' => 'Events'
			)
		);
	}
}
