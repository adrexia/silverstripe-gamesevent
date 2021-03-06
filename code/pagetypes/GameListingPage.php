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

	public function getCurrentGames() {
		$siteConfig = SiteConfig::current_site_config();

		// show all if in draft mode
		$mode = Versioned::get_reading_mode();

		if($mode == 'Stage.Stage') {
			$items = Game::get()->filter(array(
				'ParentID'=>$siteConfig->CurrentEventID
			));
		} else {
			$items = Game::get()->filter(array(
				'Status'=> true,
				'ParentID'=>$siteConfig->CurrentEventID
			));
		}

		return $items;
	}

}

class GameListingPage_Controller extends Page_Controller {

	private static $allowed_actions = array(
		'show'
	);

	public function FilteredGames($pageSize = 30){
		$items = $this->getCurrentGames();
		$items->sort('Title','ASC');

		// Apply pagination
		$list = new AjaxPaginatedList($items, $this->request);
		$list->setPageLength($pageSize);
		return $list;
	}

	public function getAllTags($current = false) {
		if($current){
			$games = $this->getCurrentGames();
		} else {
			$games = Game::get();
		}

		$list = array();
		$result = new ArrayList();
		foreach ($games as $game){
			$tags = $game->getGenresArray();
			for ($i = 0; $i < count($tags); $i++){
				$list[$tags[$i]] = $tags[$i];
			}
		}

		asort($list);

		foreach($list as $item) {
			$result->push(new ArrayData(array(
				'Title' => $item
			)));
		}

		return $result;
	}

	/**
	 * Returns all modules, sorted by their title.
	 * @return GroupedList
	 */
	public function getGroupedGames($sort = 'Session') {
		$items = $this->getCurrentGames();

		return GroupedList::create($items->sort($sort));
	}

	public function show($request) {
		$data = DataObject::get_by_id("Game", $request->param('ID'));
		if(!($data && $data->exists())) {
			return $this->httpError(404);
		}

		return $this->customise($data)->renderWith(array('GameListingPage_show', 'Page'));
	}

	// Strip underscores and spaces from a string
	public function NiceString($title, $sentenceCase = false) {
		$title = str_replace("_",  " ", $title);
		if ($sentenceCase) {
			$title = ucfirst($title);
		}
		return str_replace("-",  " ", $title);
	}

}
