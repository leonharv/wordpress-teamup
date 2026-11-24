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
	 * The technical markers, which are maintained inside the event's
	 * description but must never be shown on the website.
	 *
	 * `Angebot=[...]` is the legacy mechanism, which has been replaced by
	 * the structured Teamup field `Zielgruppe`. `Temperatur=[...]` is used
	 * for the heating only.
	 *
	 * @since 1.1.0
	 * @var string[]
	 */
	private static $technical_markers = ['angebot', 'temperatur'];

	/**
	 * The known target groups.
	 *
	 * @since 1.1.0
	 * @var string[]
	 */
	private static $target_groups = ['kinder', 'jugend', 'erwachsene', 'senioren'];

	/**
	 * Remove all technical markers like `Angebot=[...]` and
	 * `Temperatur=[...]` from a text and strip all html tags.
	 *
	 * The markers are removed before and after stripping the html tags, so
	 * markers which are interrupted by html (e.g. `<b>Angebot</b>=[...]`) are
	 * caught as well. A marker without a closing bracket removes the rest of
	 * its line. Block level tags are turned into line breaks first, so
	 * neighbouring paragraphs are not glued together.
	 *
	 * @since 1.1.1
	 * @param string $text The text to clean.
	 * @return string The cleaned text without html.
	 */
	private function remove_markers($text) {
		$text = (string) $text;
		$markers = join('|', self::$technical_markers);
		$space = '(?:\s|&nbsp;|&#160;)*';
		$closed = '/\b(' . $markers . ')' . $space . '=' . $space . '\[[^\]]*\]/iu';
		$open = '/\b(' . $markers . ')' . $space . '=.*$/imu';

		$text = preg_replace($closed, '', $text);
		$text = preg_replace('#<br\s*/?>|</(p|div|li|h[1-6])>#i', "\n", $text);
		$text = strip_tags($text);
		$text = str_replace(['&nbsp;', '&#160;', "\xC2\xA0"], ' ', $text);
		$text = preg_replace($closed, '', $text);
		$text = preg_replace($open, '', $text);

		// Collapse the whitespace left behind by the removed markers.
		$text = preg_replace('/[ \t]*\R\s*/u', "\n", $text);

		return trim($text);
	}

	/**
	 * The event's description without technical markers and html tags.
	 *
	 * @since 1.1.0
	 * @param stdclass 	$event 	The event to filter.
	 * @return string The filtered description.
	 */
	private function get_contact($event) {
		return $this->remove_markers(isset($event->notes) ? $event->notes : '');
	}

	/**
	 * Read the target group from the structured Teamup field `Zielgruppe`.
	 *
	 * The Teamup API delivers custom fields inside the `custom` object. The
	 * field `Zielgruppe` is a multiple choice field, therefore its value is
	 * an array of lower case strings. A single choice or text field would
	 * deliver a plain string, which is handled as well.
	 *
	 * @since 1.1.0
	 * @param stdclass	$event	The event to read the target group from.
	 * @return string A comma separated list of target groups.
	 */
	private function get_target_group($event) {
		if(!isset($event->custom)) {
			return '';
		}

		$custom = (array) $event->custom;
		$value = null;
		foreach($custom as $field => $field_value) {
			if(strtolower($field) === 'zielgruppe') {
				$value = $field_value;
				break;
			}
		}

		if($value === null) {
			return '';
		}

		// A formatted text field is delivered as an object containing html.
		if(is_object($value)) {
			$value = isset($value->html) ? $value->html : '';
		}

		$groups = [];
		foreach((array) $value as $entry) {
			$entry = strtolower(trim(strip_tags((string) $entry)));
			if($entry !== '') {
				$groups[] = $entry;
			}
		}

		return join(', ', array_unique($groups));
	}

	/**
	 * Render a target group for the public table.
	 *
	 * @since 1.1.0
	 * @param string	$target_group	The stored target group.
	 * @return string The human readable target group.
	 */
	private function render_target_group($target_group) {
		$groups = array_filter(array_map('trim', explode(',', (string) $target_group)));
		$groups = array_map(function($group) {
			return function_exists('mb_convert_case') ? mb_convert_case($group, MB_CASE_TITLE, 'UTF-8') : ucfirst($group);
		}, $groups);

		return join(', ', $groups);
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

		if(!is_array($attrs)) {
			return $this->render_all_calendar();
		}

		if(array_key_exists('event', $attrs)) {
			return $this->render_event_header($attrs['event']);
		} elseif(array_key_exists('zielgruppe', $attrs)) {
			return $this->render_target_groups($attrs['zielgruppe']);
		} elseif(array_key_exists('angebot', $attrs)) {
			// Legacy attribute, kept so existing pages keep working.
			return $this->render_target_groups($attrs['angebot']);
		}
			
		return $this->render_all_calendar();
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
	 * Render only the events of the given target groups.
	 * 
	 * @since 1.1.0
	 * @param $target_group string A list of target groups separated by comma.
	 * @return string The rendered table.
	 */
	private function render_target_groups($target_group) {
		$this->check_last_fetch();

		$target_groups = explode(',', $target_group);
		$rows = [];
		$seen = [];
		foreach($target_groups as $group) {
			$single_group = strtolower(trim($group));
			if($single_group === '') {
				continue;
			}

			// An event can belong to several target groups, so skip duplicates.
			foreach($this->query_by_target_group($single_group) as $row) {
				if(in_array($row->id, $seen)) {
					continue;
				}

				$seen[] = $row->id;
				$rows[] = $row;
			}
		}

		usort($rows, array($this, 'compare_start'));

		return $this->render_calendar($rows);
	}

	/**
	 * Helper function to compare each row by its start.
	 * 
	 * @since 1.0.4
	 * @param $a std_object An event to compare.
	 * @param $b std_object An event to compare.
	 * @return int Zero, if equal otherwise -1 if $a < $b else 1.
	 */
	private function compare_start($a, $b) {
		if($a->start_time == $b->start_time) {
			return 0;
		}

		return date_create($a->start_time) < date_create($b->start_time) ? -1 : 1;
	}

	/**
	 * Render the whole calendar. Events without a target group are left out.
	 * 
	 * @since 1.0.3
	 * @return string The generated table.
	 */
	private function render_all_calendar() {
		$events = $this->get_calendar();

		// Events without a target group (e.g. cleaning of the hall) are internal.
		$events = array_values(array_filter($events, function($event) {
			return trim((string) $event->target_group) !== '';
		}));

		return $this->render_calendar($events);
	}

	/**
	 * Render the calendar as a table.
	 * 
	 * @since 1.0.4
	 * @param $events array The list of events to render.
	 * @return string The generated table.
	 */
	private function render_calendar($events) {

		$days = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

		$output = '<table class="exercises">
			<thead>
				<th style="width: 10%;">WOCHENTAG</th>
				<th style="width: 11%;">UHRZEIT</th>
				<th style="width: 22%;">ANGEBOT</th>
				<th style="width: 12%;">ZIELGRUPPE</th>
				<th style="width: 13%;">ORT</th>
				<th style="width: 17%;">ÜBUNGSLEITER</th>
				<th style="width: 15%;">KONTAKT</th>
			</thead>
		<tbody>';
		foreach($events as $event) {
			$start = date_create($event->start_time);
			$end = date_create($event->end_time);
			$output .= '<tr><td>'.$days[$start->format('N')-1].'</td><td>'.$start->format('G:i').' - '.$end->format('G:i').' Uhr</td><td>'.strip_tags($event->title).'</td><td>'.$this->render_target_group($event->target_group).'</td><td>'.strip_tags($event->location).'</td><td>'.strip_tags($event->trainer).'</td><td>'.strip_tags($event->contact).'</td></tr>';
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
			$target_group = $this->get_target_group($event);

			$rows[] = array(
				'event_id' => $id_array[0],
				'start_time' => $event->start_dt,
				'end_time' => $event->end_dt,
				'title' => $this->remove_markers($event->title),
				'location' => join(', ', $locations),
				'trainer' => $this->remove_markers($event->who),
				'contact' => $contact,
				'target_group' => $target_group
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
	 * Query the database for all events of the given target group.
	 * 
	 * @since 1.1.0
	 * @param $target_group string The target group name.
	 * @return array A list of all events.
	 */
	private function query_by_target_group($target_group) {
		return Teamup_Database::find_by_target_group($target_group);
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