<?php
/**
 * The admin settings page logic.
 * 
 * Handles the settings pages accessible via the admin interface. The default
 * location should be "Settings > Name of the Plugin".
 */
namespace MultiFeedReader\Settings;
use MultiFeedReader\Models\FeedCollection as FeedCollection;
use MultiFeedReader\Models\Feed as Feed;

const HANDLE = 'multi_feed_reader_handle';

/**
 * Postbox helper function.
 * 
 * @param string $name
 * @param function $content
 */
function postbox( $name, $content ) {
	?>
	<div class="postbox">
		<h3><span><?php echo $name; ?></span></h3>
		<div class="inside">
			<?php call_user_func( $content ); ?>
		</div> <!-- .inside -->
	</div>
	<?php
}

/**
 * @todo the whole template can probably be abstracted away
 * @todo reduce set_tab() to only receive the name and auto-deduce id from name
 * 
 * something like
 *   $settings_page = new TwoColumnSettingsPage()
 *   $tabs = new \MultiFeedReader\Lib\Tabs;
 *   // configure tabs ...
 *   $settings_page->add_tabs( $tabs );
 * 
 *   - display of content naming-convention based
 *   - needs a flexible soution for sidebar, though; first step might be to
 *     redefine sidebar for each tab separately
 *   - bonus abstraction: intelligently display page based on whether there
 *     are tabs or not
 *   - next bonus abstraction: Also implement SingleColumnSettingsPage() and
 *     have some kind of interface to plug different page classes
 */
function initialize() {
	// UPDATE action
	if ( isset( $_POST[ 'feedcollection' ] ) ) {
		$current = FeedCollection::current();
		// TODO iterate properties, not POST entries
		foreach ( FeedCollection::property_names() as $property ) {
			if ( isset( $_POST[ 'feedcollection' ][ $property ] ) ) {
				$current->$property = $_POST[ 'feedcollection' ][ $property ];
			}
		}
		$current->save();
		
		if ( isset( $_POST[ 'feedcollection' ][ 'feeds' ] ) ) {
			// update feeds
			foreach ( $_POST[ 'feedcollection' ][ 'feeds' ] as $feed_id => $feed_url ) {
				if ( ! is_numeric( $feed_id ) ) {
					continue;
				}
				$feed = Feed::find_by_id( $feed_id );
				if ( empty( $feed_url ) ) {
					$feed->delete();
				} else if ( $feed->url != $feed_url ) {
					$feed->url = $feed_url;
					$feed->save();
				}
			}
			
			// create feeds
			if ( isset( $_POST[ 'feedcollection' ][ 'feeds' ][ 'new' ] ) ) {
				foreach ( $_POST[ 'feedcollection' ][ 'feeds' ][ 'new' ] as $feed_url ) {
					$feed = new Feed();
					$feed->feed_collection_id = $current->id;
					$feed->url = $feed_url;
					$feed->save();
				}
			}
		}
		
	}
	
	// CREATE action
	if ( isset( $_POST[ 'mfr_new_feedcollection_name' ] ) ) {
		$name = $_POST[ 'mfr_new_feedcollection_name' ];
		$fc = new FeedCollection();
		$fc->name = $name;
		
		// $collection = FeedCollection::create_by_id( $id );
		if ( ! $fc->save() ) {
			?>
			<div class="error">
				<p>
					<?php echo wp_sprintf( \MultiFeedReader\t( 'Feedcollection "%1s" already exists.' ), $name ) ?>
				</p>
			</div>
			<?php
		}
	}
	
	$tabs = new \MultiFeedReader\Lib\Tabs;
	$tabs->set_tab( 'edit', \MultiFeedReader\t( 'Edit Feedcollection' ) );
	$tabs->set_tab( 'add', \MultiFeedReader\t( 'Add Feedcollection' ) );
	$tabs->set_default( 'edit' );
	
	if ( ! FeedCollection::has_entries() ) {
		$tabs->enforce_tab( 'add' );
	}
	?>
	<div class="wrap">

		<div id="icon-options-general" class="icon32"></div>
		<?php $tabs->display() ?>

		<div class="metabox-holder has-right-sidebar">

			<div class="inner-sidebar">

				<?php display_creator_metabox(); ?>

				<!-- ... more boxes ... -->

			</div> <!-- .inner-sidebar -->

			<div id="post-body">
				<div id="post-body-content">
					<?php
					switch ( $tabs->get_current_tab() ) {
						case 'edit':
							display_edit_page();
							break;
						case 'add':
							display_add_page();
							break;
						default:
							die( 'Whoops! The tab "' . $tabs->get_current_tab() . '" does not exist.' );
							break;
					}
					?>
				</div> <!-- #post-body-content -->
			</div> <!-- #post-body -->

		</div> <!-- .metabox-holder -->

	</div> <!-- .wrap -->
	<?php
}

/**
 * @todo this should be a template/partial
 */
function display_creator_metabox() {
	postbox( \MultiFeedReader\t( 'Creator' ), function () {
		?>
		<p>
			<?php echo \MultiFeedReader\t( 'Hey, I\'m Eric. I created this plugin.<br/> If you like it, consider to flattr me a beer.' ) ?>
		</p>
		<?php
		/**
		 * @todo add flattr button
		 */
		?>
		<p>
			<?php echo wp_sprintf( \MultiFeedReader\t( 'Get in touch: Visit my <a href="%1s">Homepage</a>, follow me on <a href="%2s">Twitter</a> or look at my projects on <a href="%3s">GitHub</a>.' ), 'http://www.FarBeyondProgramming.com/', 'http://www.twitter.com/ericteubert', 'https://github.com/eteubert' ) ?>
		</p>
		<?php
	});
}

/**
 * @todo determine directory / namespace structure for settings pages
 * 
 * \MFR\Settings\Pages\AddTemplate
 * \MFR\Settings\Pages\EditTemplate
 * manual labour to include all the files. or ... autoload.
 * 
 */
function display_edit_page() {
	if ( FeedCollection::count() > 1 ) {
		postbox( \MultiFeedReader\t( 'Choose Template' ), function () {
			$all = FeedCollection::all();
			?>
			<form action="<?php echo admin_url( 'options-general.php' ) ?>" method="get">
				<input type="hidden" name="page" value="<?php echo HANDLE ?>">

				<script type="text/javascript" charset="utf-8">
					jQuery( document ).ready( function() {
						// hide button only if js is enabled
						jQuery( '#choose_template_button' ).hide();
						// if js is enabled, auto-submit form on change
						jQuery( '#choose_template_id' ).change( function() {
							this.form.submit();
						} );
					});
				</script>

				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">
								<?php echo \MultiFeedReader\t( 'Template to Edit' ) ?>
							</th>
							<td>
								<select name="choose_template_id" id="choose_template_id" style="width:99%">
									<?php foreach ( $all as $c ): ?>
										<?php $selected = ( $_REQUEST[ 'choose_template_id' ] == $c->id ) ? 'selected="selected"' : ''; ?>
										<option value="<?php echo $c->id ?>" <?php echo $selected ?>><?php echo $c->name ?></option>
									<?php endforeach ?>
								</select>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit" id="choose_template_button">
					<input type="submit" class="button-primary" value="<?php echo \MultiFeedReader\t( 'Choose Template' ) ?>" />
				</p>

				<br class="clear" />

			</form>
			<?php
		});
	}
	
	postbox( wp_sprintf( \MultiFeedReader\t( 'Settings for "%1s" Collection' ), FeedCollection::current()->name ), function () {
		$current = FeedCollection::current();
		$feeds = $current->feeds();
		?>
		<script type="text/javascript" charset="utf-8">
		jQuery( document ).ready( function( $ ) {
			$("#feed_form .add_feed").click(function(e) {
				e.preventDefault();
				
				var input_html = '<input type="text" name="feedcollection[feeds][new][]" value="" class="large-text" />';
				$(input_html).insertAfter("#feed_form input:last");
				
				return false;
			});
		});
		</script>
		
		<form action="<?php echo admin_url( 'options-general.php?page=' . HANDLE ) ?>" method="post">
			<input type="hidden" name="choose_template_id" value="<?php echo $current->id ?>">
			
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row" colspan="2">
							<h4><?php echo \MultiFeedReader\t( 'Feeds' ) ?></h4>
						</th>
					</tr>
					<tr valign="top">
						<th></th>
						<td scope="row" id="feed_form">
							<?php foreach ( $feeds as $feed ): ?>
								<input type="text" name="feedcollection[feeds][<?php echo $feed->id ?>]" value="<?php echo $feed->url; ?>" class="large-text" />
							<?php endforeach; ?>
							<a href="#" class="add_feed">Add Feed</a>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" colspan="2">
							<h4><?php echo \MultiFeedReader\t( 'Template Options' ) ?></h4>
						</th>
					</tr>
					<tr valign="top">
						<th scope="row">
							<?php echo \MultiFeedReader\t( 'Template Name' ) ?>
						</th>
						<td>
							<input type="text" name="feedcollection[name]" value="<?php echo $current->name ?>" class="large-text">
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<?php echo \MultiFeedReader\t( 'Before Template' ) ?>
						</th>
						<td>
							<textarea name="feedcollection[before_template]" rows="10" class="large-text"><?php echo $current->before_template ?></textarea>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<?php echo \MultiFeedReader\t( 'Body Template' ) ?>
						</th>
						<td>
							<textarea name="feedcollection[body_template]" rows="10" class="large-text"><?php echo $current->body_template ?></textarea>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<?php echo \MultiFeedReader\t( 'After Template' ) ?>
						</th>
						<td>
							<textarea name="feedcollection[after_template]" rows="10" class="large-text"><?php echo $current->after_template ?></textarea>
						</td>
					</tr>
				</tbody>
			</table>
			
			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e( 'Save Changes' ) ?>" style="float:right" />
				<input type="submit" class="button-secondary" style="color:#BC0B0B; margin-right:20px; float: right" name="delete" value="<?php echo \MultiFeedReader\t( 'delete permanently' ) ?>">
			</p>
			
			<br class="clear" />
		</form>
		<?php
	});

}

function display_add_page() {
	postbox( \MultiFeedReader\t( 'Add Feedcollection' ), function () {
		?>
		<form action="" method="post">

			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">
							<?php echo \MultiFeedReader\t( 'New Feedcollection Name' ); ?>
						</th>
						<td>
							<input type="text" name="mfr_new_feedcollection_name" value="" id="mfr_new_feedcollection_name" class="large-text">
							<p>
								<small><?php echo \MultiFeedReader\t( 'This name will be used in the shortcode to identify the feedcollection.<br/>Example: If you name the collection "rockstar", then you can use it with the shortcode <em>[multi-feed-reader template="rockstar"]</em>' ); ?></small>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<input type="submit" class="button-primary" value="<?php echo \MultiFeedReader\t( 'Add New Feedcollection' ); ?>" />
			</p>
			
			<br class="clear" />
			
		</form>
		<?php
	} );
}