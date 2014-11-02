<?php
/**
 * Customisation to the MemberProfilePage from the memberprofile module, to deal with event specific fields
 */

class RegistrationPage extends MemberProfilePage {

	private static $hide_ancestor = "MemberProfilePage";
	private static $has_one = array(
		'Registration'=> 'Registration'
	);
}

class RegistrationPage_Controller extends MemberProfilePage_Controller {

	private static $allowed_actions = array (
		'index',
		'RegisterForm',
		'afterregistration',
		'ProfileForm',
		'add',
		'AddForm',
		'confirm',
		'show'
	);
	/**
	 * @uses   MemberProfilePage_Controller::indexRegister
	 * @uses   MemberProfilePage_Controller::indexProfile
	 * @return array
	 */
	public function index() {
		if (isset($_GET['BackURL'])) {
			Session::set('MemberProfile.REDIRECT', $_GET['BackURL']);
		}
		$mode = Member::currentUser() ? 'profile' : 'register';
		$data = Member::currentUser() ? $this->indexProfile() : $this->indexRegister();

		// Need to check if already a member but no current registration, and show indexProfile with extra fields
		// If member and registration, need to hide from main menu (but allow from login edit)

		if (is_array($data)) {
			return $this->customise($data)->renderWith(array('MemberProfilePage_'.$mode, 'MemberProfilePage', 'Page'));
		}
		return $data;
	}

	/**
	 * Allow users to register if registration is enabled.
	 *
	 * @return array
	 */
	protected function indexRegister() {
		if(!$this->AllowRegistration) return Security::permissionFailure($this, _t (
			'MemberProfiles.CANNOTREGPLEASELOGIN',
			'You cannot register on this profile page. Please login to edit your profile.'
		));

		return array (
			'Title'   => $this->obj('RegistrationTitle'),
			'Content' => $this->obj('RegistrationContent'),
			'Form'    => $this->RegisterForm()
		);
	}


	/**
	 * Handles validation and saving new Member objects, as well as sending out validation emails.
	 */
	public function register($data, Form $form) {
		if($member = $this->addMember($form)) {
			$this->addRegistration($form, $member);


			if(!$this->RequireApproval && $this->EmailType != 'Validation' && !$this->AllowAdding) {
				$member->logIn();
			}

			if ($this->RegistrationRedirect) {
				if ($this->PostRegistrationTargetID) {
					$this->redirect($this->PostRegistrationTarget()->Link());
					return;
				}

				if ($sessionTarget = Session::get('MemberProfile.REDIRECT')) {
					Session::clear('MemberProfile.REDIRECT');
					if (Director::is_site_url($sessionTarget)) {
						$this->redirect($sessionTarget);
						return;
					}
				}
			}


			return $this->redirect($this->Link('afterregistration'));
		} else {
			return $this->redirectBack();
		}
	}


	/**
	 * Attempts to save a registration 
	 *
	 * @return Member|null
	 */
	protected function addRegistration($form, $member) {
		$register = new Registration();
		$siteConfig = SiteConfig::current_site_config();

		$form->saveInto($register);

		$register->MemberID = $member->ID;
		$register->ParentID = $siteConfig->CurrentEventID;

		try {
			$register->write();
		} catch(ValidationException $e) {
			$form->sessionMessage($e->getResult()->message(), 'bad');
			return;
		}

		return $register;
	}


	public function getRegistration($memberID) {
		$siteConfig = SiteConfig::current_site_config();

		return Registration::get()->filter(array(
			"MemberID"=>$memberID,
			'ParentID'=>$siteConfig->CurrentEventID
		))->First();
	}



	/**
	 * Allows users to edit their profile if they are in at least one of the
	 * groups this page is restricted to, and editing isn't disabled.
	 *
	 * If editing is disabled, but the current user can add users, then they
	 * are redirected to the add user page.
	 *
	 * @return array
	 */
	protected function indexProfile() {
		if(!$this->AllowProfileEditing) {
			if($this->AllowAdding && Injector::inst()->get('Member')->canCreate()) {
				return $this->redirect($this->Link('add'));
			}

			return Security::permissionFailure($this, _t(
				'MemberProfiles.CANNOTEDIT',
				'You cannot edit your profile via this page.'
			));
		}

		$member = Member::currentUser();
		$registration = $this->getRegistration($member->ID);

		foreach($this->Groups() as $group) {
			if(!$member->inGroup($group)) {
				return Security::permissionFailure($this);
			}
		}

		$form = $this->ProfileForm();
		$form->loadDataFrom($member);

		if($registration){
			$form->loadDataFrom($registration);
		}

		if($password = $form->Fields()->fieldByName('Password')) {
			$password->setCanBeEmpty(false);
			$password->setValue(null);
			$password->setCanBeEmpty(true);
		}

		return array (
			'Title' => $this->obj('ProfileTitle'),
			'Content' => $this->obj('ProfileContent'),
			'Form'  => $form
		);
	}


	/**
	 * @uses   MemberProfilePage_Controller::getProfileFields
	 * @return Form
	 */
	public function RegisterForm() {
		$fields = $this->getProfileFields('Registration');
		$fields = $this->RegistrationFields($fields);

		$form = new Form (
			$this,
			'RegisterForm',
			$fields,
			new FieldList(
				new FormAction('register', _t('MemberProfiles.REGISTER', 'Register'))
			),
			new MemberProfileValidator($this->Fields())
		);

		if(class_exists('SpamProtectorManager')) {
			SpamProtectorManager::update_form($form);
		}

		$this->extend('updateRegisterForm', $form);

		return $form;
	}

	public function RegistrationFields($fields){
		$siteConfig = SiteConfig::current_site_config();
		$event = $siteConfig->CurrentEvent();

		$attending = new CompositeField(array(
			new CheckboxField('AttendingWholeEvent', 'Attending whole event'),
			new TextField('AttendingTheseSessions', '...or Attending these rounds')
		));

		$fields->push($attending);

		$playing = new CompositeField(array(
			new TextField('PlayWith', "I want to play with"),
			new TextField('NotPlayWith', "I'd rather not play with")
		));

		$fields->push($playing);

		if($event->MealOption) {
			$meals = new CompositeField(array(
				new CheckboxField('Meals', 'I want meals provided'),
				new TextField('SpecialDietryInfo')
			));
			
			$fields->push($meals);
		}

		if($event->Accommodation) {

			$accom = array();

			for ($i = 1; $i <= $event->Accommodation; $i++){
				$accom[$i] = $i." night(s)";
				if($i === 1) {
					$accom[$i] = $i." night";
				} else {
					$accom[$i] = $i." nights";
				}
			}

			$nights = new DropdownField('Accommodation', 'Accommodation required', $accom);
			$nights->setEmptyString("None");
			$fields->push($nights);
		}
		$fields->push(new TextareaField('ExtraDetail', 'Comments'));
		return $fields;
	}


	/**
	 * @uses   MemberProfilePage_Controller::getProfileFields
	 * @return Form
	 */
	public function ProfileForm() {
		$fields = $this->getProfileFields('Profile');
		$fields = $this->RegistrationFields($fields); // need to fill with stored values. sort out saving first
		$form = new Form (
			$this,
			'ProfileForm',
			$fields,
			new FieldList(
				new FormAction('save', _t('MemberProfiles.SAVE', 'Save'))
			),
			new MemberProfileValidator($this->Fields(), Member::currentUser())
		);
		$this->extend('updateProfileForm', $form);
		return $form;
	}

	/**
	 * Updates an existing Member's profile.
	 */
	public function save(array $data, Form $form) {
		$member = Member::currentUser();
		$registration = $this->getRegistration($member->ID);

		$groupIds = $this->getSettableGroupIdsFrom($form, $member);
		$member->Groups()->setByIDList($groupIds);

		$form->saveInto($member);
		$form->saveInto($registration);

		try {
			$member->write();
		} catch(ValidationException $e) {
			$form->sessionMessage($e->getResult()->message(), 'bad');
			return $this->redirectBack();
		}

		try {
			$registration->write();
		} catch(ValidationException $e) {
			$form->sessionMessage($e->getResult()->message(), 'bad');
			return $this->redirectBack();
		}

		$form->sessionMessage (
			'Your details have been updated.',
			'good'
		);
		return $this->redirectBack();
	}


	
}

