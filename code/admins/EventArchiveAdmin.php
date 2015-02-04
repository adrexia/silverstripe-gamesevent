<?php
/*
 * Admin for all events
 *
 */

class EventArchiveAdmin extends ModelAdmin {

	private static $managed_models = array('Game', 'Registration');
	private static $url_segment = 'eventarchive';
	private static $menu_title = 'Events Archive';

	private static $menu_icon = "gamesevent/images/ghost.png";

	public function getEditForm($id = null, $fields = null){
		$form = parent::getEditForm($id, $fields);

		$gridField = $form->Fields()->fieldByName($this->sanitiseClassName($this->modelClass));

		$gridField->getConfig()->getComponentByType('GridFieldExportButton')
			->setExportColumns(
				singleton($this->sanitiseClassName($this->modelClass))->getExportFields()
			);


		return $form;
	}

}
