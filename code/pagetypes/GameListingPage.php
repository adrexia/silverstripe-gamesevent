<?php
class GameListingPage extends Page {

	private static $icon = "gamesevent/images/gamelist.png";

	/**
	 * Modified version of Breadcrumbs, to cater for viewing data objects.
	 */
	public function Breadcrumbs($maxDepth = 20, $unlinked = false, $stopAtPageType = false, $showHidden = false) {
		$page = $this;
		$pages = array();
		
		while(
			$page  
 			&& (!$maxDepth || count($pages) < $maxDepth) 
 			&& (!$stopAtPageType || $page->ClassName != $stopAtPageType)
 		) {
			if($showHidden || $page->ShowInMenus || ($page->ID == $this->ID)) { 
				$pages[] = $page;
			}
			
			$page = $page->Parent;
		}

		// Add on the item we're currently showing.
		$controller = Controller::curr();
		if ($controller) {
			$request = $controller->getRequest();
			if ($request->param('Action') == 'show') {
				$id = $request->param('ID');
				if ($id) {
					$object = DataObject::get_by_id($this->getDataClass(), $id);
					array_unshift($pages, $object);
				}
			}
		}
		
		$template = new SSViewer('BreadcrumbsTemplate');
		
		return $template->process($this->customise(new ArrayData(array(
			'Pages' => new ArrayList(array_reverse($pages))
		))));
	}

}

class GameListingPage_Controller extends Page_Controller {

	private static $allowed_actions = array(
		'show'
	);

	public function getGames($pageSize = 30){
		$siteConfig = SiteConfig::current_site_config();

		$items =  Game::get()->filter(array(
			'Status'=> true,
			'ParentID'=>$siteConfig->CurrentEventID
		));
		$items->sort('ASC');
		// Apply pagination
		$list = new AjaxPaginatedList($items, $this->request);
		$list->setPageLength($pageSize);
		return $list;
	}

	public function show($request) {
		$data = DataObject::get_by_id("Game", $request->param('ID'));
		if(!($data && $data->exists())) {
			return $this->httpError(404);
		}

		return $this->customise($data)->renderWith(array('GameListingPage_show', 'Page'));
	}

}