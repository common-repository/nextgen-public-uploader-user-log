<?php

/*
Plugin Name: NextGEN Public Uploader User Log
Plugin URI: http://dev.infiniteschema.com/nextgen-public-uploader-user-log/
Description: Logs who uploads each picture via NextGEN Public Uploader plugin.
Version: 3.6.1
Author: Infinite Schema
Author URI: http://infiniteschema.com
License: GPLv2
*/

/* 
Copyright (C) 2013 Calen Fretts

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
*/

defined('ABSPATH') or die("Cannot access directly.");

// If NextGEN Public Uploader doesn't exist, or it's not active...
if ( !in_array( 'nextgen-public-uploader/nextgen-public-uploader.php', get_option('active_plugins') ) ) {
	// Display Error Message
	add_action( 'admin_notices', 'npuul_error_message' );
	function npuul_error_message() {
		// Include thickbox support
		add_thickbox();

		// Generate our error message
		$output = '';
		$output .= '<div id="message" class="error">';
		$output .= '<p><strong>NextGEN Public Uploader User Log</strong> requires NextGEN Public Uploader in order to work. Please deactivate NextGEN Public Uploader User Log or activate <a href="' . admin_url( '/plugin-install.php?tab=plugin-information&plugin=nextgen-public-uploader&TB_iframe=true&width=600&height=550' ) . '" target="_blank" class="thickbox onclick">NextGEN Public Uploader</a>.</strong></p>';
		$output .= '</div>';
		echo $output;
	}
	return;
}

global $npuul_db_version;
$npuul_db_version = "1.0";

/**
 * Adds new column to the NGG table.
 * 
 * @return void
 */
function npuul_install() {
   global $wpdb, $npuul_db_version;

	$installed_ver = get_option( "npuul_db_version" );
	if( $installed_ver == $npuul_db_version ) {
		return;
	}

	// add charset & collate like wp core
	$charset_collate = '';

	if ( version_compare(mysql_get_server_info(), '4.1.0', '>=') ) {
		if ( ! empty($wpdb->charset) )
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		if ( ! empty($wpdb->collate) )
			$charset_collate .= " COLLATE $wpdb->collate";
	}

	$sql = "CREATE TABLE $wpdb->nggpictures (
	pid BIGINT(20) NOT NULL AUTO_INCREMENT ,
	image_slug VARCHAR(255) NOT NULL ,
	post_id BIGINT(20) DEFAULT '0' NOT NULL ,
	galleryid BIGINT(20) DEFAULT '0' NOT NULL ,
	filename VARCHAR(255) NOT NULL ,
	description MEDIUMTEXT NULL ,
	alttext MEDIUMTEXT NULL ,
	imagedate DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
	exclude TINYINT NULL DEFAULT '0' ,
	sortorder BIGINT(20) DEFAULT '0' NOT NULL ,
	meta_data LONGTEXT,
	uid BIGINT(20),
	PRIMARY KEY  (pid),
	KEY post_id (post_id)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');//for dbDelta
	dbDelta( $sql );

	add_option( "npuul_db_version", $npuul_db_version );
}
register_activation_hook( __FILE__, 'npuul_install' );

/**
 * Adds uid to newly uploaded image.
 * 
 * @param array $array_image array( 'id' => $pic_id, 'filename' => $picture, 'galleryID' => $galleryID)
 * 
 * @return void
 */
function npuul_added_new_image($array_image) {
	global $wpdb;
	$uid = get_current_user_id();

	$wpdb->update( 
		$wpdb->nggpictures, 
		array( 'uid' => $uid ), 
		array( 'pid' => $array_image['id'] ), 
		array( '%d' ), 
		array( '%d' ) 
	);
}
add_action( 'ngg_added_new_image', 'npuul_added_new_image' );

/**
 * Returns user's uploaded images as debug string or list of ids
 * 
 * @param array $atts
 * 
 * @return string
 */
function npuul_shortcode( $atts ) {
	extract( shortcode_atts( array(
		'format' => FALSE,
		'ngg_images_params' => 'display_type="photocrati-nextgen_basic_thumbnails"',
	), $atts ) );
	
	$uid = get_current_user_id();
	$pids = npuul_images($uid);
	
	if (empty($pids)) {
		return '<div class="npuul npuul-empty">'.__( 'No images uploaded yet.', 'nextgen-public-uploader-user-log' ).'</div>';
	}
	
	if ($format == "debug") {
		return '<div class="npuul npuul-debug">'.print_r($pids, TRUE).'</div>';
	}
	
	if ($format == "list") {
		$content = "";
		foreach ($pids as $pid) {
			$content .= '<li>'.$pid.'</li>';
		}
		return '<div class="npuul npuul-list"><ul>'.$content.'</ul></div>';
	}

	//$images = nggdb::find_images_in_list($pids);//could use this in future
	
	// see: http://www.nextgen-gallery.com/nextgen-gallery-shortcodes/
	$content = do_shortcode('[ngg_images image_ids="'.implode(",", $pids).'" '.$ngg_images_params.']');
	return '<div class="npuul npuul-content">'.$content.'</div>';
}
add_shortcode( 'npuul', 'npuul_shortcode' );

/**
 * Returns user's uploaded images as array
 * 
 * @param int $uid
 * 
 * @return array | FALSE
 */
function npuul_images( $uid ) {
	global $wpdb;
	return $wpdb->get_col("SELECT pid FROM $wpdb->nggpictures WHERE uid = '$uid'");
}
