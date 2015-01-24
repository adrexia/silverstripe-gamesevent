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

}
