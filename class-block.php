<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Efipay_Blocks extends AbstractPaymentMethodType {

    private $gateway;
    protected $name = 'efipay';// your payment gateway name

    public function __construct( $gateway_instance ) {
        $this->gateway = $gateway_instance;
    }

    public function initialize() {
        $this->settings = get_option( 'woocommerce_efipay_settings', [] );
    }

    

    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {

        wp_register_script(
            'efipay-blocks-integration',
            plugin_dir_url(__FILE__) . 'checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );
        if( function_exists( 'wp_set_script_translations' ) ) {            
            wp_set_script_translations( 'efipay-blocks-integration');
            
        }
        return [ 'efipay-blocks-integration' ];
    }

    public function get_payment_method_data() {
        return [
            'title' => "Efipay",
            'description' => "Al realizar un pago con EfiPay, podrás recibir notificaciones por SMS y correo electrónico sobre el estado de tu compra
            Al realizar la compra con este método de pago, aceptas nuestras Condiciones y Política de Privacidad.",
            'icon' => plugin_dir_url(__FILE__) . './img/logoEfipay.png', 

        ];
    }

}
?>