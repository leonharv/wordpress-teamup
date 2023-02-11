<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 *
 * @package    Teamup
 * @subpackage Teamup/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Teamup
 * @subpackage Teamup/admin
 * @author     Viktor Leonhardt <viktor.leo87@gmail.com>
 */
class Teamup_Admin {

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
	 * Register the settings page and fields.
	 * 
	 * @since 1.0.0
	 */
	public function register_fields() {
		add_settings_section(
			$this->plugin_name.'_general_section',
			'',
			array($this, 'display_general'),
			$this->plugin_name.'_general_settings'
		);

		$args = array (
			'type'      => 'input',
			'subtype'   => 'text',
			'id'    => $this->plugin_name.'_api_key',
			'name'      => $this->plugin_name.'_api_key',
			'required' => 'true',
			'get_options_list' => '',
			'value_type'=>'normal',
			'wp_data' => 'option'
		);
		add_settings_field(
			$this->plugin_name.'_api_key',
			'Teamup API key',
			array($this, 'render_settings_field'),
			$this->plugin_name.'_general_settings',
			$this->plugin_name.'_general_section',
			$args
		);

		$args = array (
			'type'      => 'input',
			'subtype'   => 'text',
			'id'    => $this->plugin_name.'_calendar_id',
			'name'      => $this->plugin_name.'_calendar_id',
			'required' => 'true',
			'get_options_list' => '',
			'value_type'=>'normal',
			'wp_data' => 'option'
		);
		add_settings_field(
			$this->plugin_name.'_calendar_id',
			'Teamup Kalender ID',
			array($this, 'render_settings_field'),
			$this->plugin_name.'_general_settings',
			$this->plugin_name.'_general_section',
			$args
		);

		register_setting(
			$this->plugin_name.'_general_settings',
			$this->plugin_name.'_api_key'
		);
		register_setting(
			$this->plugin_name.'_general_settings',
			$this->plugin_name.'_calendar_id'
		);

	}

	/**
	 * Display the general settings section.
	 * 
	 * @since 1.0.0
	 * @return string The description of the settings section.
	 */
	public function display_general() {
		echo '';
	}

	/**
	 * Render the HTML for a setting based on its arguments.
	 * 
	 * @since 1.0.0
	 * @param array $args The properties of this setting.
	 */
	public function render_settings_field($args) {
		/* EXAMPLE INPUT
			'type'      => 'input',
			'subtype'   => '',
			'id'    => $this->plugin_name.'_example_setting',
			'name'      => $this->plugin_name.'_example_setting',
			'required' => 'required="required"',
			'get_option_list' => "",
			'value_type' = serialized OR normal,
         	'wp_data'=>(option or post_meta),
         	'post_id' =>
		*/
		if($args['wp_data'] == 'option'){
			$wp_data_value = get_option($args['name']);
		} elseif($args['wp_data'] == 'post_meta'){
			$wp_data_value = get_post_meta($args['post_id'], $args['name'], true );
		}

		switch ($args['type']) {
			case 'input':
				$value = ($args['value_type'] == 'serialized') ? serialize($wp_data_value) : $wp_data_value;
				if($args['subtype'] != 'checkbox'){
					$prependStart = (isset($args['prepend_value'])) ? '<div class="input-prepend"> <span class="add-on">'.$args['prepend_value'].'</span>' : '';
					$prependEnd = (isset($args['prepend_value'])) ? '</div>' : '';
					$step = (isset($args['step'])) ? 'step="'.$args['step'].'"' : '';
					$min = (isset($args['min'])) ? 'min="'.$args['min'].'"' : '';
					$max = (isset($args['max'])) ? 'max="'.$args['max'].'"' : '';
					if(isset($args['disabled'])){
						// hide the actual input bc if it was just a disabled input the informaiton saved in the database would be wrong - bc it would pass empty values and wipe the actual information
						echo $prependStart.'<input type="'.$args['subtype'].'" id="'.$args['id'].'_disabled" '.$step.' '.$max.' '.$min.' name="'.$args['name'].'_disabled" size="40" disabled value="' . esc_attr($value) . '" /><input type="hidden" id="'.$args['id'].'" '.$step.' '.$max.' '.$min.' name="'.$args['name'].'" size="40" value="' . esc_attr($value) . '" />'.$prependEnd;
					} else {
						echo $prependStart.'<input type="'.$args['subtype'].'" id="'.$args['id'].'" "'.$args['required'].'" '.$step.' '.$max.' '.$min.' name="'.$args['name'].'" size="60" value="' . esc_attr($value) . '" />'.$prependEnd;
					}
					/*<input required="required" '.$disabled.' type="number" step="any" id="'.$this->plugin_name.'_cost2" name="'.$this->plugin_name.'_cost2" value="' . esc_attr( $cost ) . '" size="25" /><input type="hidden" id="'.$this->plugin_name.'_cost" step="any" name="'.$this->plugin_name.'_cost" value="' . esc_attr( $cost ) . '" />*/
	
				} else {
					$checked = ($value) ? 'checked' : '';
					echo '<input type="'.$args['subtype'].'" id="'.$args['id'].'" "'.$args['required'].'" name="'.$args['name'].'" size="40" value="1" '.$checked.' />';
				}
				break;
			default:
				# code...
				break;
		}
	}

	/**
	 * Adds the options page to the admin menu.
	 * 
	 * @since 1.0.0
	 */
	public function options_page() {
		add_menu_page(
			$this->plugin_name,
			'Teamup',
			'manage_options',
			$this->plugin_name,
			array($this, 'render_options_page'),
			'dashicons-calendar',
			26
		);
	}

	/**
	 * Renders the settings page.
	 * 
	 * @since 1.0.0
	 */
	public function render_options_page() {
		$plugin_name = $this->plugin_name;
		require 'partials/admin-options.php';
	}

	/**
	 * This uninstalls all persistent data.
	 * 
	 * @since 1.0.0
	 */
	public static function uninstall() {
		delete_option('teamup_api_key');
		delete_option('teamup_calendar_id');
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Teamup_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Teamup_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/plugin-name-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Teamup_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Teamup_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/plugin-name-admin.js', array( 'jquery' ), $this->version, false );

	}

}