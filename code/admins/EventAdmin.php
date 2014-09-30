<?php
/*
* Admin for events
*
*/

class EventAdmin extends ModelAdmin {

	private static $managed_models = array('Game', 'Registration');
	private static $url_segment = 'event';
	private static $menu_title = 'Event';

	private static $menu_icon = "gamesevent/images/pacman.png";

	public function getEditForm($id = null, $fields = null){
		$form = parent::getEditForm($id, $fields);

		$gridField = $form->Fields()->fieldByName($this->sanitiseClassName($this->modelClass));

		$siteConfig = SiteConfig::current_site_config();
		$current = $siteConfig->getCurrentEventID();

		$gridField->getConfig()->getComponentByType('GridFieldDataColumns')->setDisplayFields(
			singleton($this->sanitiseClassName($this->modelClass))->getCurrentDisplayFields()
		);

		$list = $gridField->getList()->filter(array('ParentID'=>$current));
		$gridField->setList($list);

		return $form;
	}
}
