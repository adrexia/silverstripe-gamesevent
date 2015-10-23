<?php
/*
* Admin for current event
*/

class EventAdmin extends ModelAdmin {

	private static $managed_models = array(
		'Game' => array(
			'title' => 'Games'
		),
		'Registration' => array(
			'title' => 'Registrations'
		),
		'PlayerGame' => array(
			'title' => 'Player Games'
		)
	);

	private static $url_segment = 'event';
	private static $menu_title = 'Event';

	private static $menu_icon = "gamesevent/images/pacman.png";

	public function getEditForm($id = null, $fields = null){
		$form = parent::getEditForm($id, $fields);

		$gridField = $form->Fields()->fieldByName($this->sanitiseClassName($this->modelClass));

		$siteConfig = SiteConfig::current_site_config();
		$current = $siteConfig->getCurrentEventID();

		$gridField->getConfig()->getComponentByType('GridFieldDataColumns')->setDisplayFields(
			singleton($this->sanitiseClassName($this->modelClass))->getActiveEventDisplayFields()
		);

		$gridField->getConfig()->getComponentByType('GridFieldPaginator')->setItemsPerPage(150);
		$gridField->getConfig()->getComponentByType('GridFieldExportButton')
				->setExportColumns(
					singleton($this->sanitiseClassName($this->modelClass))->getExportFields()
				);

		if($this->sanitiseClassName($this->modelClass) == 'PlayerGame') {
			$list = $gridField->getList()->filter(array('EventID' => $current));
		} else {
			$list = $gridField->getList()->filter(array('ParentID' => $current));
		}

		$gridField->setList($list);

		$gridField->getConfig()->addComponent(new GridFieldOrderableRows());

		return $form;
	}
}
