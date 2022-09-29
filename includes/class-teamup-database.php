<?php

/**
 * Contains all methods to interact with the database.
 *
 * @since      1.0.0
 *
 * @package    Teamup
 * @subpackage Teamup/includes
 */

/**
 * Contains all methods to interact with the database.
 *
 * It is used to create and modify the database. In addition, it adds
 * the site option `teamup_db_version` to identify the version of 
 * the database.
 *
 * @package    Teamup
 * @subpackage Teamup/includes
 * @author     Viktor Leonhardt <viktor.leo87@gmail.com>
 */
class Teamup_Database {

    /**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

    /**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Identifies, if the database schema needs to be updated.
	 * 
	 * The option `teamup_db_verion` is used to identify, if the present
	 * database is equal to the plugin version. If not, the schema will be
	 * updated and the option is set to the current version.
	 * 
	 * @since 1.0.1
	 */
    public function check_database() {
        if ( get_option( 'teamup_db_version', '1.0.0' ) != $this->version ) {
            self::create_table();
            update_option( 'teamup_db_version', $this->version );
        }
    }

	/**
	 * This method creates the database scheme.
	 * 
	 * @since 1.0.0
	 */
    public static function create_table() {
        global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . 'teamup';

		$sql = "CREATE TABLE ".$table_name." (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			start_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			end_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			title varchar(255) NOT NULL,
			location varchar(255) NOT NULL,
			trainer varchar(255) NOT NULL,
			contact text NOT NULL,
			age varchar(255) NOT NULL,
			PRIMARY KEY id (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
    }

	/**
	 * This method removes all persistent data.
	 * 
	 * @since 1.0.0
	 */
	public static function uninstall() {
		self::delete_table();

		delete_option('teamup_db_version');
	}

	/**
	 * Removes the table from the database.
	 * 
	 * @since 1.0.1
	 */
	public static function delete_table() {
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->prefix}teamup");
		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}teamup");
	}

	/**
	 * Removes all content from the table.
	 * 
	 * @since 1.0.2
	 */
	public static function flush_table() {
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->prefix}teamup");
	}

	/**
	 * Inserts data into the table.
	 * 
	 * @since 1.0.2
	 * @param array $rows The data to insert.
	 */
	public static function insert_rows($rows) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'teamup';

		foreach($rows as $row) {
			$row_safe = $wpdb->_escape($row);
			$wpdb->query('INSERT INTO '.$table_name.' (start_time, end_time, title, location, trainer, contact, age) VALUES ("'.join('", "', $row_safe).'")');
		}
	}

	/**
	 * Get all rows from the table.
	 * 
	 * @since 1.0.2
	 * @return array A list of all rows.
	 */
	public static function query_rows() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'teamup';

		$results = $wpdb->get_results('SELECT * FROM '.$table_name);

		return $results;
	}
}