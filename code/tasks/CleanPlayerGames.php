<?php

class ProcessMembersTask extends BuildTask {

	protected $title = "Clean up PlayerGames";

	protected $description = "Removes all playergames with no registration (useful for deleting test data)";

	/**
	 * Main task function
	 */
	public function run($request)  {
		$playergames = PlayerGame::get();

		$count = 0;

		foreach ($playergames as $playergame) {
			if(!$playergame->Parent()->ID) {
				$playergame->delete();
				$count++;
			}
		}

		echo 'Deleted ' . $count . ' unattached player games';
	}



}
