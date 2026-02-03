<?php
/*
Plugin Name: Efipay Gateway Payment WooCommerce 
Plugin URI: https://sag.efipay.co/docs/1.0/overview
Description: Plugin de integracion entre Wordpress-Woocommerce con Efipay
Version: 2.1.3
Author: Efipay
Author URI: https://efipay.co
*/



function clear_cart_ajax_handler() {
    // Llamar a la función de WooCommerce para vaciar el carrito
    if (function_exists('WC')) {
        WC()->cart->empty_cart();
        die(); // Terminar el script después de vaciar el carrito
    }
}
add_action('wp_ajax_clear_cart_ajax', 'clear_cart_ajax_handler'); // Para usuarios autenticados
add_action('wp_ajax_nopriv_clear_cart_ajax', 'clear_cart_ajax_handler'); // Para usuarios no autenticados


function declare_cart_checkout_blocks_compatibility() {
    // Check if the required class exists
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        // Declare compatibility for 'cart_checkout_blocks'
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}
// Hook the custom function to the 'before_woocommerce_init' action
add_action('before_woocommerce_init', 'declare_cart_checkout_blocks_compatibility');


// Hook the custom function to the 'woocommerce_blocks_loaded' action
add_action( 'woocommerce_blocks_loaded', 'oawoo_register_order_approval_payment_method_type' );


// Hook para agregar enlace de ajustes en la vista de plugins
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'plugin_add_link_settings');
function plugin_add_link_settings($links) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=efipay">Ajustes</a>';
    array_unshift($links, $settings_link);
    return $links;
}

/**
 * Custom function to register a payment method type

 */
function oawoo_register_order_approval_payment_method_type() {
    // Check if the required class exists
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }

    // Include the custom Blocks Checkout class
    require_once plugin_dir_path(__FILE__) . 'class-block.php';

    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
            // Register an instance of My_Custom_Gateway_Blocks
            if (class_exists('WC_Efipay')) {
                $efipay_instance = new WC_Efipay();
                $payment_method_registry->register( new Efipay_Blocks($efipay_instance) );
            }
        }
    );
}


add_filter('woocommerce_payment_gateways', 'agregar_efipay_gateway');
function agregar_efipay_gateway($methods) {
    $methods[] = 'WC_Efipay';
    return $methods;
}

// Campos de facturación adicionales para Efipay (tipo y número de identificación) - igual en todas las tiendas
// Estos campos siempre se agregan porque Efipay los necesita, incluso si no hay formulario de envío
add_filter('woocommerce_checkout_fields', 'efipay_add_identification_checkout_fields');
function efipay_add_identification_checkout_fields($fields) {
    // Solo agregar campos si WooCommerce y Efipay están disponibles
    if (!function_exists('WC') || !WC() || !WC()->payment_gateways) {
        return $fields;
    }
    $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
    if (!isset($available_gateways['efipay'])) {
        return $fields;
    }
    
    $fields['billing']['billing_identification_type'] = array(
        'type'        => 'select',
        'label'       => __('Tipo de identificación', 'efipay'),
        'placeholder' => __('Seleccione', 'efipay'),
        'required'    => true,
        'class'       => array('form-row-wide'),
        'options'     => array(
            ''           => __('Seleccione', 'efipay'),
            'CC'         => 'CC',
            'CE'         => 'CE',
            'TI'         => 'TI',
            'PA'         => 'PA',
            'PEP'        => 'PEP',
            'PPT'        => 'PPT',
            'NIT'        => 'NIT',
            'Pasaporte'  => __('Pasaporte', 'efipay'),
            'Otro'       => __('Otro', 'efipay'),
        ),
        'priority'    => 35,
    );
    $fields['billing']['billing_identification_number'] = array(
        'type'        => 'text',
        'label'       => __('Número de identificación', 'efipay'),
        'placeholder' => __('Número de documento', 'efipay'),
        'required'    => true,
        'class'       => array('form-row-wide'),
        'priority'    => 36,
    );
    return $fields;
}

add_action('woocommerce_checkout_update_order_meta', 'efipay_save_identification_checkout_fields', 10, 2);
function efipay_save_identification_checkout_fields($order_id, $posted = null) {
    if (!empty($_POST['billing_identification_type'])) {
        update_post_meta($order_id, '_billing_identification_type', sanitize_text_field($_POST['billing_identification_type']));
    }
    if (!empty($_POST['billing_identification_number'])) {
        update_post_meta($order_id, '_billing_identification_number', sanitize_text_field($_POST['billing_identification_number']));
    }
    if (get_current_user_id() && !empty($_POST['billing_identification_type'])) {
        update_user_meta(get_current_user_id(), 'billing_identification_type', sanitize_text_field($_POST['billing_identification_type']));
    }
    if (get_current_user_id() && !empty($_POST['billing_identification_number'])) {
        update_user_meta(get_current_user_id(), 'billing_identification_number', sanitize_text_field($_POST['billing_identification_number']));
    }
}

add_filter('woocommerce_checkout_get_value', 'efipay_prefill_identification_checkout', 10, 2);
function efipay_prefill_identification_checkout($value, $input) {
    if ($value !== null) return $value;
    $user_id = get_current_user_id();
    if (!$user_id) return $value;
    if ($input === 'billing_identification_type') {
        return get_user_meta($user_id, 'billing_identification_type', true) ?: $value;
    }
    if ($input === 'billing_identification_number') {
        return get_user_meta($user_id, 'billing_identification_number', true) ?: $value;
    }
    return $value;
}

add_action('woocommerce_admin_order_data_after_billing_address', 'efipay_display_identification_in_admin', 10, 1);
function efipay_display_identification_in_admin($order) {
    $type = $order->get_meta('_billing_identification_type');
    $number = $order->get_meta('_billing_identification_number');
    if ($type || $number) {
        echo '<p><strong>' . esc_html__('Tipo de identificación', 'efipay') . ':</strong> ' . esc_html($type) . '<br>';
        echo '<strong>' . esc_html__('Número de identificación', 'efipay') . ':</strong> ' . esc_html($number) . '</p>';
    }
}

add_action('woocommerce_loaded', 'woocommerce_efipay_gateway');

function woocommerce_efipay_gateway() {
    if (!class_exists('WC_Payment_Gateway')) return;

    class WC_Efipay extends WC_Payment_Gateway {

        /** Monedas aceptadas por la pasarela Efipay */
        const SUPPORTED_CURRENCIES = array('USD', 'COP', 'EUR');

        public function __construct() {
            $this->id = 'efipay';
            $this->icon = apply_filters('woocomerce_efipay_icon', plugins_url('/img/logoEfipay.png', __FILE__));
            $this->has_fields = false;
            $this->method_title = 'Efipay (tarjetas debito, credito, pse, efectivos)';
            $this->method_description = 'Integración de Woocommerce a la pasarela de pagos de Efipay. Solo acepta USD, COP y EUR.';

            $this->init_form_fields();
            $this->init_settings();

            // payment_embebed
            $this->enabled_embebed = $this->get_option('enabled_embebed');
            $this->api_key = $this->get_option('api_key');
            $this->office_id = $this->get_option('office_id');
            $this->webhook_url = $this->get_option('webhook_url');
            $this->token = $this->get_option('token');

            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
            }
            add_action('woocommerce_receipt_efipay', array($this, 'receipt_page'));
			add_action('before_woocommerce_init', array($this, 'declare_compatibility'));

        }

        /**
         * Comprueba si la moneda actual de WooCommerce está soportada por Efipay (USD, COP, EUR).
         */
        public function is_currency_supported() {
            $currency = get_woocommerce_currency();
            return in_array($currency, self::SUPPORTED_CURRENCIES, true);
        }

        /**
         * Solo mostrar el método de pago si está habilitado y la moneda es USD, COP o EUR.
         */
        public function is_available() {
            if ($this->enabled !== 'yes') {
                return false;
            }
            if (!$this->is_currency_supported()) {
                return false;
            }
            return parent::is_available();
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled_embebed' => array(
                    'title' => __('Habilitar/Deshabilitar Payments Embebed', 'efipay'),
                    'type' => 'checkbox',
                    'label' => __('Habilita la opcion de pago incrustado en tu sitio web', 'efipay'),
                    'default' => 'no'
                ),
                'api_key' => array(
                    'title' => __('API Key', 'efipay'),
                    'type' => 'text',
                    'description' => __('Llave que sirve para encriptar la comunicación con Efipay.', 'efipay')
                ),
				'token' => array(
                    'title' => __('Token weebhook', 'efipay'),
                    'type' => 'text',
                    'description' => __('Token que sirve para encriptar la comunicación con Efipay.', 'efipay')
                ),
                'office_id' => array(
                    'title' => __('Id Sucursal/Oficina', 'efipay'),
                    'type' => 'text',
                    'description' => __('ID de tu sucursal Efipay.', 'efipay')
                )
            );
        }

		public function admin_options() {
			echo "<div style='text-align: start;'>";
			echo "<div style='text-align: center;'>";
			echo      "<img src=\"" . $this->icon . "\" style='object-fit: cover;width: 200px;'></img>";
			echo "</div>";
			if (!$this->is_currency_supported()) {
				$currency = get_woocommerce_currency();
				echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__('Efipay no está disponible en el checkout:', 'efipay') . '</strong> ';
				echo esc_html(sprintf(
					__('La moneda configurada en WooCommerce (%s) no es soportada por esta pasarela. Efipay solo acepta USD, COP y EUR. Cambia la moneda en WooCommerce → Ajustes → General para poder usar este método de pago.', 'efipay'),
					$currency
				)) . '</p></div>';
			}
			$this->generate_settings_html();
			echo "</table>";
			echo "</div>";
		}

		public function receipt_page($orderId) {
            if (defined('EFIPAY_RECEIPT_LOADED')) {
                return;
            }
            define('EFIPAY_RECEIPT_LOADED', true);

			$order = wc_get_order($orderId);
			if($order) {
                include(plugin_dir_path(__FILE__) . 'views/efipay_receipt.php');
                exit;   
			}
		}

		public function get_params_post($order_id): array {
            $order = wc_get_order($order_id);
            $currency = get_woocommerce_currency();
            if (!in_array($currency, self::SUPPORTED_CURRENCIES, true)) {
                throw new Exception(sprintf(
                    __('La moneda configurada (%s) no es soportada por Efipay. Solo se aceptan USD, COP y EUR.', 'efipay'),
                    $currency
                ));
            }
            $amount = number_format(($order->get_total()), 2, '.', '');
            $description = implode(',', array_column($order->get_items(), 'name'));
            if (strlen($description) > 255) {
                $description = substr($description, 0, 240) . ' y otros...';
            }

            $default_woocomerce_url = $order->get_checkout_order_received_url();

            $limit_date = null;
            // Forzar timezone de Colombia (America/Bogota) solo para este cálculo
            try {
                $timezone_colombia = new DateTimeZone('America/Bogota');
                $limit_date_object = new DateTime('now', $timezone_colombia);
                $limit_date_object->modify('+1 hour');

                $limit_date = $limit_date_object->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $limit_date = date('Y-m-d H:i:s', strtotime('+1 hour'));
            }

			$parameters_args = [
				"payment" => [
					"description" => 'Pago del pedido Woocommerce: '.$order->id,
					"amount" => $amount,
					"currency_type" => $currency,
					"checkout_type" => "redirect"
				],
				"advanced_options" => [
                    "limit_date" => $limit_date,
					"references" => [
						"".$order->id."",
                        $order->get_billing_email(),
                        "Plugin Woocomerce"
					],
					"result_urls" => array_filter([
						"approved" => $default_woocomerce_url,
						"rejected" => $default_woocomerce_url,
						"pending" => $default_woocomerce_url,
						"webhook" => home_url().'/wp-json/efipay/v1/webhook'
					]),
					"has_comments" => false
				],
				"office" => $this->office_id
			];

            return $parameters_args;
        }

		/**
		 * Datos del cliente desde el pedido de WooCommerce (facturación estándar + tipo/número identificación).
		 * Así no se piden de nuevo en la página de pago Efipay y es igual en todas las tiendas.
		 * Usa siempre campos de billing (facturación) que siempre existen, incluso sin formulario de envío.
		 */
		public function get_customer_data_for_receipt($order) {
			if (is_numeric($order)) {
				$order = wc_get_order($order);
			}
			if (!$order || !($order instanceof WC_Order)) {
				return array();
			}
			
			// Siempre usar billing (facturación) que siempre existe en WooCommerce
			$name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
			$phone = $order->get_billing_phone();
			$country = $order->get_billing_country();
			$state_code = $order->get_billing_state();
			
			// Obtener nombre completo del estado desde el código (ej: CO-AMA -> Amazonas)
			$state_name = $state_code;
			if ($state_code && $country && function_exists('WC') && WC()->countries) {
				$states = WC()->countries->get_states($country);
				if ($states && isset($states[$state_code])) {
					$state_name = $states[$state_code];
				}
			}
			
			return array(
				'name'                   => $name ?: '',
				'email'                  => $order->get_billing_email() ?: '',
				'phone'                  => $phone ?: '',
				'address_1'              => $order->get_billing_address_1() ?: '',
				'address_2'              => $order->get_billing_address_2() ?: '',
				'city'                   => $order->get_billing_city() ?: '',
				'state'                  => $state_name ?: '',
				'state_code'             => $state_code ?: '', // Guardar también el código por si se necesita
				'postcode'               => $order->get_billing_postcode() ?: '',
				'zip_code'               => $order->get_billing_postcode() ?: '',
				'country'                => $country ?: '',
				'identification_type'    => $order->get_meta('_billing_identification_type') ?: 'CC',
				'identification_number'  => $order->get_meta('_billing_identification_number') ?: '',
			);
		}

		public function process_payment($order_id) {
            if (!$this->is_currency_supported()) {
                $currency = get_woocommerce_currency();
                wc_add_notice(
                    sprintf(
                        __('La moneda configurada en la tienda (%s) no es soportada por la pasarela Efipay. Esta pasarela solo acepta pagos en USD, COP y EUR. Por favor, elige otro método de pago o contacta con la tienda.', 'efipay'),
                        $currency
                    ),
                    'error'
                );
                return array('result' => 'failure');
            }
            $order = wc_get_order($order_id);
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.19', '<=')) {
                return array(
                    'result' => 'success',
                    'redirect' => add_query_arg(
						'order', 
						$order->id, add_query_arg(
							'key', $order->order_key,
							get_permalink(
								get_option('woocommerce_pay_page_id')
								)
							)
						)
                );
            } else {
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            }
        }
    }



}

add_action('woocommerce_api_efipay_webhook', 'handle_efipay_webhook');
function handle_efipay_webhook($request) {

	// $computedSignature = hash_hmac('sha256', $request->getContent(), $this->token);

	$body = json_decode($request->get_body(), true);
	$transaction_data = $body['transaction']; 
	$checkout = $body['checkout'];

	if (!isset($transaction_data)) {
		error_log("No se ha recibido información de la transacción");
		wp_die("Error interno del servidor", "Error", array(
			'response' => 500
		));
	}

	$order_id = $checkout['payment_gateway']['advanced_option']['references'][0] ?? $body['payment_gateway']['advanced_option']['references'][0];
	if (!isset($order_id)) {
		die("No se ha recibido información de referencia");
	}

	try {
		$order = wc_get_order($order_id);
		if(!$order){
			die("No se ha encontrado el pedido");
		}

		switch ($transaction_data['status']) {
			case 'Aprobada':
				$order->update_status('completed', __('Pago completado a través de efipay.', 'efipay'));
				break;
			case 'Iniciada':
            case 'Pendiente':
            case 'Por Pagar':
				$order->update_status('on-hold');
				break;
            case 'Reversada':
            case 'Reversion Escalada':
                $order->update_status('refunded');
                break;
			default:
				$order->update_status('failed');
		}
		
		$order->add_order_note(__('Efipay transaction details', 'textdomain'));
		foreach ($transaction_data as $key => $value) {
			$order->add_order_note(sprintf("%s: %s", $key, $value));
		}
		wp_send_json_success( 'Pago procesado correctamente', 200 );
	} catch (Exception $e) {
		add_filter('woocommerce_email_attachments', 'efipay_add_exception_to_emails', 10, 3);
		wc_add_notice(sprintf(__('Se ha producido un error inesperado. Por favor, contacta con nosotros para más detalles.')));
		wc_add_notice(sprintf(__('Error procesando el pago: %s', 'textdomain'), $e->getMessage()), 'error');
		remove_action('woocommerce_thankyou', 'woocommerce_output_thanks');
		add_action('woocommerce_thankyou', 'custom_thankyou_page');
	}
}

function woocommerce_output_thanks(){
	echo '<div class="alert alert-success">';
	esc_html_e('Gracias por tu compra! Tu pedido se ha enviado correctamente. Te estaremos informando sobre cualquier cambio del estado del mismo.');
}
function custom_thankyou_page(){
	wc_get_template('checkout/thankyou.php');
}


add_action( 'rest_api_init', 'register_webhook_route' );
function register_webhook_route() {
	register_rest_route( 'efipay/v1', '/webhook', array(
		'methods'  => 'POST',
        'callback' => 'handle_efipay_webhook',
	));
}

// Agrega los estilos personalizados al hook 'wp_enqueue_scripts' para que se carguen en el frontend
add_action( 'wp_enqueue_scripts', 'style_customized' );
function style_customized() {
    $ruta_css = plugin_dir_url( __FILE__ ) . 'css/style.css';
    wp_enqueue_style( 'styles', $ruta_css );
}

add_filter( 'woocommerce_gateway_description', 'add_text', 10, 2 );

function add_text($icon_html, $gateway_id){
	// html icon en description
	// <div style='display: flex; justify-content: center'>
	// 	'<img src=\"" . plugins_url('/img/logoEfipay.png', __FILE__) . "\" style='object-fit: cover;width: 200px;'></img>'
	// </div>
	if ( 'efipay' === $gateway_id ) {
		return "<div class='efipay-text'>
			<p>
			Al realizar un pago con EfiPay, podrás recibir notificaciones por SMS y correo electrónico sobre el estado de tu compra<br>
			Al realizar la compra con este método de pago, aceptas nuestras Condiciones y Política de Privacidad.
			</p>
			</div>"
			;
	}
}

add_filter( 'woocommerce_gateway_icon', 'add_icon', 10, 2 );

function add_icon($icon_html, $gateway_id){
	if ( 'efipay' === $gateway_id ) { 
		$icon_html = "
			<img src=\"" . plugins_url('/img/logoEfipay.png', __FILE__) . "\" style='object-fit: cover;width: 100px; text-align:0.5rem'></img>
		";
		return $icon_html;
	}
}

// Script para asegurar que el botón de proceder al pedido se active correctamente
function efipay_checkout_script() {
    // Solo añadir en la página de checkout
    if (is_checkout()) {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Verificar el estado del botón cuando cambia el método de pago
            $(document.body).on('change', 'input[name="payment_method"]', function() {
                var paymentMethod = $('input[name="payment_method"]:checked').val();
                
                // Si el método de pago es efipay, asegurar que el botón esté habilitado
                if (paymentMethod === 'efipay') {
                    $('#place_order').prop('disabled', false).removeClass('disabled');
                }
            });
            
            // También verificar en la carga inicial de la página
            $(document.body).on('updated_checkout', function() {
                var paymentMethod = $('input[name="payment_method"]:checked').val();
                if (paymentMethod === 'efipay') {
                    $('#place_order').prop('disabled', false).removeClass('disabled');
                }
                
                // Si solo hay un método de pago y es efipay, asegurar que esté seleccionado y el botón habilitado
                if ($('input[name="payment_method"]').length === 1 && 
                    $('input[name="payment_method"]').val() === 'efipay') {
                    $('input[name="payment_method"]').prop('checked', true);
                    $('#place_order').prop('disabled', false).removeClass('disabled');
                }
            });
        });
        </script>
        <?php
    }
}
add_action('wp_footer', 'efipay_checkout_script');

// SE COMENTA ESTA FUNCION PARA VER SI ES LA CAUSANTE DEL PROBLEMA CON EL CLIENTE
// function my_enqueue_scripts() {
    // wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
    // wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js', array('jquery'), null, true);
    // wp_enqueue_script('my-script', get_template_directory_uri() . '/js/my-script.js', array('jquery', 'bootstrap-js'), null, true);
// }
// add_action('wp_enqueue_scripts', 'my_enqueue_scripts');
