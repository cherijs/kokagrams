<?php
/*
Plugin Name: Kokagrams
Plugin URI: http://slurp.lv
Description: Lieto Kokagram lai importētu instagrama bildes savā wordpress lapā.
Author URI: http://slurp.lv
Author: Artūrs Cirsis
Version: 1.0
*/
define("KOKAGRAMS_PLUGIN_NAME", "Kokagrams", true);
define("KOKAGRAMS_API_KEY", "daa64adb0ec444fdb53f2f4fa492faff", true);
define("KOKAGRAMS_AUTH_URL", "http://ideariga.lv/instagram_auth", true);
define("KOKAGRAMS_POST_TYPE", "kokagrams", true);
require_once ('lib/instagram.class.php');

function kokagrams_init()
{
    load_plugin_textdomain('kokagrams', false, dirname(plugin_basename(__FILE__)));
}

add_action('init', 'kokagrams_init');
register_activation_hook(__FILE__, 'kokagrams_activation');
add_action('kokagrams_hashtag_photos', 'kokagrams_get_hashtag_photos');
add_action('kokagrams_user_photos', 'kokagrams_get_user_photos');
add_action('kokagrams_placeid_photos', 'kokagrams_get_placeid_photos');

function kokagrams_activation()
{
    $settings = get_option("kokagrams_settings");
    if ($settings['kokagrams_import_interval'] === ''):
        $cron_time = 'twicedaily';
    else:
        $cron_time = $settings['kokagrams_import_interval'];
    endif;
    wp_schedule_event(current_time('timestamp') , $cron_time, 'kokagrams_hashtag_photos');
    wp_schedule_event(current_time('timestamp') , $cron_time, 'kokagrams_user_photos');
    wp_schedule_event(current_time('timestamp') , $cron_time, 'kokagrams_placeid_photos');
}

register_deactivation_hook(__FILE__, 'kokagrams_deactivation');

function kokagrams_deactivation()
{
    wp_clear_scheduled_hook('kokagrams_hashtag_photos');
    wp_clear_scheduled_hook('kokagrams_user_photos');
    wp_clear_scheduled_hook('kokagrams_placeid_photos');
    kokagrams_remove_wp_pointers();
}

function kokagrams_remove_wp_pointers()
{
    $admin_users = get_users('role=administrator');
    foreach($admin_users as $admin_user):
        $user_meta = get_user_meta($admin_user->ID, 'dismissed_wp_pointers');
        $pointers = explode(',', $user_meta[0]);
        $indexPointer = array_search('wp_kokagrams_instagram', $pointers);
        if ($indexPointer !== false):
            unset($pointers[$indexPointer]);
            $new_pointers = implode(',', $pointers);
            update_user_meta($admin_user->ID, 'dismissed_wp_pointers', $new_pointers);
        endif;
    endforeach;
}

function kokagrams_register_settings()
{
    $settings = get_option("kokagrams_settings");
    if (empty($settings)) {
        $settings = array(
            'kokagrams_auth' => 'no',
            'kokagrams_access_token' => '',
            'kokagrams_auth_user_id' => '',
            'kokagrams_auth_user' => '',
            'kokagrams_post_type' => KOKAGRAMS_POST_TYPE,
            'kokagrams_import_interval' => 'twicedaily',
            'kokagrams_import_limit' => '20',
            'kokagrams_user' => array() ,
            'kokagrams_user_id' => array() ,
            'kokagrams_hashtag' => array() ,
            'kokagrams_user_public_hashtag' => '',
            'kokagrams_user_public_placeid' => '',
            'kokagrams_rename_post_singular' => __('Photo', 'kokagrams') ,
            'kokagrams_rename_post_plural' => __('Photos', 'kokagrams') ,
            'kokagrams_post_status' => 'pending',
            'kokagrams_featured_image' => ''
        );
        add_option("kokagrams_settings", $settings, '', 'yes');
    }
}

add_action('admin_init', 'kokagrams_register_settings');
add_action('admin_menu', 'kokagrams_plugin_settings');

function kokagrams_shortcode($atts)
{
    extract(shortcode_atts(array(
        'photos' => '12',
        'lightbox' => 'no',
        'class' => ' wp_kokagrams_instagram_photo',
        'style' => 'no'
    ) , $atts));
    $settings = get_option("kokagrams_settings");
    $args = array(
        'post_type' => $settings['kokagrams_post_type'],
        'posts_per_page' => $photos
    );
    $kokagrams_html = '';
    $kokagrams_photos_query = new WP_Query($args);
    if ($kokagrams_photos_query->have_posts()) {
        $kokagrams_html = '<div class="wp-instagram-grid">';
        while ($kokagrams_photos_query->have_posts()) {
            $kokagrams_photos_query->the_post();
            if (has_post_thumbnail()):
                $thumb_id = get_post_thumbnail_id();
                $thumb_url = wp_get_attachment_image_src($thumb_id, 'full', true);
                $kokagrams_html.= '<div class="wp-instagram-item ' . $class . '"><a href="' . $thumb_url[0] . '">' . get_the_post_thumbnail(get_the_ID() , 'medium') . '</a></div>';
            else:
                $doc = new DOMDocument();
                @$doc->loadHTML(get_the_content());
                $tags = $doc->getElementsByTagName('img');
                foreach($tags as $tag) {
                    $image_url = $tag->getAttribute('src');
                }

                $kokagrams_html.= '<div class="wp-instagram-item ' . $class . '"><a href="' . $image_url . '">' . get_the_content() . '</a></div>';
            endif;
        }

        $kokagrams_html.= '</div>';
    }
    else {
        $kokagrams_html = __('No photos found. ', 'kokagrams');
    }

    if ($style === 'yes'):
        wp_enqueue_style('kokagrams_grid', plugins_url('css/kokagrams-grid.css', __FILE__));
    endif;
    if ($lightbox === 'yes'):
        wp_enqueue_script('kokagrams_magnific_popup', plugins_url('js/jquery.magnific-popup.min.js', __FILE__) , array(
            'jquery'
        ) , '1.0', true);
        wp_enqueue_script('kokagrams_magnific_popup_script', plugins_url('js/kokagrams-lightbox.js', __FILE__) , array(
            'kokagrams_magnific_popup'
        ) , '1.0', true);
        wp_enqueue_style('kokagrams_magnific_popup_css', plugins_url('css/magnific-popup.css', __FILE__));
    endif;
    wp_reset_postdata();
    return $kokagrams_html;
}

add_shortcode('kokagrams', 'kokagrams_shortcode');

function kokagrams_load_css_js()
{
    wp_enqueue_script('kokagrams_plugins_js', plugins_url('/js/kokagrams-plugins.js', __FILE__) , array(
        'jquery'
    ));
    wp_enqueue_script('kokagrams_main_js', plugins_url('/js/kokagrams-main.js', __FILE__) , array(
        'jquery'
    ));
    wp_enqueue_style('kokagrams_css', plugins_url('/css/kokagrams.css', __FILE__));
}

add_action('admin_enqueue_scripts', 'kokagrams_load_css_js');

// DEV TIMING

function kokagrams_add_oneminute($schedules)
{
    $schedules['oneminute'] = array(
        'interval' => 60,
        'display' => __('Once every 60 seconds', 'kokagrams')
    );
    return $schedules;
}

add_filter('cron_schedules', 'kokagrams_add_oneminute');

// General Settings Pages

function kokagrams_plugin_settings()
{
    $settings_page = add_menu_page(__('Kokagrams', 'kokagrams') , __('Kokagrams', 'kokagrams') , 'administrator', 'kokagrams', 'kokagrams_display_settings', plugins_url('images/icon.png', __FILE__));
    add_action("load-{$settings_page}", 'kokagrams_load_settings_page');
}

function kokagrams_load_settings_page()
{
    if (isset($_POST["kokagrams-settings-submit"]))
    if ($_POST["kokagrams-settings-submit"] == 'Y') {
        check_admin_referer("kokagrams-settings-page");
        kokagrams_save_theme_settings();
        $url_parameters = isset($_GET['tab']) ? 'updated=true&tab=' . $_GET['tab'] : 'updated=true';
        wp_redirect(admin_url('admin.php?page=kokagrams&' . $url_parameters));
        exit;
    }
}

function kokagrams_save_theme_settings()
{
    global $pagenow;
    $settings = get_option("kokagrams_settings");
    if ($pagenow == 'admin.php' && $_GET['page'] == 'kokagrams') {
        if (isset($_GET['tab'])) $tab = $_GET['tab'];
        else $tab = 'homepage';
        switch ($tab) {
        case 'homepage':
            $settings['kokagrams_user'] = @$_POST['kokagrams_user'];
            $settings['kokagrams_user_id'] = @$_POST['kokagrams_user_id'];
            $settings['kokagrams_hashtag'] = @$_POST['kokagrams_hashtag'];
            $settings['kokagrams_public_hashtag'] = @$_POST['kokagrams_public_hashtag'];
            $settings['kokagrams_public_placeid'] = @$_POST['kokagrams_public_placeid'];
            break;

        case 'post_type':
            $old_interval = $settings['kokagrams_import_interval'];
            $settings['kokagrams_post_type'] = @$_POST['kokagrams_post_type'];
            $settings['kokagrams_rename_post_singular'] = @$_POST['kokagrams_rename_post_singular'];
            $settings['kokagrams_rename_post_plural'] = @$_POST['kokagrams_rename_post_plural'];
            $settings['kokagrams_post_status'] = @$_POST['kokagrams_post_status'];
            $settings['kokagrams_featured_image'] = @$_POST['kokagrams_featured_image'];
            $settings['kokagrams_import_limit'] = @$_POST['kokagrams_import_limit'];
            $settings['kokagrams_import_interval'] = @$_POST['kokagrams_import_interval'];
            if ($old_interval !== @$_POST['kokagrams_import_interval']):
                wp_clear_scheduled_hook('kokagrams_user_photos');
                wp_clear_scheduled_hook('kokagrams_hashtag_photos');
                wp_clear_scheduled_hook('kokagrams_placeid_photos');
                wp_schedule_event(current_time('timestamp') , $_POST['kokagrams_import_interval'], 'kokagrams_user_photos');
                wp_schedule_event(current_time('timestamp') , $_POST['kokagrams_import_interval'], 'kokagrams_hashtag_photos');
                wp_schedule_event(current_time('timestamp') , $_POST['kokagrams_import_interval'], 'kokagrams_placeid_photos');
            endif;
            break;
        }
    }

    $updated = update_option("kokagrams_settings", $settings);
}

function kokagrams_admin_tabs($current = 'homepage')
{
    $tabs = array(
        'homepage' => __('Hashtags & Users', 'kokagrams') ,
        'post_type' => __('Import Options', 'kokagrams') ,
        'shortcode' => __('Shortcodes', 'kokagrams') ,
        'unlink' => __('Unlink Account', 'kokagrams') ,
        'reload' => __('Reload', 'kokagrams')
    );
    $links = array();
    echo '<h2 class="nav-tab-wrapper kokagramsNavTab">';
    foreach($tabs as $tab => $name) {
        $class = ($tab == $current) ? ' nav-tab-active' : '';
        echo "<a class='nav-tab$class' href='?page=kokagrams&tab=$tab'>$name</a>";
    }

    echo '</h2>';
}

function kokagrams_display_settings()
{
    global $pagenow;
    $settings = get_option("kokagrams_settings");
    $kokagrams_user = (isset($settings['kokagrams_user']) ? $settings['kokagrams_user'] : '');
    $kokagrams_user_id = (isset($settings['kokagrams_user_id']) ? $settings['kokagrams_user_id'] : '');
    $kokagrams_hashtag = (isset($settings['kokagrams_hashtag']) ? $settings['kokagrams_hashtag'] : '');
    $kokagrams_public_hashtag = (isset($settings['kokagrams_public_hashtag']) ? $settings['kokagrams_public_hashtag'] : '');
    $kokagrams_public_placeid = (isset($settings['kokagrams_public_placeid']) ? $settings['kokagrams_public_placeid'] : '');
    $kokagrams_post_type = (isset($settings['kokagrams_post_type']) ? $settings['kokagrams_post_type'] : '');
    $kokagrams_post_singular = (isset($settings['kokagrams_rename_post_singular']) ? $settings['kokagrams_rename_post_singular'] : 'Photo');
    $kokagrams_post_plural = (isset($settings['kokagrams_rename_post_plural']) ? $settings['kokagrams_rename_post_plural'] : 'Photos');
    $kokagrams_post_status = (isset($settings['kokagrams_post_status']) ? $settings['kokagrams_post_status'] : '');
    $kokagrams_featured_image = (isset($settings['kokagrams_featured_image']) ? $settings['kokagrams_featured_image'] : '');
    $kokagrams_import_limit = (isset($settings['kokagrams_import_limit']) ? $settings['kokagrams_import_limit'] : '');
    $kokagrams_import_interval = (isset($settings['kokagrams_import_interval']) ? $settings['kokagrams_import_interval'] : '');
?>
	
	<div class="wrap">
		<h2 id="kokagramswelcomeTitle"><?php
    echo KOKAGRAMS_PLUGIN_NAME; ?></h2>

		<?php
    if (isset($_GET['unlink'])):
        if ($_GET['unlink'] === 'true'):
            $settings = get_option("kokagrams_settings");
            $settings['kokagrams_auth'] = 'no';
            $settings['kokagrams_access_token'] = '';
            $settings['kokagrams_auth_user_id'] = '';
            $settings['kokagrams_auth_user'] = '';
            $updated = update_option("kokagrams_settings", $settings);
?>
			<div id="setting-error-settings_updated" class="updated settings-error"> 
				<p><strong><?php
            _e('Your instagram access token was deleted, but you also need to revoke permissions from this plugin, <a href="https://instagram.com/accounts/manage_access" target="_blank">click here</a> and revoke access to the "Kokagrams Importer" app.', 'kokagrams'); ?></strong></p>
			</div>
		<?php
        endif;
    endif;
?>
		

		<?php
    if (isset($_GET['error'])):
?>
			<div id="setting-error-settings_updated" class="updated settings-error"> 
				<p><strong><?php
        _e('Error', 'kokagrams'); ?></strong></p>
			</div>
		<?php
    endif;
?>


		<?php
    if (isset($_GET['updated']))
    if ('true' == esc_attr($_GET['updated'])):
?>
		<div id="setting-error-settings_updated" class="updated settings-error"> 
			<p><strong><?php
        _e('Settings saved.', 'kokagrams'); ?></strong></p>
		</div>
		<?php
    endif;
?>

		<?php
    if (isset($_GET['kokagrams_auth'])): ?>
			<?php
        if ($_GET['kokagrams_auth'] === 'success'):
            $settings = get_option("kokagrams_settings");
            $settings['kokagrams_auth'] = 'yes';
            $settings['kokagrams_access_token'] = urldecode($_GET['access_token']);
            $settings['kokagrams_auth_user_id'] = urldecode($_GET['user_id']);
            $settings['kokagrams_auth_user'] = urldecode($_GET['username']);
            $updated = update_option("kokagrams_settings", $settings);
?>
				<div id="setting-error-settings_updated" class="updated settings-error"> 
					<p><strong><?php
            _e('Authorization succeeded!', 'kokagrams'); ?></strong></p>
				</div>
			<?php
        else: ?>
				<div id="setting-error-settings_updated" class="error settings-error"> 
					<p><strong><?php
            _e('There was an error, try again...', 'kokagrams'); ?></strong></p>
				</div>
			<?php
        endif; ?>
		<?php
    endif; ?>

		<?php
    if ($settings['kokagrams_auth'] === 'no'):
        $kokagrams_redirect_url = kokagrams_get_current_url();
?>
			<p><?php
        _e('Click to be taken to Instagram\'s site to securely authorize this plugin for use with your account.', 'kokagrams'); ?></p>
			<a href="<?php
        echo KOKAGRAMS_AUTH_URL; ?>?redirect_url=<?php
        echo $kokagrams_redirect_url; ?>" target="_self" class="button-primary authenticate"><?php
        _e('Secure Authentication', 'kokagrams'); ?></a>
		<?php
    else:
?>

			<?php
        if (isset($_GET['tab'])) kokagrams_admin_tabs($_GET['tab']);
        else kokagrams_admin_tabs('homepage');
?>

			<div id="poststuff">
				<form class="kokagrams_settings_form" method="post" action="<?php
        admin_url('admin.php?page=kokagrams'); ?>">

				<?php
        wp_nonce_field("kokagrams-settings-page");
        if ($pagenow == 'admin.php' && $_GET['page'] == 'kokagrams') {
            if (isset($_GET['tab'])) $tab = $_GET['tab'];
            else $tab = 'homepage';
            echo '<table id="kokagramsMainTable" class="form-table">';
            $no_save = false;
            switch ($tab) {
            case 'homepage':
                $kokagrams_total_users = count($kokagrams_user);
?>
								<tr valign="top">
									<td colspan="2">
										<h2><?php
                _e('Team &amp; Tags', 'kokagrams'); ?></h2>
										<hr>
										<p><?php
                _e('This is where your Instagram users are managed. Click <span id="kokagramsUserLabel">"Add New Team Member"</span> to add <span id="kokagramsUserNumber">your first one</span>.', 'kokagrams'); ?></p>
									</td>
								</tr>
					<?php
                $kokagrams_user_fields = 10;
                $active_users = 0;
                for ($i = 1; $i <= $kokagrams_total_users; $i++):
                    $user = (isset($kokagrams_user[$i - 1]) ? $kokagrams_user[$i - 1] : '');
                    $user_id = (isset($kokagrams_user_id[$i - 1]) ? $kokagrams_user_id[$i - 1] : '');
                    $hashtag = (isset($kokagrams_hashtag[$i - 1]) ? $kokagrams_hashtag[$i - 1] : '');
                    if ((!empty($user) && !empty($user_id))):
                        $active_users++;
?>
								
								<tr valign="top" class="kokagrams_user_hashtag">
									<td scope="row">

										<table class="form-table kokagrams-hover-table"> 
											<tr valign="top">
												<td scope="row"><label><?php
                        _e('Instagram Username:', 'kokagrams'); ?></label></th>
												<td>
													<input name="kokagrams_user[]" type="text" id="kokagrams_user" class="regular-text kokagrams-float-left kokagrams-UserValidation" value="<?php
                        echo @$user; ?>" placeholder="johndoe">
													<div class="kokagrams-live-icon">
														<img src="<?php
                        echo plugins_url('/images/loading.gif', __FILE__) ?>" class="kokagrams-loading hidden" />
														<img src="<?php
                        echo plugins_url('/images/yes.png', __FILE__) ?>" class="kokagrams-yes hidden" />
														<img src="<?php
                        echo plugins_url('/images/no.png', __FILE__) ?>" class="kokagrams-no hidden" />
														<img src="<?php
                        echo plugins_url('/images/alert.png', __FILE__) ?>" class="kokagrams-alert hidden" />
													</div>
													<input name="kokagrams_user_id[]" type="hidden" id="kokagrams_user_id" value="<?php
                        echo @$user_id; ?>">
													<div class="clearfix"></div>
													<a href="#" class="kokagrams-trash"><img src="<?php
                        echo plugins_url('/images/trash.png', __FILE__) ?>"  /></a>
													<p class="description hidden kokagrams-message"><?php
                        _e('We can\'t import photos from this account because it is private', 'kokagrams'); ?></p>
												</td>
											</tr>
											<tr valign="top" >
												<td scope="row"><label><?php
                        _e('Import photos tagged:', 'kokagrams'); ?></label></th>
												<td>
													<input name="kokagrams_hashtag[]" type="text" id="kokagrams_hashtag" class="regular-text" value="<?php
                        echo @$hashtag; ?>" placeholder="example: cats,dogs,parrots">
													<p class="description"><?php
                        _e('Insert the hashtags without # and separated by comma, don\'t use blank spaces.', 'kokagrams'); ?></p>
												</td>
											</tr>
											
										</table>

									</td>
									
								</tr>
								<tr valign="top" class="kokagrams_user_tr">
									<td colspan="2">
										<hr>
									</td>
								</tr>
											
					<?php
                    endif;
                endfor;
                if ($active_users == 0):
?>
								<tr valign="top" class="kokagrams_user_hashtag hidden">
									<td scope="row">

										<table class="form-table kokagrams-hover-table"> 
											<tr valign="top">
												<td scope="row"><label><?php
                    _e('Instagram Username:', 'kokagrams'); ?></label></th>
												<td>
													<input name="kokagrams_user[]" type="text" id="kokagrams_user" class="regular-text kokagrams-float-left kokagrams-UserValidation" value="<?php
                    echo @$user; ?>" placeholder="johndoe">
													<div class="kokagrams-live-icon">
														<img src="<?php
                    echo plugins_url('/images/loading.gif', __FILE__) ?>" class="kokagrams-loading hidden" />
														<img src="<?php
                    echo plugins_url('/images/yes.png', __FILE__) ?>" class="kokagrams-yes hidden" />
														<img src="<?php
                    echo plugins_url('/images/no.png', __FILE__) ?>" class="kokagrams-no hidden" />
														<img src="<?php
                    echo plugins_url('/images/alert.png', __FILE__) ?>" class="kokagrams-alert hidden" />
													</div>
													<input name="kokagrams_user_id[]" type="hidden" id="kokagrams_user_id" value="<?php
                    echo @$user_id; ?>">
													<div class="clearfix"></div>
													<a href="#" class="kokagrams-trash"><img src="<?php
                    echo plugins_url('/images/trash.png', __FILE__) ?>"  /></a>
													<p class="description hidden kokagrams-message"><?php
                    echo __('We can\'t import photos from this account because it is private', 'kokagrams'); ?></p>
												</td>
											</tr>
											<tr valign="top" >
												<td scope="row"><label><?php
                    _e('Import photos tagged:', 'kokagrams'); ?></label></th>
												<td>
													<input name="kokagrams_hashtag[]" type="text" id="kokagrams_hashtag" class="regular-text" value="<?php
                    echo @$hashtag; ?>" placeholder="example: cats,dogs,parrots">
													<p class="description"><?php
                    _e('Insert the hashtags without # and separated by comma, don\'t use blank spaces.', 'kokagrams'); ?></p>
												</td>
											</tr>
											
										</table>

									</td>
									
								</tr>
								<tr valign="top" class="kokagrams_user_tr hidden">
									<td colspan="2">
										<hr>
									</td>
								</tr>
					<?php
                endif;
?>
							</table>
							<table class="form-table">
								<tr valign="top">
									<td  scope="row">
										<?php
                if ($kokagrams_user_fields == $active_users): ?>
										<a href="#" class="button-secondary" id="kokagrams-addUser"><?php
                    _e('Add New Team Member', 'kokagrams'); ?></a>
										<?php
                else: ?>
										<a href="#" class="button-secondary" id="kokagrams-addUser"><?php
                    _e('Add Another Team Member', 'kokagrams'); ?></a>
										<?php
                endif; ?>
									</td>
								</tr>
								<tr valign="top">
									<td colspan="2">
										<h2><?php
                _e('Public Tags', 'kokagrams'); ?></h2>
										<hr>
										<p><?php
                _e('Tags added here will import any photo found on Instagram matching these tags, even if they are not owned by a team member above. We recommend setting "Default Post Status" (under the Import Options tab) to "Draft", "Pending" or "Private" if you use this option.', 'kokagrams'); ?></p>
									</td>
								</tr>
								<tr valign="top">
									<td scope="row">
										<table class="form-table">
											<tr valign="top">
												<td scope="row">
													<label for="kokagrams_hashtag"><?php
                _e('Publicly Searchable Tags:', 'kokagrams'); ?></label>
												</td>
												<td>
													<input name="kokagrams_public_hashtag" type="text" id="kokagrams_public_hashtag" class="regular-text" value="<?php
                echo $kokagrams_public_hashtag; ?>" placeholder="example: cats,dogs,parrots">
													<p class="description"><?php
                _e('Insert the hashtags without # and separated by comma, don\'t use blank spaces.', 'kokagrams'); ?></p>
												</td>
											</tr>
											<tr valign="top">
												<td colspan="2">
													<hr>
												</td>
											</tr>
										</table>
									</td>
												
								</tr>




								<tr valign="top">
									<td colspan="2">
										<h2><?php
                _e('Places', 'kokagrams'); ?></h2>
										<hr>
										<p><?php
                _e('Places id added here will import any photo these id, even if they are not owned by a team member above.', 'kokagrams'); ?></p>
									</td>
								</tr>
								<tr valign="top">
									<td scope="row">
										<table class="form-table">
											<tr valign="top">
												<td scope="row">
													<label for="kokagrams_placeid"><?php
                _e('Places id:', 'kokagrams'); ?></label>
												</td>
												<td>
													<input name="kokagrams_public_placeid" type="text" id="kokagrams_public_placeid" class="regular-text" value="<?php
                echo $kokagrams_public_placeid; ?>" placeholder="example: 246274166,3348708">
													<p class="description"><?php
                _e('Insert the places id without # and separated by comma, don\'t use blank spaces.', 'kokagrams'); ?></p>
												</td>
											</tr>
											<tr valign="top">
												<td colspan="2">
													<hr>
												</td>
											</tr>
										</table>
									</td>
												
								</tr>


								
								<?php
                break;

            case 'post_type':
?>
								<tr valign="top">
									<th colspan="2">
										<h2><?php
                _e('Import Post Rules', 'kokagrams'); ?></h2>
										<hr>
									</th>
								</tr>
								<tr>
									<th scope="row"><label for="kokagrams_post_type"><?php
                echo __('Import to Post Type', 'kokagrams'); ?></label></th>
									<td>
										<select name="kokagrams_post_type" id="kokagrams_post_type">
											<?php
                $args = array(
                    'public' => true
                );
                $post_types = get_post_types($args);
                $current_post_type = isset($kokagrams_post_type) ? $kokagrams_post_type : '';
                foreach($post_types as $post_type):
?>
													<option value="<?php
                    echo $post_type; ?>" <?php
                    selected($current_post_type, $post_type); ?> ><?php
                    echo $post_type; ?></option>
											<?php
                endforeach;
?>
										</select>
										<p class="description"><?php
                _e('Choose the post type that all photos will be imported as.', 'kokagrams'); ?></p>
									</td>
								</tr>
								<tr valign="top">
									<th colspan="2">
										<h2><?php
                _e('Import Image Rules', 'kokagrams'); ?></h2>
										<hr>
									</th>
								</tr>

								<tr valign="top">
									<th scope="row"><label for="kokagrams_post_status"><?php
                _e('Default post status', 'kokagrams'); ?></label></th>
									<td>
										<select name="kokagrams_post_status" id="kokagrams_post_status">
											<?php
                $draft_status = $kokagrams_post_status;
?>
											<option value="publish" <?php
                selected($draft_status, 'publish'); ?>>
												<?php
                _e('Published - Automatically post to website', 'kokagrams'); ?>
											</option>
											<option value="pending" <?php
                selected($draft_status, 'pending'); ?>>
												<?php
                _e('Pending - Wait for moderation', 'kokagrams'); ?>
											</option>
											<option value="private" <?php
                selected($draft_status, 'private'); ?>>
												<?php
                _e('Private - Must be logged in to view', 'kokagrams'); ?>
											</option>
										</select>
										<p class="description"><?php
                _e('Choose the post status of all the imported photos.', 'kokagrams'); ?></p>
									</td>
								</tr>
								<tr valign="top">
									<th scope="row"><?php
                _e('Featured Image?', 'kokagrams'); ?></th>
									<td>
										<label for="kokagrams_featured_image">
											<input name="kokagrams_featured_image" type="checkbox" id="kokagrams_featured_image" value="yes" <?php
                checked($kokagrams_featured_image, 'yes'); ?>>
											<?php
                _e('Save imported image as featured image.', 'kokagrams'); ?>
										</label>
									</td>
								</tr>
								<tr valign="top">
									<th scope="row"><label for="kokagrams_import_limit"><?php
                _e('Import Quantity', 'kokagrams'); ?></label></th>
									<td>
										<select name="kokagrams_import_limit" id="kokagrams_import_limit">
											<?php
                $limit_saved = $kokagrams_import_limit;
?>
											<option value="20" <?php
                selected($limit_saved, '20'); ?>>
												<?php
                _e('20 Photos', 'kokagrams'); ?>
											</option>
											<option value="40" <?php
                selected($limit_saved, '40'); ?>>
												<?php
                _e('40 Photos', 'kokagrams'); ?>
											</option>
											<option value="60" <?php
                selected($limit_saved, '60'); ?>>
												<?php
                _e('60 Photos', 'kokagrams'); ?>
											</option>
											<option value="80" <?php
                selected($limit_saved, '80'); ?>>
												<?php
                _e('80 Photos', 'kokagrams'); ?>
											</option>
											<option value="100" <?php
                selected($limit_saved, '100'); ?>>
												<?php
                _e('100 Photos', 'kokagrams'); ?>
											</option>
											<option value="200" <?php
                selected($limit_saved, '200'); ?>>
												<?php
                _e('200 Photos', 'kokagrams'); ?>
											</option>
										</select>
										<p class="description"><?php
                _e('Choose the number of items to query per API call.', 'kokagrams'); ?></p>
									</td>
								</tr>
								<tr valign="top">
									<th scope="row"><label for="kokagrams_import_interval"><?php
                _e('Import Interval', 'kokagrams'); ?></label></th>
									<td>
										<select name="kokagrams_import_interval" id="kokagrams_import_interval">
											<?php
                $import_interval = $kokagrams_import_interval;
?>
											<option value="oneminute" <?php
                selected($import_interval, 'oneminute'); ?>>
												<?php
                _e('Every Minute', 'kokagrams'); ?>
											</option>
											<option value="hourly" <?php
                selected($import_interval, 'hourly'); ?>>
												<?php
                _e('Once Hourly', 'kokagrams'); ?>
											</option>
											<option value="twicedaily" <?php
                selected($import_interval, 'twicedaily'); ?>>
												<?php
                _e('Twice Daily', 'kokagrams'); ?>
											</option>
											<option value="daily" <?php
                selected($import_interval, 'daily'); ?>>
												<?php
                _e('Once Daily', 'kokagrams'); ?>
											</option>
										</select>
									</td>
								</tr>
								<?php
                break;

            case 'shortcode':
                $no_save = true;
?>
								<tr valign="top">
									<th scope="row"><label><?php
                _e('Overview', 'kokagrams'); ?></label></th>
									<td>
										<p><?php
                _e('This plugin supports <a href="http://codex.wordpress.org/Shortcode_API" target="_blank">shortcodes</a>. Include the shortcode [kokagrams] in any page where you want your photo grid to appear.', 'kokagrams'); ?></p>
									</td>
								</tr>
								
								<tr valign="top">
									<th scope="row"><label><?php
                _e('Controlling the number of photos to display', 'kokagrams'); ?></label></th>
									<td>
										<p><?php
                _e('There is an option to limit the number of photos that appear in the grid (showing the newest first). You can enable this functionality by including photos=x in the shortcode.', 'kokagrams'); ?></p>
										 
										<p><?php
                _e('Example: <br /><br /><b>[kokagrams photos=12]', 'kokagrams'); ?></b></p><br />
									</td>
								</tr>
								
								<tr valign="top">
									<th scope="row"><label><?php
                _e('Adding a CSS class to the shortcode', 'kokagrams'); ?></label></th>
									<td>		 
										<p><?php
                _e('There is also an option to add a custom class to the images. You can enable this by adding class=somecustomclass to the shortcode. A custom class is helpful for adding things like floats and other CSS to support responsive grids.', 'kokagrams'); ?></p>
										 
										<p><?php
                _e('Example: <br /><br /><b>[kokagrams photos=12 class=some_custom_class]', 'kokagrams'); ?></b></p>
									</td>
								</tr>

								<tr valign="top">
									<th scope="row"><label><?php
                _e('Add a CSS Grid ', 'kokagrams'); ?></label></th>
									<td>		 
										<p><?php
                _e('Adding this option to your shortcode will convert your photos into a 4 column responsive grid.', 'kokagrams'); ?></p>
										 
										<p><?php
                _e('Example: <br /><br /><b>[kokagrams photos=12 style=yes]', 'kokagrams'); ?></b></p>
									</td>
								</tr>

								<tr valign="top">
									<th scope="row"><label><?php
                _e('Add a Lightbox ', 'kokagrams'); ?></label></th>
									<td>		 
										<p><?php
                _e('Use this shortcode to tell the plugin if you want users to be able to click on a larger version of the photo.', 'kokagrams'); ?></p>
										 
										<p><?php
                _e('Example: <br /><br /><b>[kokagrams photos=12 lightbox=yes]', 'kokagrams'); ?></b></p>
									</td>
								</tr>

								
								<?php
                break;

            case 'unlink':
                $no_save = true;
?>	
								<tr valign="top">
									<td colspan="2">
										<h2><?php
                _e('Unlink Your Instagram Account', 'kokagrams'); ?></h2>
										<hr>
										<p><?php
                _e('Use this screen to unlink your Instagram Account. If you proceed no new images will be pulled from Instagram and you will need to reactivate an account.', 'kokagrams'); ?></p>
										<br /><br />
										<a href="admin.php?page=kokagrams&unlink=true" id="kokagrams-unlinkAccount" class="button-primary red"><?php
                _e('Unlink Instagram Account', 'kokagrams'); ?></a>
									</td>
								</tr>
								
							<?php
                break;

            case 'reload':
                $no_save = true;


                kokagrams_get_hashtag_photos();
                kokagrams_get_placeid_photos();
                kokagrams_get_user_photos();

                break;
            }

            echo '</table>';
        }

?>
					<p class="submit" style="clear: both;">
						<?php
        if ($no_save !== true): ?>
						<input type="submit" name="Submit" id="kokagrams-submitForm"  class="button-primary" value="<?php
            _e('Save Settings', 'kokagrams'); ?>" />
						<?php
        endif; ?>
						<input type="hidden" name="kokagrams-settings-submit" value="Y" />
					</p>

				</form>
				
			</div>
		<?php
    endif;
?>
	</div>
<?php
}

function kokagrams_get_current_url()
{
    if ($_SERVER["SERVER_NAME"] !== ''):
        $pageURL = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
        if ($_SERVER["SERVER_PORT"] != "80") {
            $pageURL.= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
        }
        else {
            $pageURL.= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
        }

        $pageURL = kokagrams_remove_querystring_var($pageURL, 'unlink');
    else:
        $pageURL = get_bloginfo('url') . '/wp-admin/admin.php?page=kokagrams';
    endif;
    return urlencode($pageURL);
}

function kokagrams_remove_querystring_var($url, $key)
{
    $url = preg_replace('/(.*)(?|&)' . $key . '=[^&]+?(&)(.*)/i', '$1$2$4', $url . '&');
    $url = substr($url, 0, -1);
    return $url;
}

add_action('wp_ajax_kokagrams_check_user_id', 'kokagrams_check_user_id');

function kokagrams_check_user_id()
{
    if (empty($_POST['username'])):
        echo 'false';
        die();
    endif;
    $username = strtolower($_POST['username']);
    $settings = get_option("kokagrams_settings");
    $token = $settings['kokagrams_access_token'];
    $url = "https://api.instagram.com/v1/users/search?q=" . $username . "&access_token=" . $token;
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $output = curl_exec($ch);
        curl_close($ch);
    }
    else {
        $output = file_get_contents($url);
    }

    $json = json_decode($output);
    foreach($json->data as $user):
        if ($user->username === $username) {
            $instagram = new Instagram(KOKAGRAMS_API_KEY);
            $instagram->setAccessToken($token);
            $query_user = $instagram->getUserRelationship($user->id);
            if ($query_user->data->target_user_is_private === false):
                echo @$user->id;
                die();
            else:
                echo 'alert';
                die();
            endif;
        }

    endforeach;
    echo 'false';
    die();
}

function kokagrams_get_user_photos()
{
    global $wpdb;
    global $post;
    $settings = get_option('kokagrams_settings');
    $auth = $settings['kokagrams_auth'];
    if ($auth === 'yes'):
        $upload_images = $settings['kokagrams_featured_image'];
        $instagram = new Instagram(KOKAGRAMS_API_KEY);
        $instagram->setAccessToken($settings['kokagrams_access_token']);
        $insta_users = $settings['kokagrams_user_id'];
        $insta_hashtags = $settings['kokagrams_hashtag'];
        $page_num = $settings['kokagrams_import_limit'] / 20;
        if (!empty($insta_users)):
            $counter = 0;
            foreach($insta_users as $insta_user):
                if (isset($insta_hashtags[$counter])):
                    if ($insta_hashtags[$counter] === ''):
                        $hashtags_per_user = '';
                    else:
                        $hashtags_per_user = explode(',', $insta_hashtags[$counter]);
                    endif;
                endif;
                if (empty($insta_user)):
                    break;
                endif;
                $media = $instagram->getUserMedia($insta_user);
                if (@$media->error_message || @$media->meta->error_message) {

                    // var_dump(@$media->error_message);

                    echo "get_user_photos ".@$media->meta->error_message;
                    break;
                }

                $next_id = '';
                for ($i = 1; $i <= $page_num; $i++):
                    if ($next_id === ''):
                        if (@$media->error_message || @$media->meta->code != 400):
                            kokagrams_check_picture($media, $hashtags_per_user);
                        endif;
                    else:
                        $media = $instagram->pagination($media);
                        if (@$media->error_message || @$media->meta->error_message) {

                            // var_dump(@$media->error_message);

                            echo  "get_user_photos ".@$media->meta->error_message;
                            break;
                        }
                        else {
                            kokagrams_check_picture($media, $hashtags_per_user);
                        }

                    endif;
                    if (isset($media->pagination->next_url)):
                        $next_id = $media->pagination->next_url;
                    else:
                        break;
                    endif;
                endfor;
                $counter++;
            endforeach;
        endif;
    endif;
}

function kokagrams_get_hashtag_photos()
{
    global $wpdb;
    global $post;
    $settings = get_option('kokagrams_settings');
    $auth = $settings['kokagrams_auth'];
    if ($auth === 'yes'):
        $upload_images = $settings['kokagrams_featured_image'];
        $instagram = new Instagram(KOKAGRAMS_API_KEY);
        $instagram->setAccessToken($settings['kokagrams_access_token']);
        $hashtags = explode(',', $settings['kokagrams_public_hashtag']);
        $placeids = explode(',', $settings['kokagrams_public_placeid']);
        $page_num = $settings['kokagrams_import_limit'] / 20;


        if (!empty($hashtags)):
            $counter = 0;
            foreach($hashtags as $hashtag):
                if (empty($hashtag)):
                    break;
                endif;
                $media = $instagram->getTagMedia($hashtag, $settings['kokagrams_import_limit']);
      
// echo "<pre>";
// var_dump($hashtag);
// var_dump($media);
// echo "</pre>";

                if (@$media->error_message || @$media->meta->error_message) {

                    // var_dump(@$media->error_message);

                    echo  "get_hashtag_photos ".@$media->meta->error_message;
                    break;
                }

                $next_id = '';
                for ($i = 1; $i <= $page_num; $i++):
                    if ($next_id === ''):
                        if (@$media->error_message || @$media->meta->code != 400):
                            kokagrams_check_picture($media, NULL);
                        endif;
                    else:
                        $media = $instagram->pagination($media);
                        if (@$media->error_message || @$media->meta->error_message) {

                            // var_dump(@$media->error_message);

                            echo "get_hashtag_photos ".@$media->meta->error_message;
                            break;
                        }
                        else {
                            kokagrams_check_picture($media, NULL);
                        }

                    endif;
                    if (!isset($media->pagination->next_url)):
                        break;
                    else:
                        $next_id = $media->pagination->next_url;
                    endif;
                endfor;
            endforeach;
        endif;
    endif;
}

function kokagrams_get_placeid_photos()
{
    global $wpdb;
    global $post;
    $settings = get_option('kokagrams_settings');
    $auth = $settings['kokagrams_auth'];
    if ($auth === 'yes'):
        $upload_images = $settings['kokagrams_featured_image'];
        $instagram = new Instagram(KOKAGRAMS_API_KEY);
        $instagram->setAccessToken($settings['kokagrams_access_token']);
        $placeids = explode(',', $settings['kokagrams_public_placeid']);
        $page_num = $settings['kokagrams_import_limit'] / 20;
        if (!empty($placeids)):
            $counter = 0;
            foreach($placeids as $placeid):
                if (empty($placeid)):
                    break;
                endif;
                $media = $instagram->getLocationMedia($placeid);
                if (@$media->error_message || @$media->meta->error_message) {

                    // var_dump(@$media->error_message);

                    echo "get_placeid_photos ".@$media->meta->error_message;
                    break;
                }

                $next_id = '';
                for ($i = 1; $i <= $page_num; $i++):
                    if ($next_id === ''):
                        if (@$media->error_message || @$media->meta->code != 400):
                            kokagrams_check_picture($media, NULL);
                        endif;
                    else:
                        $media = $instagram->pagination($media);
                        if (@$media->error_message || @$media->meta->error_message) {

                            // var_dump(@$media->error_message);

                            echo "get_placeid_photos ".@$media->meta->error_message;
                            break;
                        }
                        else {
                            kokagrams_check_picture($media, NULL);
                        }

                    endif;
                    if (!isset($media->pagination->next_url)):
                        break;
                    else:
                        $next_id = $media->pagination->next_url;
                    endif;
                endfor;
            endforeach;
        endif;
    endif;
}

function kokagrams_insert_picture($data)
{
    global $wpdb;
    global $post;
    $settings = get_option('kokagrams_settings');
    $upload_images = $settings['kokagrams_featured_image'];
    $media_type = $data->type;
    if ($data->caption) {
        $text = $data->caption->text;
    }

    $text = wp_strip_all_tags(@$data->caption->text);
    $title = mb_strimwidth($text, 0, 30, '', 'utf-8');
    $type = 'instagram';
    $lat = 'null';
    $lng = 'null';
    $location_name = NULL;
    $picid = $data->id;
    $orig_date = $data->created_time;
    $user = $data->user->username;
    $user_fullname = $data->user->full_name;
    $user_picture = $data->user->profile_picture;
    $video_url = '';
    $img_url = $data->link;
    if ($media_type == 'video') {
        $video_url = $data->videos->standard_resolution->url;
    }

    if ($data->location) {
        if (isset($data->location->name) && $data->location->name) {
            $location_name = $data->location->name;
        }

        $lat = $data->location->latitude;
        $lng = $data->location->longitude;
    }

    $tags = '';
    if ($data->tags) {
        foreach($data->tags as $tag) {
            if ($tags == '') {
                $tags = $tag;
            }
            else {
                $tags.= ' ,' . $tag;
            }
        }
    }

    if (!$text) {
        if ($location_name) {
            $title = $location_name;
        }
        else {
            $title = 'no title';
        }
    }

    $new_post = array(
        'comment_status' => 'closed',
        'ping_status' => 'closed',
        'post_author' => 1,
        'post_content' => wp_strip_all_tags(kokagrams_removeEmoji($title)) ,
        'post_date' => date_i18n('Y-m-d H:i:s', $orig_date) , //[ Y-m-d H:i:s ] //The time post was made.
        'post_date_gmt' => date('Y-m-d H:i:s', $orig_date) , //[ Y-m-d H:i:s ] //The time post was made, in GMT.
        'post_status' => $settings['kokagrams_post_status'],
        'post_title' => wp_strip_all_tags(kokagrams_removeEmoji($title)) ,
        'post_type' => $settings['kokagrams_post_type'],
        'tags_input' => $tags,
    );
    if (isset($_GET['tab']) && $_GET['tab'] == "reload") {
        echo "<br />insert_picture: " . $data->id . "<br />";
    }

    // echo "<pre>";
    // var_dump($data);
    // echo "</pre>";

    $post_id = wp_insert_post($new_post, true);
    if (@$post_id->errors) {
        echo "<pre>";
        var_dump($post_id->errors);
        var_dump($new_post);
        echo "</pre>";
        exit;
    }

    if (@$post_id && !@$post_id->errors && @$post_id > 0) {
        update_post_meta($post_id, 'img_url', $img_url);
        update_post_meta($post_id, 'type', $type);
        update_post_meta($post_id, 'picid', $picid);
        update_post_meta($post_id, 'orig_date', $orig_date);
        update_post_meta($post_id, 'lat', $lat);
        update_post_meta($post_id, 'lng', $lng);
        update_post_meta($post_id, 'user', $user);
        update_post_meta($post_id, 'user_fullname', $user_fullname);
        update_post_meta($post_id, 'location_name', $location_name);
        update_post_meta($post_id, 'media_type', $media_type);
        update_post_meta($post_id, 'video_url', $video_url);
        $place = array(
            'lat' => $lat,
            'lng' => $lng,
        );
        update_post_meta($post_id, 'place', $place);
        if ($upload_images === 'yes'):
            $photoURL = kokagrams_upload_image($data->images->standard_resolution->url, $post_id, $title, true);
            if ($photoURL !== 'error'):

                // $post_update = array(
                //     'ID'           => $post_id,
                //     'post_content' => '<img src="'.$photoURL.'" />'
                // );
                // wp_update_post( $post_update );

            endif;
        endif;
        $user_pictureURL = kokagrams_upload_image($user_picture, $post_id, $user, false);
        update_post_meta($post_id, 'user_picture', $user_pictureURL);
        update_post_meta($post_id, 'user_picture_remote', $user_picture);
        if (isset($_GET['tab']) && $_GET['tab'] == "reload") {
            echo '<br /><img src="' . $data->images->thumbnail->url . '" />';
            echo "<br /> POST ID: " . $post_id . "<hr>";
        }
    }
    else {
        echo "<h2>Nevar izveidot Postu</h2>";
    }
}

function kokagrams_removeEmoji($text)
{
    $clean_text = "";

    // Match Emoticons

    $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
    $clean_text = preg_replace($regexEmoticons, '', $text);

    // Match Miscellaneous Symbols and Pictographs

    $regexSymbols = '/[\x{1F300}-\x{1F5FF}]/u';
    $clean_text = preg_replace($regexSymbols, '', $clean_text);

    // Match Transport And Map Symbols

    $regexTransport = '/[\x{1F680}-\x{1F6FF}]/u';
    $clean_text = preg_replace($regexTransport, '', $clean_text);

    // Match Miscellaneous Symbols

    $regexMisc = '/[\x{2600}-\x{26FF}]/u';
    $clean_text = preg_replace($regexMisc, '', $clean_text);

    // Match Dingbats

    $regexDingbats = '/[\x{2700}-\x{27BF}]/u';
    $clean_text = preg_replace($regexDingbats, '', $clean_text);
    return $clean_text;
}

function kokagrams_check_picture($media, $hashtags_per_user)
{
    global $wpdb;
    global $post;
    if (@$media->data) {
        foreach($media->data as $data):
            $media_type = $data->type;
            if ($media_type === 'image' || $media_type === 'video'):
                $photo_desc = strtolower(@$data->caption->text);
                if (!empty($hashtags_per_user)):
                    foreach($hashtags_per_user as $hashtag):
                        $hashtag = '#' . strtolower($hashtag);
                        if (strpos($photo_desc, $hashtag) !== false):
                            (string)$sql = "SELECT meta_id FROM " . $wpdb->postmeta . " WHERE meta_value = '" . $data->id . "' AND meta_key = 'picid'";
                            if ($wpdb->get_var($sql) == NULL):
                                kokagrams_insert_picture($data);
                            endif;
                        endif;
                    endforeach;
                else:
                    if (isset($_GET['tab']) && $_GET['tab'] == "reload") {
                        echo "<br />Parbaudam vai DB nav šis ieraksts: " . $data->id . " ";
                        echo '<br /><img width="50" height="50"  src="' . $data->images->thumbnail->url . '" />';
                    }

                    (string)$sql = "SELECT meta_id FROM " . $wpdb->postmeta . " WHERE meta_value = '" . $data->id . "' AND meta_key = 'picid'";
                    if ($wpdb->get_var($sql) == NULL):
                        kokagrams_insert_picture($data);
                    endif;
                endif;
            endif;
        endforeach;
    }
    else {

        //   	echo "<pre>";
        // var_dump($media);
        // echo "</pre>";

    }
}

function kokagrams_upload_image($url, $post_id, $title, $set_thumb = true)
{
    require_once (ABSPATH . '/wp-admin/includes/file.php');

    require_once (ABSPATH . '/wp-admin/includes/media.php');

    require_once (ABSPATH . '/wp-admin/includes/image.php');

    if ($url !== ''):
        $tmp = download_url($url);
        preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $url, $matches);
        $file_array['name'] = basename($matches[0]);
        $file_array['tmp_name'] = $tmp;
        if (is_wp_error($tmp)):
            @unlink($file_array['tmp_name']);
            $file_array['tmp_name'] = '';
            return 'error';
        endif;
        $photo_id = media_handle_sideload($file_array, $post_id, $title);
        if (is_wp_error($photo_id)):
            @unlink($file_array['tmp_name']);
            return 'error';
        endif;
        if ($set_thumb == true) {
            set_post_thumbnail($post_id, $photo_id);
        }

        return wp_get_attachment_url($photo_id);
    endif;
}

add_action('admin_enqueue_scripts', 'kokagrams_pointer_load', 1000);

function kokagrams_pointer_load($hook_suffix)
{
    if (get_bloginfo('version') < '3.3') return;
    $screen = get_current_screen();
    $screen_id = $screen->id;
    $pointers = apply_filters('kokagrams_admin_pointers-' . $screen_id, array());
    if (!$pointers || !is_array($pointers)) return;
    $dismissed = explode(',', (string)get_user_meta(get_current_user_id() , 'dismissed_wp_pointers', true));
    $valid_pointers = array();
    foreach($pointers as $pointer_id => $pointer) {
        if (in_array($pointer_id, $dismissed) || empty($pointer) || empty($pointer_id) || empty($pointer['target']) || empty($pointer['options'])) continue;
        $pointer['pointer_id'] = $pointer_id;
        $valid_pointers['pointers'][] = $pointer;
    }

    if (empty($valid_pointers)) return;
    wp_enqueue_style('wp-pointer');
    wp_enqueue_script('kokagrams-pointer', plugins_url('js/kokagrams-pointer.js', __FILE__) , array(
        'wp-pointer'
    ));
    wp_localize_script('kokagrams-pointer', 'kokagramsPointer', $valid_pointers);
}

add_action('after_switch_theme', 'kokagrams_flush_rewrite_rules');

function kokagrams_flush_rewrite_rules()
{
    flush_rewrite_rules();
}

function kokagrams_check_empty_fields()
{
    $settings = get_option('kokagrams_settings');
    if (empty($settings['kokagrams_public_hashtag'][0]) && empty($settings['kokagrams_user_id'][0])):
        return __('You need to', 'kokagrams') . '<a href="' . get_admin_url('', 'admin.php?page=kokagrams') . '">' . __('add your first team member', 'kokagrams') . '</a>';
    else:
        return __('No photos available yet.', 'kokagrams');
    endif;
}

add_action('init', 'kokagrams_photo_post_type_init');

function kokagrams_photo_post_type_init()
{
    $settings = get_option("kokagrams_settings");
    $kokagrams_post_singular = (isset($settings['kokagrams_rename_post_singular']) ? $settings['kokagrams_rename_post_singular'] : 'Photo');
    $kokagrams_post_plural = (isset($settings['kokagrams_rename_post_plural']) ? $settings['kokagrams_rename_post_plural'] : 'Photos');
    register_post_type(KOKAGRAMS_POST_TYPE, array(
        'labels' => array(
            'name' => __('Kokagrams Pic', 'kokagrams') ,
            'singular_name' => __('Kokagrams Pic', 'kokagrams') ,
            'all_items' => __('All Kokagrams Pics', 'kokagrams') ,
            'add_new' => __('Add New', 'kokagrams') ,
            'add_new_item' => __('Add New Kokagrams Pic', 'kokagrams') ,
            'edit' => __('Edit', 'kokagrams') ,
            'edit_item' => __('Edit Kokagrams Pics', 'kokagrams') ,
            'new_item' => __('New Kokagrams Pic', 'kokagrams') ,
            'view_item' => __('View Kokagrams Pic', 'kokagrams') ,
            'search_items' => __('Search Kokagrams Pics', 'kokagrams') ,
            'not_found' => kokagrams_check_empty_fields() ,
            'not_found_in_trash' => __('Nothing found in Trash', 'kokagrams') ,
            'parent_item_colon' => ''
        ) ,
        'description' => __('This is the custom post type for the Kokagrams photos', 'kokagrams') ,
        'public' => true,
        'publicly_queryable' => true,
        'exclude_from_search' => false,
        'show_ui' => true,
        'query_var' => true,
        'menu_position' => 20,
        'menu_icon' => plugins_url('images/icon.png', __FILE__) ,
        'rewrite' => array(
            'slug' => 'instagram',
            'with_front' => false
        ) ,
        'has_archive' => 'instagram_photos',
        'capability_type' => 'post',
        'hierarchical' => false,
        'taxonomies' => array(
            'post_tag'
        ) ,
        'supports' => array(
            'title',
            'editor',
            'thumbnail'
        ) //,'custom-fields'
    ));
}