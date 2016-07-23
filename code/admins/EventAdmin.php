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

		$gridConf = $gridField->getConfig();

		$gridConf->getComponentByType('GridFieldDataColumns')->setDisplayFields(
			singleton($this->sanitiseClassName($this->modelClass))->getActiveEventDisplayFields()
		);

		$gridConf->getComponentByType('GridFieldPaginator')->setItemsPerPage(150);
		$gridConf->getComponentByType('GridFieldExportButton')
				->setExportColumns(
					singleton($this->sanitiseClassName($this->modelClass))->getExportFields()
				);

		if($this->sanitiseClassName($this->modelClass) == 'PlayerGame') {
			$list = $gridField->getList()->filter(array('EventID' => $current));
		} else {
			$list = $gridField->getList()->filter(array('ParentID' => $current));
		}

		$gridField->setList($list);

		$gridConf->addComponent(new GridFieldOrderableRows());

		$gridConf->removeComponentsByType('GridFieldDeleteAction');
		$gridConf->removeComponentsByType('GridFieldEditButton');
		$gridConf->removeComponentsByType('GridFieldDataColumns');
		$gridConf->addComponent($cols = new GridFieldEditableColumns());
		$gridConf->addComponent(new GridFieldDeleteAction());
		$gridConf->addComponent(new GridFieldButtonRow('after'));

		$gridConf->addComponent(new GridFieldEditButton());
		$gridConf->addComponent(new Milkyway\SS\GridFieldUtils\SaveAllButton('buttons-after-right'));

		$cols->setDisplayFields(singleton($this->sanitiseClassName($this->modelClass))->getEditibleDisplayFields());


		return $form;
	}
}
