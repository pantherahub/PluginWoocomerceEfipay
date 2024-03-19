<?php
/*
Plugin Name: Efipay Gateway Payment WooCommerce 
Plugin URI: http://www.efipay.com/
Description: Plugin de integracion entre Wordpress-Woocommerce con Efipay
Version: 0.01
Author: Efipay
Author URI: http://www.efipay.com/
*/



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
			$efipay_instance = new WC_Efipay();
			add_filter('woocommerce_payment_gateways', function ($methods) use ($efipay_instance) {
				$methods[] = $efipay_instance;
				return $methods;
			});

            $payment_method_registry->register( new Efipay_Blocks($efipay_instance) );
        }
    );
}


add_action('plugins_loaded', 'woocommerce_efipay_gateway', 0);

function woocommerce_efipay_gateway() {
    if (!class_exists('WC_Payment_Gateway')) return;

    class WC_Efipay extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'efipay';
            $this->icon = apply_filters('woocomerce_efipay_icon', plugins_url('/img/logoEfipay.png', __FILE__));
            $this->has_fields = false;
            $this->method_title = 'Efipay (tarjetas debito, credito, pse, efectivos)';
            $this->method_description = 'Integración de Woocommerce a la pasarela de pagos de Efipay';

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->api_key = $this->get_option('api_key');
            $this->office_id = $this->get_option('office_id');
            $this->test = $this->get_option('test') === 'yes';
            $this->response_page = $this->get_option('response_page');
            $this->await_page = $this->get_option('await_page');
            $this->confirmation_page = $this->get_option('confirmation_page');
            $this->currency = $this->get_option('currency');
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

		public function declare_compatibility() {
			if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
			}
		}

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Habilitar/Deshabilitar', 'efipay'),
                    'type' => 'checkbox',
                    'label' => __('Habilita la pasarela de pago Efipay', 'efipay'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Título', 'efipay'),
                    'type' => 'text',
                    'description' => __('Título que el usuario verá durante checkout.', 'efipay'),
                    'default' => __('Efipay', 'efipay')
                ),
                'currency' => array(
                    'title' => __('Moneda', 'efipay'),
                    'type' => 'select',
                    'options' => array('COP' => 'COP', 'USD' => 'USD', 'EUR' => 'EUR'),
                    'description' => __('Selecciona la moneda con la cual se realizará el pago.', 'efipay')
                ),
                'api_key' => array(
                    'title' => __('API Key', 'efipay'),
                    'type' => 'text',
                    'description' => __('Llave que sirve para encriptar la comunicación con Efipay.', 'efipay')
                ),
				'token' => array(
                    'title' => __('Token Efipay', 'efipay'),
                    'type' => 'text',
                    'description' => __('Token que sirve para encriptar la comunicación con Efipay.', 'efipay')
                ),
                'office_id' => array(
                    'title' => __('Comercio ID', 'efipay'),
                    'type' => 'text',
                    'description' => __('ID de tu comercio Efipay.', 'efipay')
                ),
                'test' => array(
                    'title' => __('Transacciones en modo de prueba', 'efipay'),
                    'type' => 'checkbox',
                    'label' => __('Habilita las transacciones en modo de prueba.', 'efipay'),
                    'default' => 'no'
                ),
                'response_page' => array(
                    'title' => __('Página de respuesta'),
                    'type' => 'text',
                    'description' => __('URL de la página mostrada después de finalizar el pago.', 'efipay'),
                    'default' => __('https://su.dominio.com/response', 'efipay')
                ),
				'await_page' => array(
                    'title' => __('Página de espera'),
                    'type' => 'text',
                    'description' => __('URL de la página que recibe la respuesta definitiva sobre los pagos.', 'efipay'),
                    'default' => __('https://su.dominio.com/await', 'efipay')
                ),
                'confirmation_page' => array(
                    'title' => __('Página de confirmación'),
                    'type' => 'text',
                    'description' => __('URL de la página que recibe la respuesta definitiva sobre los pagos.', 'efipay'),
                    'default' => __('https://su.dominio.com/confirmation', 'efipay')
				)
            );
        }

		public function admin_options() {
			echo "<div style='text-align: start;'>";
			echo "<div style='text-align: center;'>";
			echo      "<img src=\"" . $this->icon . "\" style='object-fit: cover;width: 200px;'></img>";
			echo "</div>";
			$this->generate_settings_html();
			echo "</table>";
			echo "</div>";
		}

        public function receipt_page($order) {
			
			$order =  wc_get_order($order);
			if($order){
				echo "<div style='text-align: center;'>";
				echo     "<img src=\"" . $this->icon . "\" style='object-fit: cover;width: 200px;'></img>";
				echo "</div>";
				echo '<p>' . __('Gracias por su pedido, haga clic en el botón para continuar el pago con Efipay.', 'efipay') . '</p>';
				echo $this->generate_efipay_form($order);
			}
        }

		public function get_params_post($order_id): array {
            $order = wc_get_order($order_id);
            $currency = get_woocommerce_currency();
            $amount = number_format(($order->get_total()), 2, '.', '');
            $description = implode(',', array_column($order->get_items(), 'name'));
            if (strlen($description) > 255) {
                $description = substr($description, 0, 240) . ' y otros...';
            }

            $test = $this->test ? 1 : 0;
			$parameters_args = [
				"payment"  => [
					"description" => 'Pago Plugin Woocommerce',
					"amount" => $amount,
					"currency_type" => $currency,
					"checkout_type" => "redirect"
				],
				"advanced_options" => [
					"limit_date" => date('Y-m-d', strtotime('+1 day')),
					"references" => [
						"".$order->id.""
					],
					"result_urls" => [
						"approved" => $this->confirmation_page,
						"rejected" => $this->response_page,
						"pending" => $this->await_page,
						"webhook" => home_url().'/wp-json/efipay/v1/webhook'
					],
					"has_comments" => true,
					"comments_label" => "Aqui tu comentario"
				],
				"office" => $this->office_id
			];
            return $parameters_args;
        }

		public function generate_efipay_form($order_id) {
			// Obtener los parámetros para el formulario de pago
			$parameters_args = $this->get_params_post($order_id);
		
			// Construir el objeto de datos para enviar
			$data = json_encode($parameters_args);
		
			// Construir el formulario de pago
			ob_start();
			?>
			<form id="efipay_form">
				<?php wp_nonce_field( 'efipay_form_submit', '_efipay_nonce' ); ?>
				<input type="hidden" name="data" value="<?php echo htmlentities($data); ?>">
				<input type="submit" id="submit_efipay" value="<?php echo esc_html__('Pagar', 'efipay'); ?>" style="
					background-color: #4CAF50;
					color: white;
					padding: 12px 20px;
					margin: 8px 0;
					border: none;
					border-radius: 10px;
					cursor: pointer;
					font-size: 16px;
					text-align: center;
					text-decoration: none;
					display: inline-block;
					transition-duration: 0.4s;
					width: 100%;
					box-sizing: border-box;
				">
			</form>
			<?php
			$form_html = ob_get_clean();
		
			// Agregar un script JavaScript para manejar el envío del formulario
			ob_start();
			?>
			<script>
			document.getElementById("efipay_form").addEventListener("submit", function(event) {
				event.preventDefault();
		
				// Obtener el token CSRF del formulario
				var csrfToken = document.querySelector('input[name="_efipay_nonce"]').value;
		
				// Obtener los datos del formulario
				var formData = new FormData(this);
		
				// Agregar el token CSRF a los datos del formulario
				formData.append("_efipay_nonce", csrfToken);
		
				// Obtener el objeto de datos JSON
				var jsonData = JSON.parse(formData.get("data"));
		
				fetch("https://sag.efipay.co/api/v1/payment/generate-payment", {
					method: "POST",
					headers: {
						"Content-Type": "application/json",
						"Accept": "application/json",
						"Authorization": "Bearer <?php echo esc_js($this->api_key); ?>"
					},
					body: JSON.stringify(jsonData)
				})
				.then(response => {
					if (!response.ok) {
						throw new Error("Error en la respuesta del servidor: " + response.statusText);
					}
					return response.json();
				})
				.then(data => {
					if (data.saved) {
						// Redirigir al usuario a la URL devuelta en la respuesta
						window.open(data.url);
					} else {
						console.error("Error en la respuesta del servidor");
					}
				})
				.catch(error => {
					console.error("Error en la solicitud:", error);
				});
			});
			</script>
			<?php
			$script = ob_get_clean();
		
			return $form_html . $script;
		}
		

		public function process_payment($order_id) {
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

	$order_id = $checkout['payment_gateway']['advanced_option']['references'][0];
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
			case 'Pending':
				$order->update_status('on-hold');
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
