<?php
/*
Plugin Name: Efipay Gateway Payment WooCommerce 
Plugin URI: http://www.efipay.com/
Description: Plugin de integracion entre Wordpress-Woocommerce con Efipay
Version: 0.01
Author: Efipay
Author URI: http://www.efipay.com/
*/

add_action('plugins_loaded', 'woocommerce_efipay_gateway', 0);

function woocommerce_efipay_gateway() {
    if (!class_exists('WC_Payment_Gateway')) return;

    class WC_Efipay extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'efipay';
            $this->icon = apply_filters('woocomerce_efipay_icon', plugins_url('/img/logoEfipay.png', __FILE__));
            $this->has_fields = false;
            $this->method_title = 'Efipay';
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

		// Modifica la función admin_options() para incluir el botón que abrirá el modal
		public function admin_options() {
			echo "<div style='text-align: center;'>";
			echo "<div style='text-align: center;'>";
			echo      "<img src=\"" . $this->icon . "\" style='object-fit: cover;width: 200px;'></img>";
			echo "</div>";
			$this->generate_settings_html();
			echo "</table>";
			echo "</div>";
		}

        public function receipt_page($order) {
			$order =  wc_get_order($order);
			echo "<div style='text-align: center;'>";
			echo     "<img src=\"" . $this->icon . "\" style='object-fit: cover;width: 200px;'></img>";
			echo "</div>";
            echo '<p>' . __('Gracias por su pedido, haga clic en el botón para continuar el pago con Efipay.', 'efipay') . '</p>';
            echo $this->generate_efipay_form($order);
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
					"result_urls" => [
						"approved" => $this->confirmation_page,
						"rejected" => $this->response_page,
						"pending" => $this->await_page,
						"webhook" => home_url().'/wp-json/my-plugin/v1/webhook'
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
			$form_html = '<form id="efipay_form">
				<input type="hidden" name="data" value="' . htmlentities($data) . '">
				<input type="submit" id="submit_efipay" value="' . __('Pagar', 'efipay') . '" style="
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
			</form>';
		
			// Agregar un script JavaScript para manejar el envío del formulario
			$script = '<script>
			document.getElementById("efipay_form").addEventListener("submit", function(event) {
				event.preventDefault();
		
				// Obtener los datos del formulario
				var formData = new FormData(this);
		
				// Obtener el objeto de datos JSON
				var jsonData = JSON.parse(formData.get("data"));

				var xhr = new XMLHttpRequest();
				xhr.open("POST", "https://soporte.efipay.co/api/v1/payment/generate-payment");
				xhr.setRequestHeader("Content-Type", "application/json");
				xhr.setRequestHeader("Authorization", "Bearer ' . $this->api_key . '");
		
				xhr.onload = function() {
					if (xhr.status >= 200 && xhr.status < 300) {
						var response = JSON.parse(xhr.responseText);
						if (response.saved) {
							// Redirigir al usuario a la URL devuelta en la respuesta
							window.open(response.url)
						} else {
							console.error("Error en la respuesta del servidor");
						}
					} else {
						console.error(xhr.statusText);
					}
				};
				xhr.send(JSON.stringify(jsonData));
			});
			</script>';
		
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


    function add_efipay($methods) {
        $methods[] = 'WC_Efipay';
        return $methods;
    }


    add_filter('woocommerce_payment_gateways', 'add_efipay');
}

// En tu plugin de WooCommerce, registra un punto final para el webhook
add_action('woocommerce_api_efipay_webhook', 'handle_efipay_webhook');
// deberian ir en una clase
function handle_efipay_webhook() {
    // Verifica si la solicitud POST contiene datos de la transacción
	var_dump($_REQUEST, $_POST);
    $transaction_data = $_POST; // Asegúrate de sanitizar y validar estos datos
	// debugea  los datos del pedido en este punto
	if (!isset($transaction_data['reference'])) {
		die("No se ha recibido información de referencia");
	}

    try {
        // Busca el pedido por su referencia externa (en este caso es "reference")
        $order = wc_get_order(wc_clean($transaction_data['reference']));

        if(!$order){
            die("No se ha encontrado el pedido");
        }

        // Actualiza el estado del pedido según el resultado de la transacción
        switch ($transaction_data['status']) {
            case 'approved':
                $order->update_status('completed');
                break;
            case 'pending':
                $order->update_status('on-hold');
                break;
            default:
                $order->update_status('failed');
        }
        
        // Guarda los detalles de la transacción en el pedido
        $order->add_order_note(__('Efipay transaction details', 'textdomain'));
        foreach ($transaction_data as $key => $value) {
            $order->add_order_note(sprintf("%s: %s", $key, $value));
        }

        // Redirige al usuario a una página de éxito o error
        wp_redirect(add_query_arg('payment_method', 'efipay', get_permalink($order->get_id())));
        exit();
    } catch (Exception $e) {
        // Envía un correo electrónico con los detalles de la excepción
        add_filter('woocommerce_email_attachments', 'efipay_add_exception_to_emails', 10, 3);
        wc_add_notice(sprintf(__('Se ha producido un error inesperado. Por favor, contacta con nosotros para más detalles.')));
        wc_add_notice(sprintf(__('Error procesando el pago: %s', 'textdomain'), $e->getMessage()), 'error');
        remove_action('woocommerce_thankyou', 'woocommerce_output_thanks');
        add_action('woocommerce_thankyou', 'custom_thankyou_page');
    }

    // Aquí procesa los datos de la transacción y actualiza el estado de la orden en WooCommerce
    // Puedes usar la función wc_update_order_status() para actualizar el estado de la orden

    // Ejemplo:
    $order_id = $transaction_data['order_id'];
    $order = wc_get_order($order_id);
    $order->update_status('completed', __('Pago completado a través de efipay.', 'tu-plugin'));
}

function woocommerce_output_thanks(){
	echo '<div class="alert alert-success">';
	esc_html_e('Gracias por tu compra! Tu pedido se ha enviado correctamente. Te estaremos informando sobre cualquier cambio del estado del mismo.');
}
function custom_thankyou_page(){
	wc_get_template('checkout/thankyou.php');
}
// deberian ir en una clase

add_action( 'rest_api_init', 'register_webhook_route' );

/**
 * Register the webhook route.
 */
function register_webhook_route() {
	register_rest_route( 'efipay/v1', '/webhook', array(
		'methods'  => 'POST',
        'callback' => 'handle_efipay_webhook',
	));
}
// /**
//  * Callback function for processing webhook requests.
//  *
//  * @param WP_REST_Request $request The REST request object.
//  * @return WP_REST_Response|WP_Error Response object or error.
//  */
// function handle_webhook_request( $request ) {
// 	// Verificar la autenticidad de la solicitud, por ejemplo, mediante la verificación de una clave secreta.
// 	// $secret_key = 'your_secret_key';
// 	// $provided_key = $request->get_header( 'X-Secret-Key' );

// 	// if ( $provided_key !== $secret_key ) {
// 	// 	return new WP_Error( 'unauthorized', 'Unauthorized request.', array( 'status' => 401 ) );
// 	// }

// 	// // Recuperar los datos de la solicitud.
// 	// $order_id = $request->get_param( 'order_id' );
// 	// $new_status = $request->get_param( 'new_status' );

// 	// // Actualizar el estado de la orden en WooCommerce.
// 	// $order = wc_get_order( $order_id );

// 	// if ( ! $order ) {
// 	// 	return new WP_Error( 'invalid_order', 'Invalid order ID.', array( 'status' => 404 ) );
// 	// }

// 	// $order->update_status( $new_status );

// 	return new WP_REST_Response( 'Order status updated successfully.', 200 );
// }

// add_action( 'rest_api_init', 'register_webhook_route' );
// /**
//  * Register the webhook route.
//  */
// function register_webhook_route() {
// 	echo WP_REST_Server::CREATABLE;
// 	register_rest_route( 'my-plugin/v1', '/webhook', array(
// 		'methods'  => WP_REST_Server::CREATABLE,
// 		'callback' => 'handle_webhook_request',
// 	) );
// }
