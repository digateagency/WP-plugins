<?php
/*
 * Plugin Name: Yandex SmartCaptcha Guard
 * Description: Yandex SmartCaptcha protection for the custom hook.
 * Version: 3.8
 * Author: Denis Chernyshov
 */

if (!defined('ABSPATH')) {
    exit;
}

final class YSCG_Plugin {

    const OPTION_SITE_KEY   = 'yscg_site_key';
    const OPTION_SECRET_KEY = 'yscg_secret_key';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_settings_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);

        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        /*
         * В mailer.php этот хук вызывается так:
         * do_action('mailer_before_parse_fields', $_POST);
         *
         * Поэтому callback должен принимать 1 аргумент, не 5.
         */
        add_action('mailer_before_parse_fields', [__CLASS__, 'validate_before_parse_fields'], 1, 1);

        /*
         * В dynamic_functions.php письмо собирается из $form_data,
         * а не из $_POST. Поэтому служебные поля нужно удалить через фильтр.
         */
        add_filter('mailer_before_parse_fields_filter', [__CLASS__, 'cleanup_form_data'], 1, 1);

        add_shortcode('yandex_smartcaptcha', [__CLASS__, 'shortcode']);
    }

    public static function add_settings_page() {
        add_options_page(
            'Yandex SmartCaptcha Guard',
            'Yandex SmartCaptcha',
            'manage_options',
            'yscg',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function register_settings() {
        register_setting('yscg_settings', self::OPTION_SITE_KEY, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        register_setting('yscg_settings', self::OPTION_SECRET_KEY, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);
    }

    public static function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Yandex SmartCaptcha Guard</h1>

            <form method="post" action="options.php">
                <?php settings_fields('yscg_settings'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="yscg_site_key">Client Key</label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="yscg_site_key"
                                name="<?php echo esc_attr(self::OPTION_SITE_KEY); ?>"
                                value="<?php echo esc_attr(get_option(self::OPTION_SITE_KEY, '')); ?>"
                                class="regular-text"
                                autocomplete="off"
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="yscg_secret_key">Server Key</label>
                        </th>
                        <td>
                            <input
                                type="password"
                                id="yscg_secret_key"
                                name="<?php echo esc_attr(self::OPTION_SECRET_KEY); ?>"
                                value="<?php echo esc_attr(get_option(self::OPTION_SECRET_KEY, '')); ?>"
                                class="regular-text"
                                autocomplete="new-password"
                            >
                            <button
                                type="button"
                                class="button"
                                id="yscg_toggle_secret"
                                style="margin-left: 8px;"
                            >Показать</button>

                            <script>
                                document.addEventListener('DOMContentLoaded', function () {
                                    var input = document.getElementById('yscg_secret_key');
                                    var button = document.getElementById('yscg_toggle_secret');

                                    if (!input || !button) {
                                        return;
                                    }

                                    button.addEventListener('click', function () {
                                        var isPassword = input.getAttribute('type') === 'password';

                                        input.setAttribute('type', isPassword ? 'text' : 'password');
                                        button.textContent = isPassword ? 'Скрыть' : 'Показать';
                                    });
                                });
                            </script>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr>

            <p>PHP вставка в форму:</p>
            <code>&lt;?php the_yandex_smartcaptcha(); ?&gt;</code>

            <p>Шорткод:</p>
            <code>[yandex_smartcaptcha]</code>
        </div>
        <?php
    }

    public static function enqueue_assets() {
        $site_key = get_option(self::OPTION_SITE_KEY, '');

        if ($site_key) {
            wp_enqueue_script(
                'yscg-smartcaptcha',
                'https://smartcaptcha.cloud.yandex.ru/captcha.js',
                [],
                null,
                true
            );
        }

        wp_register_script('yscg-guard', '', [], '3.8', true);
        wp_enqueue_script('yscg-guard');

        wp_add_inline_script('yscg-guard', self::frontend_js());

        wp_register_style('yscg-guard', false, [], '3.8');
        wp_enqueue_style('yscg-guard');

        wp_add_inline_style('yscg-guard', self::frontend_css());
    }

    private static function frontend_css() {
        return '
            .yscg-error-message {
                display: none;
                margin-top: 12px;
                padding: 16px 20px;
                border-radius: 18px;
                background: #f8dede;
                color: #c62828;
                font-size: 16px;
                line-height: 1.4;
            }
        ';
    }

    private static function frontend_js() {
        $site_key   = get_option(self::OPTION_SITE_KEY, '');
        $secret_key = get_option(self::OPTION_SECRET_KEY, '');

        $config = [
            'configured'       => (bool) ($site_key && $secret_key),
            'notConfiguredMsg' => 'Капча не настроена.',
            'notVerifiedMsg'   => 'Подтвердите, что вы не робот.',
        ];

        return 'window.YSCG_CONFIG = ' . wp_json_encode($config) . ';' . "\n" . <<<'JS'
(function () {
    function cfg() {
        return window.YSCG_CONFIG || {};
    }

    function messageNotConfigured() {
        return cfg().notConfiguredMsg || 'Капча не настроена.';
    }

    function messageNotVerified() {
        return cfg().notVerifiedMsg || 'Подтвердите, что вы не робот.';
    }

    function isConfigured() {
        return !!cfg().configured;
    }

    function findCaptchaWrap(form) {
        return form ? form.querySelector('.yscg-captcha-wrap') : null;
    }

    function findError(form) {
        return form ? form.querySelector('.yscg-error-message') : null;
    }

    function ensureError(form) {
        if (!form) return null;

        var error = findError(form);

        if (!error) {
            error = document.createElement('div');
            error.className = 'yscg-error-message';
            error.textContent = messageNotVerified();

            var wrap = findCaptchaWrap(form);

            if (wrap) {
                wrap.insertAdjacentElement('afterend', error);
            } else {
                form.appendChild(error);
            }
        }

        return error;
    }

    function showError(form, message) {
        var error = ensureError(form);

        if (!error) return;

        error.textContent = message || messageNotVerified();
        error.style.display = 'block';
    }

    function hideError(form) {
        var error = findError(form);

        if (error) {
            error.style.display = 'none';
        }
    }

    function formHasCaptcha(form) {
        return !!(form && form.querySelector('input[name="yscg_enabled"]'));
    }

    function getToken(form) {
        var token = form ? form.querySelector('input[name="smart-token"], input[name="smart_token"]') : null;
        return token && token.value ? token.value.trim() : '';
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!formHasCaptcha(form)) {
            return;
        }

        /*
         * Сначала браузерная валидация required/email.
         * Если поля формы невалидны — не показываем ошибку капчи.
         */
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            return;
        }

        if (!isConfigured()) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            showError(form, messageNotConfigured());

            return false;
        }

        if (!getToken(form)) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            showError(form, messageNotVerified());

            return false;
        }
    }, true);

    document.addEventListener('input', function (event) {
        var form = event.target && event.target.closest ? event.target.closest('form') : null;

        if (formHasCaptcha(form)) {
            hideError(form);
        }
    }, true);

    document.addEventListener('click', function (event) {
        var wrap = event.target && event.target.closest ? event.target.closest('.yscg-captcha-wrap') : null;

        if (!wrap) {
            return;
        }

        var form = wrap.closest('form');

        if (formHasCaptcha(form)) {
            hideError(form);
        }
    }, true);

    document.addEventListener('mousemove', function (event) {
        var wrap = event.target && event.target.closest ? event.target.closest('.yscg-captcha-wrap') : null;

        if (!wrap) {
            return;
        }

        var form = wrap.closest('form');

        if (formHasCaptcha(form)) {
            hideError(form);
        }
    }, true);
})();
JS;
    }

    public static function shortcode() {
        return self::render_captcha();
    }

    public static function render_captcha() {
        $site_key   = get_option(self::OPTION_SITE_KEY, '');
        $secret_key = get_option(self::OPTION_SECRET_KEY, '');

        /*
         * Marker нужен всегда, если helper/shortcode вставлен в форму.
         * Так backend понимает: именно эту форму нужно защищать.
         */
        ob_start();
        ?>
        <div class="yscg-captcha-wrap">
            <input type="hidden" name="yscg_enabled" value="1">

            <?php if ($site_key && $secret_key): ?>
                <div
                    class="smart-captcha"
                    data-sitekey="<?php echo esc_attr($site_key); ?>"
                ></div>
            <?php else: ?>
                <div class="yscg-error-message yscg-config-error" style="display:block;">
                    Капча не настроена.
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function validate_before_parse_fields($post_data = []) {
        /*
         * Если в форму не вставлен шорткод/PHP helper — не трогаем её.
         */
        if (empty($_POST['yscg_enabled'])) {
            return;
        }

        $site_key   = get_option(self::OPTION_SITE_KEY, '');
        $secret_key = get_option(self::OPTION_SECRET_KEY, '');

        if (!$site_key || !$secret_key) {
            self::send_error('Капча не настроена.');
        }

        $token = '';

        if (!empty($_POST['smart-token'])) {
            $token = sanitize_text_field(wp_unslash($_POST['smart-token']));
        } elseif (!empty($_POST['smart_token'])) {
            $token = sanitize_text_field(wp_unslash($_POST['smart_token']));
        }

        if (!$token) {
            self::send_error('Подтвердите, что вы не робот.');
        }

        $response = wp_remote_post(
            'https://smartcaptcha.cloud.yandex.ru/validate',
            [
                'timeout' => 10,
                'body'    => [
                    'secret' => $secret_key,
                    'token'  => $token,
                    'ip'     => self::get_ip(),
                ],
            ]
        );

        if (is_wp_error($response)) {
            self::send_error('Ошибка проверки капчи.');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['status']) || $body['status'] !== 'ok') {
            self::send_error('Подтвердите, что вы не робот.');
        }

        /*
         * Удаляем служебные поля до сборки письма в mailer.php.
         */
        unset($_POST['smart-token'], $_POST['smart_token'], $_POST['yscg_enabled']);
    }

    public static function cleanup_form_data($form_data) {
        if (!is_array($form_data)) {
            return $form_data;
        }

        unset($form_data['smart-token']);
        unset($form_data['smart_token']);
        unset($form_data['yscg_enabled']);

        return $form_data;
    }

    private static function get_ip() {
        return isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';
    }

    private static function send_error($message) {
        $response = [
            'delay'     => 0,
            'status'    => 'error',
            'error'     => 'ERROR_YANDEX_CAPTCHA',
            'error_msg' => $message,
            'message'   => $message,
        ];

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(200);
        }

        echo wp_json_encode($response);
        exit;
    }
}

YSCG_Plugin::init();

function the_yandex_smartcaptcha() {
    echo YSCG_Plugin::render_captcha();
}
