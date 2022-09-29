<?php

/**
 * Defines the main routines for this plugin.
 *
 * This class initializes and renders the shortcode. It contains the 
 * routines to call the Teamup API.
 *
 * @since      1.0.0
 *
 * @package    Teamup
 * @subpackage Teamup/includes
 */

 require_once 'class-teamup-database.php';

/**
 * Defines the main routines for this plugin.
 *
 * This class initializes and renders the shortcode. It contains the 
 * routines to call the Teamup API.
 *
 * @since      1.0.0
 * @package    Teamup
 * @subpackage Teamup/includes
 * @author     Viktor Leonhardt <viktor.leo87@gmail.com>
 */
class Teamup_Shortcode {

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

	private $api_key;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version, $api_key ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->api_key = $api_key;

	}

	/**
	 * Filter out the age of the event's description. In addition, all 
	 * html tags are removed.
	 * 
	 * @since 1.0.0
	 * @param stdclass 	$event 	The event to filter.
	 * @return string The filtered description.
	 */
	private function get_contact($event) {
		$filtering = ['kinder', 'jugend', 'erwachsene', 'senioren'];
		return preg_replace('/('.join('|', $filtering).')\s+/i', '', strip_tags($event->notes));
	}

	/**
	 * Identify the age from the event's description.
	 * 
	 * @since 1.0.0
	 * @param stdclass	$event	The event to identify the age.
	 * @return string The identified age name.
	 */
	private function get_age($event) {
		$filtering = ['kinder', 'jugend', 'erwachsene', 'senioren'];
		preg_match('/('.join('|', $filtering).')\s+/i', strip_tags($event->notes), $match);
		return $match[0];
	}

	/**
	 * This function is called to process the shortcode.
	 * 
	 * @since 1.0.0
	 * @param array $atts The attributes of the shortcode call.
	 * @param string $content The content. if existent inside the shortcode.
	 * @return string The generated table.
	 */
    public function callback($atts, $content = null) {
		$events = $this->get_calendar();

		$days = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

		$output = '<table class="exercises">
			<thead>
				<th style="width: 10%;">WOCHENTAG</th>
				<th style="width: 12%;">UHRZEIT</th>
				<th style="width: 25%;">ANGEBOT</th>
				<th style="width: 15%;">ORT</th>
				<th style="width: 20%;">ÜBUNGSLEITER</th>
				<th style="width: 16%;">KONTAKT</th>
			</thead>
		<tbody>';
		foreach($events as $event) {
			$start = date_create($event->start_time);
			$end = date_create($event->end_time);
			$output .= '<tr><td>'.$days[$start->format('N')-1].'</td><td>'.$start->format('G:i').' - '.$end->format('G:i').' Uhr</td><td>'.strip_tags($event->title).'</td><td>'.strip_tags($event->location).'</td><td>'.strip_tags($event->trainer).'</td><td>'.strip_tags($event->contact).'</td></tr>';
		}
		$output .= '</tbody></table>';
		return $output;
    }

	/**
	 * Get all recuring events.
	 * 
	 * This method either gets the data of the database or fetches it from
	 * Teamup. It identifies if the Monday of this week is the same one
	 * stored in the database. If so, the database contains values not older
	 * than this week. Otherwise, the database is updated from Teamup and
	 * the Monday of this week is stored as the least valid date.
	 * 
	 * @since 1.0.2
	 * @return array The list of all recurrent events.
	 */
	private function get_calendar() {
		$last_fetch = get_option('teamup_last_fetch', 0);
		$last_monday = date_create('Monday this week');

		// If the Monday from this week is not the stored one, we have a new week.
		if($last_monday != $last_fetch) {
			$events = $this->fetch_recuring_events();
			$calendars = $this->fetch_calendar();

			$this->store_events($events, $calendars);

			update_option('teamup_last_fetch', $last_monday);
		}

		return $this->query_calendar();
	}

	/**
	 * Fetches all calendars from Teamup.
	 * 
	 * @since 1.0.0
	 * @return array An associative array, which maps from id to name.
	 */
	private function fetch_calendar() {
		$ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://api.teamup.com/kssw3hmcj3e2ab46wg/subcalendars");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Teamup-Token: '.$this->api_key]);
	
		$server_output = curl_exec($ch);
		curl_close ($ch);
	
		$result_object = json_decode($server_output);
		
		$subcalendars = [];
		foreach($result_object->subcalendars as $calendar) {
			$subcalendars[$calendar->id] = $calendar->name;
		}
		return $subcalendars;
	}

	/**
	 * Fetches all recurrent events from Teamup.
	 * 
	 * @since 1.0.0
	 * @return array All recurrent events as an stdclass.
	 */
	private function fetch_recuring_events() {
		$ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://api.teamup.com/kssw3hmcj3e2ab46wg/events?startDate=Monday+this+week&endDate=Sunday+this+week");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Teamup-Token: '.$this->api_key]);
	
		$server_output = curl_exec($ch);
		curl_close ($ch);
	
		$result_object = json_decode($server_output);
		$events = $result_object->events;
		
		$recuring_events = [];
		foreach($events as &$event) {
			if(strpos($event->rrule, 'FREQ=WEEKLY') !== false) {
				$recuring_events[] = $event;
			}
		}
		return $recuring_events;
	}

	/**
	 * Stores all events in the database.
	 * 
	 * @since 1.0.2
	 * @param array $events All events to store.
	 * @param array $calendars The associative array, which maps from id to name.
	 */
	private function store_events($events, $calendars) {
		Teamup_Database::flush_table();

		$rows = [];
		foreach($events as $event) {
			$locations = [];
			foreach($event->subcalendar_ids as $subcalendar) {
				$locations[] = $calendars[$subcalendar];
			}

			$contact = $this->get_contact($event);
			$age = $this->get_age($event);

			$rows[] = array(
				'start_time' => $event->start_dt,
				'end_time' => $event->end_dt,
				'title' => $event->title,
				'location' => join(', ', $locations),
				'trainer' => $event->who,
				'contact' => $contact,
				'age' => $age
			);
		}

		Teamup_Database::insert_rows($rows);
	}

	/**
	 * Query the database for all recuring events.
	 * 
	 * @since 1.0.2
	 * @return array A list of all stored events.
	 */
	private function query_calendar() {
		return Teamup_Database::query_rows();
	}

	/**
	 * Uninstall routine to delete from persistent storage.
	 * 
	 * @since 1.0.2
	 */
	public static function uninstall() {
		delete_option('teamup_last_fetch');
	}
}