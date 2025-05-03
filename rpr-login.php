<?php

use JetBrains\PhpStorm\NoReturn;

if (!class_exists('RPR_Login')) {
    class RPR_Login {
        /**
         * Constructor
         */
        public function __construct()
        {
            add_action('login_enqueue_scripts', array($this, 'rpr_login_enqueue_scripts'), 10, 0);

            add_filter('authenticate', array($this, 'rpr_authenticate'), 100, 3);
            add_filter('allow_password_reset', array($this, 'rpr_filter_allow_password_reset'), 10, 2);

            add_action('init', array($this, 'rpr_login_init'), 10, 1);
            add_filter('random_password', array($this, 'rpr_login_filter_random_password'), 10, 1); // Replace random password with user set password
            add_filter('update_user_metadata', array($this, 'rpr_filter_update_user_metadata'), 10, 5);

            add_action('register_form', array($this, 'rpr_register_form'), 9, 0); // Higher priority to avoid getting bumped by other plugins
            add_filter('registration_errors', array($this, 'rpr_registration_errors'), 10, 3); // applied to the list of registration errors generated while registering a user for a new account.
            add_action('login_form_verifyemail', array($this, 'rpr_login_form_verifyemail'), 10, 0);
            add_action('login_form_emailverify', array($this, 'rpr_login_form_emailverify'), 10, 0);
            add_action('login_form_adminverify', array($this, 'rpr_login_form_adminverify'), 10, 0);

            add_action('user_register', array($this, 'rpr_user_register'), 10, 1); // Runs when a user's profile is first created. Action function argument: user ID.
            add_filter('registration_redirect', array($this, 'rpr_filter_registration_redirect'), 10, 1);

            add_action('login_head', array($this, 'rpr_login_head'), 10, 0); // Print CSS
            add_action('login_footer', array($this, 'rpr_login_footer'), 10, 0); // Print scripts
            add_filter('login_headerurl', array($this, 'rpr_filter_login_headerurl'), 10, 1); // Modify url to point to site
            add_filter('login_headertext', array($this, 'rpr_filter_login_headertext'), 10, 1); // Modify header to blogname
        }

        /**
         * @return void
         */
        public function rpr_login_enqueue_scripts(): void
        {
            if (isset($_GET['action']) && 'register' === $_GET['action']) {
                global $register_plus_redux;
                $enqueue_jquery = false;
                if (isset($_REQUEST['user_login']) || isset($_REQUEST['user_email'])) { $enqueue_jquery = TRUE; }
                if ('1' === $register_plus_redux->rpr_get_option('default_css')) { $enqueue_jquery = TRUE; }
                if ('1' === $register_plus_redux->rpr_get_option('required_fields_asterisk')) { $enqueue_jquery = TRUE; }
                if ('1' === $register_plus_redux->rpr_get_option('user_set_password') && '1' === $register_plus_redux->rpr_get_option('show_password_meter')) { $enqueue_jquery = TRUE; }
                if ($enqueue_jquery) wp_enqueue_script('jquery');

                $redux_usermeta = get_option('register_plus_redux_usermeta-rv2');
                if (is_array($redux_usermeta)) {
                    foreach ($redux_usermeta as $meta_field) {
                        if ('1' === $meta_field['show_on_registration'] && '1' === $meta_field['show_datepicker']) {
                            wp_enqueue_style('jquery-ui-style', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.10.1/themes/ui-lightness/jquery-ui.css', false);
                            wp_enqueue_script('jquery-ui-datepicker');
                            break;
                        }
                    }
                }
                wp_enqueue_style('register-plus-redux', plugin_dir_url(__FILE__) . 'css/rpr-login.css', [], RPR_VERSION);
            }
        }

        /**
         * @param object $user
         * @param string $username
         * @param string $password
         * @return object|null
         */
        public function rpr_authenticate(object $user, string $username, string $password): object|null
        {
            if (!empty($user) && !is_wp_error($user)) {
                if (null !== get_role('rpr_unverified') && in_array('rpr_unverified', $user->roles)) {
                    return null;
                }
            }
            return $user;
        }

        /**
         * @param bool $allow
         * @param int $user_id
         * @return bool
         */
        public function rpr_filter_allow_password_reset(bool $allow, int $user_id): bool
        {
            $user = get_userdata($user_id);
            if (!empty($user) && !is_wp_error($user)) {
                if (null !== get_role('rpr_unverified') && in_array('rpr_unverified', $user->roles)) {
                    return false;
                }
            }
            return $allow;
        }

        /**
         * WordPress quirk, following registration add action for later processing
         * @return void
         */
        public function rpr_login_init(): void
        {
            global $register_plus_redux;
            global $pagenow;

            if ($pagenow === 'wp-login.php' && isset($_GET['checkemail']) && ('registered' === $_GET['checkemail'])) {
                if ('1' === $register_plus_redux->rpr_get_option('verify_user_email')) {
                    $_REQUEST['action'] = 'emailverify';
                }
                elseif ('1' === $register_plus_redux->rpr_get_option('verify_user_admin')) {
                    $_REQUEST['action'] = 'adminverify';
                }

            }
        }

        /**
         * @param string $password
         * @return string
         */
        public function rpr_login_filter_random_password(string $password): string
        {
            global $register_plus_redux;
            global $pagenow;

            if ('wp-login.php' === $pagenow && '1' === $register_plus_redux->rpr_get_option('user_set_password')) {
                if (isset($_REQUEST['action']) && 'register' === $_REQUEST['action']) {
                    if (isset($_POST['pass1'])) {
                        $password = sanitize_text_field((string) $_POST['pass1']);
                        // Stowe password in $_REQUEST to allow random password generator to continue while preserving for user_register action
                        $_REQUEST['password'] = $password;
                        unset($_POST['pass1']);
                    }
                }
            }
            return $password;
        }

        /**
         * @param bool|null $return
         * @param int $object_id
         * @param string $meta_key
         * @param $meta_value
         * @param $prev_value
         * @return bool|null
         */
        public function rpr_filter_update_user_metadata(bool|null $return, int $object_id, string $meta_key, $meta_value, $prev_value): bool|null
        {
            global $register_plus_redux;
            global $pagenow;

            if ('default_password_nag' === $meta_key && 'wp-login.php' === $pagenow && '1' === $register_plus_redux->rpr_get_option('user_set_password')) {
                if (isset($_REQUEST['action']) && 'register' === $_REQUEST['action']) {
                    if (isset($_POST['pass1'])) {
                        return false;
                    }
                }
            }

            return $return;
        }

        /**
         * @return void
         */
        public function rpr_register_form(): void
        {
            global $register_plus_redux;

            $terms_exist = false;
            $_REQUEST = stripslashes_deep($_REQUEST);
            $min_expected_seconds_to_register = absint($register_plus_redux->rpr_get_option('min_expected_seconds_to_register'));
            if (!is_numeric($min_expected_seconds_to_register) || $min_expected_seconds_to_register < 1) $min_expected_seconds_to_register = 0;
            if ($min_expected_seconds_to_register > 0) {
                $now = new DateTimeImmutable();
                echo PHP_EOL, '<input type="hidden" id="registration_timestamp" name="registration_timestamp" value="', $now->getTimestamp(), '">';
            }
            $tabindex = absint($register_plus_redux->rpr_get_option('starting_tabindex'));
            if (!is_numeric($tabindex) || $tabindex < 1) $tabindex = 0;
            if ('1' === $register_plus_redux->rpr_get_option('double_check_email')) {
                $user_email2 = isset($_REQUEST['user_email2']) ? (string) $_REQUEST['user_email2'] : '';
                echo PHP_EOL, '<p id="user_email2-p"><label id="user_email2-label" for="user_email2">';
                if ($register_plus_redux->rpr_get_option('required_fields_asterisk')) echo '*';
                echo __('Confirm E-mail', 'register-plus-redux'), '<br><input type="text" autocomplete="off" name="user_email2" id="user_email2" class="input" value="', esc_attr($user_email2), '"';
                if (0 !== $tabindex) echo ' tabindex="', $tabindex++, '" ';
                echo '></label></p>';
            }
            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('first_name', $register_plus_redux->rpr_get_option('show_fields'))) {
                $first_name = isset($_REQUEST['first_name']) ? (string) $_REQUEST['first_name'] : '';
                echo PHP_EOL, '<p id="first_name-p"><label id="first_name-label" for="first_name">';
                if ('1' === $register_plus_redux->rpr_get_option('required_fields_asterisk') && is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('first_name', $register_plus_redux->rpr_get_option('required_fields'))) echo '*';
                echo __('First Name', 'register-plus-redux'), '<br><input type="text" name="first_name" id="first_name" class="input" value="', esc_attr($first_name), '"';
                if (0 !== $tabindex) echo ' tabindex="', $tabindex++, '" ';
                echo '></label></p>';
            }
            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('last_name', $register_plus_redux->rpr_get_option('show_fields'))) {
                $last_name = isset($_REQUEST['last_name']) ? (string) $_REQUEST['last_name'] : '';
                echo PHP_EOL, '<p id="last_name-p"><label id="last_name-label" for="last_name">';
                if ('1' === $register_plus_redux->rpr_get_option('required_fields_asterisk') && is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('last_name', $register_plus_redux->rpr_get_option('required_fields'))) echo '*';
                echo __('Last Name', 'register-plus-redux'), '<br><input type="text" name="last_name" id="last_name" class="input" value="', esc_attr($last_name), '"';
                if (0 !== $tabindex) echo ' tabindex="', $tabindex++, '" ';
                echo '></label></p>';
            }
            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('user_url', $register_plus_redux->rpr_get_option('show_fields'))) {
                $user_url = isset($_REQUEST['user_url']) ? (string) $_REQUEST['user_url'] : '';
                echo PHP_EOL, '<p id="user_url-p"><label id="user_url-label" for="user_url">';
                if ('1' === $register_plus_redux->rpr_get_option('required_fields_asterisk') && is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('user_url', $register_plus_redux->rpr_get_option('required_fields'))) echo '*';
                echo __('Website', 'register-plus-redux'), '<br><input type="text" name="user_url" id="user_url" class="input" value="', esc_attr($user_url), '"';
                if (0 !== $tabindex) echo ' tabindex="', $tabindex++, '" ';
                echo '></label></p>';
            }
            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('aim', $register_plus_redux->rpr_get_option('show_fields'))) {
                $aim = isset($_REQUEST['aim']) ? (string) $_REQUEST['aim'] : '';
                echo PHP_EOL, '<p id="aim-p"><label id="aim-label" for="aim">';
                if ('1' === $register_plus_redux->rpr_get_option('required_fields_asterisk') && is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('aim', $register_plus_redux->rpr_get_option('required_fields'))) echo '*';
                echo __('AIM', 'register-plus-redux'), '<br><input type="text" name="aim" id="aim" class="input" value="', esc_attr($aim), '"';
                if (0 !== $tabindex) echo ' tabindex="', $tabindex++, '" ';
                echo '></label></p>';
            }
            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('yahoo', $register_plus_redux->rpr_get_option('show_fields'))) {
                $yahoo = isset($_REQUEST['yahoo']) ? (string) $_REQUEST['yahoo'] : '';
                echo PHP_EOL, '<p id="yahoo-p"><label id="yahoo-label" for="yahoo">';
                if ('1' === $register_plus_redux->rpr_get_option('required_fields_asterisk') && is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('yahoo', $register_plus_redux->rpr_get_option('required_fields'))) echo '*';
                echo __('Yahoo IM', 'register-plus-redux'), '<br><input type="text" name="yahoo" id="yahoo" class="input" value="', esc_attr($yahoo), '"';
                if (0 !== $tabindex) echo ' tabindex="', $tabindex++, '" ';
                echo '></label></p>';
            }
            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('jabber', $register_plus_redux->rpr_get_option('show_fields'))) {
                $jabber = isset($_REQUEST['jabber']) ? (string) $_REQUEST['jabber'] : '';
                echo PHP_EOL, '<p id="jabber-p"><label id="jabber-label" for="jabber">';
                if ('1' === $register_plus_redux->rpr_get_option('required_fields_asterisk') && is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('jabber', $register_plus_redux->rpr_get_option('required_fields'))) echo '*';
                echo __('Jabber / Google Talk', 'register-plus-redux'), '<br><input type="text" name="jabber" id="jabber" class="input" value="', esc_attr($jabber), '"';
                if (0 !== $tabindex) echo ' tabindex="', $tabindex++, '" ';
                echo '></label></p>';
            }
            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('about', $register_plus_redux->rpr_get_option('show_fields'))) {
                $description = isset($_REQUEST['description']) ? (string) $_REQUEST['description'] : '';
                echo PHP_EOL, '<p id="description-p"><label id="description-label" for="description-p">';
                if ('1' === $register_plus_redux->rpr_get_option('required_fields_asterisk') && is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('about', $register_plus_redux->rpr_get_option('required_fields'))) echo '*';
                echo __('About Yourself', 'register-plus-redux'), '<br>';
                echo PHP_EOL, '<span id="description_msg">', __('Share a little biographical information to fill out your profile. This may be shown publicly.', 'register-plus-redux'), '</span>';
                echo PHP_EOL, '<textarea name="description" id="description"';
                if (0 !== $tabindex) echo ' tabindex="', $tabindex++, '" ';
                echo '>', esc_textarea($description), '</textarea></label></p>';
            }
            $redux_usermeta = get_option('register_plus_redux_usermeta-rv2');
            if (is_array($redux_usermeta)) {
                foreach ($redux_usermeta as $meta_field) {
                    if (!empty($meta_field['show_on_registration'])) {
                        $meta_key = esc_attr($meta_field['meta_key']);
                        if ('checkbox' === $meta_field['display']) {
                            $meta_value = isset($_REQUEST[$meta_key]) ? (array) $_REQUEST[$meta_key] : '';
                        }
                        else {
                            $meta_value = isset($_REQUEST[$meta_key]) ? (string) $_REQUEST[$meta_key] : '';
                        }
                        switch ($meta_field['display']) {
                            case 'textbox':
                                echo PHP_EOL, '<p id="', $meta_key, '-p">';
                                echo PHP_EOL, '<label id="', $meta_key, '-label" for="', $meta_key, '">';
                                if ('1' === $register_plus_redux->rpr_get_option('required_fields_asterisk') && !empty($meta_field['require_on_registration'])) {
                                    echo '*';
                                }
                                echo esc_html($meta_field['label']), '<br><input type="text" name="', $meta_key, '" id="', $meta_key, '" ';
                                echo ('1' === $meta_field['show_datepicker']) ? 'class="datepicker" ' : 'class="input" ';
                                echo 'value="', esc_attr($meta_value), '"';
                                if (0 !== $tabindex) {
                                    echo ' tabindex="', $tabindex++, '"';
                                }
                                echo '></label></p>';
                                break;
                            case 'select':
                                echo PHP_EOL, '<p id="', $meta_key, '-p">';
                                echo PHP_EOL, '<label id="', $meta_key, '-label">';
                                if ('1' === $register_plus_redux->rpr_get_option('required_fields_asterisk') && !empty($meta_field['require_on_registration'])) {
                                    echo '*';
                                }
                                echo esc_html($meta_field['label']), '<br>';
                                echo PHP_EOL, '<select name="', $meta_key, '" id="', $meta_key, '"';
                                if (0 !== $tabindex) {
                                    echo ' tabindex="', $tabindex++, '"';
                                }
                                echo '>';
                                $field_options = explode(',', $meta_field['options']);
                                foreach ($field_options as $field_option) {
                                    echo '<option id="', $meta_key, '-', Register_Plus_Redux::sanitize_text($field_option), '" value="', esc_attr($field_option), '"';
                                    if ($meta_value === esc_attr($field_option)) {
                                        echo ' selected="selected"';
                                    }
                                    echo '>', esc_html($field_option), '</option>';
                                }
                                echo '</select>';
                                echo PHP_EOL, '</label></p>';
                                break;
                            case 'checkbox':
                                echo PHP_EOL, '<p id="', $meta_key, '-p" class="margin_bottom_16">';
                                echo PHP_EOL, '<label id="', $meta_key, '-label">';
                                if ('1' === $register_plus_redux->rpr_get_option('required_fields_asterisk') && !empty($meta_field['require_on_registration'])) echo '*';
                                echo esc_html($meta_field['label']), '</label><br>';
                                $field_options = explode(',', $meta_field['options']);
                                foreach ($field_options as $field_option) {
                                    $id = "$meta_key-" . Register_Plus_Redux::sanitize_text($field_option);
                                    echo PHP_EOL, '<input type="checkbox" name="', $meta_key, '[]" id="', $id, '" value="', esc_attr($field_option), '" ';
                                    if (0 !== $tabindex) echo ' tabindex="', $tabindex++, '"';
                                    if ((is_array($meta_value) && in_array(esc_attr($field_option), $meta_value)) ||
                                        (!is_array($meta_value) && ($meta_value === esc_attr($field_option)))) {
                                        echo ' checked="checked"';
                                    }
                                    echo '>';
                                    echo PHP_EOL, '<label id="', $id, '-label" class="', $meta_key, '" for="', $id, '">&nbsp;', esc_html($field_option), '</label><br>';
                                }
                                echo PHP_EOL, '</p>';
                                break;
                            case 'radio':
                                echo PHP_EOL, '<p id="', $meta_key, '-p" class="margin_bottom_16">';
                                echo PHP_EOL, '<label id="', $meta_key, '-label">';
                                if ('1' === $register_plus_redux->rpr_get_option('required_fields_asterisk') && !empty($meta_field['require_on_registration'])) {
                                    echo '*';
                                }
                                echo esc_html($meta_field['label']), '</label><br>';
                                $field_options = explode(',', $meta_field['options']);
                                foreach ($field_options as $field_option) {
                                    $id = "$meta_key-" . Register_Plus_Redux::sanitize_text($field_option);
                                    echo PHP_EOL, '<input type="radio" name="', $meta_key, '" id="', $id, '" value="', esc_attr($field_option), '"';
                                    if (0 !== $tabindex) {
                                        echo ' tabindex="', $tabindex++, '"';
                                    }
                                    if ($meta_value === esc_attr($field_option)) {
                                        echo ' checked="checked"';
                                    }
                                    echo '><label id="', $id, '-label" class="', $meta_key, '" for="', $id, '">&nbsp;', esc_html($field_option), '</label><br>';
                                }
                                echo PHP_EOL, '</p>';
                                break;
                            case 'textarea':
                                echo PHP_EOL, '<p id="', $meta_key, '-p">';
                                echo PHP_EOL, '<label id="', $meta_key, '-label" for="', $meta_key, '">';
                                if ('1' === $register_plus_redux->rpr_get_option('required_fields_asterisk') && !empty($meta_field['require_on_registration'])) {
                                    echo '*';
                                }
                                echo esc_html($meta_field['label']), '<br><textarea name="', $meta_key, '" id="', $meta_key, '"';
                                if (0 !== $tabindex) {
                                    echo ' tabindex="', $tabindex++, '"';
                                }
                                echo '>', esc_textarea($meta_value), '</textarea></label></p>';
                                break;
                            case 'hidden':
                                echo PHP_EOL, '<input type="hidden" name="', $meta_key, '" id="', $meta_key, '" value="', esc_attr($meta_value), '"';
                                if (0 !== $tabindex) {
                                    echo ' tabindex="', $tabindex++, '" ';
                                }
                                echo '>';
                                break;
                            case 'text':
                                echo PHP_EOL, '<p id="', $meta_key, '-p">', esc_html($meta_field['label']), '</p>';
                                break;
                        }
                    }
                }
            }
            if ('1' === $register_plus_redux->rpr_get_option('user_set_password')) {
                echo PHP_EOL, '<p id="pass1-p"><label id="pass1-label" for="pass1">';
                if ('1' === $register_plus_redux->rpr_get_option('required_fields_asterisk')) echo '*';
                echo __('Password', 'register-plus-redux'), '<br><input type="password" autocomplete="off" name="pass1" id="pass1"';
                if (0 !== $tabindex) echo ' tabindex="', $tabindex++, '" ';
                echo '></label></p>';
                if ('1' !== $register_plus_redux->rpr_get_option('disable_password_confirmation')) {
                    echo PHP_EOL, '<p id="pass2-p"><label id="pass2-label" for="pass2">';
                    if ('1' === $register_plus_redux->rpr_get_option('required_fields_asterisk')) echo '*';
                    echo __('Confirm Password', 'register-plus-redux'), '<br><input type="password" autocomplete="off" name="pass2" id="pass2" ';
                    if (0 !== $tabindex) echo ' tabindex="', $tabindex++, '"';
                    echo '></label></p>';
                }
                if ('1' === $register_plus_redux->rpr_get_option('show_password_meter')) {
                    echo PHP_EOL, '<div id="pass-strength-result">', $register_plus_redux->rpr_get_option('message_empty_password'), '</div>';
                }
                echo PHP_EOL, '<p id="pass_strength_msg">', sprintf(__('Your password must be at least %d characters long. To make your password stronger, use upper and lower case letters, numbers, and the following symbols !@#$%%^&amp;*()', 'register-plus-redux'), absint($register_plus_redux->rpr_get_option('min_password_length'))), '</p>';
            }
            if ('1' === $register_plus_redux->rpr_get_option('enable_invitation_code')) {
                $invitation_code = isset($_REQUEST['invitation_code']) ? (string) $_REQUEST['invitation_code'] : '';
                echo PHP_EOL, '<p id="invitation_code-p"><label id="invitation_code-label" for="invitation_code-p">';
                if ('1' === $register_plus_redux->rpr_get_option('required_fields_asterisk') && '1' === $register_plus_redux->rpr_get_option('require_invitation_code')) echo '*';
                echo __('Invitation Code', 'register-plus-redux'), '<br><input type="text" name="invitation_code" id="invitation_code" class="input" value="', esc_attr($invitation_code), '"';
                if (0 !== $tabindex) echo ' tabindex="', $tabindex++, '" ';
                echo '></label>';
                echo PHP_EOL, '<span id="invitation_code_msg">';
                if ('1' === $register_plus_redux->rpr_get_option('require_invitation_code')) {
                    _e('This website is currently closed to public registrations. You will need an invitation code to register.', 'register-plus-redux');
                }
                else {
                    _e('Have an invitation code? Enter it here. (This is not required)', 'register-plus-redux');
                }
                echo '</span>';
                echo '</p>';
            }
            if ('1' === $register_plus_redux->rpr_get_option('show_disclaimer')) {
                $accept_disclaimer = isset($_REQUEST['accept_disclaimer']) ? '1' : '0';
                echo PHP_EOL, '<div id="disclaimer-div">';
                echo PHP_EOL, '<label id="disclaimer_title">', esc_html($register_plus_redux->rpr_get_option('message_disclaimer_title')), '</label><br>';
                echo PHP_EOL, '<div id="disclaimer">', nl2br($register_plus_redux->rpr_get_option('message_disclaimer'), false), '</div>';
                if ('1' === $register_plus_redux->rpr_get_option('require_disclaimer_agree')) {
                    echo PHP_EOL, '<label id="accept_disclaimer-label" class="accept_check" for="accept_disclaimer"><input type="checkbox" name="accept_disclaimer" id="accept_disclaimer" value="1"'; if (!empty($accept_disclaimer)) echo ' checked="checked"';
                    if (0 !== $tabindex) echo ' tabindex="', $tabindex++, '" ';
                    echo '>&nbsp;', esc_html($register_plus_redux->rpr_get_option('message_disclaimer_agree')), '</label>';
                }
                echo PHP_EOL, '</div>';
            }
            if ('1' === $register_plus_redux->rpr_get_option('show_license')) {
                $accept_license = isset($_REQUEST['accept_license']) ? '1' : '0';
                echo PHP_EOL, '<div id="license-div">';
                echo PHP_EOL, '<label id="license_title">', esc_html($register_plus_redux->rpr_get_option('message_license_title')), '</label><br>';
                echo PHP_EOL, '<div id="license">', nl2br($register_plus_redux->rpr_get_option('message_license'), false), '</div>';
                if ('1' === $register_plus_redux->rpr_get_option('require_license_agree')) {
                    echo PHP_EOL, '<label id="accept_license-label" class="accept_check" for="accept_license"><input type="checkbox" name="accept_license" id="accept_license" value="1"'; if (!empty($accept_license)) echo ' checked="checked"';
                    if (0 !== $tabindex) echo ' tabindex="', $tabindex++, '" ';
                    echo '>&nbsp;', esc_html($register_plus_redux->rpr_get_option('message_license_agree')), '</label>';
                }
                echo PHP_EOL, '</div>';
            }
            if ('1' === $register_plus_redux->rpr_get_option('show_privacy_policy')) {
                $accept_privacy_policy = isset($_REQUEST['accept_privacy_policy']) ? '1' : '0';
                echo PHP_EOL, '<div id="privacy_policy-div">';
                echo PHP_EOL, '<label id="privacy_policy_title">', esc_html($register_plus_redux->rpr_get_option('message_privacy_policy_title')), '</label><br>';
                echo PHP_EOL, '<div id="privacy_policy">', nl2br($register_plus_redux->rpr_get_option('message_privacy_policy'), false), '</div>';
                if ('1' === $register_plus_redux->rpr_get_option('require_privacy_policy_agree')) {
                    echo PHP_EOL, '<label id="accept_privacy_policy-label" class="accept_check" for="accept_privacy_policy"><input type="checkbox" name="accept_privacy_policy" id="accept_privacy_policy" value="1"'; if (!empty($accept_privacy_policy)) echo ' checked="checked"';
                    if (0 !== $tabindex) echo ' tabindex="', $tabindex++, '" ';
                    echo '>&nbsp;', esc_html($register_plus_redux->rpr_get_option('message_privacy_policy_agree')), '</label>';
                }
                echo PHP_EOL, '</div>';
            }
            if (is_array($redux_usermeta)) {
                foreach ($redux_usermeta as $meta_field) {
                    if ('terms' === $meta_field['display']) {
                        $terms_exist = true;
                        break;
                    }
                }
                if ($terms_exist) {
                    foreach ($redux_usermeta as $meta_field) {
                        if ('terms' === $meta_field['display'] && '1' === $meta_field['show_on_registration']) {
                            $meta_value = isset($_REQUEST[$meta_key]) ? (string) $_REQUEST[$meta_key] : 'N';
                            $meta_key = (string) esc_attr($meta_field['meta_key']);
                            echo PHP_EOL, '<p id="', $meta_key, '-p">';
                            echo PHP_EOL, '<label id="', $meta_key, '-label">', esc_html($meta_field['label']), '</label><br>';
                            echo PHP_EOL, '<span id="', $meta_key, '-content">', nl2br($meta_field['terms_content'], false), '</span>';
                            if ('1' === $meta_field['require_on_registration']) {
                                echo PHP_EOL, '<label id="accept_', $meta_key, '-label" class="accept_check" for="', $meta_key, '"><input type="checkbox" name="', $meta_key, '" id="', $meta_key, '" value="Y" ', checked($meta_value, 'Y', false);
                                if (0 !== $tabindex) echo ' tabindex="', $tabindex++, '" ';
                                echo '>&nbsp;', esc_html($meta_field['terms_agreement_text']), '</label>';
                            }
                            echo PHP_EOL, '</p>';
                        }
                    }
                }
            }
        }

        public function rpr_registration_errors(object $errors, string $sanitized_user_login, string $user_email): object
        {
            global $register_plus_redux;

            // SPAM protection
            // Email domain on the blacklist check
            if (filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
                $blacklist = explode(',', $register_plus_redux->rpr_get_option('domain_blacklist'));
                $blacklist = array_map('trim', $blacklist);
                foreach ($blacklist as $pattern) {
                    if (preg_match("/$pattern/i", $user_email)) {
                        $errors->add(
                            'invalid_email',
                            '<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' .
                                __('Registration not allowed.', 'register-plus-redux')
                        );
                        return $errors;
                    }
                }
            }
            // Minimal registration duration check
            $min_expected_seconds_to_register = absint($register_plus_redux->rpr_get_option('min_expected_seconds_to_register'));
            if (!is_numeric($min_expected_seconds_to_register) || $min_expected_seconds_to_register < 1) $min_expected_seconds_to_register = 0;
            if ($min_expected_seconds_to_register > 0) {
                $now = new DateTimeImmutable();
                if (empty($_POST['registration_timestamp'])) {
                    $errors->add('empty_registration_timestamp', '<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Registration not allowed.', 'register-plus-redux'));
                }
                elseif (!is_numeric($_POST['registration_timestamp'])) {
                    $errors->add('invalid_registration_timestamp', '<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Registration not allowed.', 'register-plus-redux'));
                }
                elseif (($now->getTimestamp() - (int) $_POST['registration_timestamp']) < $min_expected_seconds_to_register) {
                    $errors->add('min_expected_seconds_to_register_violation', '<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Registration not allowed.', 'register-plus-redux'));
                }
            }
            // Company
            $company = $_POST['company_name'] ?? '';
            if (preg_match("/(cucumber|youtube)/i", $company) || (strip_tags($company) != $company)) {
                $errors->add(
                    'invalid_company',
                    '<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' .
                    __('Registration not allowed.', 'register-plus-redux')
                );
                return $errors;
            }
            // Input data validation
            if ('1' === $register_plus_redux->rpr_get_option('username_is_email')) {
                if (is_array($errors->errors) && isset($errors->errors['empty_username'])) {
                    $temp = $errors->errors;
                    unset($temp['empty_username']);
                    $errors->errors = $temp;
                }
                if (is_array($errors->error_data) && isset($errors->error_data['empty_username'])) {
                    $temp = $errors->error_data;
                    unset($temp['empty_username']);
                    $errors->error_data = $temp;
                }
                $sanitized_user_login = sanitize_user($user_email);
                if ($sanitized_user_login !== $user_email) {
                    $errors->add('invalid_email', '<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Email address is not appropriate as a username, please enter another email address.', 'register-plus-redux'));
                }
            }
            if ('1' === $register_plus_redux->rpr_get_option('double_check_email')) {
                if (empty($_POST['user_email2'])) {
                    $errors->add('empty_email', '<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Please confirm your e-mail address.', 'register-plus-redux'));
                }
                elseif ($_POST['user_email'] !== $_POST['user_email2']) {
                    $errors->add('email_mismatch', '<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Your e-mail address does not match.', 'register-plus-redux'));
                }
            }
            if (is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('first_name', $register_plus_redux->rpr_get_option('required_fields'))) {
                if (empty($_POST['first_name'])) {
                    $errors->add('empty_first_name', '<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Please enter your first name.', 'register-plus-redux'));
                }
            }
            if (is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('last_name', $register_plus_redux->rpr_get_option('required_fields'))) {
                if (empty($_POST['last_name'])) {
                    $errors->add('empty_last_name', '<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Please enter your last name.', 'register-plus-redux'));
                }
            }
            if (is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('user_url', $register_plus_redux->rpr_get_option('required_fields'))) {
                if (empty($_POST['user_url'])) {
                    $errors->add('empty_user_url', '<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Please enter your website URL.', 'register-plus-redux'));
                }
            }
            if (is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('aim', $register_plus_redux->rpr_get_option('required_fields'))) {
                if (empty($_POST['aim'])) {
                    $errors->add('empty_aim', '<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Please enter your AIM username.', 'register-plus-redux'));
                }
            }
            if (is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('yahoo' , $register_plus_redux->rpr_get_option('required_fields'))) {
                if (empty($_POST['yahoo'])) {
                    $errors->add('empty_yahoo', '<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Please enter your Yahoo IM username.', 'register-plus-redux'));
                }
            }
            if (is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('jabber', $register_plus_redux->rpr_get_option('required_fields'))) {
                if (empty($_POST['jabber'])) {
                    $errors->add('empty_jabber', '<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Please enter your Jabber / Google Talk username.', 'register-plus-redux'));
                }
            }
            if (is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('about', $register_plus_redux->rpr_get_option('required_fields'))) {
                if (empty($_POST['description'])) {
                    $errors->add('empty_description', '<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Please enter some information about yourself.', 'register-plus-redux'));
                }
            }
            $redux_usermeta = get_option('register_plus_redux_usermeta-rv2');
            if (is_array($redux_usermeta)) {
                foreach ($redux_usermeta as $meta_field) {
                    $meta_key = $meta_field['meta_key'];
                    if (!empty($meta_field['show_on_registration']) && !empty($meta_field['require_on_registration']) && empty($_POST[$meta_key])) {
                        $errors->add('empty_' . $meta_key, sprintf('<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Please enter a value for %s.', 'register-plus-redux'), $meta_field['label']));
                    }
                    if (!empty($meta_field['show_on_registration']) && ('textbox' === $meta_field['display']) && !empty($meta_field['options']) && !preg_match($meta_field['options'], (string) $_POST[$meta_key])) {
                        $errors->add('invalid_' . $meta_key, sprintf('<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Please enter new value for %s, value specified is not in the correct format.', 'register-plus-redux'), $meta_field['label']));
                    }
                }
            }
            if ('1' === $register_plus_redux->rpr_get_option('user_set_password')) {
                if (empty($_POST['pass1'])) {
                    $errors->add('empty_password', '<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Please enter a password.', 'register-plus-redux'));
                }
                elseif (strlen((string) $_POST['pass1']) < absint($register_plus_redux->rpr_get_option('min_password_length'))) {
                    $errors->add('password_length', sprintf('<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Your password must be at least %d characters in length.', 'register-plus-redux'), absint($register_plus_redux->rpr_get_option('min_password_length'))));
                }
                elseif ('1' !== $register_plus_redux->rpr_get_option('disable_password_confirmation') && $_POST['pass1'] !== $_POST['pass2']) {
                    $errors->add('password_mismatch', '<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Your password does not match.', 'register-plus-redux'));
                }
                else {
                    if (isset($_POST['pass2'])) unset($_POST['pass2']);
                }
            }
            if ('1' === $register_plus_redux->rpr_get_option('enable_invitation_code')) {
                if (empty($_POST['invitation_code']) && '1' === $register_plus_redux->rpr_get_option('require_invitation_code')) {
                    $errors->add('empty_invitation_code', '<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Please enter an invitation code.', 'register-plus-redux'));
                }
                elseif (!empty($_POST['invitation_code'])) {
                    $invitation_code_bank = get_option('register_plus_redux_invitation_code_bank-rv1');
                    if (is_array($invitation_code_bank)) {
                        if ('1' !== $register_plus_redux->rpr_get_option('invitation_code_case_sensitive')) {
                            $_POST['invitation_code'] = strtolower((string) $_POST['invitation_code']);
                            foreach ($invitation_code_bank as $index => $invitation_code)
                                $invitation_code_bank[$index] = strtolower($invitation_code);
                        }
                        if (is_array($invitation_code_bank) && !in_array((string) $_POST['invitation_code'], $invitation_code_bank)) {
                            $errors->add('invitation_code_mismatch', '<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('That invitation code is invalid.', 'register-plus-redux'));
                        }
                        else {
                            // reverts lowercase key to expirationtimestamp case
                            $key = array_search((string) $_POST['invitation_code'], $invitation_code_bank);
                            $invitation_code_bank = get_option('register_plus_redux_invitation_code_bank-rv1');
                            $_POST['invitation_code'] = $invitation_code_bank[$key];
                            if ('1' === $register_plus_redux->rpr_get_option('invitation_code_unique')) {
                                global $wpdb;
                                if ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->usermeta WHERE meta_key = 'invitation_code' AND meta_value = %s;", (string) $_POST['invitation_code'])) > 0) {
                                    $errors->add('invitation_code_exists', '<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('This invitation code is already in use, please enter a unique invitation code.', 'register-plus-redux'));
                                }
                            }
                        }
                    }
                }
            }
            if ('1' === $register_plus_redux->rpr_get_option('show_disclaimer') && '1' === $register_plus_redux->rpr_get_option('require_disclaimer_agree')) {
                if (empty($_POST['accept_disclaimer'])) {
                    $errors->add('accept_disclaimer', sprintf('<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Please accept the %s', 'register-plus-redux'), esc_html($register_plus_redux->rpr_get_option('message_disclaimer_title'))) . '.');
                }
            }
            if ('1' === $register_plus_redux->rpr_get_option('show_license') && '1' === $register_plus_redux->rpr_get_option('require_license_agree')) {
                if (empty($_POST['accept_license'])) {
                    $errors->add('accept_license', sprintf('<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Please accept the %s', 'register-plus-redux'), esc_html($register_plus_redux->rpr_get_option('message_license_title'))) . '.');
                }
            }
            if ('1' === $register_plus_redux->rpr_get_option('show_privacy_policy') && '1' === $register_plus_redux->rpr_get_option('require_privacy_policy_agree')) {
                if (empty($_POST['accept_privacy_policy'])) {
                    $errors->add('accept_privacy_policy' , sprintf('<strong>' . __('ERROR', 'register-plus-redux') . '</strong>:&nbsp;' . __('Please accept the %s', 'register-plus-redux'), esc_html($register_plus_redux->rpr_get_option('message_privacy_policy_title'))) . '.');
                }
            }
            return $errors;
        }

        /**
         * @param string $get_code
         * @return int|WP_Error
         */
        private function check_verification_code(string $get_code): int|WP_Error
        {
            global $wpdb;

            $code = preg_replace('/[^a-z0-9]/i', '', $get_code);

            if (empty($code) || !is_string($code) || $code !== $get_code)
                return new WP_Error('invalid_code', __('Invalid verification code'));

            $user_id = (int)$wpdb->get_var(
                    $wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'email_verification_code' AND meta_value = %s;", $get_code)
            );

            if (empty($user_id))
                return new WP_Error('invalid_key', __('Invalid verification code'));

            return $user_id;
        }

        public function rpr_login_form_verifyemail(): void
        {
            global $register_plus_redux;
            global $errors;

            if (isset($_GET['verification_code']) && '1' === $register_plus_redux->rpr_get_option('verify_user_email')) {
                if (!is_wp_error($errors)) $errors = new WP_Error();
                $user_id = $this->check_verification_code((string) $_GET['verification_code']);
                if (!is_wp_error($user_id)) {
                    if ('1' !== $register_plus_redux->rpr_get_option('verify_user_admin')) {
                        $user_password = get_user_meta($user_id, 'stored_user_password', TRUE);
                        $user = get_userdata($user_id);
                        if (!is_multisite()) {
                            $user->set_role((string) get_option('default_role'));
                        }
                        else {
                            $user->remove_role('rpr_unverified');
                        }
                        delete_user_meta($user_id, 'email_verification_code');
                        delete_user_meta($user_id, 'email_verification_sent');
                        delete_user_meta($user_id, 'stored_user_password');
                        if (empty($user_password)) {
                            $user_password = wp_generate_password();
                            wp_set_password($user_password, $user_id);
                        }
                        do_action('rpr_signup_complete', $user_id);
                        if ('1' !== $register_plus_redux->rpr_get_option('disable_user_message_registered')) {
                            $register_plus_redux->send_welcome_user_mail($user_id, $user_password);
                        }
                        if ('1' === $register_plus_redux->rpr_get_option('admin_message_when_verified')) {
                            $register_plus_redux->send_admin_mail($user_id, $user_password);
                        }
                        if ('1' === $register_plus_redux->rpr_get_option('user_set_password')) {
                            $errors->add('account_verified', sprintf(__('Thank you %s, your account has been verified, please login with the password you specified during registration.', 'register-plus-redux'), $user->user_login), 'message');
                        }
                        else {
                            $errors->add('account_verified_checkemail', sprintf(__('Thank you %s, your account has been verified, your password will be emailed to you.', 'register-plus-redux'), $user->user_login), 'message');
                        }
                    }
                    else {
                        update_user_meta($user_id, 'email_verified', gmdate('Y-m-d H:i:s'));
                        $errors->add('admin_review', __('Your account will be reviewed by an administrator and you will be notified when it is activated.', 'register-plus-redux'), 'message');
                    }
                }
                else {
                    $errors->add('invalid_verification_code', __('Invalid verification code.', 'register-plus-redux'), 'error');
                }
                login_header(__('Verify Email', 'register-plus-redux'), '', $errors);
                login_footer();
                exit();
            }
        }

        /**
         * @return void
         */
        #[NoReturn] public function rpr_login_form_emailverify(): void {
            global $register_plus_redux;
            global $errors;
            if (is_array($errors->errors) && isset($errors->errors['registered'])) {
                $temp = $errors->errors;
                unset($temp['registered']);
                $errors->errors = $temp;
            }
            if (is_array($errors->error_data) && isset($errors->error_data['registered'])) {
                $temp = $errors->error_data;
                unset($temp['registered']);
                $errors->error_data = $temp;
            }
            if (!is_wp_error($errors)) $errors = new WP_Error();
            $errors->add('verify_user_email', nl2br($register_plus_redux->rpr_get_option('message_verify_user_email'), false), 'message');
            login_header(__('Verify Email', 'register-plus-redux'), '', $errors);
            login_footer();
            exit();
        }

        /**
         * @return void
         */
        #[NoReturn] public function rpr_login_form_adminverify(): void
        {
            global $register_plus_redux;
            global $errors;

            if (is_array($errors->errors) && isset($errors->errors['registered'])) {
                $temp = $errors->errors;
                unset($temp['registered']);
                $errors->errors = $temp;
            }
            if (is_array($errors->error_data) && isset($errors->error_data['registered'])) {
                $temp = $errors->error_data;
                unset($temp['registered']);
                $errors->error_data = $temp;
            }
            if (!is_wp_error($errors)) $errors = new WP_Error();
            $errors->add('verify_user_admin', nl2br($register_plus_redux->rpr_get_option('message_verify_user_admin'), false), 'message');
            login_header(__('Admin Verification', 'register-plus-redux'), '', $errors);
            login_footer();
            exit();
        }

        public function rpr_user_register(int $user_id): void
        {
            global $register_plus_redux;
            global $pagenow;

            if ('wp-login.php' !== $pagenow) return;

            $source = $_POST;

            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('first_name', $register_plus_redux->rpr_get_option('show_fields')) && !empty($source['first_name'])) {
                update_user_meta($user_id, 'first_name', sanitize_text_field((string) $source['first_name']));
            }
            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('last_name', $register_plus_redux->rpr_get_option('show_fields')) && !empty($source['last_name'])) {
                update_user_meta($user_id, 'last_name', sanitize_text_field((string) $source['last_name']));
            }
            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('user_url', $register_plus_redux->rpr_get_option('show_fields')) && !empty($source['user_url'])) {
                $user_url = esc_url_raw((string) $source['user_url']);
                $user_url = preg_match('/^(https?|ftps?|mailto|news|irc|gopher|nntp|feed|telnet):/i', $user_url) ? $user_url : 'https://' . $user_url;
                wp_update_user(array('ID' => $user_id, 'user_url' => sanitize_text_field($user_url)));
            }
            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('aim', $register_plus_redux->rpr_get_option('show_fields')) && !empty($source['aim'])) {
                update_user_meta($user_id, 'aim', sanitize_text_field((string) $source['aim']));
            }
            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('yahoo', $register_plus_redux->rpr_get_option('show_fields')) && !empty($source['yahoo'])) {
                update_user_meta($user_id, 'yim', sanitize_text_field((string) $source['yahoo']));
            }
            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('jabber', $register_plus_redux->rpr_get_option('show_fields')) && !empty($source['jabber'])) {
                update_user_meta($user_id, 'jabber', sanitize_text_field((string) $source['jabber']));
            }
            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('about', $register_plus_redux->rpr_get_option('show_fields')) && !empty($source['description'])) {
                update_user_meta($user_id, 'description', wp_filter_kses((string) $source['description']));
            }

            $redux_usermeta = get_option('register_plus_redux_usermeta-rv2');

            if (is_array($redux_usermeta)) {
                foreach ($redux_usermeta as $meta_field) {
                    if ('1' === $meta_field['show_on_registration']) {
                        if ('checkbox' === $meta_field['display']) {
                            $meta_value = isset($source[ (string) $meta_field['meta_key']]) ? (array) $source[ (string) $meta_field['meta_key']] : '';
                        }
                        else if ('terms' === $meta_field['display']) {
                            $meta_value = isset($source[ (string) $meta_field['meta_key']]) ? (string) $source[ (string) $meta_field['meta_key']] : 'N';
                        }
                        else {
                            $meta_value = isset($source[ (string) $meta_field['meta_key']]) ? (string) $source[ (string) $meta_field['meta_key']] : '';
                        }
                        $register_plus_redux->rpr_update_user_meta($user_id, $meta_field, $meta_value);
                    }
                }
            }

            if ('1' === $register_plus_redux->rpr_get_option('enable_invitation_code') && !empty($source['invitation_code'])) {
                update_user_meta($user_id, 'invitation_code', sanitize_text_field((string) $source['invitation_code']));
            }

            if ('1' === $register_plus_redux->rpr_get_option('verify_user_email') || '1' === $register_plus_redux->rpr_get_option('verify_user_admin')) {
                if ('1' === $register_plus_redux->rpr_get_option('user_set_password')) {
                    update_user_meta($user_id, 'stored_user_password', (string) $_REQUEST['password']);
                }
                $user = get_userdata($user_id);
                $user->set_role('rpr_unverified');
            }

            if ('1' !== $register_plus_redux->rpr_get_option('verify_user_email') && '1' !== $register_plus_redux->rpr_get_option('verify_user_admin')) {
                do_action('rpr_signup_complete', $user_id);

                if ('1' === $register_plus_redux->rpr_get_option('autologin_user')) {
                    $user = get_userdata($user_id);
                    $credentials['user_login'] = $user->user_login;
                    $credentials['user_password'] = (string) $_REQUEST['password'];
                    $credentials['remember'] = false;
                    wp_signon($credentials, false);
                }
            }
        }

        /**
         * @param string $redirect_to
         * @return string
         */
        public function rpr_filter_registration_redirect(string $redirect_to): string
        {
            global $register_plus_redux;

            // NOTE: default $redirect_to = 'wp-login.php?checkemail=registered'
            if ('1' === $register_plus_redux->rpr_get_option('autologin_user') && '1' !== $register_plus_redux->rpr_get_option('verify_user_email') && '1' !== $register_plus_redux->rpr_get_option('verify_user_admin')) {
                $redirect_to = admin_url();
            }

            if ($register_plus_redux->rpr_get_option('registration_redirect_url')) {
                $redirect_to = esc_url($register_plus_redux->rpr_get_option('registration_redirect_url'));
            }

            return $redirect_to;
        }

        /**
         * @return void
         */
        public function rpr_login_head(): void
        {
            global $register_plus_redux;

            if ($register_plus_redux->rpr_get_option('custom_logo_url')) {
                if (ini_get('allow_url_fopen')) {
                    list($width, $height) = getimagesize(esc_url($register_plus_redux->rpr_get_option('custom_logo_url')));
                }
                ?>
                <style>
                    #login h1 a {
                        background-image: url("<?= esc_url($register_plus_redux->rpr_get_option('custom_logo_url')) ?>");
                        margin: 0 0 0 8px;
                        <?php if (!empty($width)) echo 'width: ', $width, 'px;', PHP_EOL; ?>
                        <?php if (!empty($height)) echo 'height: ', $height, 'px;', PHP_EOL; ?>
                        <?php if (!empty($width) && !empty($height)) echo 'background-size: ', $width, 'px ', $height, 'px;', "\n"; ?>
                    }
                </style>
                <?php
            }
            if (isset($_GET['checkemail']) && 'registered' === $_GET['checkemail'] && ('1' === $register_plus_redux->rpr_get_option('verify_user_admin') || '1' === $register_plus_redux->rpr_get_option('verify_user_email'))) {
                ?>
                <style>
                    #loginform { display: none; }
                    #nav { display: none; }
                </style>
                <?php
            }
            if (isset($_GET['action']) && ('register' === $_GET['action'])) {
                $redux_usermeta = get_option('register_plus_redux_usermeta-rv2');
                if (is_array($redux_usermeta)) {
                    foreach ($redux_usermeta as $meta_field) {
                        if ('1' === $meta_field['show_on_registration']) {
                            $meta_key = esc_attr($meta_field['meta_key']);
                            if ('textbox' === $meta_field['display']) {
                                if (empty($show_custom_textbox_fields))
                                    $show_custom_textbox_fields = '#' . $meta_key;
                                else
                                    $show_custom_textbox_fields .= ', #' . $meta_key;
                            }
                            if ('select' === $meta_field['display']) {
                                if (empty($show_custom_select_fields))
                                    $show_custom_select_fields = '#' . $meta_key;
                                else
                                    $show_custom_select_fields .= ', #' . $meta_key;
                            }
                            if ('checkbox' === $meta_field['display']) {
                                $field_options = explode(',', $meta_field['options']);
                                foreach ($field_options as $field_option) {
                                    if (empty($show_custom_checkbox_fields))
                                        $show_custom_checkbox_fields = '#' . $meta_key . '-' . Register_Plus_Redux::sanitize_text($field_option) . ', #' . $meta_key . '-' . Register_Plus_Redux::sanitize_text($field_option) . '-label';
                                    else
                                        $show_custom_checkbox_fields .= ', #' . $meta_key . '-' . Register_Plus_Redux::sanitize_text($field_option) . ', #' . $meta_key . '-' . Register_Plus_Redux::sanitize_text($field_option) . '-label';
                                }
                            }
                            if ('radio' === $meta_field['display']) {
                                $field_options = explode(',', $meta_field['options']);
                                foreach ($field_options as $field_option) {
                                    if (empty($show_custom_radio_fields))
                                        $show_custom_radio_fields = '#' . $meta_key . '-' . Register_Plus_Redux::sanitize_text($field_option) . ', #' . $meta_key . '-' . Register_Plus_Redux::sanitize_text($field_option) . '-label';
                                    else
                                        $show_custom_radio_fields .= ', #' . $meta_key . '-' . Register_Plus_Redux::sanitize_text($field_option) . ', #' . $meta_key . '-' . Register_Plus_Redux::sanitize_text($field_option) . '-label';
                                }
                            }
                            if ('textarea' === $meta_field['display']) {
                                if (empty($show_custom_textarea_fields))
                                    $show_custom_textarea_fields = '#' . $meta_key;
                                else
                                    $show_custom_textarea_fields .= ', #' . $meta_key;
                            }
                            if ('text' === $meta_field['display']) {
                                if (empty($show_custom_text_fields))
                                    $show_custom_text_fields = '#login form #' . $meta_key . '-p';
                                else
                                    $show_custom_text_fields .= ', #login form #' . $meta_key . '-p';
                            }
                            if (!empty($meta_field['require_on_registration'])) {
                                if (empty($required_meta_fields))
                                    $required_meta_fields = '#' . $meta_key;
                                else
                                    $required_meta_fields .= ', #' . $meta_key;
                            }
                        }
                    }
                }

                if (is_array($register_plus_redux->rpr_get_option('show_fields'))) {
                    $show_fields = '#' . implode(', #', $register_plus_redux->rpr_get_option('show_fields'));
                }

                if (is_array($register_plus_redux->rpr_get_option('required_fields'))) {
                    $required_fields = '#' . implode(', #', $register_plus_redux->rpr_get_option('required_fields'));
                }

                echo PHP_EOL, '<style>';
                if ('1' === $register_plus_redux->rpr_get_option('default_css')) {
                    if ('1' === $register_plus_redux->rpr_get_option('double_check_email')) echo PHP_EOL, '#user_email2 { font-size:24px; width:100%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }';
                    if (!empty($show_fields)) echo PHP_EOL, $show_fields, ' { font-size:24px; width:100%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }';
                    if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('about', $register_plus_redux->rpr_get_option('show_fields')))  {
                        echo PHP_EOL, '#description { font-size:18px; height: 60px; width:100%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }';
                        echo PHP_EOL, '#description_msg { font-size: smaller; }';
                    }
                    if (!empty($show_custom_textbox_fields)) echo PHP_EOL, $show_custom_textbox_fields, ' { font-size:24px; width:100%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }';
                    if (!empty($show_custom_select_fields)) echo PHP_EOL, $show_custom_select_fields, ' { font-size:24px; width:100%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }';
                    if (!empty($show_custom_checkbox_fields)) echo PHP_EOL, $show_custom_checkbox_fields, ' { font-size:18px; }';
                    if (!empty($show_custom_radio_fields)) echo PHP_EOL, $show_custom_radio_fields, ' { font-size:18px; }';
                    if (!empty($show_custom_textarea_fields)) echo PHP_EOL, $show_custom_textarea_fields, ' { font-size:18px; height: 60px; width:100%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }';
                    if (!empty($show_custom_text_fields)) echo PHP_EOL, $show_custom_text_fields, ' { font-size: larger; color: #777; margin-bottom:16px; }';
                    if ('1' === $register_plus_redux->rpr_get_option('user_set_password')) echo PHP_EOL, '#pass1, #pass2 { font-size:24px; width:100%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }';
                    if ('1' === $register_plus_redux->rpr_get_option('enable_invitation_code')) {
                        echo PHP_EOL, '#invitation_code { font-size:24px; width:100%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:4px; border:1px solid #e5e5e5; background:#fbfbfb; }';
                        echo PHP_EOL, '#invitation_code_msg { font-size: smaller; color: #777; display: inline-block; margin-bottom:8px; }';
                    }
                }
                if ('1' === $register_plus_redux->rpr_get_option('show_disclaimer')) { echo PHP_EOL, '#disclaimer { font-size:12px; display: block; width: 100%; padding: 3px; margin-top:2px; margin-right:6px; margin-bottom:8px; background-color:#fff; border:solid 1px #A7A6AA; font-weight:normal;'; if (strlen($register_plus_redux->rpr_get_option('message_disclaimer')) > 525) echo 'height: 160px; overflow: auto;'; echo ' }'; }
                if ('1' === $register_plus_redux->rpr_get_option('show_license')) { echo PHP_EOL, '#license { font-size:12px; display: block; width: 100%; padding: 3px; margin-top:2px; margin-right:6px; margin-bottom:8px; background-color:#fff; border:solid 1px #A7A6AA; font-weight:normal;'; if (strlen($register_plus_redux->rpr_get_option('message_license')) > 525) echo 'height: 160px; overflow: auto;'; echo ' }'; }
                if ('1' === $register_plus_redux->rpr_get_option('show_privacy_policy')) { echo PHP_EOL, '#privacy_policy { font-size:12px; display: block; width: 100%; padding: 3px; margin-top:2px; margin-right:6px; margin-bottom:8px; background-color:#fff; border:solid 1px #A7A6AA; font-weight:normal;'; if (strlen($register_plus_redux->rpr_get_option('message_privacy_policy')) > 525) echo 'height: 160px; overflow: auto;'; echo ' }'; }
                if ('1' === $register_plus_redux->rpr_get_option('show_disclaimer') || '1' === $register_plus_redux->rpr_get_option('show_license') || '1' === $register_plus_redux->rpr_get_option('show_privacy_policy')) echo PHP_EOL, '.accept_check { display:block; margin-bottom:8px; }';
                if ('1' === $register_plus_redux->rpr_get_option('user_set_password')) {
                    echo PHP_EOL, '#reg_passmail { display: none; }';
                    if ('1' === $register_plus_redux->rpr_get_option('show_password_meter')) {
                        echo PHP_EOL, '.login #pass-strength-result { width: 100%; margin-top: 0px; margin-right: 6px; margin-bottom: 8px; margin-left: 0px; border-width: 1px; border-style: solid; padding: 3px 0; text-align: center; font-weight: bold; display: block; }';
                        echo PHP_EOL, '#pass-strength-result { background-color: #eee; border-color: #ddd !important; }';
                        echo PHP_EOL, '#pass-strength-result.bad { background-color: #ffb78c; border-color: #ff853c !important; }';
                        echo PHP_EOL, '#pass-strength-result.good { background-color: #ffec8b; border-color: #fc0 !important; }';
                        echo PHP_EOL, '#pass-strength-result.short { background-color: #ffa0a0; border-color: #f04040 !important; }';
                        echo PHP_EOL, '#pass-strength-result.strong { background-color: #c3ff88; border-color: #8dff1c !important; }';
                    }
                    echo PHP_EOL, '#login form #pass_strength_msg { font-size: smaller; color: #777; margin-top: -8px; margin-bottom: 16px; }';
                }
                if ($register_plus_redux->rpr_get_option('required_fields_style')) {
                    echo PHP_EOL, '#user_login, #user_email { ', esc_html($register_plus_redux->rpr_get_option('required_fields_style')), '} ';
                    if ('1' === $register_plus_redux->rpr_get_option('double_check_email')) echo PHP_EOL, '#user_email2 { ', esc_html($register_plus_redux->rpr_get_option('required_fields_style')), ' }';
                    if (!empty($required_fields)) echo PHP_EOL, $required_fields, ' { ', esc_html($register_plus_redux->rpr_get_option('required_fields_style')), ' }';
                    if (is_array($register_plus_redux->rpr_get_option('required_fields')) && in_array('about', $register_plus_redux->rpr_get_option('required_fields')))  {
                        echo PHP_EOL, '#description { ', esc_html($register_plus_redux->rpr_get_option('required_fields_style')), ' }';
                    }
                    if (!empty($required_meta_fields)) echo PHP_EOL, $required_meta_fields, ' { ', esc_html($register_plus_redux->rpr_get_option('required_fields_style')), ' }';
                    if ('1' === $register_plus_redux->rpr_get_option('user_set_password')) echo PHP_EOL, '#pass1, #pass2 { ', esc_html($register_plus_redux->rpr_get_option('required_fields_style')), ' }';
                    if ('1' === $register_plus_redux->rpr_get_option('require_invitation_code')) echo PHP_EOL, '#invitation_code { ', esc_html($register_plus_redux->rpr_get_option('required_fields_style')), ' }';
                }
                if ($register_plus_redux->rpr_get_option('custom_registration_page_css')) echo PHP_EOL, esc_html($register_plus_redux->rpr_get_option('custom_registration_page_css'));
                echo PHP_EOL, '</style>';
            }
            else {
                if ($register_plus_redux->rpr_get_option('custom_login_page_css')) {
                    echo PHP_EOL, '<style>';
                    echo PHP_EOL, esc_html($register_plus_redux->rpr_get_option('custom_login_page_css'));
                    echo PHP_EOL, '</style>';
                }
            }
        }

        /**
         * @return void
         */
        public function rpr_login_footer(): void
        {
            global $register_plus_redux;

            if (isset($_GET['action']) && 'register' === $_GET['action']) {
                $user_login = isset($_REQUEST['user_login']) ? stripslashes((string) $_REQUEST['user_login']) : '';
                $user_email = isset($_REQUEST['user_email']) ? stripslashes((string) $_REQUEST['user_email']) : '';
                if (!empty($user_login) || !empty($user_email)) {
                    // TODO: I'd rather escape than sanitize
                    ?>
                    <script>
                        var $ = jQuery.noConflict();
                        $(document).ready(function() {
                            $("#user_login").val("<?= sanitize_user($user_login) ?>");
                            $("#user_email").val("<?= is_email($user_email) ?>");
                        });
                    </script>
                    <?php
                }

                $redux_usermeta = get_option('register_plus_redux_usermeta-rv2');

                if (is_array($redux_usermeta)) {
                    foreach ($redux_usermeta as $meta_field) {
                        if ('1' === $meta_field['show_on_registration'] && '1' === $meta_field['show_datepicker']) {
                            ?>
                            <script>
                                var $ = jQuery.noConflict();
                                $(document).ready(function() {
                                    $(".datepicker").datepicker();
                                });
                            </script>
                            <?php
                            break;
                        }
                    }
                }

                // TODO: this may not be the best option to tie this behavior to
                if ('1' === $register_plus_redux->rpr_get_option('default_css')) {
                    $tabindex = absint($register_plus_redux->rpr_get_option('starting_tabindex'));
                    $tabindex -= 2;
                    if ($tabindex <= 0) {
                        $tabindex = 1;
                        $register_plus_redux->rpr_set_option('starting_tabindex', 3, true);
                    }
                    ?>
                    <script>
                        var $ = jQuery.noConflict();
                        $(document).ready(function() {
                            var user_login = $("#user_login");
                            user_login.removeProp("size");
                            user_login.parent().prop("id", "user_login-label");
                            user_login.parent().parent().prop("id", "user_login-p");
                            user_login.prop("tabindex", "<?= $tabindex++ ?>");
                            var user_email = $("#user_email");
                            user_email.removeProp("size");
                            user_email.parent().prop("id", "user_email-label");
                            user_email.parent().parent().prop("id", "user_email-p");
                            user_email.prop("tabindex", "<?= $tabindex++ ?>");
                        });
                    </script>
                    <?php
                }
                if ('1' === $register_plus_redux->rpr_get_option('required_fields_asterisk')) {
                    ?>
                    <script>
                        var $ = jQuery.noConflict();
                        $(document).ready(function() {
                            $("#user_login").parent().prepend("*");
                            $("#user_email").parent().prepend("*");
                        });
                    </script>
                    <?php
                }
                if ('1' === $register_plus_redux->rpr_get_option('user_set_password') && '1' === $register_plus_redux->rpr_get_option('show_password_meter')) {
                    // TODO: Messages could be compromised, needs to be escaped, look into methods used by comments to display
                    ?>
                    <script>
                        /* <![CDATA[ */
                        pwsL10n = {
                            empty: "<?= $register_plus_redux->rpr_get_option('message_empty_password'); ?>",
                            short: "<?= $register_plus_redux->rpr_get_option('message_short_password'); ?>",
                            bad: "<?= $register_plus_redux->rpr_get_option('message_bad_password'); ?>",
                            good: "<?= $register_plus_redux->rpr_get_option('message_good_password'); ?>",
                            strong: "<?= $register_plus_redux->rpr_get_option('message_strong_password'); ?>",
                            mismatch: "<?= $register_plus_redux->rpr_get_option('message_mismatch_password'); ?>"
                        }
                        /* ]]> */
                        function check_pass_strength() {
                            // HACK support username_is_email in function
                            var user = $("<?php if ('1' === $register_plus_redux->rpr_get_option('username_is_email')) echo '#user_email'; else echo '#user_login'; ?>").val();
                            var pass1 = $("#pass1").val();
                            var pass2 = $("#pass2").val();
                            var strength;
                            var pass_strength_result = $("#pass-strength-result");
                            pass_strength_result.removeClass("short bad good strong mismatch");
                            if (!pass1) {
                                pass_strength_result.html(pwsL10n.empty);
                                return;
                            }
                            strength = passwordStrength(pass1, user, pass2);
                            switch (strength) {
                                case 2:
                                    pass_strength_result.addClass("bad").html(pwsL10n['bad']);
                                    break;
                                case 3:
                                    pass_strength_result.addClass("good").html(pwsL10n['good']);
                                    break;
                                case 4:
                                    pass_strength_result.addClass("strong").html(pwsL10n['strong']);
                                    break;
                                case 5:
                                    pass_strength_result.addClass("mismatch").html(pwsL10n['mismatch']);
                                    break;
                                default:
                                    pass_strength_result.addClass("short").html(pwsL10n['short']);
                                    break;
                            }
                        }
                        function passwordStrength(password1, username, password2) {
                            // HACK support disable_password_confirmation in function
                            password2 = typeof password2 !== 'undefined' ? password2 : '';
                            var shortPass = 1, badPass = 2, goodPass = 3, strongPass = 4, mismatch = 5, symbolSize = 0, natLog, score;
                            if (password1 !== password2 && password2.length > 0)
                                return mismatch;
                            if (password1.length < <?= absint($register_plus_redux->rpr_get_option('min_password_length')) ?>)
                                return shortPass;
                            if (password1.toLowerCase() === username.toLowerCase())
                                return badPass;
                            if (password1.match(/[0-9]/))
                                symbolSize +=10;
                            if (password1.match(/[a-z]/))
                                symbolSize +=26;
                            if (password1.match(/[A-Z]/))
                                symbolSize +=26;
                            if (password1.match(/[^a-zA-Z0-9]/))
                                symbolSize +=31;
                                natLog = Math.log(Math.pow(symbolSize, password1.length));
                                score = natLog / Math.LN2;
                            if (score < 40)
                                return badPass;
                            if (score < 56)
                                return goodPass;
                            return strongPass;
                        }
                        var $ = jQuery.noConflict();
                        $(document).ready(function() {
                            $("#pass1").val("").keyup(check_pass_strength);
                            $("#pass2").val("").keyup(check_pass_strength);
                        });
                    </script>
                    <?php
                }
                if ('1' === $register_plus_redux->rpr_get_option('username_is_email')) {
                    ?>
                    <!--[if (lte IE 8)]>
                    <script>
                        document.getElementById("registerform").childNodes[0].style.display = "none";
                    </script>
                    <![endif]-->
                    <!--[if (gt IE 8)|!(IE)]><!-->
                    <script>
                        document.getElementById("registerform").childNodes[1].style.display = "none";
                    </script>
                    <!--<![endif]-->
                    <?php
                }
            }
            elseif (isset($_GET['action']) && 'lostpassword' === $_GET['action']) {
                if ('1' === $register_plus_redux->rpr_get_option('username_is_email')) {
                    ?>
                    <!--[if (lte IE 8)]>
                    <script>
                        document.getElementById("lostpasswordform").childNodes[0].childNodes[0].childNodes[0].nodeValue = "<?= __('E-mail', 'register-plus-redux') ?>";
                    </script>
                    <![endif]-->
                    <!--[if (gt IE 8)|!(IE)]><!-->
                    <script>
                        document.getElementById("lostpasswordform").childNodes[1].childNodes[1].childNodes[0].nodeValue = "<?= __('E-mail', 'register-plus-redux') ?>";
                    </script>
                    <!--<![endif]-->
                    <?php
                }
            }
            elseif (!isset($_GET['action'])) {
                if ('1' === $register_plus_redux->rpr_get_option('username_is_email')) {
                    ?>
                    <!--[if (lte IE 8)]>
                    <script>
                        document.getElementById("loginform").childNodes[0].childNodes[0].childNodes[0].nodeValue = "<?= __('E-mail', 'register-plus-redux') ?>";
                    </script>
                    <![endif]-->
                    <!--[if (gt IE 8)|!(IE)]><!-->
                    <script>
                        document.getElementById("loginform").childNodes[1].childNodes[1].childNodes[0].nodeValue = "<?= __('E-mail', 'register-plus-redux') ?>";
                    </script>
                    <!--<![endif]-->
                    <?php
                }
            }
        }

        /**
         * @param string $href
         * @return string
         */
        public function rpr_filter_login_headerurl(string $href): string
        {
            return home_url();
        }

        public function rpr_filter_login_headertext(string $text): string
        {
            $desc = get_option('blogdescription');

            if (empty($desc))
                $text = get_option('blogname') . ' - ' . $desc;
            else
                $text = get_option('blogname');

            return $text;
        }
    }
}

if (class_exists('RPR_Login')) $rpr_login = new RPR_Login();
