<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @since      1.0.0
 *
 * @package    Teamup
 * @subpackage Teamup/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
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

    public function callback($atts, $content = null) {
		$events = $this->get_recuring_events();
		$calendars = $this->get_calendar();

		$output = '<table><thead><th>WOCHENTAG</th><th>UHRZEIT</th><th>ANGEBOT</th><th>ORT</th><th>ÜBUNGSLEITER</th></thead><tbody>';
		foreach($events as $event) {
			$start = date_create($event->start_dt);
			$end = date_create($event->end_dt);
			$locations = [];
			// var_dump($event);
			foreach($event->subcalendar_ids as $subcalendar) {
				$locations[] = $calendars[$subcalendar];
			}
			$output .= '<tr><td>'.$start->format('l').'</td><td>'.$start->format('G:i').' - '.$end->format('G:i').' Uhr</td><td>'.$event->title.'</td><td>'.join(', ', $locations).'</td><td>'.$event->who.'</td></tr>';
		}
		$output .= '</tbody></table>';
		return $output;
    }

	private function get_calendar() {
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

	private function get_recuring_events() {
		$ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://api.teamup.com/kssw3hmcj3e2ab46wg/events?startDate=last+Monday&endDate=next+Sunday");
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
			} else {
				echo '<h1>'.$event->title.'</h1>';
			}
		}
		return $recuring_events;
	}
}