<?php
if (!class_exists('RPR_Activate')) {
    class RPR_Activate {
        /**
         * Constructor
         */
        public function __construct()
        {
            add_filter('random_password', array($this, 'rpr_activate_filter_random_password'), 10, 1); // Replace random password with user set password
            add_filter('wpmu_welcome_user_notification', array($this, 'rpr_filter_wpmu_welcome_user_notification'), 10, 3);
            add_filter('wpmu_welcome_notification', array($this, 'rpr_filter_wpmu_welcome_notification'), 10, 5);
            add_action('wpmu_activate_user', array($this, 'rpr_wpmu_activate_user'), 10, 3); // Restore metadata to activated user's profile
            add_action('wpmu_activate_blog', array($this, 'rpr_wpmu_activate_blog'), 10, 5);
        }

        /**
         * @return void
         */
        public function rpr_activate_init(): void
        {
            global $pagenow;

            if ('wp-activate.php' === $pagenow) {
                trigger_error(sprintf(__('Register Plus Redux DEBUG: rpr_activate_init from %s', 'register-plus-redux'), $pagenow));
            }
        }

        /**
         * @param string $password
         * @return string
         */
        public function rpr_activate_filter_random_password(string $password): string
        {
            global $register_plus_redux;
            global $pagenow;

            if ('wp-activate.php' === $pagenow && '1' === $register_plus_redux->rpr_get_option('user_set_password')) {
                $key = isset($_REQUEST['key']) ? (string) $_REQUEST['key'] : '';
                if (!empty($key)) {
                    global $wpdb;
                    /*.object.*/ $signup = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->signups WHERE activation_key = %s;", $key));
                    if (!empty($signup)) {
                        /*.array[string]string.*/ $meta = maybe_unserialize($signup->meta);
                        if (is_array($meta) && !empty($meta['pass1'])) {
                            $password = $meta['pass1'];
                            unset($meta['pass1']);
                            $wpdb->update($wpdb->signups, array('meta' => serialize($meta)), array('activation_key' => $key));
                        }
                    }
                }
            }

            return $password;
        }

        /**
         * @param int $user_id
         * @param string $password
         * @param mixed $meta
         * @return bool
         */
        public function rpr_filter_wpmu_welcome_user_notification(int $user_id, string $password, mixed $meta): bool
        {
            global $register_plus_redux;

            return ('1' !== $register_plus_redux->rpr_get_option('disable_user_message_registered'));
        }

        /**
         * @param int $blog_id
         * @param int $user_id
         * @param string $password
         * @param string $title
         * @param mixed $meta
         * @return bool
         */
        public function rpr_filter_wpmu_welcome_notification(int $blog_id, int $user_id, string $password, string $title, mixed $meta): bool
        {
            return $this->rpr_filter_wpmu_welcome_user_notification($user_id, $password, $meta);
        }

        /**
         * @param int $user_id
         * @param string $password
         * @param mixed $meta
         * @return void
         */
        public function rpr_wpmu_activate_user(int $user_id, string $password, mixed $meta): void
        {
            global $register_plus_redux;

            // TODO: Not the most elegant solution, it would be better to interupt the activation and keep the data in the signups table with a flag to alert admin to complete activation
            if ('1' === $register_plus_redux->rpr_get_option('verify_user_admin')) {
                update_user_meta($user_id, 'stored_user_password', sanitize_text_field($password));
                $user = get_userdata($user_id);
                $user->set_role('rpr_unverified');
            }

            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('first_name', $register_plus_redux->rpr_get_option('show_fields')) && !empty($meta['first_name'])) update_user_meta($user_id, 'first_name', sanitize_text_field($meta['first_name']));
            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('last_name', $register_plus_redux->rpr_get_option('show_fields')) && !empty($meta['last_name'])) update_user_meta($user_id, 'last_name', sanitize_text_field($meta['last_name']));
            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('user_url', $register_plus_redux->rpr_get_option('show_fields')) && !empty($meta['user_url'])) {
                $user_url = esc_url_raw($meta['user_url']);
                $user_url = preg_match('/^(https?|ftps?|mailto|news|irc|gopher|nntp|feed|telnet):/i', $user_url) ? $user_url : 'https://' . $user_url;
                wp_update_user(array('ID' => $user_id, 'user_url' => sanitize_text_field($user_url)));
            }
            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('aim', $register_plus_redux->rpr_get_option('show_fields')) && !empty($meta['aim'])) update_user_meta($user_id, 'aim', sanitize_text_field($meta['aim']));
            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('yahoo', $register_plus_redux->rpr_get_option('show_fields')) && !empty($meta['yahoo'])) update_user_meta($user_id, 'yim', sanitize_text_field($meta['yahoo']));
            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('jabber', $register_plus_redux->rpr_get_option('show_fields')) && !empty($meta['jabber'])) update_user_meta($user_id, 'jabber', sanitize_text_field($meta['jabber']));
            if (is_array($register_plus_redux->rpr_get_option('show_fields')) && in_array('about', $register_plus_redux->rpr_get_option('show_fields')) && !empty($meta['description'])) update_user_meta($user_id, 'description', wp_filter_kses($meta['description']));

            $redux_usermeta = get_option('register_plus_redux_usermeta-rv2');
            if (is_array($redux_usermeta)) {
                foreach ($redux_usermeta as $meta_field) {
                    if ('1' === $meta_field['show_on_registration']) {
                        if ('checkbox' === $meta_field['display']) {
                            $meta_value = isset($meta[ (string) $meta_field['meta_key']]) ? (array) $meta[ (string) $meta_field['meta_key']] : '';
                        }
                        else if ('terms' === $meta_field['display']) {
                            $meta_value = isset($meta[ (string) $meta_field['meta_key']]) ? (string) $meta[ (string) $meta_field['meta_key']] : 'N';
                        }
                        else {
                            $meta_value = isset($meta[ (string) $meta_field['meta_key']]) ? (string) $meta[ (string) $meta_field['meta_key']] : '';
                        }
                        $register_plus_redux->rpr_update_user_meta($user_id, $meta_field, $meta_value);
                    }
                }
            }

            if ('1' === $register_plus_redux->rpr_get_option('enable_invitation_code') && !empty($meta['invitation_code'])) update_user_meta($user_id, 'invitation_code', sanitize_text_field($meta['invitation_code']));

            // TODO: Eh, semi-autologin works
            if ('1' === $register_plus_redux->rpr_get_option('autologin_user') && '1' !== $register_plus_redux->rpr_get_option('verify_user_email') && '1' !== $register_plus_redux->rpr_get_option('verify_user_admin')) {
                $user = get_userdata($user_id);
                ?>
                <form name="loginform" id="loginform" action="<?= esc_url(site_url('wp-login.php', 'login_post')) ?>" method="post">
                <input type="hidden" name="log" value="<?= $user->user_login ?>">
                <input type="hidden" name="pwd" value="<?= $password ?>">
                </form>

                <script>
                    var $ = jQuery.noConflict();
                    $(document).ready(function() {
                        $(document).on("click", "a:contains('Log in')", function(eventObject) {
                            eventObject.preventDefault();
                            $("#loginform").submit();
                        });
                    });
                    window.onbeforeunload = function() {
                        post("<?= esc_url(site_url('wp-login.php', 'login_post')) ?>", { log: "<?= $user->user_login ?>", pwd: "<?= $password ?>" });
                    };
                </script>
                <?php
            }
        }

        public function rpr_wpmu_activate_blog(int $blog_id, int $user_id, string $password, string $title, mixed $meta): void
        {
            $this->rpr_wpmu_activate_user($user_id, $password, $meta);
        }
    }
}

if (class_exists('RPR_Activate')) $rpr_activate = new RPR_Activate();
?>
