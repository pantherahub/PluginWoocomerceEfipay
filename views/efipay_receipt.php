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

<div class="container">
    <div class="text-center">
        <img class="mb-3" src="<?php echo esc_url($this->icon); ?>" style='object-fit: cover;width: 200px;'></img>
    </div>


    <div class="w-100 d-flex justify-content-center">
        <div class="w-lg-75 card p-3" id="payments">
           
        </div>
    </div>

</div>


<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
<script
  src="https://code.jquery.com/jquery-3.7.1.min.js"
  integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo="
  crossorigin="anonymous"></script><script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

$(document).ready(async function(){
    await getPaymentsAvailable()
    await sleep(2000);
    $('#selectOther').click(function() {
        console.log('event Success')
        if(!$('#collapseCreditCard').hasClass('collapsing')){
            $('#collapseCreditCard').collapse('hide');
            $('#submit_efipay').prop('disabled', false);
            // Quitar los atributos required de los campos de tarjeta de crédito
            $('#payment_card_number').removeAttr('required');
            $('#payment_card_name').removeAttr('required');
            $('#payment_card_cvv').removeAttr('required');
            $('#payment_card_expiration_date').removeAttr('required');
            $('#payment_card_identification_type').removeAttr('required');
            $('#payment_card_id_number').removeAttr('required');
            $('#payment_card_installments').removeAttr('required');
            $('#payment_card_dialling_code').removeAttr('required');
            $('#payment_card_cellphone').removeAttr('required');

            $('#payment_card_email').removeAttr('required');
            $('#payment_card_address_1').removeAttr('required');
            $('#payment_card_address_2').removeAttr('required');
            $('#payment_card_city').removeAttr('required');
            $('#payment_card_state').removeAttr('required');
            $('#payment_card_zip_code').removeAttr('required');
            $('#payment_card_country').removeAttr('required');
        }
    });

    $('#selectCreditCard').click(function() {
        console.log('event Success')
        if (!$('#collapseCreditCard').hasClass('show') && !$('#collapseCreditCard').hasClass('collapsing')) {
            $('#collapseCreditCard').collapse('show');
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
        console.log(code)
        selectOptions += '<option value="' + dialingCode + '">' + '(+' + dialingCode + ') ' + countryName + '</option>';
    }

    // Establecer las opciones dentro del select
    $('#payment_card_dialling_code').html(selectOptions);

    // optener los countries
    getCountries()
    document.getElementById("efipay_form").addEventListener("submit", function(event) {
        event.preventDefault();


            var formData = new FormData(this);
            var jsonData = JSON.parse(formData.get("data"));
            if($('#selectCreditCard').is(':checked') || $('#selectOther').is(':checked')){

                if($('#selectCreditCard').is(':checked')){jsonData.payment.checkout_type = "api";}

                showSpinner()

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
                    throw new Error("Error en la respuesta del servidor, revisa tu configuración de pagos o comunícate con soporte@efipay.co");
                }
                return response.json();
            })
            .then(async data => {
                if (data.saved) {
                    // Si es redirect
                    if($('#selectOther').is(':checked')){
                        hideSpinner()
                        await clearCart()
                        window.location.href = data.url;
                    }
                    else{
                    // Si es API
                    var paymentData = {
                        number: formData.get("number"),
                        name: formData.get("name"),
                        expiration_date: formData.get("expiration_date"),
                        cvv: formData.get("cvv"),
                        identification_type: formData.get("identification_type"),
                        id_number: formData.get("id_number"),
                        installments: formData.get("installments"),
                        dialling_code:"+" + formData.get("dialling_code"),
                        cellphone: formData.get("cellphone")
                    };
                    var customerPayer = {
                        name: formData.get("name"),
                        email: formData.get("email"),
                        address_1 : formData.get('address_1'),
                        address_2 : formData.get('address_2'),
                        city : formData.get('city'),
                        state : formData.get('state'),
                        zip_code : formData.get('zip_code'),
                        country : formData.get('country'),
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
                Swal.fire({
                                title: "Error",
                                text: error,
                                icon: "error"
                            });
                console.error("Error en la solicitud:", error);
                hideSpinner()
            });
            }
         else {
            Swal.fire({
                title: "",
                text: "Por favor, selecciona un método de pago.",
                icon: "warning"
           });
         hideSpinner()
        }
    });
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
            <div class="form-check">
                <input class="form-check-input" type="radio" name="flexRadioDefault" id="selectOther" aria-expanded="false" aria-controls="collapseCreditCard">
                <label class="form-check-label" for="selectOther">
                    Pagar con otro medio de pago
                </label>
            </div>
        `;
        if (paymentsAvailable.credit && enabledEmbebed === 'yes') {
            paymentsAvailableHtml += `
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="flexRadioDefault" id="selectCreditCard" aria-expanded="false" aria-controls="collapseCreditCard" checked>
                    <label class="form-check-label" for="selectCreditCard">
                        Pagar con tarjeta de crédito
                    </label>
                </div>
                <form id="efipay_form">
                    <?php wp_nonce_field( 'efipay_form_submit', '_efipay_nonce' ); ?>
                    <input type="hidden" name="data" value="<?php echo htmlentities($data); ?>">

                    <div class="collapse mb-3" id="collapseCreditCard">
                        <div class="card card-body">
                            <div class="mb-3">
                                <label for="payment_card_email" class="form-label">Correo</label>
                                <input type="email" class="form-control" id="payment_card_email" name="email"  >
                            </div>

                            <div class="mb-3">
                                <label for="payment_card_address_1" class="form-label">Address 1</label>
                                <input type="text" class="form-control" id="payment_card_address_1" name="address_1"  >
                            </div>

                            <div class="mb-3">
                                <label for="payment_card_address_2" class="form-label">Address 2</label>
                                <input type="text" class="form-control" id="payment_card_address_2" name="address_2"  >
                            </div>

                            <div class="mb-3 row">
                                <div class="col-lg-6">
                                    <label for="payment_card_city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="payment_card_city" name="city"  >
                                </div>
                                <div class="col-lg-6">
                                    <label for="payment_card_state" class="form-label">State</label>
                                    <input type="text" class="form-control" id="payment_card_state" name="state"  >
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <div class="col-lg-6">
                                    <label for="payment_card_country" class="form-label">Country</label>
                                    <select class="form-select" id="payment_card_country" name="country" >
                                        <!-- Opciones de countries se agregarán aquí dinámicamente -->
                                    </select>
                                </div>
                                <div class="col-lg-6">
                                    <label for="payment_card_zip_code" class="form-label">Zip Code</label>
                                    <input type="text" class="form-control" id="payment_card_zip_code" name="zip_code"  >
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="payment_card_number" class="form-label">Número de la tarjeta</label>
                                <input type="number" class="form-control" id="payment_card_number" name="number" aria-describedby="emailHelp" >
                            </div>
                            <div class="mb-3">
                                <label for="payment_card_name" class="form-label">Titular de la tarjeta</label>
                                <input type="text" class="form-control" id="payment_card_name" name="name"  >
                            </div>

                            <div class="mb-3 row">
                                <div class="col-lg-6">
                                    <label for="payment_card_cvv" class="form-label">CVV</label>
                                    <input type="password" class="form-control" id="payment_card_cvv" name="cvv"  >
                                </div>
                                <div class="col-lg-6">
                                    <label for="payment_card_expiration_date" class="form-label">Fecha expiración</label>
                                    <input type="month"  id="cardExpiration" name="expiration_date" class="form-control" placeholder="YY-MM" autocomplete="cc-exp" >
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <div class="col-lg-6">
                                    <label for="payment_card_identification_type" class="form-label">Tipo de identificación</label>
                                    <select class="form-select" id="payment_card_identification_type" name="identification_type" >
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
                                </div>
                                <div class="col-lg-6">
                                    <label for="payment_card_id_number" class="form-label">Número de documento</label>
                                    <input type="number" class="form-control" id="payment_card_id_number" name="id_number"  >
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <div class="col-lg-4">
                                    <label for="payment_card_installments" class="form-label">Cantidad de cuotas</label>
                                    <select class="form-select" id="payment_card_installments" name="installments" >
                                        <?php for ($i = 1; $i <= 24; $i++) { ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-lg-4">
                                    <label for="payment_card_dialling_code" class="form-label">Indicativo</label>
                                    <select class="form-select" id="payment_card_dialling_code" name="dialling_code" >
                                        <!-- Opciones de indicativo se agregarán aquí dinámicamente -->
                                    </select>
                                </div>

                                <div class="col-lg-4">
                                    <label for="payment_card_cellphone" class="form-label">Número de celular</label>
                                    <input type="number" class="form-control" id="payment_card_cellphone" name="cellphone" >
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="position-relative">
                        <button type="submit" id="submit_efipay" class="btn btn-success rounded-pill w-100" disabled>
                            Pagar
                            <div id="spinner" class="spinner-border spinner-border-sm text-white" role="status" style="display: none;">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </button>
                    </div>
                </form>
            `;
        }else{
            paymentsAvailableHtml += `
                <form id="efipay_form">
                    <?php wp_nonce_field( 'efipay_form_submit', '_efipay_nonce' ); ?>
                    <input type="hidden" name="data" value="<?php echo htmlentities($data); ?>">
                    <div class="position-relative">
                        <button type="submit" id="submit_efipay" class="btn btn-success rounded-pill w-100" disabled>
                            Pagar
                            <div id="spinner" class="spinner-border spinner-border-sm text-white" role="status" style="display: none;">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </button>
                    </div>
                </form>
            `
        }
        document.getElementById('payments').innerHTML = paymentsAvailableHtml;

        // Mostrar el colapso después de agregar el contenido
        const collapseElement = document.getElementById('collapseCreditCard');
        if (collapseElement) {
            const bsCollapse = new bootstrap.Collapse(collapseElement, {
                toggle: false
            });
            bsCollapse.show();
        }
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
