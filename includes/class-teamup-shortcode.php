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

	/**
	 * The key for the API access.
	 * 
	 * @since	1.0.0
	 * @access	private
	 * @var		string		$api_key	The key for the API access.
	 */
	private $api_key;

	/**
	 * The ID of the used calendar
	 * 
	 * @since	1.0.3
	 * @access	private
	 * @var	string			$calendar_id	The ID of the used calendar.
	 */
	private $calendar_id;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version, $api_key, $calendar_id ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->api_key = $api_key;
		$this->calendar_id = $calendar_id;
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
	 * @param array $attrs The attributes of the shortcode call.
	 * @param string $content The content. if existent inside the shortcode.
	 * @return string The generated table.
	 */
    public function callback($attrs, $content = null) {

		if(array_key_exists('event', $attrs)) {
			return $this->render_event_header($attrs['event']);
		}
			
		return $this->render_calendar();
    }

	/**
	 * Render one event.
	 * 
	 * @since 1.0.3
	 * @param $event_id string|int The event_id to render.
	 * @return string The generated view of an event.
	 */
	private function render_event_header($event_id) {
		$id_array = explode('-', $event_id);

		$event = $this->get_event($id_array[0]);
		if(sizeof($event) < 1) {
			return '';
		}

		$event = $event[0];

		$start = date_create($event->start_time);
		$end = date_create($event->end_time);
		$days = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

		$output = '<p><strong>TRAININGSZEIT:</strong></p>';
		$output .= '<p><time>'. $days[$start->format('N')-1] .': '. $start->format('G:i') .' - '. $end->format('G:i') .' Uhr</time></p>';
		$output .= '<p><strong>TRAININGSORT:</strong></p>';
		$output .= '<p>'. htmlentities($event->location) .'</p>';
		$output .= '<p><strong>ÜBUNGSLEITENDE:</strong></p>';
		$output .= '<p>'. htmlentities($event->trainer) .'</p>';
		return $output;
	}

	/**
	 * Render the whole calendar as a table.
	 * 
	 * @since 1.0.3
	 * @return string The generated table.
	 */
	private function render_calendar() {
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
	 * Identifies, if the database needs to be updated. Updates the database.
	 * 
	 * This method either gets the data of the database or fetches it from
	 * Teamup. It identifies if the Monday of this week is the same one
	 * stored in the database. If so, the database contains values not older
	 * than this week. Otherwise, the database is updated from Teamup and
	 * the Monday of this week is stored as the least valid date.
	 * 
	 * @since 1.0.3
	 */
	private function check_last_fetch() {
		$last_fetch = get_option('teamup_last_fetch', 0);
		$last_monday = date_create('Monday this week');

		// If the Monday from this week is not the stored one, we have a new week.
		if($last_monday != $last_fetch) {
			$events = $this->fetch_recuring_events($this->calendar_id);
			// var_dump($events);
			$calendars = $this->fetch_calendar($this->calendar_id);

			$this->store_events($events, $calendars);

			update_option('teamup_last_fetch', $last_monday);
		}
	}

	/**
	 * Get all recurring events.
	 * 
	 * @since 1.0.2
	 * @return array The list of all recurrent events.
	 */
	private function get_calendar() {
		$this->check_last_fetch();

		return $this->query_calendar();
	}

	/**
	 * Get a single event by it event_id.
	 * 
	 * @since 1.0.3
	 * @param $event_id string|int The event_id to look for.
	 * @return array One event.
	 */
	private function get_event($event_id) {
		$this->check_last_fetch();

		return $this->query_event($event_id);
	}

	/**
	 * Fetches all calendars from Teamup.
	 * 
	 * @since 1.0.0
	 * @param string $calendar The ID of the calendar to fetch.
	 * @return array An associative array, which maps from id to name.
	 */
	private function fetch_calendar($calendar) {
		$ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://api.teamup.com/". $calendar ."/subcalendars");
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
	 * @param string $calendar The ID of the calendar to fetch.
	 * @return array All recurrent events as an stdclass.
	 */
	private function fetch_recuring_events($calendar) {
		$ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://api.teamup.com/". $calendar ."/events?startDate=Monday+this+week&endDate=Sunday+this+week");
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

			$id_array = explode('-', $event->id);

			$contact = $this->get_contact($event);
			$age = $this->get_age($event);

			$rows[] = array(
				'event_id' => $id_array[0],
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
	 * Query the database for the event by its event_id.
	 * 
	 * @since 1.0.3
	 * @param $event_id string|int The event_id to search for.
	 * @return array A list of one event.
	 */
	private function query_event($event_id) {
		return Teamup_Database::find_by_event_id($event_id);
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