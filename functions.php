<?php
/**
 * Global scope functions
 *
 * @package Site_Walker
 */

defined( 'ABSPATH' ) || die();

function stwlk_get_site_walker(): Site_Walker\Plugin {
	global $stwlk_plugin;
	return $stwlk_plugin;
}
