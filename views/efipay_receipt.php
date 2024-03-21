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
        <div class="w-lg-75 card p-3">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="flexRadioDefault" id="selectOther" aria-expanded="false" aria-controls="collapseCreditCard">
                <label class="form-check-label" for="selectOther">
                    Pagar con otro medio de pago
                </label>
            </div>   
            <div class="form-check">
                <input class="form-check-input" type="radio" name="flexRadioDefault" id="selectCreditCard" aria-expanded="false" aria-controls="collapseCreditCard">
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
                        <label for="payment_card_number" class="form-label">Número de la tarjeta</label>
                        <input type="number" class="form-control" id="payment_card_number" name="number" aria-describedby="emailHelp" >
                    </div>
                    <div class="mb-3">
                        <label for="payment_card_name" class="form-label">Titular de la tarjeta</label>
                        <input type="text" class="form-control" id="payment_card_name" name="name"  >
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo</label>
                        <input type="email" class="form-control" id="email" name="email"  >
                    </div>
                    <div class="mb-3 row">
                        <div class="col-lg-6">
                            <label for="payment_card_cvv" class="form-label">CVV</label>
                            <input type="number" class="form-control" id="payment_card_cvv" name="cvv"  >
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
                <button type="submit" id="submit_efipay" class="btn btn-success w-100" disabled>Pagar
                <div id="spinner" class="spinner-border spinner-border-sm text-white" role="status" style="display: none;">
                    <span class="visually-hidden">Loading...</span>
                </div>

                </button>
                
            </div>


            </form>
        </div>
    </div>

</div>


<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<script>

function showSpinner() {
    $('#spinner').show();
    $('#spinner').show();
    $('#submit_efipay').prop('disabled', true);
}

function hideSpinner(){
    $('#spinner').hide();
    $('#submit_efipay').prop('disabled', false);
}


$(document).ready(function(){
    $('#selectOther').click(function() {
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
        }
    });

    $('#selectCreditCard').click(function() {
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
            .then(data => {
                if (data.saved) {
                    // Si es redirect
                    if($('#selectOther').is(':checked')){
                        hideSpinner()
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
                        email: formData.get("email")
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
