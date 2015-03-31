<?php
/*
Plugin Name: Wpit Blog Stats
Plugin URI: http://paolovalenti.info/
Description: Basic stats about your blog
Author: wolly aka Paolo Valenti
Version: 1.0
Author URI: http://paolovalenti.info
*/

/*
LICENSE

Copyright 2015 Wolly aka Paolo Valenti <wolly66@gmail.com>

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 2 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
details.

You should have received a copy of the GNU General Public License along with
this program; if not, write to the Free Software Foundation, Inc., 59 Temple
Place, Suite 330, Boston, MA 02111-1307 USA
*/

define ( 'WPIT_BLOSTA_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define ( 'WPIT_BLOSTA_PLUGIN_SLUG', basename( dirname( __FILE__ ) ) );
define ( 'WPIT_BLOSTA_PLUGIN_VERSION', '1.0' );
define ( 'WPIT_BLOSTA_PLUGIN_VERSION_NAME', 'wpit_blogstats_version' );


// Create text domain for localization purpose, po files must be in languages directory
function wpit_stats_text_domain(){

load_plugin_textdomain('wpitstats', false, basename( dirname( __FILE__ ) ) . '/languages' );

}

add_action('init', 'wpit_stats_text_domain');



/**
 * Wpit_Stats class.
 */
class Wpit_Stats {

	var $option_name = 'wpit-stats-options';
	var $years_option_name = 'wpit-stats-years-options';
	var $stats_data;

	private $options;

    private $years_option;

    private $now;


	//A static member variable representing the class instance
	private static $_instance = null;



	/**
	 * Wpit_Stats::__construct()
	 * Locked down the constructor, therefore the class cannot be externally instantiated
	 *
	 * @param array $args various params some overidden by default
	 *
	 * @return
	 */

	private function __construct() {



		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'update_check' ) );
        add_action( 'init', array( $this, 'init' ) );
        add_shortcode( 'wpitstats',  array( $this, 'shortcode' ) );
        add_action( 'admin_init', array( $this, 'save' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'wpitstats_css' ) );
	}

	/**
	 * Wpit_Stats::__clone()
	 * Prevent any object or instance of that class to be cloned
	 *
	 * @return
	 */
	public function __clone() {
		trigger_error( "Cannot clone instance of Singleton pattern ...", E_USER_ERROR );
	}

	/**
	 * Wpit_Stats::__wakeup()
	 * Prevent any object or instance to be deserialized
	 *
	 * @return
	 */
	public function __wakeup() {
		trigger_error( 'Cannot deserialize instance of Singleton pattern ...', E_USER_ERROR );
	}

	/**
	 * Wpit_Stats::getInstance()
	 * Have a single globally accessible static method
	 *
	 * @param mixed $args
	 *
	 * @return
	 */
	public static function getInstance( $args = array() ) {
		if ( ! is_object( self::$_instance ) )
			self::$_instance = new self( $args );

		return self::$_instance;


	}


	/**
	 * init function.
	 *
	 * @access public
	 *
	 */
	public function init(){

		$this->stats_data = get_option( $this->option_name );

		$this->years_option = get_option( $this->years_option_name );

		$this->now = date( 'Y', time() );

	}

	/**
	 * update_check function.
	 *
	 * @access public
	 *
	 */
	public function update_check() {
	// Do checks only in backend
		if ( is_admin() ) {

			if ( version_compare( get_site_option( WPIT_BLOSTA_PLUGIN_VERSION_NAME ), WPIT_BLOSTA_PLUGIN_VERSION,  "<" ) ) {

			$this->update_plugin();

			}
		} //end if only in the admin
	}



	/**
	 * save function.Create stast and save it in $this->option_name option
	 *
	 * @access public
	 * @return void
	 */
	public function save(){

		if ( isset( $_POST['nonce_wpit_stats'] ) &&  wp_verify_nonce( $_POST['nonce_wpit_stats'], 'nonce_wpit_stats' ) &&  $_POST['check_stats'] == 'create_stats' ){

			$data = $this->data();

			update_option( $this->option_name, $data );

			$this->stats_data = get_option( $this->option_name );

			}


	}

	/**
	 * update_plugin function.
	 *
	 * This function run when the plugin is updated
	 * By now, it modifies only the option with the plugin version
	 *
	 * @access private
	 *
	 */
	private function update_plugin(){

		update_option( WPIT_BLOSTA_PLUGIN_VERSION_NAME, WPIT_BLOSTA_PLUGIN_VERSION );
	}


	/**
	 * shortcode function. Shortcode to rendere the table stats
	 *
	 * @access public
	 * @param mixed $atts
	 * @return $render_stats
	 */
	public function shortcode( $atts ) {

	// Attributes
	extract( shortcode_atts(
		array(
			'year' => 'all',
		), $atts )
	);

	$render_stats = $this->render_stats( $year );

	return $render_stats;
	}




    /**
     * add_plugin_page function.
     *
     * @access public
     *
     */
    public function add_plugin_page(){
        // This page will be under "Settings"
        $wpitstats_page = add_options_page(
            'Wpit Stats Settings',
            'Wpit Stats Settings',
            'manage_options',
            'wpit-stats-settings',
            array( $this, 'create_admin_page' )
        );

        add_action( 'admin_head-'. $wpitstats_page, array( $this, 'save' ) );
    }


    /**
     * create_admin_page function.
     *
     * Options page callback
     *
     * @access public
     * @return void
     */
    public function create_admin_page(){


		//check if years_option is an array and if there is last year
        if ( ( ! is_array( $this->years_option ) ) || ( is_array( $this->years_option ) &&  ! in_array( $this->now - 1, $this->years_option )  ) ){

			//create years array
	        $this->save_years();

	        //get new option
	        $this->years_option = get_option( $this->years_option_name );


        }

		//create the html
        ?>
        <div class="wrap">
            <h2><?php _e('Wpit Stats Settings - version: ', 'wpitstats' ) ?> <?php echo get_site_option( WPIT_BLOSTA_PLUGIN_VERSION_NAME ); ?></h2>
            <form method="post" action="">
	            <?php wp_nonce_field( 'nonce_wpit_stats', 'nonce_wpit_stats' ); ?>
	            <input type="hidden" id="check_stats" name="check_stats" value="create_stats" />
            <?php

				//get the stats list, if there are stats, $stats_list['message'] == 1
                $stats_list = $this->get_stats_list();



                if ( '1' == $stats_list['message'] ){

	                $exist = $stats_list['exist'];
	                $not_exist = $stats_list['not_exist'];

	                if ( is_array( $exist ) ){

		                ?>
		                <h3><?php echo __( 'There are stats for the following years: ', 'wpitstats') ?></h3>

		                <?php echo __( '<p>Use this shortcode in posts or pages, to show all years:
<strong> [wpitstats year="all"]</strong></p>', 'wpitstats' ); ?>

						<?php echo __( '<p>Use the following shortcodes in posts or pages, to show stats for specific year.</p>', 'wpitstats' ); ?>

		                <div id="years_list">

			            <?php

		                foreach ( $exist as $ex ){ ?>

		                	<div class="single_year"><strong>[wpitstats year="<?php echo $ex ?>"]</strong></div>

		                <?php }

			                ?></div><?php

	                }

	                if ( is_array( $not_exist ) ){

		                ?>
		                <h3 class="stats_red"><?php echo __( 'There are NO stats for the following years, please create stats.', 'wpitstats') ?></h3>

		                <div id="years_no_list"><?php

		                foreach ( $not_exist as $ex ){ ?>

		                	<div class="single_no_year"><?php echo $ex ?></span>


		                <?php }

			                ?></div><?php

	                }

                } else {

	                echo __('<h3 class="stats_red">No stats, yet. Please create stats</h3>', 'wpitstats' );
                }


                submit_button( 'Create stats' );

            ?>
            </form>
            <?php

	            echo __( '<h3>Help</h3>', 'wpitstats' );

	            echo __( '<p>You can copy the following code and then add to yours CSS theme, to have a reponsive table or copy the sample.css that you can find in the css plugin directory</p>', 'wpitstats' );

	            echo '<code>/* div stats table container */</code><br /><code>.table-stats-responsive {width: 98%; padding-left: 10px;}</code><br /><code>/* table stats border style */</code><br /><code>.table-stats-responsive table {border: #ccc solid 1px;}</code><br /><code>/* th and td */</code><br /><code>.table-stats-responsive table td, .table-stats-responsive table th {min-width: 50px; width: 16.5%; border: #ccc solid 1px; word-break: break-all; text-align: center; padding: 1%;}</code>';

		?>

        </div>
        <?php
    }


    /**
     * get_stats_list function.
     *
     * @access private
     * @return $stats_list
     */
    private function get_stats_list(){

	    if ( ! empty( $this->stats_data ) && is_array( $this->stats_data ) ){


		    foreach ( $this->years_option as  $sd ){

			    if ( array_key_exists( $sd, $this->stats_data ) ){

				    $stats_list['exist'][] = $sd;

			    } else {

				    $stats_list['not_exist'][] = $sd;
			    }
		    }

		    $stats_list['message'] = '1';


	    } else {

		    $stats_list['message'] = '-1';

	    }



	    return $stats_list;
    }

	/**
	 * render_stats function.
	 *
	 * Render of the stats
	 *
	 * @access public
	 * @param string $shortcode (default: 'all')
	 * @return $render
	 */
	public function render_stats( $shortcode = 'all' ){


		if (  is_array( $this->stats_data ) && ( 'all' == $shortcode || array_key_exists( $shortcode, $this->stats_data) ) ){

		$render ='
					<div class="table-stats-responsive">
					<table>
						<thead>
		 				<tr>
		 					<th>' . __('Year', 'wpitstats' ) . '</th>
		 					<th>' . __('Nr. Post', 'wpitstats' ). '</th>
		 					<th>' . __('Avg post character', 'wpitstats' ) . '</th>
		 					<th>' . __('Tot post character', 'wpitstats' ) . '</th>
		 					<th>' . __('Avg Comments', 'wpitstats' ) . '</th>
		 					<th>' . __('Tot Comments', 'wpitstats' ) . '</th>
		 				</tr>
		 				</thead>
		 				<tbody>';

		if ( 'all' != $shortcode ){

		$data_to_render[$shortcode] = $this->stats_data[$shortcode];


		} else {

			$data_to_render = $this->stats_data;

		}

		foreach ( $data_to_render as $key => $st ){



		$year_link = '<a href="' . get_year_link( $key ) . '" title="Archive for ' . $key . '" ><strong>' . $key . '</strong></a>';

		$render .='
					<tr>
		 				<td>' . $year_link . '</td>
		 				<td>' . number_format( $st[tot_posts] ) . '</td>
		 				<td>' . number_format( $st[avg_posts_length] ) . '</td>
		 				<td>' . number_format( $st[tot_posts_length] ) . '</td>
		 				<td>' . number_format( $st[avg_comments_for_post] ) . '</td>
		 				<td>' . number_format( $st[tot_comments] ) . '</td>
		 			</tr>';

		}


		// Close the table

		$render .='
					</tbody>
					</table>
					</div>';

		$render .= '<input type="hidden" id="check" name="check_stats" value="sent" />';

		return $render;



	} else {//close check if is array

		return _e( 'Sorry, no data', 'wpitstats' );

		}//close check if is array

	}


	/**
	 * data function.
	 *
	 * create stats
	 *
	 * @access private
	 * @return $stats
	 */
	private function data(){

		global $wpdb;

		foreach ( $this->years_option as $y ){


			$sql = "SELECT COUNT(*) FROM $wpdb->posts WHERE YEAR( post_date ) = $y AND post_type = 'post' AND post_status = 'publish' ";

			$stats[$y]['tot_posts'] = $wpdb->get_var( $sql );

			$sql = "SELECT COUNT(*) FROM $wpdb->comments WHERE YEAR( comment_date ) = $y AND comment_approved = '1' ";

			$stats[$y]['tot_comments'] = $wpdb->get_var( $sql );

			$sql = "SELECT AVG( LENGTH( post_content ) ) FROM $wpdb->posts WHERE YEAR( post_date ) = $y AND post_type = 'post' AND post_status = 'publish' ";

			$stats[$y]['avg_posts_length'] = ceil( $wpdb->get_var( $sql ) );

			$sql = "SELECT SUM( LENGTH( post_content ) ) FROM $wpdb->posts WHERE YEAR( post_date ) = $y AND post_type = 'post' AND post_status = 'publish' ";

			$stats[$y]['tot_posts_length'] =  $wpdb->get_var( $sql );

			$stats[$y]['avg_comments_for_post'] = ceil( $stats[$y]['tot_comments'] / $stats[$y]['tot_posts'] );


		}

		return $stats;
	}


	/**
	 * get_years function.
	 *
	 * find all years in wp_posts, where post_status = publish and post_type = post
	 *
	 * @access private
	 * @return $results
	 */
	private function get_years(){

		global $wpdb;

		$sql = "SELECT EXTRACT(YEAR from post_date) as year
		FROM $wpdb->posts  WHERE post_status = 'publish' AND post_type = 'post' GROUP BY year ORDER BY year DESC";



		$results = $wpdb->get_results( $sql, ARRAY_N );

		$results = $this->remove_array_level( $results );

		foreach ($results as $key => $rs ){

			if ( $this->now == $rs ){

				unset( $results[$key] );

				}

		}

		return $results;


	}


	/**
	 * save_years function.
	 *
	 * @access private
	 * Save option $this->years_option_name
	 */
	private function save_years(){

		$years = $this->get_years();

		if ( is_array( $years ) ){

			update_option( $this->years_option_name, $years );


		}


	}


	/**
	 * remove_array_level function.
	 *
	 * @access private
	 * @param mixed $array
	 * @return $array_reduced
	 */
	private function remove_array_level( $array ){

		$array_reduced = array_reduce( $array, function ( $result, $current ) { $result[] = current( $current ); return $result; }, array() );

		return $array_reduced;

	}


	/**
	 * wpitstats_css function.
	 *
	 * @access public
	 *
	 */
	public function wpitstats_css(){

		wp_register_style( 'wpitstats_css', plugins_url( '/css/wpitstats.css', __FILE__ ) );
		wp_enqueue_style( 'wpitstats_css' );
	}



}// class end

//class instance
$wpit_stats = Wpit_Stats::getInstance();