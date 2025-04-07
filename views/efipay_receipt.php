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


<div class="efipay-container-payment-methods">
    <div class="efipay-container-logo">
        <img class="efipay-logo" src="<?php echo esc_url($this->icon); ?>"></img>
    </div>
    
    <div id="efipayPaymentsContainerForms">
        
    </div>
</div>

<script src="<?php echo plugins_url('../js/jquery-3.7.1.min.js', __FILE__); ?>"></script>
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
    $('#efipay-spinner').show();
    $('#efipay-spinner').show();
    $('#submit_efipay').prop('disabled', true);
}

function hideSpinner(){
    $('#efipay-spinner').hide();
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
                        number: $("#efipay_payment_card_number").val(),
                        name: $("#efipay_payment_card_name").val(),
                        expiration_date: $('#efipay_payment_card_expiration_date').val(),
                        cvv: $('#efipay_payment_card_cvv').val(),
                        identification_type: $('#efipay_payment_card_identification_type').val(),
                        id_number: $('#efipay_payment_card_id_number').val(),
                        installments: $('#efipay_payment_card_installments').val(),
                        dialling_code:"+" + $('#efipay_payment_card_dialling_code').val(),
                        cellphone: $('#efipay_payment_card_cellphone').val(),
                    };
                    var customerPayer = {
                        name: $("#efipay_payment_card_name").val(),
                        email: $('#efipay_payment_card_email').val(),
                        address_1: $('#efipay_payment_card_address_1').val(),
                        address_2: $('#efipay_payment_card_address_2').val(),
                        city: $('#efipay_payment_card_city').val(),
                        state: $('#efipay_payment_card_state').val(),
                        zip_code: $('#efipay_payment_card_zip_code').val(),
                        country: $('#efipay_payment_card_country').val(),
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
        setTimeout(function() {
            if ($('.accordion-item-efipay').hasClass('efipay-active')) {
                $('#submit_efipay').prop('disabled', false);
                // Agregar los atributos required a los campos de tarjeta de crédito
                $('#efipay_payment_card_number').attr('required', true);
                $('#efipay_payment_card_name').attr('required', true);
                $('#efipay_payment_card_cvv').attr('required', true);
                $('#efipay_payment_card_expiration_date').attr('required', true);
                $('#efipay_payment_card_identification_type').attr('required', true);
                $('#efipay_payment_card_id_number').attr('required', true);
                $('#efipay_payment_card_installments').attr('required', true);
                $('#efipay_payment_card_dialling_code').attr('required', true);
                $('#efipay_payment_card_cellphone').attr('required', true);
    
                $('#efipay_payment_card_email').attr('required', true);
                $('#efipay_payment_card_address_1').attr('required', true);
                $('#efipay_payment_card_address_2').attr('required', true);
                $('#efipay_payment_card_city').attr('required', true);
                $('#efipay_payment_card_state').attr('required', true);
                $('#efipay_payment_card_zip_code').attr('required', true);
                $('#efipay_payment_card_country').attr('required', true);
            }
        }, 500);
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
    $('#efipay_payment_card_dialling_code').html(selectOptions);

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
        $('#efipay_payment_card_country').html(optionsCountry);

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

function toggleAccordion(element) {
    let item = element.parentElement;
    let isActive = item.classList.contains("efipay-active");

    document.querySelectorAll(".accordion-item-efipay").forEach(i => i.classList.remove("efipay-active"));

    if (!isActive) {
        item.classList.add("efipay-active");
    }
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
            <div class="accordion-efipay">
                <div class="accordion-item-efipay efipay-border-radius" onClick="generatePayment('redirect', <?php echo htmlentities($data); ?>)" >
                    <div class="accordion-header-efipay efipay-border-radius efipay-button-accordion-item" onclick="toggleAccordion(this)">
                        <div class="efipay-container-items">
                            <img src="<?php echo plugins_url('../img/icon-redirect.svg', __FILE__); ?>"></img>
                            Pagar en nuestro check-out
                        </div>
                        <svg width="14px" height="14px" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Capa_1" x="0px" y="0px" width="306px" height="306px" viewBox="0 0 306 306" style="enable-background:new 0 0 306 306;" xml:space="preserve">
                            <g>
                                <g id="keyboard-arrow-right">
                                    <polygon points="58.65,267.75 175.95,153 58.65,35.7 94.35,0 247.35,153 94.35,306   "/>
                                </g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                        </svg>
                    </div>
                </div>   
        `;
        if (paymentsAvailable.credit && enabledEmbebed === 'yes') {
            paymentsAvailableHtml += `
                <div class="accordion-item-efipay efipay-border-radius">
                    <div class="accordion-header-efipay efipay-border-radius efipay-button-accordion-item" onclick="toggleAccordion(this)">
                        <div class="efipay-container-items" id="efipaySelectCreditCard">
                            <img src="<?php echo plugins_url('../img/icon-enbbeded.svg', __FILE__); ?>"></img>
                            Pagar con tarjeta de crédito o débito
                        </div>
                        <svg class="arrow-efipay" width="14px" height="14px" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Capa_1" x="0px" y="0px" width="306px" height="306px" viewBox="0 0 306 306" style="enable-background:new 0 0 306 306;" xml:space="preserve">
                            <g>
                                <g id="keyboard-arrow-right">
                                    <polygon points="58.65,267.75 175.95,153 58.65,35.7 94.35,0 247.35,153 94.35,306   "/>
                                </g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                            <g>
                            </g>
                        </svg>
                    </div>
                    <div class="accordion-content-efipay">
                        <form class="form-efipay" id="efipay_form">
                            <label for="efipay_payment_card_name">Titular de la tarjeta</label>
                            <input type="text" id="efipay_payment_card_name" name="name"  >

                            <label for="efipay_payment_card_number">Número de la tarjeta</label>
                            <input type="number" id="efipay_payment_card_number" name="number" aria-describedby="emailHelp" >

                            <label for="efipay_payment_card_expiration_date">Fecha expiración</label>
                            <input type="month" id="efipay_payment_card_expiration_date" name="expiration_date" placeholder="YY-MM" autocomplete="cc-exp" >

                            <label for="efipay_payment_card_cvv">CVV</label>
                            <input type="password" id="efipay_payment_card_cvv" name="cvv"  >

                            <label for="efipay_payment_card_installments">Cantidad de cuotas</label>
                            <select id="efipay_payment_card_installments" name="installments" >
                                <?php for ($i = 1; $i <= 24; $i++) { ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php } ?>
                            </select>

                            <label for="efipay_payment_card_identification_type">Tipo de identificación</label>                                    
                            <div style="display: flex;">
                                <select id="efipay_payment_card_identification_type" name="identification_type" style="border-radius: 8px 0 0 8px !important; border-right: 1px solid #6f6f6b !important; flex-grow: 1;">
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

                                <input type="number" id="efipay_payment_card_id_number" name="id_number"  >
                            </div>

                            <div class="d-flex" style="display: flex;">                                             
                                <div>
                                    <label for="efipay_payment_card_dialling_code">Indicativo</label>
                                    <select id="efipay_payment_card_dialling_code" name="dialling_code" style="border-radius: 8px 0 0 8px !important; border-right: 1px solid #6f6f6b !important;">
                                        <!-- Opciones de indicativo se agregarán aquí dinámicamente -->
                                    </select>
                                </div>

                                <div style="flex-grow: 1;">
                                    <label for="efipay_payment_card_cellphone">Número de celular</label>
                                    <input type="number" id="efipay_payment_card_cellphone" name="cellphone" >
                                </div>
                            </div>
                            
                            <label for="efipay_payment_card_email">Correo electrónico</label>
                            <input type="email" id="efipay_payment_card_email" name="email"  >
                                                
                            <label for="efipay_payment_card_country">Country</label>
                            <select id="efipay_payment_card_country" name="country" >
                                <!-- Opciones de countries se agregarán aquí dinámicamente -->
                            </select>
                        
                            <label for="efipay_payment_card_state">State</label>
                            <input type="text" id="efipay_payment_card_state" name="state"  >
                                                                
                            <label for="efipay_payment_card_city">City</label>
                            <input type="text" id="efipay_payment_card_city" name="city"  >
                        
                            <label for="efipay_payment_card_address_1">Address 1</label>
                            <input type="text" id="efipay_payment_card_address_1" name="address_1"  >
                        
                            <label for="efipay_payment_card_address_2">Address 2</label>
                            <input type="text" id="efipay_payment_card_address_2" name="address_2"  >
                        
                            <label for="efipay_payment_card_zip_code">Zip Code</label>
                            <input type="text" id="efipay_payment_card_zip_code" name="zip_code"  >

                            <button onClick="generatePayment('api', <?php echo htmlentities($data); ?>)" id="submit_efipay" disabled>
                                Pagar

                                <span id="efipay-spinner" class="efipay-loader" style="display: none;"></span>
                            </button>
                        </form>
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
