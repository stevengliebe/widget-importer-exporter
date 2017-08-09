<?php
/**
 * Admin Page Functions
 *
 * @package    Widget_Importer_Exporter
 * @subpackage Functions
 * @copyright  Copyright (c) 2013 - 2017, churchthemes.com
 * @link       https://churchthemes.com/plugins/widget-importer-exporter
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * @since      0.1
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Add import/export page under Tools
 *
 * Also enqueue Stylesheet for this page only.
 *
 * @since 0.1
 */
function wie_add_import_export_page() {

	// Add page
	$page_hook = add_management_page(
		esc_html__( 'Widget Importer & Exporter', 'widget-importer-exporter' ), // page title
		esc_html__( 'Widget Importer & Exporter', 'widget-importer-exporter' ), // menu title
		'edit_theme_options', // capability (can manage Appearance > Widgets)
		'widget-importer-exporter', // menu slug
		'wie_import_export_page_content' // callback for displaying page content
	);

	// Enqueue stylesheet
 	add_action( 'admin_print_styles-' . $page_hook, 'wie_enqueue_styles' );

}

add_action( 'admin_menu', 'wie_add_import_export_page' ); // register post type

/**
 * Enqueue stylesheets for import/export page
 *
 * @since 0.1
 */
function wie_enqueue_styles() {
	wp_enqueue_style( 'wie-main', WIE_URL . '/' . WIE_CSS_DIR . '/style.css', false, WIE_VERSION ); // bust cache on update
}

/**
 * Import/export page content
 *
 * @since 0.1
 */
function wie_import_export_page_content() {

	?>

	<div class="wrap">

		<?php screen_icon(); ?>

		<h2><?php esc_html_e( 'Widget Importer & Exporter', 'widget-importer-exporter' ); ?></h2>

		<?php
		// Show import results if have them
		if ( wie_have_import_results() ) {

			wie_show_import_results();

			wie_footer();

			return; // don't show content below

		}
		?>

		<h3 class="title"><?php echo esc_html_x( 'Import Widgets', 'heading', 'widget-importer-exporter' ); ?></h3>

		<p>
			<?php
			echo wp_kses(
				__( 'Please select a <b>.wie</b> file generated by this plugin.', 'widget-importer-exporter' ),
				array(
					'b' => array()
				)
			);
			?>
		</p>

		<form method="post" enctype="multipart/form-data">

			<?php wp_nonce_field( 'wie_import', 'wie_import_nonce' ); ?>

			<input type="file" name="wie_import_file" id="wie-import-file" />

			<?php submit_button( esc_html_x( 'Import Widgets', 'button', 'widget-importer-exporter' ) ); ?>

		</form>

		<?php if ( ! empty( $wie_import_results ) ) : ?>
			<p id="wie-import-results">
				<?php echo $wie_import_results; ?>
			</p>
			<br />
		<?php endif; ?>

		<h3 class="title"><?php echo esc_html_x( 'Export Widgets', 'heading', 'widget-importer-exporter' ); ?></h3>

		<p>
			<?php
			echo wp_kses(
				__( 'Click below to generate a <b>.wie</b> file for all active widgets.', 'widget-importer-exporter' ),
				array(
					'b' => array()
				)
			);
			?>
		</p>

		<p class="submit">
			<a href="<?php echo esc_url( admin_url( basename( $_SERVER['PHP_SELF'] ) . '?page=' . $_GET['page'] . '&export=1&wie_export_nonce=' . wp_create_nonce( 'wie_export' ) ) ); ?>" id="wie-export-button" class="button button-primary">
				<?php echo esc_html_x( 'Export Widgets', 'button', 'widget-importer-exporter' ); ?>
			</a>
		</p>

	</div>

	<?php

	wie_footer();

}

/**
 * Have import results to show?
 *
 * @since 0.3
 * @global string $wie_import_results
 * @return bool True if have import results to show
 */
function wie_have_import_results() {

	global $wie_import_results;

	if ( ! empty( $wie_import_results ) ) {
		return true;
	}

	return false;

}

/**
 * Show import results
 *
 * This is shown in place of import/export page's regular content.
 *
 * @since 0.3
 * @global string $wie_import_results
 */
function wie_show_import_results() {

	global $wie_import_results;

	?>

	<h3 class="title"><?php echo esc_html_x( 'Import Results', 'heading', 'widget-importer-exporter' ); ?></h3>

	<p>
		<?php
		printf(
			wp_kses(
				__( 'You can manage your <a href="%s">Widgets</a> or <a href="%s">Go Back</a>.', 'widget-importer-exporter' ),
				array(
					'a' => array(
						'href' => array()
					)
				)
			),
			esc_url( admin_url( 'widgets.php' ) ),
			esc_url( admin_url( basename( $_SERVER['PHP_SELF'] ) . '?page=' . $_GET['page'] ) )
		);
		?>
	</p>

	<table id="wie-import-results">

		<?php
		// Loop sidebars
		$results = $wie_import_results;
		foreach ( $results as $sidebar ) :
		?>

			<tr class="wie-import-results-sidebar">
				<td colspan="2" class="wie-import-results-sidebar-name">
					<?php echo $sidebar['name']; // sidebar name if theme supports it; otherwise ID ?>
				</td>
				<td class="wie-import-results-sidebar-message wie-import-results-message wie-import-results-message-<?php echo $sidebar['message_type']; ?>">
					<?php echo $sidebar['message']; // sidebar may not exist in theme ?>
				</td>
			</tr>

			<?php
			// Loop widgets
			foreach ( $sidebar['widgets'] as $widget ) :
			?>

			<tr class="wie-import-results-widget">
				<td class="wie-import-results-widget-name">
					<?php echo $widget['name']; // widget name or ID if name not available (not supported by site) ?>
				</td>
				<td class="wie-import-results-widget-title">
					<?php echo $widget['title']; // shows "No Title" if widget instance is untitled ?>
				</td>
				<td class="wie-import-results-widget-message wie-import-results-message wie-import-results-message-<?php echo $widget['message_type']; ?>">
					<?php echo $widget['message']; // sidebar may not exist in theme ?>
				</td>
			</tr>

			<?php endforeach; ?>

			<tr class="wie-import-results-space">
				<td colspan="100%"></td>
			</tr>

		<?php endforeach; ?>

	</table>

	<?php

}

/**
 * Show footer
 *
 * Outputs information on supporting the project and getting support
 */
function wie_footer() {

	?>

	<p id="wie-help">

		<?php
		printf(
			wp_kses(
				/* translators: %1$s is URL to support forum */
				__( '<b>Need Help?</b> Post your question in the plugin\'s <a href="%1$s" target="_blank">Support Forum</a>.', 'widget-importer-exporter' ),
				array(
					'b' => array(),
					'a' => array(
						'href'	=> array(),
						'target'	=> array(),
					),
				)
			),
			'https://wordpress.org/support/plugin/widget-importer-exporter/'
		);
		?>

	</p>

	<div id="wie-support-project" class="wie-box">

		<h4>Support This Project</h4>

		<p>

			<?php
			printf(
				wp_kses(
					__( 'Please be one of the special few to support this plugin with a gift or review. There are costs to cover with more than 1,000,000 free downloads and free support. <b>Thank you!</b>', 'widget-importer-exporter' ),
					array(
						'b' => array(),
					)
				),
				'https://churchthemes.com/project-support/',
				'https://wordpress.org/support/plugin/widget-importer-exporter/reviews/?filter=5'
			);
			?>

		</p>

		<p>
			<a href="https://churchthemes.com/project-support/" class="button" target="_blank"><?php esc_html_e( 'Give $5 or More', 'widget-importer-exporter' ); ?></a>
			<a href="https://wordpress.org/support/plugin/widget-importer-exporter/reviews/?filter=5" class="button" target="_blank"><?php esc_html_e( 'Add Your Review', 'widget-importer-exporter' ); ?></a>
		</p>

		<p>

			<i>

				<?php
				printf(
					wp_kses(
						__( 'Visit <a href="%1$s" target="_blank">churchthemes.com</a> and follow us on <a href="%2$s" target="_blank">Twitter</a> and <a href="%3$s" target="_blank">Facebook</a>', 'widget-importer-exporter' ),
						array(
							'a' => array(
								'href' => array(),
								'target' => array(),
							),
						)
					),
					'https://churchthemes.com',
					'https://twitter.com/churchthemes',
					'https://www.facebook.com/churchthemescom'
				);
				?>

			</i>

		</p>

	</div>

	<?php

}
