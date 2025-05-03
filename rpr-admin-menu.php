<?php
if (!class_exists('RPR_Admin_Menu')) {
    class RPR_Admin_Menu {
        /**
         * Constructor
         */
        public function __construct()
        {
            global $wp_version;

            if ($wp_version < 3.5)
                add_action('admin_notices', array($this, 'rpr_version_warning'), 10, 0); // Runs after the admin menu is printed to the screen.
            if (is_multisite() && !Register_Plus_Redux::rpr_active_for_network())
                add_action('admin_notices', array($this, 'rpr_network_activate_warning'), 10, 0); // Runs after the admin menu is printed to the screen.

            if (!is_multisite()) {
                add_action('admin_menu', array($this, 'rpr_admin_menu'), 10, 0);
            }
            if (is_multisite()) {
                add_action('network_admin_menu', array($this, 'rpr_admin_menu'), 10, 0);
            }
        }

        /**
         * @return void
         */
        public function rpr_version_warning(): void
        {
            global $wp_version;
            global $pagenow;

            if ('plugins.php' === $pagenow || ('admin.php' === $pagenow && isset($_GET['page']) && 'register-plus-redux' === $_GET['page'])) {
                echo '<div id="register-plus-redux-warning" class="updated"><p><strong>', sprintf(__('Register Plus Redux requires WordPress 3.5 or greater. You are currently using WordPress %s, please upgrade WordPress or deactivate Register Plus Redux.', 'register-plus-redux'), $wp_version), '</strong></p></div>', "\n";
            }
        }

        /**
         * @return void
         */
        public function rpr_network_activate_warning(): void
        {
            global $pagenow;

            if ('plugins.php' === $pagenow || ('admin.php' === $pagenow && isset($_GET['page']) && 'register-plus-redux' === $_GET['page'])) {
                echo '<div id="register-plus-redux-warning" class="updated"><p><strong>', sprintf(__('Register Plus Redux must be Network Activated by Super Admin under WordPress Multisite. You will have limited functionality while not Network Activated. Please refer to <a href="%s">radiok.info</a> for help resolving this issue.', 'register-plus-redux'), 'https://radiok.info/blog/wordpress-multisite-activation-and-the-illogical-disregard-for-plugins/'), '</strong></p></div>', "\n";
            }
        }

        /**
         * @return void
         */
        public function rpr_new_user_notification_warning(): void
        {
            global $pagenow;

            if ('plugins.php' === $pagenow || ('admin.php' === $pagenow && isset($_GET['page']) && 'register-plus-redux' === $_GET['page'])) {
                echo '<div id="register-plus-redux-warning" class="updated"><p><strong>', sprintf(__('There is another active plugin that is conflicting with Register Plus Redux. The conflicting plugin is creating its own wp_new_user_notification function, this function is used to alter the messages sent out following the creation of a new user. Please refer to the <a href="%s">project</a> for help resolving this issue.', 'register-plus-redux'), 'https://github.com/radiok/register-plus-redux/issues'), '</strong></p></div>', "\n";
            }
        }

        /**
         * @return void
         */
        public function rpr_admin_menu(): void
        {
            global $register_plus_redux;

            $hookname = add_menu_page(__('Register Plus Redux', 'register-plus-redux'), __('Register Plus Redux', 'register-plus-redux'), 'manage_options', 'register-plus-redux', array($this, 'rpr_options_submenu'));
            // NOTE: $hookname = toplevel_page_register-plus-redux
            
            add_action('load-' . $hookname, array($this, 'rpr_options_submenu_load'), 10, 1);
            add_action('admin_footer-' . $hookname, array($this, 'rpr_options_submenu_footer'), 10, 1);
            if (!is_multisite()) {
                add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'rpr_filter_plugin_action_links'), 10, 4);
            }
            if (is_multisite()) {
                add_filter('network_admin_plugin_action_links_' . plugin_basename(__FILE__), array($this, 'rpr_filter_plugin_action_links'), 10, 4);
            }
            $user_query = new WP_User_Query(array('role' => 'rpr_unverified'));
            if (0 < $user_query->total_users || '1' === $register_plus_redux->rpr_get_option('verify_user_email') || '1' === $register_plus_redux->rpr_get_option('verify_user_admin')) {
                add_submenu_page('users.php', __('Unverified Users', 'register-plus-redux'), __('Unverified Users', 'register-plus-redux'), 'promote_users', 'unverified-users', array($this, 'rpr_users_submenu'));
            }
        }

        /**
         * @param array $actions
         * @param string $plugin_file
         * @param string $plugin_data
         * @param string $context
         * @return array
         */
        public function rpr_filter_plugin_action_links(array $actions, string $plugin_file, string $plugin_data, string $context): array
        {
            if (!is_multisite()) {
                $actions['settings'] = '<a href="' . admin_url('admin.php?page=register-plus-redux') . '">'. __('Settings', 'register-plus-redux') . '</a>';
            }
            if (is_multisite()) {
                $actions['settings'] = '<a href="' . admin_url('admin.php?page=register-plus-redux') . '">'. __('Settings', 'register-plus-redux') . '</a>';
            }

            return $actions;
        }

        /**
         * @return void
         */
        public function rpr_options_submenu_load(): void
        {
            add_action('admin_enqueue_scripts', array($this, 'rpr_admin_enqueue_scripts'), 10, 1);
        }

        /**
         * @param string $hook_suffix
         * @return void
         */
        public function rpr_admin_enqueue_scripts(string $hook_suffix) : void
        {
            wp_enqueue_style('jquery-ui-style', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.10.1/themes/ui-lightness/jquery-ui.css', false);
            if (!is_multisite()) wp_enqueue_style('thickbox');
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_script('jquery-ui-sortable');
            if (!is_multisite()) wp_enqueue_script('media-upload');
            if (!is_multisite()) wp_enqueue_script('thickbox');
            wp_enqueue_style('register-plus-redux', plugin_dir_url(__FILE__) . 'css/rpr-admin.css', [], RPR_VERSION);
        }

        /**
         * @return void
         */
        public function rpr_options_submenu_scripts(): void
        {
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-sortable');
            if (!is_multisite()) wp_enqueue_script('media-upload');
            if (!is_multisite()) wp_enqueue_script('thickbox');
        }

        /**
         * @return void
         */
        public function rpr_options_submenu_styles(): void
        {
            if (!is_multisite()) {
                wp_enqueue_style('thickbox');
            }
        }

        /**
         * @return void
         */
        public function rpr_options_submenu(): void
        {
            global $register_plus_redux;

            if (isset($_POST['update_settings'])) {
                check_admin_referer('register-plus-redux-update-settings');
                $this->update_settings();
                echo '<div id="message" class="updated"><p><strong>', __('Settings Saved', 'register-plus-redux'), '</strong></p></div>', PHP_EOL;
            }
            ?>
            <div class="wrap">
            <h2><?= _e('Register Plus Redux Settings', 'register-plus-redux') ?></h2>
            <form method="post">
                <?= wp_nonce_field('register-plus-redux-update-settings'); ?>
                <table class="form-table">
                    <?php if (!is_multisite()) { ?>
                    <tr class="top">
                        <th scope="row"><?= __('Custom Logo URL', 'register-plus-redux') ?></th>
                        <td>
                            <input type="text" name="custom_logo_url" id="custom_logo_url" value="<?= esc_attr($register_plus_redux->rpr_get_option('custom_logo_url')) ?>">
                            <input type="button" class="button" name="upload_custom_logo_button" id="upload_custom_logo_button" value="<?php esc_attr_e('Upload Image', 'register-plus-redux'); ?>">
                            <br>
                            <?= __('Custom Logo will be shown on Registration and Login Forms in place of the default Wordpress logo. For the best results custom logo should not exceed 350px width.', 'register-plus-redux') ?>
                            <?php if ($register_plus_redux->rpr_get_option('custom_logo_url')) { ?>
                                <br>
                                <img src="<?= esc_url($register_plus_redux->rpr_get_option('custom_logo_url')) ?>" alt="Custom logo">
                                <br>
                                <?php if (ini_get('allow_url_fopen')) {
                                    list($custom_logo_width, $custom_logo_height) = getimagesize(esc_url($register_plus_redux->rpr_get_option('custom_logo_url')));
                                    echo $custom_logo_width, 'x', $custom_logo_height, '<br>', PHP_EOL;
                                } ?>
                                <label>
                                    <input type="checkbox" name="remove_logo" id="remove_logo" value="1">&nbsp;
                                    <?= __('Remove Logo', 'register-plus-redux') ?>
                                </label>
                                <br>
                                <?= __('You must Save Changes to remove logo.', 'register-plus-redux') ?>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php } ?>
                    <tr class="top">
                        <th scope="row"><?= __('Email Verification', 'register-plus-redux') ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="verify_user_email" id="verify_user_email" class="showHideSettings" value="1" <?= checked($register_plus_redux->rpr_get_option('verify_user_email'), 1, false) ?>>&nbsp;
                                <?= __('Verify all new users email address...', 'register-plus-redux') ?>
                            </label>
                            <br>
                            <?= __('A verification code will be sent to any new users email address, new users will not be able to login or reset their password until they have completed the verification process. Administrators may authorize new users from the Unverified Users Page at their own discretion.', 'register-plus-redux') ?>
                            <div id="verify_user_email_settings"<?php if (!$register_plus_redux->rpr_get_option('verify_user_email')) echo ' class="disabled"'; ?>>
                                <br>
                                <?= __('The following message will be shown to users after registering. You may include HTML in this message.', 'register-plus-redux') ?>
                                <br>
                                <textarea name="message_verify_user_email" id="message_verify_user_email" rows="2"><?= esc_textarea($register_plus_redux->rpr_get_option('message_verify_user_email')) ?></textarea>
                            </div>
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row"><?= __('Admin Verification', 'register-plus-redux') ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="verify_user_admin" id="verify_user_admin" class="showHideSettings" value="1" <?= checked($register_plus_redux->rpr_get_option('verify_user_admin'), 1, false) ?>>&nbsp;<?= __('Moderate all new user registrations...', 'register-plus-redux') ?>
                            </label>
                            <br>
                            <?= __('New users will not be able to login or reset their password until they have been authorized by an administrator from the Unverified Users Page. If both verification options are enabled, users will not be able to login until an administrator authorizes them, regardless of whether they complete the email verification process.', 'register-plus-redux') ?>
                            <div id="verify_user_admin_settings"<?php if (!$register_plus_redux->rpr_get_option('verify_user_admin')) echo ' class="disabled"'; ?>>
                                <br>
                                <?= __('The following message will be shown to users after registering (or verifying their email if both verification options are enabled). You may include HTML in this message.', 'register-plus-redux') ?>
                                <br>
                                <textarea name="message_verify_user_admin" id="message_verify_user_admin" rows="2"><?= esc_textarea($register_plus_redux->rpr_get_option('message_verify_user_admin')) ?></textarea>
                            </div>
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row"><?= __('Grace Period', 'register-plus-redux') ?></th>
                        <td>
                            <label>
                                <input type="text" name="delete_unverified_users_after" id="delete_unverified_users_after" value="<?= esc_attr($register_plus_redux->rpr_get_option('delete_unverified_users_after')); ?>">&nbsp;<?= __('days', 'register-plus-redux') ?>
                            </label>
                            <br>
                            <?= __('All unverified users will automatically be deleted after the Grace Period specified, to disable this process enter 0 to never automatically delete unverified users.', 'register-plus-redux') ?>
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row"><?= __('Registration Redirect', 'register-plus-redux') ?></th>
                        <td>
                            <input type="text" name="registration_redirect_url" id="registration_redirect_url" value="<?= esc_attr($register_plus_redux->rpr_get_option('registration_redirect_url')); ?>">
                            <br>
                            <?= sprintf(__('By default, after registering, users will be sent to %s/wp-login.php?checkemail=registered, leave this value empty if you do not wish to change this behavior. You may enter another address here, however, if that address is not on the same domain, Wordpress will ignore the redirect.', 'register-plus-redux'), home_url()); ?><br>
                        </td>
                    </tr>
                    <tr class="top disabled">
                        <th scope="row"><?= __('Verification Redirect', 'register-plus-redux') ?></th>
                        <td>
                            <input type="text" name="verification_redirect_url" id="verification_redirect_url" value="<?= esc_attr($register_plus_redux->rpr_get_option('verification_redirect_url')); ?>">
                            <br>
                            <?= sprintf(__('By default, after verifying, users will be sent to %s/wp-login.php, leave this value empty if you do not wish to change this behavior. You may enter another address here, however, if that addresses is not on the same domain, Wordpress will ignore the redirect.', 'register-plus-redux'), home_url()); ?><br>
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row"><?= __('Autologin user', 'register-plus-redux') ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="autologin_user" id="autologin_user" value="1" <?= checked($register_plus_redux->rpr_get_option('autologin_user'), 1, false) ?>>&nbsp;<?= __('Autologin user after registration.', 'register-plus-redux') ?>
                            </label>
                            <br>
                            <?= sprintf(__('Works if Email Verification and Admin Verification are turned off. By default users will be sent to %s, to change this behavior, set up Registration Redirect field above.', 'register-plus-redux'), admin_url()); ?>
                        </td>
                    </tr>                       
                </table>
                <?php if (!is_multisite()) { ?>
                <h3 class="title"><?= __('Registration Form', 'register-plus-redux') ?></h3>
                <p><?= __('Select which fields to show on the Registration Form. Users will not be able to register without completing any fields marked required.', 'register-plus-redux') ?></p>
                <?php } else { ?>
                <h3 class="title"><?= _e('Signup Form', 'register-plus-redux') ?></h3>
                <p><?= __('Select which fields to show on the Signup Form. Users will not be able to signup without completing any fields marked required.', 'register-plus-redux') ?></p>
                <?php } ?>
                <table class="form-table">
                    <tr class="top">
                        <th scope="row"><?= __('Use Email as Username', 'register-plus-redux') ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="username_is_email" id="username_is_email" value="1" <?= checked($register_plus_redux->rpr_get_option('username_is_email'), 1, false) ?>>&nbsp;<?= __('New users will not be asked to enter a username, instead their email address will be used as their username.', 'register-plus-redux') ?>
                            </label>
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row"><?= __('Confirm Email', 'register-plus-redux') ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="double_check_email" id="double_check_email" value="1" <?= checked($register_plus_redux->rpr_get_option('double_check_email'), 1, false) ?>>&nbsp;<?= __('Require new users to enter e-mail address twice during registration.', 'register-plus-redux') ?>
                            </label>
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row"><?= __('Profile Fields', 'register-plus-redux') ?></th>
                        <td>
                            <table>
                                <thead class="top">
                                    <tr>
                                        <td class="padding_tbl"></td>
                                        <td class="center padding_tb">
                                            <?= __('Show', 'register-plus-redux') ?>
                                        </td>
                                        <td class="center padding_tb">
                                            <?= __('Require', 'register-plus-redux') ?>
                                        </td>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="center">
                                        <td class="padding_tbl">
                                            <? __('First Name', 'register-plus-redux') ?>
                                        </td>
                                        <td class="center padding_tb">
                                            <input type="checkbox" name="show_fields[]" id="show-fields-first-name" value="first_name" <?php if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('first_name', $register_plus_redux->rpr_get_option('show_fields'))) echo 'checked="checked"'; ?> class="modifyNextCellCheckbox">
                                        </td>
                                        <td class="center padding_tb">
                                            <input type="checkbox" name="required_fields[]" id="required-fields-first-name" value="first_name" <?php if (is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('first_name', $register_plus_redux->rpr_get_option('required_fields'))) echo 'checked="checked"'; ?>
                                            <?php if (is_array($register_plus_redux->rpr_get_option('show_fields')) && !in_array('first_name', $register_plus_redux->rpr_get_option('show_fields'))) echo 'disabled="disabled"'; ?>>
                                        </td>
                                    </tr>
                                    <tr class="center">
                                        <td class="padding_tbl">
                                            <?= __('Last Name', 'register-plus-redux') ?>
                                        </td>
                                        <td class="center padding_tb">
                                            <input type="checkbox" name="show_fields[]" id="show-fields-last-name" value="last_name" <?php if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('last_name', $register_plus_redux->rpr_get_option('show_fields'))) echo 'checked="checked"'; ?> class="modifyNextCellCheckbox">
                                        </td>
                                        <td class="center padding_tb">
                                            <input type="checkbox" name="required_fields[]" id="required-fields-last-name" value="last_name" <?php if (is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('last_name', $register_plus_redux->rpr_get_option('required_fields'))) echo 'checked="checked"'; ?> <?php if (is_array($register_plus_redux->rpr_get_option('show_fields')) && !in_array('last_name', $register_plus_redux->rpr_get_option('show_fields'))) echo 'disabled="disabled"'; ?>>
                                        </td>
                                    </tr>
                                    <tr class="center">
                                        <td class="padding_tbl">
                                            <?= _e('Website', 'register-plus-redux') ?>
                                        </td>
                                        <td class="center padding_tb">
                                            <input type="checkbox" name="show_fields[]" id="show-fields-user-url" value="user_url" <?php if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('user_url', $register_plus_redux->rpr_get_option('show_fields'))) echo 'checked="checked"'; ?> class="modifyNextCellCheckbox">
                                        </td>
                                        <td class="center padding_tb">
                                            <input type="checkbox" name="required_fields[]" id="required-fields-user-url" value="user_url" <?php if (is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('user_url', $register_plus_redux->rpr_get_option('required_fields'))) echo 'checked="checked"'; ?> <?php if (is_array($register_plus_redux->rpr_get_option('show_fields')) && !in_array('user_url', $register_plus_redux->rpr_get_option('show_fields'))) echo 'disabled="disabled"'; ?>>
                                        </td>
                                    </tr>
                                    <tr class="center">
                                        <td class="padding_tbl">
                                            <?= __('AIM', 'register-plus-redux') ?>
                                        </td>
                                        <td class="center padding_tb">
                                            <input type="checkbox" name="show_fields[]" id="show-fields-aim" value="aim" <?php if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('aim', $register_plus_redux->rpr_get_option('show_fields'))) echo 'checked="checked"'; ?> class="modifyNextCellCheckbox">
                                        </td>
                                        <td class="center padding_tb">
                                            <input type="checkbox" name="required_fields[]" id="required-fields-aim" value="aim" <?php if (is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('aim', $register_plus_redux->rpr_get_option('required_fields'))) echo 'checked="checked"'; ?> <?php if (is_array($register_plus_redux->rpr_get_option('show_fields')) && !in_array('aim', $register_plus_redux->rpr_get_option('show_fields'))) echo 'disabled="disabled"'; ?>>
                                        </td>
                                    </tr>
                                    <tr class="center">
                                        <td class="padding_tbl">
                                            <?= __('Yahoo IM', 'register-plus-redux') ?>
                                        </td>
                                        <td class="center padding_tb">
                                            <input type="checkbox" name="show_fields[]" id="show-fields-yahoo" value="yahoo" <?php if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('yahoo', $register_plus_redux->rpr_get_option('show_fields'))) echo 'checked="checked"'; ?> class="modifyNextCellCheckbox">
                                        </td>
                                        <td class="center padding_tb">
                                            <input type="checkbox" name="required_fields[]" id="required-fields-yahoo" value="yahoo" <?php if (is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('yahoo', $register_plus_redux->rpr_get_option('required_fields'))) echo 'checked="checked"'; ?> <?php if (is_array($register_plus_redux->rpr_get_option('show_fields')) && !in_array('yahoo', $register_plus_redux->rpr_get_option('show_fields'))) echo 'disabled="disabled"'; ?>>
                                        </td>
                                    </tr>
                                    <tr class="center">
                                        <td class="padding_tbl">
                                            <?= __('Jabber / Google Talk', 'register-plus-redux') ?>
                                        </td>
                                        <td class="center padding_tb">
                                            <input type="checkbox" name="show_fields[]" id="show-fields-jabber" value="jabber" <?php if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('jabber', $register_plus_redux->rpr_get_option('show_fields'))) echo 'checked="checked"'; ?> class="modifyNextCellCheckbox">
                                        </td>
                                        <td class="center padding_tb">
                                            <input type="checkbox" name="required_fields[]" id="required-fields-jabber" value="jabber" <?php if (is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('jabber', $register_plus_redux->rpr_get_option('required_fields'))) echo 'checked="checked"'; ?> <?php if (is_array($register_plus_redux->rpr_get_option('show_fields')) && !in_array('jabber', $register_plus_redux->rpr_get_option('show_fields'))) echo 'disabled="disabled"'; ?>>
                                        </td>
                                    </tr>
                                    <tr class="center">
                                        <td class="padding_tbl">
                                            <?= __('About Yourself', 'register-plus-redux') ?>
                                        </td>
                                        <td class="center padding_tb">
                                            <input type="checkbox" name="show_fields[]" id="show-fields-about" value="about" <?php if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('about', $register_plus_redux->rpr_get_option('show_fields'))) echo 'checked="checked"'; ?> class="modifyNextCellCheckbox">
                                        </td>
                                        <td class="center padding_tb">
                                            <input type="checkbox" name="required_fields[]" id="required-fields-about" value="about" <?php if (is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('about', $register_plus_redux->rpr_get_option('required_fields'))) echo 'checked="checked"'; ?> <?php if (is_array($register_plus_redux->rpr_get_option('show_fields')) && !in_array('about', $register_plus_redux->rpr_get_option('show_fields'))) echo 'disabled="disabled"'; ?>>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row"><?= __('User Set Password', 'register-plus-redux') ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="user_set_password" id="user_set_password" value="1" <?= checked($register_plus_redux->rpr_get_option('user_set_password'), 1, false) ?> class="showHideSettings">&nbsp;<?= __('Require new users enter a password during registration...', 'register-plus-redux') ?>
                            </label>
                            <br>
                            <div id="password_settings"<?php if (!$register_plus_redux->rpr_get_option('user_set_password')) echo ' class="disabled"'; ?>>
                                <label>
                                    <?= _e('Minimum password length: ', 'register-plus-redux') ?>
                                    <input type="text" name="min_password_length" id="min_password_length" value="<?= esc_attr($register_plus_redux->rpr_get_option('min_password_length')) ?>">
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="disable_password_confirmation" id="disable_password_confirmation" value="1" <?= checked($register_plus_redux->rpr_get_option('disable_password_confirmation'), 1, false) ?>>&nbsp;<?= __('Do not require users to confirm password.', 'register-plus-redux') ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="show_password_meter" id="show_password_meter" value="1" <?php checked($register_plus_redux->rpr_get_option('show_password_meter'), 1); ?> class="showHideSettings">&nbsp;<?= __('Show password strength meter...', 'register-plus-redux') ?>
                                </label>
                                <div id="meter_settings"<?php if (!$register_plus_redux->rpr_get_option('show_password_meter')) echo ' class="disabled"'; ?>>
                                    <table>
                                        <tr>
                                            <td class="padding_tbl">
                                                <label for="message_empty_password">
                                                    <?= __('Empty', 'register-plus-redux') ?>
                                                </label>
                                            </td>
                                            <td class="padding_tbl">
                                                <input type="text" name="message_empty_password" id="message_empty_password" value="<?= esc_attr($register_plus_redux->rpr_get_option('message_empty_password')); ?>">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="padding_tbl">
                                                <label for="message_short_password">
                                                    <?= __('Short', 'register-plus-redux') ?>
                                                </label>
                                            </td>
                                            <td class="padding_tbl">
                                                <input type="text" name="message_short_password" id="message_short_password" value="<?= esc_attr($register_plus_redux->rpr_get_option('message_short_password')); ?>">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="padding_tbl">
                                                <label for="message_bad_password">
                                                    <?= __('Bad', 'register-plus-redux') ?>
                                                </label>
                                            </td>
                                            <td class="padding_tbl">
                                                <input type="text" name="message_bad_password" id="message_bad_password" value="<?= esc_attr($register_plus_redux->rpr_get_option('message_bad_password')); ?>">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="padding_tbl">
                                                <label for="message_good_password">
                                                    <?= __('Good', 'register-plus-redux') ?>
                                                </label>
                                            </td>
                                            <td class="padding_tbl">
                                                <input type="text" name="message_good_password" id="message_good_password" value="<?= esc_attr($register_plus_redux->rpr_get_option('message_good_password')); ?>">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="padding_tbl">
                                                <label for="message_strong_password">
                                                    <?= __('Strong', 'register-plus-redux') ?>
                                                </label>
                                            </td>
                                            <td class="padding_tbl">
                                                <input type="text" name="message_strong_password" id="message_strong_password" value="<?= esc_attr($register_plus_redux->rpr_get_option('message_strong_password')) ?>">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="padding_tbl">
                                                <label for="message_mismatch_password">
                                                    <?= __('Mismatch', 'register-plus-redux') ?>
                                                </label>
                                            </td>
                                            <td class="padding_tbl">
                                                <input type="text" name="message_mismatch_password" id="message_mismatch_password" value="<?= esc_attr($register_plus_redux->rpr_get_option('message_mismatch_password')) ?>">
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row"><?= __('Invitation Code', 'register-plus-redux') ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_invitation_code" id="enable_invitation_code" value="1" <?= checked($register_plus_redux->rpr_get_option('enable_invitation_code'), 1, false) ?> class="showHideSettings">&nbsp;<?= __('Use invitation codes to track or authorize new user registration...', 'register-plus-redux') ?>
                            </label>
                            <div id="invitation_code_settings"<?php if (!$register_plus_redux->rpr_get_option('enable_invitation_code')) echo ' class="disabled"'; ?>>
                                <label>
                                    <input type="checkbox" name="require_invitation_code" id="require_invitation_code" value="1" <?= checked($register_plus_redux->rpr_get_option('require_invitation_code'), 1, false) ?>>&nbsp;<?= __('Require new user enter one of the following invitation codes to register.', 'register-plus-redux') ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="invitation_code_case_sensitive" id="invitation_code_case_sensitive" value="1" <?= checked($register_plus_redux->rpr_get_option('invitation_code_case_sensitive'), 1, false) ?>>&nbsp;<?= __('Enforce case-sensitivity of invitation codes.', 'register-plus-redux') ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="invitation_code_unique" id="invitation_code_unique" value="1" <?= checked($register_plus_redux->rpr_get_option('invitation_code_unique'), 1, false) ?>>&nbsp;<?= __('Each invitation code may only be used once.', 'register-plus-redux') ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="enable_invitation_tracking_widget" id="enable_invitation_tracking_widget" value="1" <?= checked($register_plus_redux->rpr_get_option('enable_invitation_tracking_widget'), 1, false) ?>>&nbsp;<?= __('Show Invitation Code Tracking widget on Dashboard.', 'register-plus-redux') ?>
                                </label>
                                <br>
                                <div id="invitation_code_bank">
                                <?php
                                    $invitation_code_bank = get_option('register_plus_redux_invitation_code_bank-rv1');
                                    if (is_array($invitation_code_bank)) {
                                        $size = sizeof($invitation_code_bank);
                                        for ($x = 0; $x < $size; $x++) {
                                            echo PHP_EOL, '<div class="invitation_code"';
                                            if ($x > 5) echo ' class="disabled"';
                                            echo '><input type="text" name="invitation_code_bank[]" id="invitation_code_bank[]" value="', esc_attr($invitation_code_bank[$x]) , '">&nbsp;<img src="', plugins_url('images/minus-circle.png', __FILE__), '" alt="', esc_attr__('Remove Code', 'register-plus-redux'), '" title="', esc_attr__('Remove Code', 'register-plus-redux'), '" class="removeInvitationCode cursor_pointer"></div>';
                                        }
                                        if ($size > 5) {
                                            echo '<div id="showHiddenInvitationCodes" class="cursor_pointer">', sprintf(_n('Show %d hidden invitation code', 'Show %d hidden invitation codes', ($size - 5), 'register-plus-redux'), ($size - 5)), '</div>';
                                        }
                                    }
                                ?>
                                </div>
                                <img src="<?= plugins_url('images/plus-circle.png', __FILE__) ?>" alt="<?php esc_attr_e('Add Code', 'register-plus-redux') ?>" title="<?php esc_attr_e('Add Code', 'register-plus-redux') ?>" id="addInvitationCode" class="cursor_pointer">&nbsp;<?= __('Add a new invitation code', 'register-plus-redux') ?>
                                <br>
                            </div>
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row"><?= __('Disclaimer', 'register-plus-redux') ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="show_disclaimer" id="show_disclaimer" value="1" <?= checked($register_plus_redux->rpr_get_option('show_disclaimer'), 1, false) ?> class="showHideSettings">&nbsp;<?= __('Show Disclaimer during registration...', 'register-plus-redux') ?>
                            </label>
                            <div id="disclaimer_settings"<?php if (!$register_plus_redux->rpr_get_option('show_disclaimer')) echo ' class="disabled"'; ?>>
                                <table id="disclaimer">
                                    <tr>
                                        <td class="padding_tbl_40">
                                            <label for="message_disclaimer_title">
                                                <?= __('Disclaimer Title', 'register-plus-redux') ?>
                                            </label>
                                        </td>
                                        <td class="padding_tb">
                                            <input type="text" name="message_disclaimer_title" id="message_disclaimer_title" value="<?= esc_attr($register_plus_redux->rpr_get_option('message_disclaimer_title')) ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="padding_tbl">
                                            <label for="message_disclaimer">
                                                <?= __('Disclaimer Content', 'register-plus-redux') ?>
                                            </label>
                                            <br>
                                            <textarea name="message_disclaimer" id="message_disclaimer"><?= esc_textarea($register_plus_redux->rpr_get_option('message_disclaimer')) ?></textarea>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="padding_tbl">
                                            <label>
                                                <input type="checkbox" name="require_disclaimer_agree" id="require_disclaimer_agree" class="enableDisableText" value="1" <?= checked($register_plus_redux->rpr_get_option('require_disclaimer_agree'), 1, false) ?>>&nbsp;<?= __('Require Agreement', 'register-plus-redux') ?>
                                            </label>
                                        </td>
                                        <td class="padding_tb">
                                            <input type="text" name="message_disclaimer_agree" id="message_disclaimer_agree" value="<?= esc_attr($register_plus_redux->rpr_get_option('message_disclaimer_agree')) ?>" <?php if (!$register_plus_redux->rpr_get_option('require_disclaimer_agree')) echo 'readonly="readonly"'; ?>>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row"><?= __('License Agreement' , 'register-plus-redux') ?></th>
                        <td>
                            <label><input type="checkbox" name="show_license" id="show_license" value="1" <?= checked($register_plus_redux->rpr_get_option('show_license'), 1, false) ?> class="showHideSettings">&nbsp;<?= __('Show License Agreement during registration...', 'register-plus-redux') ?></label>
                            <div id="license_settings"<?php if (!$register_plus_redux->rpr_get_option('show_license')) echo ' class="disabled"'; ?>>
                                <table id="license-settings">
                                    <tr>
                                        <td class="padding_tbl_40">
                                            <label for="message_license_title">
                                                <?= __('License Agreement Title', 'register-plus-redux') ?>
                                            </label>
                                        </td>
                                        <td class="padding_tb">
                                            <input type="text" name="message_license_title" id="message_license_title" value="<?= esc_attr($register_plus_redux->rpr_get_option('message_license_title')) ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="padding_tbl">
                                            <label for="message_license">
                                                <?= __('License Agreement Content', 'register-plus-redux') ?>
                                            </label>
                                            <br>
                                            <textarea name="message_license" id="message_license"><?= esc_textarea($register_plus_redux->rpr_get_option('message_license')) ?></textarea>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="padding_tbl">
                                            <label>
                                                <input type="checkbox" name="require_license_agree" id="require_license_agree" class="enableDisableText" value="1" <?= checked($register_plus_redux->rpr_get_option('require_license_agree'), 1, false) ?>>&nbsp;<?= __('Require Agreement', 'register-plus-redux') ?>
                                            </label>
                                        </td>
                                        <td class="padding_tb">
                                            <input type="text" name="message_license_agree" id="message_license_agree" value="<?= esc_attr($register_plus_redux->rpr_get_option('message_license_agree')) ?>" <?php if (!$register_plus_redux->rpr_get_option('require_license_agree')) echo 'readonly="readonly"'; ?>>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row"><?= __('Privacy Policy', 'register-plus-redux') ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="show_privacy_policy" id="show_privacy_policy" value="1" <?= checked($register_plus_redux->rpr_get_option('show_privacy_policy'), 1, false) ?> class="showHideSettings">&nbsp;<?= __('Show Privacy Policy during registration...', 'register-plus-redux') ?>
                            </label>
                            <div id="privacy_policy_settings"<?php if (!$register_plus_redux->rpr_get_option('show_privacy_policy')) echo ' class="disabled"'; ?>>
                                <table id="privacy-policy-settings">
                                    <tr>
                                        <td class="padding_tbl_40">
                                            <label for="message_privacy_policy_title">
                                                <?= __('Privacy Policy Title', 'register-plus-redux') ?>
                                            </label>
                                        </td>
                                        <td class="padding_tb">
                                            <input type="text" name="message_privacy_policy_title" id="message_privacy_policy_title" value="<?= esc_attr($register_plus_redux->rpr_get_option('message_privacy_policy_title')) ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="padding_tbl">
                                            <label for="message_privacy_policy">
                                                <?= __('Privacy Policy Content', 'register-plus-redux') ?>
                                            </label>
                                            <br>
                                            <textarea name="message_privacy_policy" id="message_privacy_policy"><?= esc_textarea($register_plus_redux->rpr_get_option('message_privacy_policy')) ?></textarea>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="padding_tbl">
                                            <label>
                                                <input type="checkbox" name="require_privacy_policy_agree" id="require_privacy_policy_agree" class="enableDisableText" value="1" <?= checked($register_plus_redux->rpr_get_option('require_privacy_policy_agree'), 1, false) ?>>&nbsp;<?= __('Require Agreement', 'register-plus-redux') ?>
                                            </label>
                                        </td>
                                        <td class="padding_tb">
                                            <input type="text" name="message_privacy_policy_agree" id="message_privacy_policy_agree" value="<?= esc_attr($register_plus_redux->rpr_get_option('message_privacy_policy_agree')) ?>" <?php if (!$register_plus_redux->rpr_get_option('require_privacy_policy_agree')) echo 'readonly="readonly"'; ?>>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row"><?= __('Use Default Style Rules', 'register-plus-redux') ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="default_css" id="default_css" value="1" <?= checked($register_plus_redux->rpr_get_option('default_css'), 1, false) ?>>&nbsp;<?= __('Apply default Wordpress styling to all fields.', 'register-plus-redux') ?>
                            </label>
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row">
                            <?= __('Required Fields Style Rules', 'register-plus-redux') ?>
                        </th>
                        <td>
                            <input type="text" name="required_fields_style" id="required_fields_style" value="<?= esc_attr($register_plus_redux->rpr_get_option('required_fields_style')) ?>">
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row">
                            <?= __('Required Fields Asterisk', 'register-plus-redux') ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="required_fields_asterisk" id="required_fields_asterisk" value="1" <?= checked($register_plus_redux->rpr_get_option('required_fields_asterisk'), 1, false) ?>>&nbsp;<?= __('Add asterisk to left of all required field\'s name.', 'register-plus-redux') ?>
                            </label>
                        </td>
                    </tr>
                    <?php if (!is_multisite()) { ?>
                    <tr class="top">
                        <th scope="row"><?= __('Starting Tabindex', 'register-plus-redux') ?></th>
                        <td>
                            <input type="text" name="starting_tabindex" id="starting_tabindex" value="<?= esc_attr($register_plus_redux->rpr_get_option('starting_tabindex')) ?>">
                            <br>
                            <?= __('The first field added will have this tabindex, the tabindex will increment by 1 for each additional field. Enter 0 to remove all tabindex\'s.', 'register-plus-redux') ?>
                        </td>
                    </tr>
                    <?php } ?>
                    <tr class="top">
                        <th scope="row">
                            <?= __('Minimal registration duration', 'register-plus-redux') ?>
                        </th>
                        <td>
                            <input type="text" name="min_expected_seconds_to_register" id="min_expected_seconds_to_register" value="<?= esc_attr($register_plus_redux->rpr_get_option('min_expected_seconds_to_register')) ?>">
                            <?= __('seconds', 'register-plus-redux') ?>
                            <br>
                            <?= __('A minimal duration of registration to prevent false registrations. Set to 0 to disable this check.', 'register-plus-redux') ?>
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row"><?= __('Blacklisted email domains', 'register-plus-redux') ?></th>
                        <td>
                            <input type="text"
                                   name="domain_blacklist"
                                   id="domain_blacklist"
                                   value="<?= esc_attr($register_plus_redux->rpr_get_option('domain_blacklist')) ?>">
                            <br>
                            <?php
                                _e('Coma separated list of domains not allowed to register',
                                    'register-plus-redux');
                            ?>
                        </td>
                    </tr>
                </table>
                <h3 class="title"><?= __('Additional Fields', 'register-plus-redux') ?></h3>
                <p>
                    <?= __('Enter additional fields to show on the User Profile and/or Registration Pages. Additional fields will be shown after existing profile fields on User Profile, and after selected profile fields on Registration Page but before Password, Invitation Code, Disclaimer, License Agreement, or Privacy Policy (if any of those fields are enabled). Options must be entered for Select, Checkbox, and Radio fields. Options should be entered with commas separating each possible value. For example, a Radio field named "Gender" could have the following options, "Male,Female".', 'register-plus-redux') ?>
                </p>
                <table id="meta-fields">
                    <tbody class="fields">
                        <?php
                        $redux_usermeta = get_option('register_plus_redux_usermeta-rv2');
                        if (is_array($redux_usermeta)) {
                            foreach ($redux_usermeta as $index => $meta_field) {
                                echo PHP_EOL, '<tr><td>';
        
                                echo PHP_EOL, '<table>';
    
                                echo PHP_EOL, '<tr class="label"><td><img src="', plugins_url('images/arrow-move.png', __FILE__), '" alt="', esc_attr__('Reorder', 'register-plus-redux'), '" title="', esc_attr__('Drag to Reorder', 'register-plus-redux'), '" class="sortHandle cursor_move">&nbsp;<input type="text" name="label[', $index, ']" id="label[', $index, ']" value="', esc_attr($meta_field['label']), '">&nbsp;<span class="enableDisableFieldSettings">', __('Show Settings', 'register-plus-redux'), '</span></td></tr>';
                                echo PHP_EOL, '<tr class="settings disabled"><td>';
        
                                echo PHP_EOL, '<table>';
    
                                echo PHP_EOL, '<tr><td>', __('Display', 'register-plus-redux'), '</td>';
                                echo PHP_EOL, '<td><select name="display[', $index, ']" id="display[', $index, ']" class="enableDisableOptions width_100">';
                                echo PHP_EOL, '<option value="textbox"', selected($meta_field['display'], 'textbox', false), '>', __('Textbox Field', 'register-plus-redux'), '</option>';
                                echo PHP_EOL, '<option value="select"', selected($meta_field['display'], 'select', false), '>', __('Select Field', 'register-plus-redux'), '</option>';
                                echo PHP_EOL, '<option value="checkbox"', selected($meta_field['display'], 'checkbox', false), '>', __('Checkbox Fields', 'register-plus-redux'), '</option>';
                                echo PHP_EOL, '<option value="radio"', selected($meta_field['display'], 'radio', false), '>', __('Radio Fields', 'register-plus-redux'), '</option>';
                                echo PHP_EOL, '<option value="textarea"', selected($meta_field['display'], 'textarea', false), '>', __('Text Area', 'register-plus-redux'), '</option>';
                                echo PHP_EOL, '<option value="hidden"', selected($meta_field['display'], 'hidden', false), '>', __('Hidden Field', 'register-plus-redux'), '</option>';
                                echo PHP_EOL, '<option value="text"', selected($meta_field['display'], 'text', false), '>', __('Static Text', 'register-plus-redux'), '</option>';
                                echo PHP_EOL, '<option value="terms"', selected($meta_field['display'], 'terms', false), '>', __('Terms', 'register-plus-redux'), '</option>';
                                echo PHP_EOL, '</select></td></tr>';
        
                                echo PHP_EOL, '<tr><td>', __('Options', 'register-plus-redux'), '</td>';
                                echo PHP_EOL, '<td><input type="text" name="options[', $index, ']" id="options[', $index, ']" value="', esc_attr($meta_field['options']), '"'; if ('textbox' !== $meta_field['display'] && 'select' !== $meta_field['display'] && 'checkbox' !== $meta_field['display'] && 'radio' !== $meta_field['display']) echo ' readonly="readonly"'; echo ' class="width_100"></td></tr>';
        
                                echo PHP_EOL, '<tr><td>', __('Database Key', 'register-plus-redux'), '</td>';
                                echo PHP_EOL, '<td><input type="text" name="meta_key[', $index, ']" id="meta_key[', $index, ']" value="', esc_attr($meta_field['meta_key']), '" class="width_100"></td></tr>';
        
                                echo PHP_EOL, '<tr><td>', __('Show on Profile', 'register-plus-redux'), '</td>';
                                echo PHP_EOL, '<td><input type="checkbox" name="show_on_profile[', $index, ']" id="show_on_profile[', $index, ']" value="1"', checked($meta_field['show_on_profile'], 1, false), '></td></tr>';
        
                                echo PHP_EOL, '<tr><td>', __('Show on Registration', 'register-plus-redux'), '</td>';
                                echo PHP_EOL, '<td><input type="checkbox" name="show_on_registration[', $index, ']" id="show_on_registration[', $index, ']" value="1"', checked($meta_field['show_on_registration'], 1, false), ' class="modifyNextRowCheckbox"></td></tr>';
        
                                echo PHP_EOL, '<tr><td>', __('Required Field', 'register-plus-redux'), '</td>';
                                echo PHP_EOL, '<td><input type="checkbox" name="require_on_registration[', $index, ']" id="require_on_registration[', $index, ']" value="1"', checked($meta_field['require_on_registration'], 1, false), ' ', disabled(empty($meta_field['show_on_registration']), true, false), '></td></tr>';
        
                                echo PHP_EOL, '<tr><td>', __('Show Datepicker', 'register-plus-redux'), '</td>';
                                echo PHP_EOL, '<td><input type="checkbox" name="show_datepicker[', $index, ']" id="show_datepicker[', $index, ']" value="1"', checked($meta_field['show_datepicker'], 1, false), ' ', disabled($meta_field['display'] === 'textbox', false, false), '></td></tr>';

                                echo PHP_EOL, '<tr><td>', __('Terms Content', 'register-plus-redux'), '</td>';
                                echo PHP_EOL, '<td><textarea name="terms_content[', $index, ']" id="terms_content[', $index, ']" class="height_160">', esc_textarea($meta_field['terms_content']),'</textarea></td></tr>';

                                echo PHP_EOL, '<tr><td>', __('Terms Agreement Text', 'register-plus-redux'), '</td>';
                                echo PHP_EOL, '<td><input type="text" name="terms_agreement_text[', $index, ']" id="terms_agreement_text[', $index, ']" value="', esc_attr($meta_field['terms_agreement_text']), '"'; if ('terms' !== $meta_field['display']) echo ' readonly="readonly"'; echo ' class="width_100"></td></tr>';

                                echo PHP_EOL, '<tr><td>', __('Revised', 'register-plus-redux'), '</td>';
                                echo PHP_EOL, '<td><input type="text" name="date_revised[', $index, ']" id="date_revised[', $index, ']" class="datepicker width_100" value="', date('m/d/Y', $meta_field['date_revised']), '"'; if ('terms' !== $meta_field['display']) echo ' readonly="readonly"'; echo '></td></tr>';

                                echo PHP_EOL, '<tr><td>', __('Actions', 'register-plus-redux'), '</td>';
                                echo PHP_EOL, '<td><img src="', plugins_url('images/question.png', __FILE__), '" alt="', esc_attr__('Help', 'register-plus-redux'), '" title="', esc_attr__('No help available', 'register-plus-redux'), '" class="helpButton cursor_pointer">';
                                echo PHP_EOL, '<img src="', plugins_url('images/minus-circle.png', __FILE__), '" alt="', esc_attr__('Remove', 'register-plus-redux'), '" title="', esc_attr__('Remove Field', 'register-plus-redux'), '" class="removeButton cursor_pointer"></td></tr>';
                                echo PHP_EOL, '</table>';
        
                                echo PHP_EOL, '</td></tr>';
                                echo PHP_EOL, '</table>';
        
                                echo PHP_EOL, '</td></tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
                <img src="<?= plugins_url('images/plus-circle.png', __FILE__); ?>" alt="<?php esc_attr_e('Add Field', 'register-plus-redux') ?>" title="<?php esc_attr_e('Add Field', 'register-plus-redux') ?>" id="addField" class="cursor_pointer">&nbsp;<?= __('Add a new custom field.', 'register-plus-redux') ?>
                <h3 class="title"><?= __('Autocomplete URL', 'register-plus-redux') ?></h3>
                <p><?= __('You can create a URL to autocomplete specific fields for the user. Additional fields use the database key. Included below are available keys and an example URL.', 'register-plus-redux') ?></p>
                <p><code>user_login user_email first_name last_name user_url aim yahoo jabber description invitation_code<?php if (is_array($redux_usermeta)) { foreach ($redux_usermeta as $meta_field) echo ' ', $meta_field['meta_key']; } ?></code></p>
                <p><code>https://www.radiok.info/wp-login.php?action=register&user_login=radiok&user_email=radiok@radiok.info&first_name=Radio&last_name=K&user_url=www.radiok.info&aim=radioko&invitation_code=1979&middle_name=Billy</code></p>
                <?php if (!is_multisite()) { ?>
                <h3 class="title"><?= __('New User Message Settings', 'register-plus-redux') ?></h3>
                <table class="form-table"> 
                    <tr class="top">
                        <th scope="row">
                            <label><?= __('New User Message', 'register-plus-redux') ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="disable_user_message_registered" id="disable_user_message_registered" value="1" <?= checked($register_plus_redux->rpr_get_option('disable_user_message_registered'), 1, false) ?>>&nbsp;<?= __('Do NOT send user an email after they are registered', 'register-plus-redux') ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="disable_user_message_created" id="disable_user_message_created" value="1" <?= checked($register_plus_redux->rpr_get_option('disable_user_message_created'), 1, false) ?>>&nbsp;<?= __('Do NOT send user an email when created by an administrator', 'register-plus-redux') ?>
                            </label>
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row">
                            <label><?= __('Custom New User Message', 'register-plus-redux') ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="custom_user_message" id="custom_user_message" value="1" <?= checked($register_plus_redux->rpr_get_option('custom_user_message'), 1, false) ?> class="showHideSettings">&nbsp;<?= __('Enable...', 'register-plus-redux') ?>
                            </label>
                            <div id="custom_user_message_settings"<?php if (!$register_plus_redux->rpr_get_option('custom_user_message')) echo ' class="disabled"'; ?>>
                                <table id="custom-user-message-settings">
                                    <tr>
                                        <td class="padding_tbl_20">
                                            <label for="user_message_from_email">
                                                <?= __('From Email', 'register-plus-redux') ?>
                                            </label>
                                        </td>
                                        <td class="padding_tb">
                                            <input type="text" name="user_message_from_email" id="user_message_from_email" value="<?= esc_attr($register_plus_redux->rpr_get_option('user_message_from_email')) ?>"><img src="<?= plugins_url('images/arrow-return-180.png', __FILE__); ?>" alt="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" title="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" class="default cursor_pointer">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="padding_tbl">
                                            <label for="user_message_from_name">
                                                <?= __('From Name', 'register-plus-redux') ?>
                                            </label>
                                        </td>
                                        <td class="padding_tb">
                                            <input type="text" name="user_message_from_name" id="user_message_from_name" value="<?= esc_attr($register_plus_redux->rpr_get_option('user_message_from_name')) ?>"><img src="<?= plugins_url('images/arrow-return-180.png', __FILE__); ?>" alt="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" title="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" class="default cursor_pointer">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="padding_tbl">
                                            <label for="user_message_subject">
                                                <?= __('Subject', 'register-plus-redux') ?>
                                            </label>
                                        </td>
                                        <td class="padding_tb">
                                            <input type="text" name="user_message_subject" id="user_message_subject" value="<?= esc_attr($register_plus_redux->rpr_get_option('user_message_subject')) ?>"><img src="<?= plugins_url('images/arrow-return-180.png', __FILE__) ?>" alt="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" title="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" class="default cursor_pointer">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="padding_tbl">
                                            <label for="user_message_body">
                                                <?= __('User Message', 'register-plus-redux') ?>
                                            </label>
                                            <br>
                                            <textarea name="user_message_body" id="user_message_body"><?= esc_textarea($register_plus_redux->rpr_get_option('user_message_body')) ?></textarea><img src="<?= plugins_url('images/arrow-return-180.png', __FILE__) ?>" alt="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" title="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" class="default cursor_pointer">
                                            <br>
                                            <strong><?= __('Replacement Keywords', 'register-plus-redux') ?>:</strong> <?= $register_plus_redux->replace_keywords(null, null) ?>
                                            <br>
                                            <label>
                                                <input type="checkbox" name="send_user_message_in_html" id="send_user_message_in_html" value="1" <?= checked($register_plus_redux->rpr_get_option('send_user_message_in_html'), 1, false) ?>>&nbsp;<?= __('Send as HTML', 'register-plus-redux') ?>
                                            </label>
                                            <br>
                                            <label>
                                                <input type="checkbox" name="user_message_newline_as_br" id="user_message_newline_as_br" value="1" <?= checked($register_plus_redux->rpr_get_option('user_message_newline_as_br'), 1, false) ?>>&nbsp;<?= __('Convert new lines to &lt;br&gt; tags (HTML only)', 'register-plus-redux') ?>
                                            </label>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row">
                            <label><?= __('Custom Verification Message', 'register-plus-redux') ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="custom_verification_message" id="custom_verification_message" value="1" <?= checked($register_plus_redux->rpr_get_option('custom_verification_message'), 1, false) ?> class="showHideSettings">&nbsp;<?= __('Enable...', 'register-plus-redux') ?>
                            </label>
                            <div id="custom_verification_message_settings"<?php if (!$register_plus_redux->rpr_get_option('custom_verification_message')) echo ' class="disabled"'; ?>>
                                <table id="custom-verification-message-settings">
                                    <tr>
                                        <td class="padding_tbl_20">
                                            <label for="verification_message_from_email">
                                                <?= __('From Email', 'register-plus-redux') ?>
                                            </label>
                                        </td>
                                        <td class="padding_tb">
                                            <input type="text" name="verification_message_from_email" id="verification_message_from_email" value="<?= esc_attr($register_plus_redux->rpr_get_option('verification_message_from_email')); ?>"><img src="<?= plugins_url('images\arrow-return-180.png', __FILE__) ?>" alt="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" title="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" class="default cursor_pointer">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="padding_tbl">
                                            <label for="verification_message_from_name">
                                                <?= __('From Name', 'register-plus-redux') ?>
                                            </label>
                                        </td>
                                        <td class="padding_tb">
                                            <input type="text" name="verification_message_from_name" id="verification_message_from_name" value="<?= esc_attr($register_plus_redux->rpr_get_option('verification_message_from_name')); ?>"><img src="<?= plugins_url('images\arrow-return-180.png', __FILE__) ?>" alt="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" title="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" class="default cursor_pointer">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="padding_tbl">
                                            <label for="verification_message_subject">
                                                <?= __('Subject', 'register-plus-redux') ?>
                                            </label>
                                        </td>
                                        <td class="padding_tb">
                                            <input type="text" name="verification_message_subject" id="verification_message_subject" value="<?= esc_attr($register_plus_redux->rpr_get_option('verification_message_subject')) ?>"><img src="<?= plugins_url('images\arrow-return-180.png', __FILE__) ?>" alt="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" title="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" class="default cursor_pointer">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="padding_tbl">
                                            <label for="verification_message_body">
                                                <?= __('User Message', 'register-plus-redux') ?>
                                            </label>
                                            <br>
                                            <textarea name="verification_message_body" id="verification_message_body"><?= esc_textarea($register_plus_redux->rpr_get_option('verification_message_body')) ?></textarea><img src="<?= plugins_url('images\arrow-return-180.png', __FILE__) ?>" alt="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" title="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" class="default cursor_pointer">
                                            <br>
                                            <strong><?= __('Replacement Keywords', 'register-plus-redux') ?>:</strong> <?= $register_plus_redux->replace_keywords(null, null) ?>
                                            <br>
                                            <label>
                                                <input type="checkbox" name="send_verification_message_in_html" id="send_verification_message_in_html" value="1" <?= checked($register_plus_redux->rpr_get_option('send_verification_message_in_html'), 1, false) ?>>&nbsp;<?= __('Send as HTML', 'register-plus-redux') ?>
                                            </label>
                                            <br>
                                            <label>
                                                <input type="checkbox" name="verification_message_newline_as_br" id="verification_message_newline_as_br" value="1" <?= checked($register_plus_redux->rpr_get_option('verification_message_newline_as_br'), 1, false) ?>>&nbsp;<?= __('Convert new lines to &lt;br&gt; tags (HTML only)', 'register-plus-redux') ?>
                                            </label>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row">
                            <label><?= __('Summary', 'register-plus-redux') ?></label>
                        </th>
                        <td>
                            <span id="user_message_summary"></span>
                        </td>
                    </tr>
                </table>
                <h3 class="title"><?= __('Admin Notification Settings', 'register-plus-redux') ?></h3>
                <table class="form-table"> 
                    <tr class="top">
                        <th scope="row">
                            <label><?= __('Admin Notification', 'register-plus-redux') ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="disable_admin_message_registered" id="disable_admin_message_registered" value="1" <?= checked($register_plus_redux->rpr_get_option('disable_admin_message_registered'), 1, false) ?>>&nbsp;<?= __('Do NOT send administrator an email whenever a new user registers', 'register-plus-redux') ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="disable_admin_message_created" id="disable_admin_message_created" value="1" <?= checked($register_plus_redux->rpr_get_option('disable_admin_message_created'), 1, false) ?>>&nbsp;<?= __('Do NOT send administrator an email whenever a new user is created by an administrator', 'register-plus-redux') ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="admin_message_when_verified" id="admin_message_when_verified" value="1" <?= checked($register_plus_redux->rpr_get_option('admin_message_when_verified'), 1, false) ?>>&nbsp;<?= __('Send administrator an email after a new user is verified', 'register-plus-redux') ?>
                            </label>
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row">
                            <label><?= __('Custom Admin Notification', 'register-plus-redux') ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="custom_admin_message" id="custom_admin_message" value="1" <?php checked($register_plus_redux->rpr_get_option('custom_admin_message'), 1) ?> class="showHideSettings">&nbsp;<?= __('Enable...', 'register-plus-redux') ?>
                            </label>
                            <div id="custom_admin_message_settings"<?php if (!$register_plus_redux->rpr_get_option('custom_admin_message')) echo ' class="disabled"'; ?>>
                                <table id="custom-admin-message-settings">
                                    <tr>
                                        <td class="padding_tbl_20">
                                            <label for="admin_message_from_email">
                                                <?= __('From Email', 'register-plus-redux') ?>
                                            </label>
                                        </td>
                                        <td class="padding_tb">
                                            <input type="text" name="admin_message_from_email" id="admin_message_from_email" value="<?= esc_attr($register_plus_redux->rpr_get_option('admin_message_from_email')) ?>"><img src="<?= plugins_url('images\arrow-return-180.png', __FILE__) ?>" alt="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" title="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" class="default cursor_pointer">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="padding_tbl">
                                            <label for="admin_message_from_name">
                                                <?= __('From Name', 'register-plus-redux') ?>
                                            </label>
                                        </td>
                                        <td class="padding_tb">
                                            <input type="text" name="admin_message_from_name" id="admin_message_from_name" value="<?= esc_attr($register_plus_redux->rpr_get_option('admin_message_from_name')); ?>"><img src="<?= plugins_url('images\arrow-return-180.png', __FILE__) ?>" alt="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" title="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" class="default cursor_pointer">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="padding_tbl">
                                            <label for="admin_message_subject">
                                                <?= _e('Subject', 'register-plus-redux') ?>
                                            </label>
                                        </td>
                                        <td class="padding_tb">
                                            <input type="text" name="admin_message_subject" id="admin_message_subject" value="<?= esc_attr($register_plus_redux->rpr_get_option('admin_message_subject')); ?>"><img src="<?= plugins_url('images\arrow-return-180.png', __FILE__) ?>" alt="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" title="<?php esc_attr_e('Restore Default', 'register-plus-redux') ?>" class="default cursor_pointer">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="padding_tbl">
                                            <label for="admin_message_body">
                                                <?= __('Admin Message', 'register-plus-redux') ?>
                                            </label>
                                            <br>
                                            <textarea name="admin_message_body" id="admin_message_body"><?= esc_textarea($register_plus_redux->rpr_get_option('admin_message_body')) ?></textarea><img src="<?= plugins_url('images\arrow-return-180.png', __FILE__) ?>" alt="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" title="<?php esc_attr_e('Restore Default', 'register-plus-redux'); ?>" class="default cursor_pointer">
                                            <br>
                                            <strong><?= __('Replacement Keywords', 'register-plus-redux') ?>:</strong> <?= $register_plus_redux->replace_keywords(null, null) ?>
                                            <br>
                                            <label>
                                                <input type="checkbox" name="send_admin_message_in_html" id="send_admin_message_in_html" value="1" <?= checked($register_plus_redux->rpr_get_option('send_admin_message_in_html'), 1, false) ?>>&nbsp;<?= __('Send as HTML', 'register-plus-redux') ?>
                                            </label>
                                            <br>
                                            <label>
                                                <input type="checkbox" name="admin_message_newline_as_br" id="admin_message_newline_as_br" value="1" <?= checked($register_plus_redux->rpr_get_option('admin_message_newline_as_br'), 1, false) ?>>&nbsp;<?= __('Convert new lines to &lt;br&gt; tags (HTML only)', 'register-plus-redux') ?>
                                            </label>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row">
                            <label><?= __('Summary', 'register-plus-redux') ?></label>
                        </th>
                        <td>
                            <span id="admin_message_summary"></span>
                        </td>
                    </tr>
                </table>
                <?php } ?>
                <h3 class="title"><?= __('Custom CSS for Register & Login Pages', 'register-plus-redux') ?></h3>
                <p><?= __('CSS Rule Example:', 'register-plus-redux') ?>&nbsp;<code>#user_login { font-size: 20px; width: 100%; padding: 3px; margin-right: 6px; }</code></p>
                <table class="form-table">
                    <tr class="top">
                        <th scope="row">
                            <label for="custom_registration_page_css">
                                <?= __('Custom Register CSS', 'register-plus-redux') ?>
                            </label>
                        </th>
                        <td>
                            <textarea name="custom_registration_page_css" id="custom_registration_page_css"><?= esc_textarea($register_plus_redux->rpr_get_option('custom_registration_page_css')) ?></textarea>
                        </td>
                    </tr>
                    <tr class="top">
                        <th scope="row">
                            <label for="custom_login_page_css">
                                <?= __('Custom Login CSS', 'register-plus-redux') ?></label>
                        </th>
                        <td>
                            <textarea name="custom_login_page_css" id="custom_login_page_css"><?= esc_textarea($register_plus_redux->rpr_get_option('custom_login_page_css')) ?></textarea>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes', 'register-plus-redux'); ?>" name="update_settings" id="update_settings">
                    <?php if (!is_multisite()) { ?>
                        <a href="<?= site_url('wp-login.php?action=register') ?>" target="_blank" class="button"><?php esc_attr_e('Preview Registration Page', 'register-plus-redux'); ?></a>
                    <?php } else { ?>
                        <a href="<?= network_site_url('wp-signup.php') ?>" target="_blank" class="button"><?php esc_attr_e('Preview Signup Page', 'register-plus-redux'); ?></a>
                    <?php } ?>
                </p>
            </form>
            </div>
            <?php
        }

        /**
         * @return void
         */
        public function rpr_options_submenu_footer(): void
        {
            ?>
            <script>
                function addInvitationCode() {
                    $("#invitation_code_bank")
                        .append($("<div>")
                        .attr("class", "invitation_code")
                        .append($("<input>")
                            .attr("type", "text")
                            .attr("name", "invitation_code_bank[]")
                            .attr("value", "")
                        )
                        .append("&nbsp;")
                        .append($("<img>")
                            .attr("src", "<?= plugins_url('images/minus-circle.png', __FILE__) ?>")
                            .attr("alt", "<?php esc_attr_e('Remove Code', 'register-plus-redux'); ?>")
                            .attr("title", "<?php esc_attr_e('Remove Code', 'register-plus-redux'); ?>")
                            .attr("class", "removeInvitationCode cursor_pointer")
                        )
                   );
                }

                function addField() {
                    $("#meta-fields").find("tbody.fields")
                        .append($("<tr>")
                        .append($("<td>")
                            .append($("<img>")
                                .attr("src", "<?= plugins_url('images\asterisk-yellow.png', __FILE__) ?>")
                                .attr("alt", "<?php esc_attr_e('New', 'register-plus-redux'); ?>")
                                .attr("title", "<?php esc_attr_e('New Field', 'register-plus-redux'); ?>")
                            )
                            .append("&nbsp;")
                            .append($("<input>")
                                .attr("type", "text")
                                .attr("name", "newMetaFields[]")
                            )
                            .append("&nbsp;")
                            .append($("<img>")
                                .attr("src", "<?= plugins_url('images\minus-circle.png', __FILE__) ?>")
                                .attr("alt", "<?php esc_attr_e('Remove', 'register-plus-redux'); ?>")
                                .attr("title", "<?php esc_attr_e('Remove Field', 'register-plus-redux'); ?>")
                                .attr("class", "removeNewButton cursor_pointer")
                             )
                            .append("&nbsp;")
                            .append($("<img>")
                                .attr("src", "<?= plugins_url('images\question.png', __FILE__) ?>")
                                .attr("alt", "<?php esc_attr_e('Help', 'register-plus-redux'); ?>")
                                .attr("title", "<?php esc_attr_e('You must save after adding new fields before all options become available.', 'register-plus-redux'); ?>")
                                .attr("class", "helpButton cursor_pointer")
                           )
                       )
                   );
                }

                function updateUserMessagesSummary() {
                    var userMessageSummary = $("#user_message_summary");
                    var verifyUserEmail = $("#verify_user_email");
                    var customVerificationMessage = $("#custom_verification_message");
                    userMessageSummary.empty();
                    if (!verifyUserEmail.prop("checked")) {
                        customVerificationMessage.prop("disabled", true);
                        customVerificationMessage.prop("checked", false);
                        $("#custom_verification_message_settings").hide();
                    }
                    else {
                        customVerificationMessage.prop("disabled", false);
                        userMessageSummary.append("<?= __('The following message will be sent when a user is registered:', 'register-plus-redux') ?>");
                        var verificationMessageFromName = "<?= Register_Plus_Redux::default_options('verification_message_from_name') ?>";
                        var verificationMessageFromEmail = "<?= Register_Plus_Redux::default_options('verification_message_from_email') ?>";
                        var verificationMessageSubject = "<?= Register_Plus_Redux::default_options('verification_message_subject') ?>";
                        var verificationMessageContentType = "text/plain";
                        var verificationMessageBody = "<?= str_replace("\n", '\n', Register_Plus_Redux::default_options('verification_message_body')) ?>";
                        if (customVerificationMessage.prop("checked")) {
                            verificationMessageFromName = $("#verification_message_from_name").val();
                            verificationMessageFromEmail = $("#verification_message_from_email").val();
                            verificationMessageSubject = $("#verification_message_subject").val();
                            if ($("#send_verification_message_in_html").prop("checked")) verificationMessageContentType = "text/html";
                            verificationMessageBody = $("#verification_message_body").val();
                        }
                        var verificationMessage = $("<p>").attr("style", "font-size: 11px; display: block; width: 50%; background-color: #efefef; padding: 8px 10px; border: solid 1px #dfdfdf; margin: 1px; overflow:auto; white-space:pre;");
                        verificationMessage.append($("<div>").text("<?= __('To: ', 'register-plus-redux') ?>" + "%user_email%"));
                        verificationMessage.append($("<div>").text("<?= __('From: ', 'register-plus-redux') ?>" + verificationMessageFromName + " (" + verificationMessageFromEmail + ")"));
                        verificationMessage.append($("<div>").text("<?= __('Subject: ', 'register-plus-redux') ?>" + verificationMessageSubject));
                        verificationMessage.append($("<div>").text("<?= __('Content-Type: ', 'register-plus-redux') ?>" + verificationMessageContentType));
                        verificationMessage.append($("<div>").text(verificationMessageBody));
                        userMessageSummary.append(verificationMessage);
                    }
                    var customUserMessage = $("#custom_user_message");
                    var disableUserMessageRegistered = $("#disable_user_message_registered");
                    var disableUserMessageCreated = $("#disable_user_message_created");
                    if (disableUserMessageRegistered.prop("checked") && disableUserMessageCreated.prop("checked")) {
                        customUserMessage.prop("disabled", true);
                        customUserMessage.prop("checked", false);
                        $("#custom_user_message_settings").hide();
                        userMessageSummary.append("<?= __('No message will be sent to user whether they are registered or created by an administrator.', 'register-plus-redux') ?>");
                    }
                    else {
                        customUserMessage.prop("disabled", false);
                        var when = "<?= __('The following message will be sent when a user is ', 'register-plus-redux') ?>";
                        if (!disableUserMessageRegistered.prop("checked")) when = when + "<?= __('registered', 'register-plus-redux') ?>";
                        if (!disableUserMessageRegistered.prop("checked") && !disableUserMessageCreated.prop("checked")) when = when + "<?= __(' or ', 'register-plus-redux') ?>";
                        if (!disableUserMessageCreated.prop("checked")) when = when + "<?= __('created', 'register-plus-redux') ?>";
                        var verifyUserAdmin = $("#verify_user_admin");
                        if (verifyUserEmail.prop("checked") || verifyUserAdmin.prop("checked")) when = when + "<?= __(' after ', 'register-plus-redux') ?>";
                        if (verifyUserEmail.prop("checked"))
                            when = when + "<?= __('the user has verified their email address', 'register-plus-redux') ?>";
                        if (verifyUserEmail.prop("checked") && verifyUserAdmin.prop("checked")) when = when + "<?= __(' and/or ', 'register-plus-redux') ?>";
                        if (verifyUserAdmin.prop("checked"))
                            when = when + "<?= __('an administrator has approved the new user', 'register-plus-redux') ?>";
                        userMessageSummary.append(when + ":");
                        var userMessageFromName = "<?= Register_Plus_Redux::default_options('user_message_from_name') ?>";
                        var userMessageFromEmail = "<?= Register_Plus_Redux::default_options('user_message_from_email') ?>";
                        var userMessageSubject = "<?= Register_Plus_Redux::default_options('user_message_subject') ?>";
                        var userMessageContentType = "text/plain";
                        var userMessageBody = "<?= str_replace("\n", '\n', Register_Plus_Redux::default_options('user_message_body')) ?>";
                        if (customUserMessage.prop("checked")) {
                            userMessageFromName = $("#user_message_from_name").val();
                            userMessageFromEmail = $("#user_message_from_email").val();
                            userMessageSubject = $("#user_message_subject").val();
                            if ($("#send_user_message_in_html").prop("checked")) userMessageContentType = "text/html";
                            userMessageBody = $("#user_message_body").val();
                        }
                        var userMessage = $("<p>").attr("style", "font-size: 11px; display: block; width: 50%; background-color: #efefef; padding: 8px 10px; border: solid 1px #dfdfdf; margin: 1px; overflow:auto; white-space:pre;");
                        userMessage.append($("<div>").text("<?= _e('To: ', 'register-plus-redux') ?>" + "%user_email%"));
                        userMessage.append($("<div>").text("<?= _e('From: ', 'register-plus-redux') ?>" + userMessageFromName + " (" + userMessageFromEmail + ")"));
                        userMessage.append($("<div>").text("<?= _e('Subject: ', 'register-plus-redux') ?>" + userMessageSubject));
                        userMessage.append($("<div>").text("<?= _e('Content-Type: ', 'register-plus-redux') ?>" + userMessageContentType));
                        userMessage.append($("<div>").text(userMessageBody));
                        userMessageSummary.append(userMessage);
                    }
                }

                function updateAdminMessageSummary() {
                    var adminMessageSummary = $("#admin_message_summary");
                    adminMessageSummary.empty();
                    var disableAdminMessageRegistered = $("#disable_admin_message_registered");
                    var disableAdminMessageCreated = $("#disable_admin_message_created");
                    var customAdminMessage = $("#custom_admin_message");
                    if (disableAdminMessageRegistered.prop("checked") && disableAdminMessageCreated.prop("checked")) {
                        customAdminMessage.prop("disabled", true);
                        customAdminMessage.prop("checked", false);
                        $("#custom_admin_message_settings").hide();
                        adminMessageSummary.append("<?= __('No message will be sent to administrator whether a user is registered or created.', 'register-plus-redux') ?>");
                    }
                    else {
                        customAdminMessage.prop("disabled", false);
                        var when = "<?= _e('The following message will be sent when a user is ', 'register-plus-redux') ?>";
                        if (!disableAdminMessageRegistered.prop("checked")) when = when + "<?= __('registered', 'register-plus-redux') ?>";
                        if (!disableAdminMessageRegistered.prop("checked") && !disableAdminMessageCreated.prop("checked")) when = when + "<?= __(' or ', 'register-plus-redux') ?>";
                        if (!disableAdminMessageCreated.prop("checked")) when = when + "<?= __('created', 'register-plus-redux') ?>";
                        adminMessageSummary.append(when + ":");
                        var adminMessageFromName = "<?= Register_Plus_Redux::default_options('admin_message_from_name') ?>";
                        var adminMessageFromEmail = "<?= Register_Plus_Redux::default_options('admin_message_from_email') ?>";
                        var adminMessageSubject = "<?= Register_Plus_Redux::default_options('admin_message_subject') ?>";
                        var adminMessageContentType = "text/plain";
                        var adminMessageBody = "<?= str_replace("\n", '\n', Register_Plus_Redux::default_options('admin_message_body')) ?>";
                        if (customAdminMessage.prop("checked")) {
                            adminMessageFromName = $("#admin_message_from_name").val();
                            adminMessageFromEmail = $("#admin_message_from_email").val();
                            adminMessageSubject = $("#admin_message_subject").val();
                            if ($("#send_admin_message_in_html").prop("checked")) adminMessageContentType = "text/html";
                            adminMessageBody = $("#admin_message_body").val();
                        }
                        var adminMessage = $("<p>").attr("style", "font-size: 11px; display: block; width: 50%; background-color: #efefef; padding: 8px 10px; border: solid 1px #dfdfdf; margin: 1px; overflow:auto; white-space:pre;");
                        adminMessage.append($("<div>").text("<?= __('To: ', 'register-plus-redux') . get_option('admin_email') ?>"));
                        adminMessage.append($("<div>").text("<?= __('From: ', 'register-plus-redux') ?>" + adminMessageFromName + " (" + adminMessageFromEmail + ")"));
                        adminMessage.append($("<div>").text("<?= __('Subject: ', 'register-plus-redux') ?>" + adminMessageSubject));
                        adminMessage.append($("<div>").text("<?= __('Content-Type: ', 'register-plus-redux') ?>" + adminMessageContentType));
                        adminMessage.append($("<div>").text(adminMessageBody));
                        adminMessageSummary.append(adminMessage);
                    }
                }

                var $ = jQuery.noConflict();
                $(document).ready(function() {
                    $(".datepicker").datepicker({
                        beforeShow: function(input, inst) {
                            if ($(input).prop("readonly")) {
                                inst.dpDiv = $('<div class="disabled"></div>');
                            }
                        }
                    });

                    $(document).on("click", "#upload_custom_logo_button", function() {
                        tb_show("<?= __('Upload/Select Logo', 'register-plus-redux') ?>",
                            "<?= admin_url('media-upload.php') ?>?post_id=0&type=image&context=custom-logo&TB_iframe=1");
                    });

                    window.send_to_editor = function(html) {
                        $("#custom_logo_url").val($("img", html).attr("src"));
                        tb_remove();
                    }

                    $("#meta-fields tbody.fields").sortable({handle:'.sortHandle'});

                    $(document).on("click", ".showHideSettings", function() {
                        if ($(this).prop("checked"))
                            $(this).parent().nextAll("div").first().show();
                        else
                            $(this).parent().nextAll("div").first().hide();
                    });

                    $(document).on("click", "#showHiddenInvitationCodes", function() {
                        $(this).parent().children().show();
                        $(this).remove();
                    });

                    $(document).on("click", "#addInvitationCode", function() {
                        addInvitationCode();
                    });

                    $(document).on("click", ".removeInvitationCode", function() {
                        $(this).parent().remove();
                    });

                    $(document).on("click", ".enableDisableText", function() {
                        if ($(this).prop("checked"))
                            $(this).parent().parent().next().find("input").prop("readOnly", false);
                        else
                            $(this).parent().parent().next().find("input").prop("readOnly", true);
                    });

                    $(document).on("click", ".helpButton", function() {
                        alert($(this).attr("title"));
                    });

                    $(document).on("click", "#addField", function() {
                        addField();
                    });

                    $(document).on("click", ".removeNewButton", function() {
                        $(this).parent().parent().remove();
                    });

                    $(document).on("click", ".removeButton", function() {
                        $(this).parent().parent().parent().parent().parent().parent().parent().remove();
                    });

                    $(document).on("click", ".enableDisableFieldSettings", function() {
                        if ($(this).text() === "<?= __('Show Settings', 'register-plus-redux') ?>") {
                            $(this).text("<?= __('Hide Settings', 'register-plus-redux') ?>");
                            $(this).parent().parent().parent().find(".settings").show();
                        }
                        else {
                            $(this).text("<?= __('Show Settings', 'register-plus-redux') ?>");
                            $(this).parent().parent().parent().find(".settings").hide();
                        }

                    });

                    $(document).on("change", ".enableDisableOptions", function() {
                        var name = $(this).attr("name");
                        var index = name.substring(name.indexOf("[") + 1, name.indexOf("]"));
                        if ($(this).val() === "textbox") {
                            $("input[name='options[" + index + "]']").prop("readOnly", false);
                            $("input[name='show_datepicker[" + index + "]']").prop("disabled", false);
                        }
                        else if ($(this).val() === "select" || $(this).val() === "checkbox" || $(this).val() === "radio" || $(this).val() === "text") {
                            $("input[name='options[" + index + "]']").prop("readOnly", false);
                            $("input[name='show_datepicker[" + index + "]']").prop("disabled", true);
                        }
                        else {
                            $("input[name='options[" + index + "]']").prop("readOnly", true);
                            $("input[name='show_datepicker[" + index + "]']").prop("disabled", true);
                        }
                    });

                    $(document).on("click", ".modifyNextCellCheckbox", function() {
                        if ($(this).prop("checked"))
                            $(this).parent().next().find("input[type='checkbox']").prop("disabled", false);
                        else {
                            $(this).parent().next().find("input[type='checkbox']").prop("checked", false);
                            $(this).parent().next().find("input[type='checkbox']").prop("disabled", true);
                        }
                    });

                    $(document).on("click", ".modifyNextRowCheckbox", function() {
                        if ($(this).prop("checked"))
                            $(this).closest("tr").next().find("input[type='checkbox']").prop("disabled", false);
                        else {
                            $(this).closest("tr").next().find("input[type='checkbox']").prop("checked", false);
                            $(this).closest("tr").next().find("input[type='checkbox']").prop("disabled", true);
                        }
                    });

                    $(document).on("click", ".upButton,.downButton", function() {
                        var row = $(this).parents("tr:first");
                        if ($(this).is(".upButton")) {
                            row.insertBefore(row.prev());
                        }
                        else {
                            row.insertAfter(row.next());
                        }
                    });

                    $("#verify_user_email,#verify_user_admin,#disable_user_message_registered,#disable_user_message_created,#custom_user_message,#user_message_from_name,#user_message_from_email,#user_message_subject,#user_message_body,#send_user_message_in_html,#custom_verification_message,#verification_message_from_name,#verification_message_from_email,#verification_message_subject,#verification_message_body,#verification_admin_message_in_html").change(function() {
                        updateUserMessagesSummary();
                    });

                    $("#disable_admin_message_registered,#disable_admin_message_created,#custom_admin_message,#admin_message_from_name,#admin_message_from_email,#admin_message_subject,#admin_message_body,#send_admin_message_in_html").change(function() {
                        updateAdminMessageSummary();
                    });

                    updateUserMessagesSummary();
                    updateAdminMessageSummary();
                });
            </script>
            <?php
        }

        /**
         * @return void
         */
        public function rpr_users_submenu(): void
        {
            global $register_plus_redux;

            if (isset($_GET['action']) && 'approve_user' === $_GET['action'] && isset($_GET['user_id'])) {
                check_admin_referer('register-plus-redux-unverified-users');
                $user_id = (int) $_GET['user_id'];
                if (current_user_can('promote_user', $user_id)) {
                    $plaintext_pass = get_user_meta($user_id, 'stored_user_password', true);
                    $user = get_userdata($user_id);
                    if (!is_multisite()) {
                        $user->set_role((string)get_option('default_role'));
                    }
                    else {
                        $user->remove_role('rpr_unverified');
                    }
                    if (empty($plaintext_pass)) {
                        $plaintext_pass = wp_generate_password();
                        update_user_option($user_id, 'default_password_nag', true, true);
                        wp_set_password($plaintext_pass, $user_id);
                    }
                    do_action('rpr_signup_complete', $user_id);
                    if (!$register_plus_redux->rpr_get_option('disable_user_message_registered'))
                        $register_plus_redux->send_welcome_user_mail($user_id, $plaintext_pass);
                    if ($register_plus_redux->rpr_get_option('admin_message_when_verified'))
                        $register_plus_redux->send_admin_mail($user_id, $plaintext_pass);
                    delete_user_meta($user_id, 'email_verification_code');
                    delete_user_meta($user_id, 'email_verification_sent');
                    delete_user_meta($user_id, 'email_verified');
                    delete_user_meta($user_id, 'stored_user_password');
                    $_REQUEST['completed'] = 'approved_user';
                }
            }
            if ((isset($_POST['action']) && 'approve_users' === $_POST['action']) || isset($_POST['approve_users'])) {
                check_admin_referer('register-plus-redux-unverified-users');
                if (!empty($_POST['users']) && is_array($_POST['users'])) {
                    foreach ($_POST['users'] as $id) {
                        $user_id = (int) $id;
                        if (current_user_can('promote_user', $user_id)) {
                            $plaintext_pass = get_user_meta($user_id, 'stored_user_password', true);
                            $user = get_userdata($user_id);
                            if (!is_multisite()) {
                                $user->set_role((string) get_option('default_role'));
                            }
                            else {
                                $user->remove_role('rpr_unverified');
                            }
                            if (empty($plaintext_pass)) {
                                $plaintext_pass = wp_generate_password();
                                update_user_option($user_id, 'default_password_nag', true, true);
                                wp_set_password($plaintext_pass, $user_id);
                            }
                            do_action('rpr_signup_complete', $user_id);
                            if (!$register_plus_redux->rpr_get_option('disable_user_message_registered'))
                                $register_plus_redux->send_welcome_user_mail($user_id, $plaintext_pass);
                            if ($register_plus_redux->rpr_get_option('admin_message_when_verified'))
                                $register_plus_redux->send_admin_mail($user_id, $plaintext_pass);
                            delete_user_meta($user_id, 'email_verification_code');
                            delete_user_meta($user_id, 'email_verification_sent');
                            delete_user_meta($user_id, 'email_verified');
                            delete_user_meta($user_id, 'stored_user_password');
                            $_REQUEST['completed'] = 'approved_users';
                        }
                    }
                }
            }
            if (isset($_GET['action']) && 'send_verification_email' === $_GET['action'] && isset($_GET['user_id'])) {
                check_admin_referer('register-plus-redux-unverified-users');
                $user_id = (int) $_GET['user_id'];
                $verification_code = wp_generate_password(20, false);
                update_user_meta($user_id, 'email_verification_code', $verification_code);
                update_user_meta($user_id, 'email_verification_sent', gmdate('Y-m-d H:i:s'));
                $register_plus_redux->send_verification_mail($user_id, $verification_code);
                $_REQUEST['completed'] = 'sent_verification_email';
            }
            if ((isset($_POST['action']) && 'send_verification_emails' === $_POST['action']) || isset($_POST['send_verification_emails'])) {
                check_admin_referer('register-plus-redux-unverified-users');
                if (!empty($_POST['users']) && is_array($_POST['users'])) {
                    foreach ($_POST['users'] as $id) {
                        $user_id = (int)$id;
                        $verification_code = wp_generate_password(20, false);
                        update_user_meta($user_id, 'email_verification_code', $verification_code);
                        update_user_meta($user_id, 'email_verification_sent', gmdate('Y-m-d H:i:s'));
                        $register_plus_redux->send_verification_mail($user_id, $verification_code);
                        $_REQUEST['completed'] = 'sent_verification_emails';
                    }
                }
            }
            if (isset($_GET['action']) && 'delete_user' === $_GET['action'] && isset($_GET['user_id'])) {
                check_admin_referer('register-plus-redux-unverified-users');
                // necessary for wp_delete_user to function
                if (!function_exists('wp_delete_user')) require_once(ABSPATH . '/wp-admin/includes/user.php');
                if (current_user_can('delete_user', (int) $_GET['user_id'])) { 
                    wp_delete_user((int) $_GET['user_id']);
                    $_REQUEST['completed'] = 'deleted_user';
                }
                // TODO: Odd bug, if unverified users exist, page exists, if from page all unverified users are deleted, on the post back page won't have any reason to exist anymore, need a redirect in that case
            }
            if ((isset($_POST['action']) && 'delete_users' === $_POST['action']) || isset($_POST['delete_users'])) {
                check_admin_referer('register-plus-redux-unverified-users');
                if (!empty($_POST['users']) && is_array($_POST['users'])) {
                    // necessary for wp_delete_user to function
                    if (!function_exists('wp_delete_user')) require_once(ABSPATH . '/wp-admin/includes/user.php');
                    foreach ($_POST['users'] as $id) {
                        $user_id = (int) $id;
                        if (current_user_can('delete_user', $user_id)) { 
                            wp_delete_user($user_id);
                            $_REQUEST['completed'] = 'deleted_users';
                        }
                    }
                    // TODO: Odd bug, if unverified users exist, page exists, if from page all unverified users are deleted, on the post back page won't have any reason to exist anymore, need a redirect in that case
                }
            }
            if (!empty($_REQUEST['completed'])) {
                switch((string) $_REQUEST['completed']) {
                    case 'approved_user':
                        echo '<div id="message" class="updated"><p>', __('User approved.', 'register-plus-redux'), '</p></div>';
                        break;
                    case 'approved_users':
                        echo '<div id="message" class="updated"><p>', __('Users approved.', 'register-plus-redux'), '</p></div>';
                        break;
                    case 'sent_verification_email':
                        echo '<div id="message" class="updated"><p>', __('Verification email sent.', 'register-plus-redux'), '</p></div>';
                        break;
                    case 'sent_verification_emails':
                        echo '<div id="message" class="updated"><p>', __('Verification emails sent.', 'register-plus-redux'), '</p></div>';
                        break;
                    case 'deleted_user':
                        echo '<div id="message" class="updated"><p>', __('User deleted.', 'register-plus-redux'), '</p></div>';
                        break;
                    case 'deleted_users':
                        echo '<div id="message" class="updated"><p>', __('Users deleted.', 'register-plus-redux'), '</p></div>';
                        break;
                    default:
                }
            }
            ?>
            <div class="wrap">
                <h2><?= __('Unverified Users', 'register-plus-redux') ?></h2>
                <form id="verify-filter" method="post">
                <?php wp_nonce_field('register-plus-redux-unverified-users'); ?>
                <div class="tablenav">
                    <div class="alignleft actions">
                        <select name="action">
                            <option value="" selected="selected"><?= __('Bulk Actions', 'register-plus-redux') ?></option>
                            <?php if (current_user_can('promote_users')) echo '<option value="approve_users">', __('Approve', 'register-plus-redux'), '</option>', PHP_EOL; ?>
                            <option value="send_verification_emails"><?= __('Send E-mail Verification', 'register-plus-redux') ?></option>
                            <?php if (current_user_can('delete_users')) echo '<option value="delete_users">', __('Delete', 'register-plus-redux'), '</option>', PHP_EOL; ?>
                        </select>
                        <input type="submit" value="<?php esc_attr_e('Apply', 'register-plus-redux'); ?>" name="doaction" id="doaction" class="button-secondary action">
                    </div>
                    <br class="clear">
                </div>
                <table class="widefat fixed" id="registration-table">
                    <thead>
                        <tr class="thead">
                            <th scope="col" id="cb" class="manage-column column-cb check-column">
                                <input type="checkbox">
                            </th>
                            <th scope="col" id="username" class="manage-column column-username">
                                <?= __('Username', 'register-plus-redux') ?>
                            </th>
                            <th scope="col" id="email" class="manage-column column-email">
                                <?= __('E-mail', 'register-plus-redux') ?>
                            </th>
                            <th scope="col" id="registered" class="manage-column column-registered">
                                <?= __('Registered', 'register-plus-redux') ?>
                            </th>
                            <th scope="col" id="verification_sent" class="manage-column column-verification_sent">
                                <?= __('Verification Sent', 'register-plus-redux') ?>
                            </th>
                            <th scope="col" id="verified" class="manage-column column-verified">
                                <?= __('Verified', 'register-plus-redux') ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="users" class="list:user user-list">
                        <?php 
                        $user_query = new WP_User_Query(array('role' => 'rpr_unverified'));
                        if (!empty($user_query->results)) {
                            $style = '';
                            foreach ($user_query->results as $user) {
                                $style = ($style == ' class="alternate"') ? '' : ' class="alternate"';
                                ?>
                                <tr id="user-<?= $user->ID ?>"<?= $style ?>>
                                    <th scope="row" class="check-column"><input type="checkbox" name="users[]" id="user_<?= $user->ID ?>" name="user_<?= $user->ID ?>" value="<?= $user->ID ?>"></th>
                                    <td class="username column-username">
                                        <strong><?php if (current_user_can('edit_users'))    echo                         '<a href="', esc_url(     add_query_arg(array(                                                                     'user_id' => $user->ID, 'wp_http_referer' => urlencode(stripslashes($_SERVER['REQUEST_URI']))), 'user-edit.php')                                       ), '"                     >', $user->user_login,                    '</a>'; else echo $user->user_login; ?></strong><br>
                                        <div class="row-actions">
                                            <?php if (current_user_can('promote_users')) echo '<span class="edit">     <a href="', wp_nonce_url(add_query_arg(array('page' => 'unverified-users', 'action' => 'approve_user',            'user_id' => $user->ID, 'wp_http_referer' => urlencode(stripslashes($_SERVER['REQUEST_URI']))), 'users.php'),    'register-plus-redux-unverified-users'), '"                     >', __('Approve', 'register-plus-redux'),           '</a></span> | ', "\n"; ?>
                                            <?php                                            echo '<span class="edit">     <a href="', wp_nonce_url(add_query_arg(array('page' => 'unverified-users', 'action' => 'send_verification_email', 'user_id' => $user->ID, 'wp_http_referer' => urlencode(stripslashes($_SERVER['REQUEST_URI']))), 'users.php'),    'register-plus-redux-unverified-users'), '"                     >', __('Send Verification', 'register-plus-redux'), '</a></span>   ', "\n"; ?>
                                            <?php if (current_user_can('delete_users'))  echo '<span class="delete"> | <a href="', wp_nonce_url(add_query_arg(array('page' => 'unverified-users', 'action' => 'delete_user',             'user_id' => $user->ID, 'wp_http_referer' => urlencode(stripslashes($_SERVER['REQUEST_URI']))), 'users.php'),    'register-plus-redux-unverified-users'), '" class="submitdelete">', __('Delete', 'register-plus-redux'),            '</a></span>   ', "\n"; ?>
                                        </div>
                                    </td>
                                    <td class="email column-email"><a href="mailto:<?= $user->user_email ?>" title="<?php esc_attr_e('E-mail: ', 'register-plus-redux'); echo $user->user_email; ?>"><?= $user->user_email ?></a></td>
                                    <td><?= $user->user_registered ?></td>
                                    <td><?= $user->email_verification_sent ?></td>
                                    <td><?= $user->email_verified ?></td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
                <div class="tablenav">
                    <div class="alignleft actions">
                        <?php if (current_user_can('promote_users')) echo       '<input type="submit" value="',    esc_attr__('Approve Selected Users',                     'register-plus-redux'),  '" name="approve_users"            class="button-secondary action">&nbsp;', "\n"; ?>
                                                                                         <input type="submit" value="<?php esc_attr_e('Send E-mail Verification to Selected Users', 'register-plus-redux'); ?>" name="send_verification_emails" class="button-secondary action">
                        <?php if (current_user_can('delete_users'))  echo '&nbsp;<input type="submit" value="',    esc_attr__('Delete Selected Users',                      'register-plus-redux'),  '" name="delete_users"             class="button-secondary action">',       "\n"; ?>
                    </div>
                    <br class="clear">
                </div>
                </form>
            </div>
            <br class="clear">
            <?php
        }

        /**
         * @return void
         */
        public function update_settings(): void
        {
            global $register_plus_redux;
            $options = array();
            $redux_usermeta = array();
            $_POST = stripslashes_deep($_POST);

            if (isset($_POST['custom_logo_url']) && !isset($_POST['remove_logo'])) $options['custom_logo_url'] = esc_url_raw((string) $_POST['custom_logo_url']);
            $options['verify_user_email'] = isset($_POST['verify_user_email']) ? '1' : '0';
            $options['message_verify_user_email'] = isset($_POST['message_verify_user_email']) ? wp_kses_post((string) $_POST['message_verify_user_email']) : '';
            $options['verify_user_admin'] = isset($_POST['verify_user_admin']) ? '1' : '0';
            $options['message_verify_user_admin'] = isset($_POST['message_verify_user_admin']) ? wp_kses_post((string) $_POST['message_verify_user_admin']) : '';
            $options['delete_unverified_users_after'] = isset($_POST['delete_unverified_users_after']) ? absint((string) $_POST['delete_unverified_users_after']) : '0';
            $options['registration_redirect_url'] = isset($_POST['registration_redirect_url']) ? esc_url_raw((string) $_POST['registration_redirect_url']) : '';
            $options['verification_redirect_url'] = isset($_POST['verification_redirect_url']) ? esc_url_raw((string) $_POST['verification_redirect_url']) : '';
            $options['autologin_user'] = isset($_POST['autologin_user']) ? '1' : '0';
            $options['username_is_email'] = isset($_POST['username_is_email']) ? '1' : '0';
            $options['double_check_email'] = isset($_POST['double_check_email']) ? '1' : '0';
            if (isset($_POST['show_fields']) && is_array($_POST['show_fields'])) $options['show_fields'] = $_POST['show_fields'];
            if (isset($_POST['required_fields']) && is_array($_POST['required_fields'])) $options['required_fields'] = $_POST['required_fields'];
            $options['user_set_password'] = isset($_POST['user_set_password']) ? '1' : '0';
            $options['min_password_length'] = isset($_POST['min_password_length']) ? absint($_POST['min_password_length']) : 0;
            $options['disable_password_confirmation'] = isset($_POST['disable_password_confirmation']) ? '1' : '0';
            $options['show_password_meter'] = isset($_POST['show_password_meter']) ? '1' : '0';
            $options['message_empty_password'] = isset($_POST['message_empty_password']) ? wp_kses_data((string) $_POST['message_empty_password']) : '';
            $options['message_short_password'] = isset($_POST['message_short_password']) ? wp_kses_data((string) $_POST['message_short_password']) : '';
            $options['message_bad_password'] = isset($_POST['message_bad_password']) ? wp_kses_data((string) $_POST['message_bad_password']) : '';
            $options['message_good_password'] = isset($_POST['message_good_password']) ? wp_kses_data((string) $_POST['message_good_password']) : '';
            $options['message_strong_password'] = isset($_POST['message_strong_password']) ? wp_kses_data((string) $_POST['message_strong_password']) : '';
            $options['message_mismatch_password'] = isset($_POST['message_mismatch_password']) ? wp_kses_data((string) $_POST['message_mismatch_password']) : '';
            $options['enable_invitation_code'] = isset($_POST['enable_invitation_code']) ? '1' : '0';
            if (isset($_POST['invitation_code_bank']) && is_array($_POST['invitation_code_bank'])) $invitation_code_bank = $_POST['invitation_code_bank'];
            $options['require_invitation_code'] = isset($_POST['require_invitation_code']) ? '1' : '0';
            $options['invitation_code_case_sensitive'] = isset($_POST['invitation_code_case_sensitive']) ? '1' : '0';
            $options['invitation_code_unique'] = isset($_POST['invitation_code_unique']) ? '1' : '0';
            $options['enable_invitation_tracking_widget'] = isset($_POST['enable_invitation_tracking_widget']) ? '1' : '0';
            $options['show_disclaimer'] = isset($_POST['show_disclaimer']) ? '1' : '0';
            $options['message_disclaimer_title'] = isset($_POST['message_disclaimer_title']) ? sanitize_text_field((string) $_POST['message_disclaimer_title']) : '';
            $options['message_disclaimer'] = isset($_POST['message_disclaimer']) ? wp_kses_post((string) $_POST['message_disclaimer']) : '';
            $options['require_disclaimer_agree'] = isset($_POST['require_disclaimer_agree']) ? '1' : '0';
            $options['message_disclaimer_agree'] = isset($_POST['message_disclaimer_agree']) ? sanitize_text_field((string) $_POST['message_disclaimer_agree']) : '';
            $options['show_license'] = isset($_POST['show_license']) ? '1' : '0';
            $options['message_license_title'] = isset($_POST['message_license_title']) ? sanitize_text_field((string) $_POST['message_license_title']) : '';
            $options['message_license'] = isset($_POST['message_license']) ? wp_kses_post((string) $_POST['message_license']) : '';
            $options['require_license_agree'] = isset($_POST['require_license_agree']) ? '1' : '0';
            $options['message_license_agree'] = isset($_POST['message_license_agree']) ? sanitize_text_field((string) $_POST['message_license_agree']) : '';
            $options['show_privacy_policy'] = isset($_POST['show_privacy_policy']) ? '1' : '0';
            $options['message_privacy_policy_title'] = isset($_POST['message_privacy_policy_title']) ? sanitize_text_field((string) $_POST['message_privacy_policy_title']) : '';
            $options['message_privacy_policy'] = isset($_POST['message_privacy_policy']) ? wp_kses_post((string) $_POST['message_privacy_policy']) : '';
            $options['require_privacy_policy_agree'] = isset($_POST['require_privacy_policy_agree']) ? '1' : '0';
            $options['message_privacy_policy_agree'] = isset($_POST['message_privacy_policy_agree']) ? sanitize_text_field((string) $_POST['message_privacy_policy_agree']) : '';
            $options['default_css'] = isset($_POST['default_css']) ? '1' : '0';
            $options['required_fields_style'] = '';

            if (isset($_POST['required_fields_style'])) {
                // Stolen from Jetpack 2.0.4 custom-css.php Jetpack_Custom_CSS::filter_attr()
                require_once('csstidy/class.csstidy.php');
                $csstidy = new csstidy();
                $csstidy->set_cfg('remove_bslash', false);
                $csstidy->set_cfg('compress_colors', false);
                $csstidy->set_cfg('compress_font-weight', false);
                $csstidy->set_cfg('discard_invalid_properties', true);
                $csstidy->set_cfg('merge_selectors', false);
                $csstidy->set_cfg('remove_last_;', false);
                $csstidy->set_cfg('css_level', 'CSS3.0');
                $required_fields_style = 'div {' . $_POST['required_fields_style'] . '}';
                $required_fields_style = preg_replace('/\\\\([0-9a-fA-F]{4})/', '\\\\\\\\$1', $required_fields_style);
                $required_fields_style = wp_kses_split($required_fields_style, array(), array());
                $csstidy->parse($required_fields_style);
                $required_fields_style = $csstidy->print->plain();
                $required_fields_style = str_replace(array("\n", "\r", "\t"), '', $required_fields_style);
                preg_match("/^div\s*{(.*)}\s*$/", $required_fields_style, $matches);
                if (!empty($matches[1])) $options['required_fields_style'] = $matches[1];
            }

            $options['required_fields_asterisk'] = isset($_POST['required_fields_asterisk']) ? '1' : '0';
            $options['starting_tabindex'] = isset($_POST['starting_tabindex']) ? absint($_POST['starting_tabindex']) : 0;
            $options['min_expected_seconds_to_register'] = $_POST['min_expected_seconds_to_register'] ?? 0;
            $options['domain_blacklist'] = isset($_POST['domain_blacklist']) ? sanitize_text_field($_POST['domain_blacklist']) : '';
            $options['disable_user_message_registered'] = isset($_POST['disable_user_message_registered']) ? '1' : '0';
            $options['disable_user_message_created'] = isset($_POST['disable_user_message_created']) ? '1' : '0';
            $options['custom_user_message'] = isset($_POST['custom_user_message']) ? '1' : '0';
            $options['user_message_from_email'] = isset($_POST['user_message_from_email']) ? sanitize_text_field((string) $_POST['user_message_from_email']) : '';
            $options['user_message_from_name'] = isset($_POST['user_message_from_name']) ? sanitize_text_field((string) $_POST['user_message_from_name']) : '';
            $options['user_message_subject'] = isset($_POST['user_message_subject']) ? sanitize_text_field((string) $_POST['user_message_subject']) : '';
            $options['user_message_body'] = isset($_POST['user_message_body']) ? wp_kses_post((string) $_POST['user_message_body']) : '';
            $options['send_user_message_in_html'] = isset($_POST['send_user_message_in_html']) ? '1' : '0';
            $options['user_message_newline_as_br'] = isset($_POST['user_message_newline_as_br']) ? '1' : '0';
            $options['custom_verification_message'] = isset($_POST['custom_verification_message']) ? '1' : '0';
            $options['verification_message_from_email'] = isset($_POST['verification_message_from_email']) ? sanitize_text_field((string) $_POST['verification_message_from_email']) : '';
            $options['verification_message_from_name'] = isset($_POST['verification_message_from_name']) ? sanitize_text_field((string) $_POST['verification_message_from_name']) : '';
            $options['verification_message_subject'] = isset($_POST['verification_message_subject']) ? sanitize_text_field((string) $_POST['verification_message_subject']) : '';
            $options['verification_message_body'] = isset($_POST['verification_message_body']) ? wp_kses_post((string) $_POST['verification_message_body']) : '';
            $options['send_verification_message_in_html'] = isset($_POST['send_verification_message_in_html']) ? '1' : '0';
            $options['verification_message_newline_as_br'] = isset($_POST['verification_message_newline_as_br']) ? '1' : '0';
            $options['disable_admin_message_registered'] = isset($_POST['disable_admin_message_registered']) ? '1' : '0';
            $options['disable_admin_message_created'] = isset($_POST['disable_admin_message_created']) ? '1' : '0';
            $options['admin_message_when_verified'] = isset($_POST['admin_message_when_verified']) ? '1' : '0';
            $options['custom_admin_message'] = isset($_POST['custom_admin_message']) ? '1' : '0';
            $options['admin_message_from_email'] = isset($_POST['admin_message_from_email']) ? sanitize_text_field((string) $_POST['admin_message_from_email']) : '';
            $options['admin_message_from_name'] = isset($_POST['admin_message_from_name']) ? sanitize_text_field((string) $_POST['admin_message_from_name']) : '';
            $options['admin_message_subject'] = isset($_POST['admin_message_subject']) ? sanitize_text_field((string) $_POST['admin_message_subject']) : '';
            $options['admin_message_body'] = isset($_POST['admin_message_body']) ? wp_kses_post((string) $_POST['admin_message_body']) : '';
            $options['send_admin_message_in_html'] = isset($_POST['send_admin_message_in_html']) ? '1' : '0';
            $options['admin_message_newline_as_br'] = isset($_POST['admin_message_newline_as_br']) ? '1' : '0';

            $options['custom_registration_page_css'] = '';
            if (isset($_POST['custom_registration_page_css'])) {
                // Stolen from Jetpack 2.0.4 custom-css.php Jetpack_Custom_CSS::init()
                // Updated to Jetpack 11.9 custom-css.php Jetpack_Custom_CSS_Enhancements::sanitize_css()
                require_once('csstidy/class.csstidy.php');
                $csstidy = new csstidy();
                $csstidy->set_cfg('remove_bslash', false);
                $csstidy->set_cfg('compress_colors', false);
                $csstidy->set_cfg('compress_font-weight', false);
                $csstidy->set_cfg('optimise_shorthands', 0);
                $csstidy->set_cfg('remove_last_;', false);
                $csstidy->set_cfg('case_properties', false);
                $csstidy->set_cfg('discard_invalid_properties', true);
                $csstidy->set_cfg('css_level', 'CSS3.0');
                $csstidy->set_cfg('preserve_css', true);
                $csstidy->set_cfg('template', dirname(__FILE__) . '/csstidy/wordpress-standard.tpl');
                $custom_registration_page_css = (string) $_POST['custom_registration_page_css'];
                $custom_registration_page_css = preg_replace('/\\\\([0-9a-fA-F]{4})/', '\\\\\\\\$1', $custom_registration_page_css);
                $custom_registration_page_css = str_replace(array('\'\\\\', '"\\\\'), array('\'\\', '"\\'), $custom_registration_page_css);
                $custom_registration_page_css = str_replace('<=', '&lt;=', $custom_registration_page_css);
                $custom_registration_page_css = wp_kses_split($custom_registration_page_css, array(), array());
                $custom_registration_page_css = str_replace('&gt;', '>', $custom_registration_page_css);
                $custom_registration_page_css = strip_tags($custom_registration_page_css);
                $csstidy->parse($custom_registration_page_css);
                $options['custom_registration_page_css'] = $csstidy->print->plain();
            }

            $options['custom_login_page_css'] = '';

            if (isset($_POST['custom_login_page_css'])) {
                // Stolen from Jetpack 2.0.4 custom-css.php Jetpack_Custom_CSS::init()
                require_once('csstidy/class.csstidy.php');
                $csstidy = new csstidy();
                $csstidy->set_cfg('remove_bslash', false);
                $csstidy->set_cfg('compress_colors', false);
                $csstidy->set_cfg('compress_font-weight', false);
                $csstidy->set_cfg('optimise_shorthands', 0);
                $csstidy->set_cfg('remove_last_;', false);
                $csstidy->set_cfg('case_properties', false);
                $csstidy->set_cfg('discard_invalid_properties', true);
                $csstidy->set_cfg('css_level', 'CSS3.0');
                $csstidy->set_cfg('preserve_css', true);
                $csstidy->set_cfg('template', dirname(__FILE__) . '/csstidy/wordpress-standard.tpl');
                $custom_login_page_css = (string) $_POST['custom_login_page_css'];
                $custom_login_page_css = preg_replace('/\\\\([0-9a-fA-F]{4})/', '\\\\\\\\$1', $custom_login_page_css);
                $custom_login_page_css = str_replace('<=', '&lt;=', $custom_login_page_css);
                $custom_login_page_css = wp_kses_split($custom_login_page_css, array(), array());
                $custom_login_page_css = str_replace('&gt;', '>', $custom_login_page_css);
                $custom_login_page_css = strip_tags($custom_login_page_css);
                $csstidy->parse($custom_login_page_css);
                $options['custom_login_page_css'] = $csstidy->print->plain();
            }
             
            if (isset($_POST['label'])) {
                foreach ((array) $_POST['label'] as $index => $v) {
                    $meta_field = array();
                    if (!empty($_POST['label'][$index])) {
                        $meta_field['label'] = sanitize_text_field((string) $_POST['label'][$index]);
                        $meta_field['meta_key'] = isset($_POST['meta_key'][$index]) ? sanitize_text_field((string) $_POST['meta_key'][$index]) : '';
                        $meta_field['display'] = isset($_POST['display'][$index]) ? sanitize_text_field((string) $_POST['display'][$index]) : '';
                        $meta_field['options'] = '';
                        if (isset($_POST['options'][$index])) {
                            if (in_array($meta_field['display'], array('checkbox', 'radio', 'select'))) {
                                $field_options = explode(',', (string) $_POST['options'][$index]);
                                foreach ($field_options as &$field_option) {
                                    $field_option = sanitize_text_field($field_option);
                                }
                                $meta_field['options'] = implode(',', $field_options);
                            }
                            else {
                                $meta_field['options'] = sanitize_text_field((string) $_POST['options'][$index]);
                            }
                        }
                        $meta_field['escape_url'] = '0';
                        $meta_field['show_on_profile'] = isset($_POST['show_on_profile'][$index]) ? '1' : '0';
                        $meta_field['show_on_registration'] = isset($_POST['show_on_registration'][$index]) ? '1' : '0';
                        $meta_field['require_on_registration'] = isset($_POST['require_on_registration'][$index]) ? '1' : '0';
                        $meta_field['show_datepicker'] = isset($_POST['show_datepicker'][$index]) ? '1' : '0';
                        $meta_field['terms_content'] = isset($_POST['terms_content'][$index]) ? wp_kses_post((string) $_POST['terms_content'][$index]) : '';
                        $meta_field['terms_agreement_text'] = isset($_POST['terms_agreement_text'][$index]) ? wp_kses_post((string) $_POST['terms_agreement_text'][$index]) : '';
                        $meta_field['date_revised'] = isset($_POST['date_revised'][$index]) ? strtotime ((string) $_POST['date_revised'][$index]) : time();
                        if (empty($meta_field['meta_key'])) {
                            $meta_field['meta_key'] = 'rpr_' . Register_Plus_Redux::sanitize_text($meta_field['label']);
                        }
                    }
                    $redux_usermeta[] = $meta_field;
                }
            }

            if (isset($_POST['newMetaFields'])) {
                foreach ((array) $_POST['newMetaFields'] as $label) {
                    $meta_field = array();
                    $meta_field['label'] = sanitize_text_field($label);
                    $meta_field['meta_key'] = 'rpr_' . Register_Plus_Redux::sanitize_text($meta_field['label']);
                    $meta_field['display'] = '';
                    $meta_field['options'] = '';
                    $meta_field['escape_url'] = '0';
                    $meta_field['show_on_profile'] = '0';
                    $meta_field['show_on_registration'] = '0';
                    $meta_field['require_on_registration'] = '0';
                    $meta_field['show_datepicker'] = '0';
                    $meta_field['terms_content'] = '';
                    $meta_field['terms_agreement_text'] = '';
                    $meta_field['date_revised'] = time();
                    $redux_usermeta[] = $meta_field;
                }
            }

            $register_plus_redux->rpr_update_options($options);
            if (!empty($invitation_code_bank)) update_option('register_plus_redux_invitation_code_bank-rv1', $invitation_code_bank);
            if (!empty($redux_usermeta)) update_option('register_plus_redux_usermeta-rv2', $redux_usermeta);
        }
    }
}

if (class_exists('RPR_Admin_Menu')) $rpr_admin_menu = new RPR_Admin_Menu();
