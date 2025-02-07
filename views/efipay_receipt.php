<?php
$order_id = $order;
// Obtener los parámetros para el formulario de pago
$parameters_args = $this->get_params_post($order_id);

// Construir el objeto de datos para enviar
$data = json_encode($parameters_args);

$names_url = 'http://country.io/names.json';
$names = file_get_contents($names_url);

$phone_url = 'http://country.io/phone.json';
$phone = file_get_contents($phone_url);

?>


<div class="py-5 px-4 efipay-container-payment-methods">
    <div class="text-center">
        <img class="mb-3 efipay-logo" src="<?php echo esc_url($this->icon); ?>"></img>
    </div>
    
    <div id="efipayPaymentsContainerForms">
        
    </div>
</div>

<link rel="stylesheet" href="<?php echo plugins_url('../css/bootstrap.min.css', __FILE__); ?>">
<script src="<?php echo plugins_url('../js/jquery-3.7.1.min.js', __FILE__); ?>"></script>
<script src="<?php echo plugins_url('../js/bootstrap.bundle.min.js', __FILE__); ?>"></script>
<script src="<?php echo plugins_url('../js/sweetalert2@11.js', __FILE__); ?>"></script>

<script>
const apiKey = "<?php echo esc_js($this->api_key); ?>";
const enabledEmbebed = "<?php echo esc_js($this->enabled_embebed); ?>";
// Ejemplo de función que llama a una función de WooCommerce para vaciar el carrito
function clearCart() {
    // Realizar una solicitud AJAX
    $.ajax({
        url: '<?php echo admin_url('admin-ajax.php'); ?>',
        type: 'POST',
        data: {
            'action': 'clear_cart_ajax' // Nombre de la acción de WordPress
        },
        success: function(response) {
            console.log('El carrito se ha vaciado correctamente.');
        },
        error: function(xhr, status, error) {
            console.error('Error al vaciar el carrito:', error);
        }
    });
}

function showSpinner() {
    $('#spinner').show();
    $('#spinner').show();
    $('#submit_efipay').prop('disabled', true);
}

function hideSpinner(){
    $('#spinner').hide();
    $('#submit_efipay').prop('disabled', false);
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function generatePayment(type, data) {
    if(type === "api") data.payment.checkout_type = "api"
    
    if(['redirect', 'api'].includes(type)){
        showSpinner()

        fetch("https://sag.efipay.co/api/v1/payment/generate-payment", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "Authorization": "Bearer <?php echo esc_js($this->api_key); ?>"
            },
            body: JSON.stringify(data)
        })
        .then(response => {
            if (!response.ok) {
                if (response.status === 422) {
                    return response.json().then(errorData => {
                        throw new Error(JSON.stringify(errorData));
                    });
                } else {                
                    throw new Error("Error en la respuesta del servidor, revisa tu configuración de pagos o comunícate con soporte@efipay.co");
                }
            }
            return response.json();
        })
        .then(async data => {
            if (data.saved) {
                // Si es redirect
                if(type === "redirect"){
                    hideSpinner()
                    await clearCart()
                    window.location.href = data.url;
                } else {
                    // Si es API
                    var paymentData = {
                        number: $("#payment_card_number").val(),
                        name: $("#payment_card_name").val(),
                        expiration_date: $('#payment_card_expiration_date').val(),
                        cvv: $('#payment_card_cvv').val(),
                        identification_type: $('#payment_card_identification_type').val(),
                        id_number: $('#payment_card_id_number').val(),
                        installments: $('#payment_card_installments').val(),
                        dialling_code:"+" + $('#payment_card_dialling_code').val(),
                        cellphone: $('#payment_card_cellphone').val(),
                    };
                    var customerPayer = {
                        name: $("#payment_card_name").val(),
                        email: $('#payment_card_email').val(),
                        address_1: $('#payment_card_address_1').val(),
                        address_2: $('#payment_card_address_2').val(),
                        city: $('#payment_card_city').val(),
                        state: $('#payment_card_state').val(),
                        zip_code: $('#payment_card_zip_code').val(),
                        country: $('#payment_card_country').val(),
                    }
                    var payment = {
                        id: data.payment_id,
                        token: data.token,
                    }

                    var send_data = {
                        payment: payment,
                        customer_payer: customerPayer,
                        payment_card: paymentData
                    }

                    fetch("https://sag.efipay.co/api/v1/payment/transaction-checkout", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "Accept": "application/json",
                            "Authorization": "Bearer <?php echo esc_js($this->api_key); ?>"
                        },
                        body: JSON.stringify(send_data)
                    }).then(response => {
                        if (!response.ok) {
                            if (response.status === 422) {
                                return response.json().then(errorData => {
                                    throw new Error(JSON.stringify(errorData.errors));
                                });
                            } else {
                                throw new Error("Error en la respuesta del servidor, revisa tu configuración de pagos o comunícate con soporte@efipay.co");
                            }
                        }
                        return response.json();
                    }).then(async data_payment => {
                        hideSpinner()
                        await Swal.fire({
                            icon: data_payment.transaction.status === 'Aprobada' ? 'success' : data_payment.transaction.status === 'Rechazada' ? 'error' : 'warning',
                            title: 'Estado',
                            text: 'Estado de la transacción: ' + data_payment.transaction.status + `${data_payment.transaction.status !== 'Aprobada' ? ', Intenta nuevamente' : ''}`,
                        });
                        if (data_payment.transaction.status === 'Aprobada') {
                            await clearCart() 
                            window.location.href = "<?php echo home_url(); ?>";
                        }
                    }).catch(error => {

                        let html = '<ul>';
                        if(typeof JSON.parse(error.message) === "object"){
                            for (const prop in JSON.parse(error.message)) {
                            html += '<li>' + JSON.parse(error.message)[prop] + '</li>';
                            }
                            html += '</ul>';
                        }

                        Swal.fire({
                            title: "Error",
                            text: typeof JSON.parse(error.message) === "object" ? ""  : error,
                            html: typeof JSON.parse(error.message) === "object" ? html  : "",
                            icon: "error"
                        });
                        console.error("Error en la solicitud:", error);
                        hideSpinner()
                    });
                }
            } else {
                Swal.fire({
                    title: "Error",
                    text: "Error en la respuesta del servidor, revisa tu configuración de pagos o comunícate con soporte@efipay.co",
                    icon: "error"
                });
                console.error("Error en la respuesta del servidor, revisa tu configuración de pagos o comunícate con soporte@efipay.co");
                hideSpinner()
            }
        })
        .catch(error => {
            let html = '<ul>';
            if(typeof JSON.parse(error.message) === "object"){
                for (const prop in JSON.parse(error.message)) {
                html += '<li>' + JSON.parse(error.message)[prop] + '</li>';
                }
                html += '</ul>';
            }

            Swal.fire({
                title: "Error",
                text: typeof JSON.parse(error.message) === "object" ? ""  : error,
                html: typeof JSON.parse(error.message) === "object" ? html  : "",
                icon: "error"
            });
            
            hideSpinner()
        });
    } else {
        Swal.fire({
            title: "",
            text: "Por favor, selecciona un método de pago.",
            icon: "warning"
        });
        hideSpinner()
    }
}

$(document).ready(async function(){
    if(enabledEmbebed !== 'yes') {
        let data = <?php echo json_encode($data, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;
        await generatePayment('redirect', JSON.parse(data))
    }
    await getPaymentsAvailable()
    await sleep(2000);

    $('#efipaySelectCreditCard').click(function() {
        if ($('#efipayCollapseCreditCard').hasClass('show') || $('#efipayCollapseCreditCard').hasClass('collapsing')) {
            $('#submit_efipay').prop('disabled', false);
            // Agregar los atributos required a los campos de tarjeta de crédito
            $('#payment_card_number').attr('required', true);
            $('#payment_card_name').attr('required', true);
            $('#payment_card_cvv').attr('required', true);
            $('#payment_card_expiration_date').attr('required', true);
            $('#payment_card_identification_type').attr('required', true);
            $('#payment_card_id_number').attr('required', true);
            $('#payment_card_installments').attr('required', true);
            $('#payment_card_dialling_code').attr('required', true);
            $('#payment_card_cellphone').attr('required', true);

            $('#payment_card_email').attr('required', true);
            $('#payment_card_address_1').attr('required', true);
            $('#payment_card_address_2').attr('required', true);
            $('#payment_card_city').attr('required', true);
            $('#payment_card_state').attr('required', true);
            $('#payment_card_zip_code').attr('required', true);
            $('#payment_card_country').attr('required', true);
        }
    });

    // Parsear los datos de nombres y códigos telefónicos
    var names = <?php echo json_encode(json_decode($names), JSON_HEX_TAG); ?>;
    var phoneCodes = <?php echo json_encode(json_decode($phone), JSON_HEX_TAG); ?>;

    // Construir las opciones para el select
    var selectOptions = '<option value="' + phoneCodes["CO"] + '">' + '(+' + phoneCodes["CO"] + ') ' + names["CO"] + '</option>';
    delete names["CO"]; // Eliminar Colombia de la lista para evitar duplicados

    var sortedCountries = Object.keys(names).sort().reduce((obj, key) => {
        obj[key] = names[key];
        return obj;
    }, {});

    for (var code in sortedCountries) {
        var countryName = sortedCountries[code];
        var dialingCode = phoneCodes[code];
        selectOptions += '<option value="' + dialingCode + '">' + '(+' + dialingCode + ') ' + countryName + '</option>';
    }

    // Establecer las opciones dentro del select
    $('#payment_card_dialling_code').html(selectOptions);

    // optener los countries
    getCountries()
});

function getCountries(){
    showSpinner()
    fetch("https://sag.efipay.co/api/v1/resources/get-countries", {
        method: "GET",
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "Authorization": `Bearer ${apiKey}`
        }
    }).then(response =>{
        return response.json()
    }).then(async countries => {
        hideSpinner()
        let optionsCountry = '';
        for (let countryCode in countries) {
            const country = countries[countryCode]; // Accede al objeto país utilizando la clave
            optionsCountry += '<option value="' + country.iso3_code + '">' + country.name + '</option>';
        }
        $('#payment_card_country').html(optionsCountry);

    }).catch(error => {
        hideSpinner()
        Swal.fire({
            title: "Error",
            text: error,
            icon: "error"
        });
        console.error("Error al obtener los countries:", error);
        hideSpinner()
    });
}

async function getPaymentsAvailable() {
    showSpinner();
    fetch("https://sag.efipay.co/api/v1/resources/available-payment-methods", {
        method: "GET",
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "Authorization": `Bearer ${apiKey}`
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(paymentsAvailable => {
        hideSpinner();
        let paymentsAvailableHtml = `
            <div class="accordion" id="efipayPaymentMethodsaccordion">
                <div class="accordion-item efipay-border-radius border-0 mb-3">
                    <div class="accordion-header" id="headingOne">
                        <button 
                            onClick="generatePayment('redirect', <?php echo htmlentities($data); ?>)" 
                            class="accordion-button efipay-button-accordion-item collapsed efipay-border-radius" 
                            type="button" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#" 
                            aria-expanded="true" 
                            aria-controls="collapseOne"
                        >
                            <img class="img-fluid me-3" src="<?php echo plugins_url('../img/icon-redirect.svg', __FILE__); ?>"></img>
                            Pagar en nuestro check-out
                        </button>
                    </div>
                </div>    
        `;
        if (paymentsAvailable.credit && enabledEmbebed === 'yes') {
            paymentsAvailableHtml += `
                <div class="accordion-item efipay-border-radius border-0 mb-3">
                    <div class="accordion-header" id="headingTwo">
                        <button 
                            id="efipaySelectCreditCard" 
                            class="accordion-button efipay-button-accordion-item collapsed efipay-border-radius" 
                            type="button" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#efipayCollapseCreditCard" 
                            aria-expanded="false" 
                            aria-controls="efipayCollapseCreditCard"
                        >
                            <img class="img-fluid me-3" src="<?php echo plugins_url('../img/icon-enbbeded.svg', __FILE__); ?>"></img>
                            Pagar con tarjeta de crédito o debito
                        </button>
                    </div>
                    <div id="efipayCollapseCreditCard" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#efipayPaymentMethodsaccordion">
                        <div class="accordion-body">
                            <form id="efipay_form">
                                <?php wp_nonce_field( 'efipay_form_submit', '_efipay_nonce' ); ?>
                                <input type="hidden" name="data" value="<?php echo htmlentities($data); ?>">

                                <div class="mb-3">
                                    <div>
                                        <div class="mb-3">
                                            <label for="payment_card_name" class="form-label">Titular de la tarjeta</label>
                                            <input type="text" class="form-control" id="payment_card_name" name="name"  >
                                        </div>
                                        <div class="mb-3">
                                            <label for="payment_card_number" class="form-label">Número de la tarjeta</label>
                                            <input type="number" class="form-control" id="payment_card_number" name="number" aria-describedby="emailHelp" >
                                        </div>
                                        <div class="mb-3">
                                            <label for="payment_card_expiration_date" class="form-label">Fecha expiración</label>
                                            <input type="month" id="payment_card_expiration_date" name="expiration_date" class="form-control" placeholder="YY-MM" autocomplete="cc-exp" >
                                        </div>
                                        <div class="mb-3">
                                            <label for="payment_card_cvv" class="form-label">CVV</label>
                                            <input type="password" class="form-control" id="payment_card_cvv" name="cvv"  >
                                        </div>
                                        <div class="mb-3">
                                            <label for="payment_card_installments" class="form-label">Cantidad de cuotas</label>
                                            <select class="form-select" id="payment_card_installments" name="installments" >
                                                <?php for ($i = 1; $i <= 24; $i++) { ?>
                                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="payment_card_identification_type" class="form-label">Tipo de identificación</label>
                                            
                                            <div class="d-flex">
                                                <select class="form-select flex-grow-1" id="payment_card_identification_type" name="identification_type" style="border-radius: 8px 0 0 8px !important; border-right: 1px solid #6f6f6b !important;">
                                                    <option value="CC">CC</option>
                                                    <option value="CE">CE</option>
                                                    <option value="TI">TI</option>
                                                    <option value="PA">PA</option>
                                                    <option value="PEP">PEP</option>
                                                    <option value="PPT">PPT</option>
                                                    <option value="NIT">NIT</option>
                                                    <option value="Pasaporte">Pasaporte</option>
                                                    <option value="Otro">Otro</option>
                                                </select>

                                                <input type="number" class="form-control" id="payment_card_id_number" name="id_number"  >
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="d-flex">                                             
                                                <div class="">
                                                    <label for="payment_card_dialling_code" class="form-label">Indicativo</label>
                                                    <select class="form-select" id="payment_card_dialling_code" name="dialling_code" style="border-radius: 8px 0 0 8px !important; border-right: 1px solid #6f6f6b !important;">
                                                        <!-- Opciones de indicativo se agregarán aquí dinámicamente -->
                                                    </select>
                                                </div>

                                                <div class="flex-grow-1">
                                                    <label for="payment_card_cellphone" class="form-label">Número de celular</label>
                                                    <input type="number" class="form-control" id="payment_card_cellphone" name="cellphone" >
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="payment_card_email" class="form-label">Correo electrónico</label>
                                            <input type="email" class="form-control" id="payment_card_email" name="email"  >
                                        </div>
                                        <div class="mb-3">                         
                                            <label for="payment_card_country" class="form-label">Country</label>
                                            <select class="form-select" id="payment_card_country" name="country" >
                                                <!-- Opciones de countries se agregarán aquí dinámicamente -->
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="payment_card_state" class="form-label">State</label>
                                            <input type="text" class="form-control" id="payment_card_state" name="state"  >
                                        </div>
                                        <div class="mb-3">                                       
                                            <label for="payment_card_city" class="form-label">City</label>
                                            <input type="text" class="form-control" id="payment_card_city" name="city"  >
                                        </div>
                                        <div class="mb-3">
                                            <label for="payment_card_address_1" class="form-label">Address 1</label>
                                            <input type="text" class="form-control" id="payment_card_address_1" name="address_1"  >
                                        </div>
                                        <div class="mb-3">
                                            <label for="payment_card_address_2" class="form-label">Address 2</label>
                                            <input type="text" class="form-control" id="payment_card_address_2" name="address_2"  >
                                        </div>
                                        <div class="mb-3">
                                            <label for="payment_card_zip_code" class="form-label">Zip Code</label>
                                            <input type="text" class="form-control" id="payment_card_zip_code" name="zip_code"  >
                                        </div>                                      
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button onClick="generatePayment('api', <?php echo htmlentities($data); ?>)" id="submit_efipay" class="btn fw-bold text-white" style="background-color: #6243ff;" disabled>
                                        Pagar
                                        <div id="spinner" class="spinner-border spinner-border-sm text-white" role="status" style="display: none;">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;
        }

        paymentsAvailableHtml += `</div>`;

        document.getElementById('efipayPaymentsContainerForms').innerHTML = paymentsAvailableHtml;
    })
    .catch(error => {
        hideSpinner();
        Swal.fire({
            title: "Error",
            text: error.message,  // Mostrar el mensaje del error
            icon: "error"
        });
        console.error("Error al obtener los métodos de pago:", error);
    });
}
</script>

<style>
    @media (min-width: 992px) {
        .w-lg-75 {
            width: 75%;
        }

    }

    input[type=number]{
        min-width: auto !important;
    }
    input[type=month]{
        min-width: auto !important;
    }
</style>
