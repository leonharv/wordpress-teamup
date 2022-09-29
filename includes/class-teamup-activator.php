<?php

/**
 * Fired during plugin activation
 *
 * @since      1.0.0
 *
 * @package    Teamup
 * @subpackage Teamup/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Teamup
 * @subpackage Teamup/includes
 * @author     Viktor Leonhardt <viktor.leo87@gmail.com>
 */
class Teamup_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		self::check_db();
	}

	/**
	 * Identifies if the present database scheme is concurrent to the 
	 * plugin version.
	 * 
	 * @since 1.0.1
	 */
	private static function check_db() {
		require_once 'class-teamup-database.php';

		Teamup_Database::create_table();
	}

}