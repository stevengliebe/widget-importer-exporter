<?php
/**
 * Import Functions
 *
 * @package    Widget_Importer_Exporter
 * @subpackage Functions
 * @copyright  Copyright (c) 2013, DreamDolphin Media, LLC
 * @link       https://github.com/stevengliebe/widget-importer-exporter
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * @since      0.3
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Upload import file
 *
 * @since 0.3
 */
function wie_upload_import_file() {

	// Check nonce for security since form was posted
	if ( ! empty( $_POST ) && ! empty( $_FILES['wie_import_file'] ) && check_admin_referer( 'wie_import', 'wie_import_nonce' ) ) { // check_admin_referer prints fail page and dies

		// Uploaded file
		$uploaded_file = $_FILES['wie_import_file'];

		// Check file type
		// This will also fire if no file uploaded
		$wp_filetype = wp_check_filetype_and_ext( $uploaded_file['tmp_name'], $uploaded_file['name'], false );
		if ( 'wie' != $wp_filetype['ext'] && ! wp_match_mime_types( 'wie', $wp_filetype['type'] ) ) {
			wp_die(
				__( 'You must upload a <b>.wie</b> file generated by this plugin.', 'widget-importer-exporter' ),
			'',
				array( 'back_link' => true )
			);
		}

		// Check and move file to uploads dir, get file data
		// Will show die with WP errors if necessary (file too large, quota exceeded, etc.)
		$overrides = array( 'test_form' => false );
		$file_data = wp_handle_upload( $uploaded_file, $overrides );
		if ( isset( $file_data['error'] ) ) {
			wp_die(
				$file_data['error'],
				'',
				array( 'back_link' => true )
			);
		}

		// Process import file
		wie_process_import_file( $file_data['file'] );

	}

}

add_action( 'load-tools_page_widget-importer-exporter', 'wie_upload_import_file' );

/**
 * Process import file
 *
 * This parses a file and triggers importation of its widgets.
 *
 * @since 0.3
 * @param string $file Path to .wie file uploaded
 * @global string $wie_import_results
 */
function wie_process_import_file( $file ) {

	global $wie_import_results;

	// File exists?
	if ( ! file_exists( $file ) ) {
		wp_die(
			__( 'Import file could not be found. Please try again.', 'widget-importer-exporter' ),
			'',
			array( 'back_link' => true )
		);
	}

	// Get file contents and decode
	$data = file_get_contents( $file );
	$data = json_decode( $data );

	// Delete import file
	unlink( $file );

	// Import the widget data
	// Make results available for display on import/export page
	$wie_import_results = wie_import_data( $data );

}

/**
 * Import widget JSON data
 * 
 * @since 0.4
 * @global array $wp_registered_sidebars
 * @param object $data JSON widget data from .wie file
 * @return array Results array
 */
function wie_import_data( $data ) {

	global $wp_registered_sidebars;

	// Have valid data?
	// If no data or could was not decoded
	if ( empty( $data ) || ! is_object( $data ) ) {
		wp_die(
			__( 'Import data could not be read. Please try a different file.', 'widget-importer-exporter' ),
			'',
			array( 'back_link' => true )
		);
	}

	// Hook before import
	do_action( 'wie_before_import' );

	// Get all available widgets site supports
	$available_widgets = wie_available_widgets();

	// Get all existing widget instances
	$widget_instances = array();
	foreach ( $available_widgets as $widget_data ) {
		$widget_instances[$widget_data['id_base']] = get_option( 'widget_' . $widget_data['id_base'] );
	}

	// Begin results
	$results = array();

	// Loop import data's sidebars
	foreach ( $data as $sidebar_id => $widgets ) {

		// Skip inactive widgets
		// (should not be in export file)
		if ( 'wp_inactive_widgets' == $sidebar_id ) {
			continue;
		}

		// Check if sidebar is available on this site
		// Otherwise add widgets to inactive, and say so
		if ( isset( $wp_registered_sidebars[$sidebar_id] ) ) {
			$sidebar_available = true;
			$sidebar_message_type = 'success';
			$sidebar_message = '';
		} else {
			$sidebar_available = false;
			$sidebar_message_type = 'error';
			$sidebar_message = __( 'Sidebar does not exist in theme', 'widget-importer-exporter' );
		}

		// Result for sidebar
		$results[$sidebar_id]['name'] = ! empty( $wp_registered_sidebars[$sidebar_id]['name'] ) ? $wp_registered_sidebars[$sidebar_id]['name'] : $sidebar_id; // sidebar name if theme supports it; otherwise ID
		$results[$sidebar_id]['message_type'] = $sidebar_message_type;
		$results[$sidebar_id]['message'] = $sidebar_message;
		$results[$sidebar_id]['widgets'] = array();

		// Loop widgets
		foreach ( $widgets as $widget_instance_id => $widget ) {

			$fail = false;

			// Get id_base (remove -# from end) and instance ID number
			$id_base = preg_replace( '/-[0-9]+$/', '', $widget_instance_id );
			$instance_id_number = str_replace( $id_base . '-', '', $widget_instance_id );

			// Does site support this widget?
			if ( ! $fail && ! isset( $available_widgets[$id_base] ) ) {
				$fail = true;
				$widget_message_type = 'error';
				$widget_message = __( 'Site does not support widget', 'widget-importer-exporter' ); // explain why widget not imported
			}

			// Does widget with identical settings already exist in same sidebar?
			if ( ! $fail && isset( $widget_instances[$id_base] ) ) {

				// Get existing widgets in this sidebar
				$sidebars_widgets = get_option( 'sidebars_widgets' );
				$sidebar_widgets = isset( $sidebars_widgets[$sidebar_id] ) ? $sidebars_widgets[$sidebar_id] : array();

				// Loop widgets with ID base
				$single_widget_instances = ! empty( $widget_instances[$id_base] ) ? $widget_instances[$id_base] : array();
				foreach ( $single_widget_instances as $check_id => $check_widget ) {

					// Is widget in same sidebar and has identical settings?
					if ( in_array( "$id_base-$check_id", $sidebar_widgets ) && (array) $widget == $check_widget ) {

						$fail = true;
						$widget_message_type = 'warning';
						$widget_message = __( 'Widget already exists', 'widget-importer-exporter' ); // explain why widget not imported

						break;

					}
	
				}

			}

			// No failure
			if ( ! $fail ) {

				// Add widget instance
				$single_widget_instances = get_option( 'widget_' . $id_base ); // all instances for that widget ID base, get fresh every time
				$single_widget_instances = ! empty( $single_widget_instances ) ? $single_widget_instances : array( '_multiwidget' => 1 ); // start fresh if have to
				$single_widget_instances[] = (array) $widget; // add it

					// Get the key it was given
					end( $single_widget_instances );
					$new_instance_id_number = key( $single_widget_instances );

					// Update option with new widget
					update_option( 'widget_' . $id_base, $single_widget_instances );

				// Assign widget instance to sidebar
				$sidebars_widgets = get_option( 'sidebars_widgets' ); // which sidebars have which widgets, get fresh every time
				$new_instance_id = $id_base . '-' . $new_instance_id_number; // use ID number from new widget instance
				$use_sidebar_id = $sidebar_available ? $sidebar_id : 'wp_inactive_widgets'; // add to inactive if sidebar does not exist in theme
				$sidebars_widgets[$use_sidebar_id][] = $new_instance_id; // add new instance to sidebar
				update_option( 'sidebars_widgets', $sidebars_widgets ); // save the amended data

				// Success message
				if ( $sidebar_available ) {
					$widget_message_type = 'success';
					$widget_message = __( 'Imported', 'widget-importer-exporter' );
				} else {
					$widget_message_type = 'warning';
					$widget_message = __( 'Imported to Inactive', 'widget-importer-exporter' );
				}

			}

			// Result for widget instance
			$results[$sidebar_id]['widgets'][$widget_instance_id]['name'] = isset( $available_widgets[$id_base]['name'] ) ? $available_widgets[$id_base]['name'] : $id_base; // widget name or ID if name not available (not supported by site)
			$results[$sidebar_id]['widgets'][$widget_instance_id]['title'] = $widget->title ? $widget->title : __( 'No Title', 'widget-importer-exporter' ); // show "No Title" if widget instance is untitled
			$results[$sidebar_id]['widgets'][$widget_instance_id]['message_type'] = $widget_message_type;
			$results[$sidebar_id]['widgets'][$widget_instance_id]['message'] = $widget_message;

		}

	}

	// Hook after import
	do_action( 'wie_after_import' );

	// Return results
	return apply_filters( 'wie_import_results', $results );

}
