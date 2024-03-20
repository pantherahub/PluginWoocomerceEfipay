<?php
$order_id = $order;
// Obtener los parámetros para el formulario de pago
$parameters_args = $this->get_params_post($order_id);

// Construir el objeto de datos para enviar
$data = json_encode($parameters_args);
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
                        <input type="number" class="form-control" id="payment_card_number" name="number" aria-describedby="emailHelp">
                    </div>
                    <div class="mb-3">
                        <label for="payment_card_name" class="form-label">Titular de la tarjeta</label>
                        <input type="text" class="form-control" id="payment_card_name" name="name" aria-describedby="emailHelp">
                    </div>
                    <div class="mb-3 row">
                        <div class="col-lg-6">
                            <label for="payment_card_cvv" class="form-label">CVV</label>
                            <input type="number" class="form-control" id="payment_card_cvv" name="cvv" aria-describedby="emailHelp">
                        </div>
                        <div class="col-lg-6">
                            <label for="payment_card_expiration_date" class="form-label">Fecha expiración</label>
                            <input type="month" name="cardExpiration" id="cardExpiration" name="expiration_date" class="form-control" placeholder="YY-MM" autocomplete="cc-exp" required="">
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <div class="col-lg-6">
                            <label for="payment_card_identification_type" class="form-label">Tipo de identificación</label>
                            <input type="text" class="form-control" id="payment_card_identification_type" name="identification_type" aria-describedby="emailHelp">
                        </div>
                        <div class="col-lg-6">
                            <label for="payment_card_id_number" class="form-label">Número de documento</label>
                            <input type="number" class="form-control" id="payment_card_id_number" name="id_number" aria-describedby="emailHelp">
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <div class="col-lg-4">
                            <label for="payment_card_installments" class="form-label">Cantidad de cuotas</label>
                            <input type="number" class="form-control" id="payment_card_installments" name="installments" aria-describedby="emailHelp">
                        </div>
                        <div class="col-lg-4">
                            <label for="payment_card_dialling_code" class="form-label">Indicativo</label>
                            <input type="number" class="form-control" id="payment_card_dialling_code" name="dialling_code" aria-describedby="emailHelp">
                        </div>
                        <div class="col-lg-4">
                            <label for="payment_card_cellphone" class="form-label">Número de celular</label>
                            <input type="number" class="form-control" id="payment_card_cellphone" name="cellphone" aria-describedby="emailHelp">
                        </div>
                    </div>
                </div>
            </div>

                <input type="submit" id="submit_efipay" value="<?php echo esc_html__('Pagar', 'efipay'); ?>" class="btn btn-success w-100" disabled>
            </form>
        </div>
    </div>

</div>


<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>


<script>
    $(document).ready(function(){
        $('#selectOther').click(function() {
            if(!$('#collapseCreditCard').hasClass('collapsing')){
                $('#collapseCreditCard').collapse('hide');
                $('#submit_efipay').prop('disabled', false);
             }
        });

        $('#selectCreditCard').click(function() {
            if (!$('#collapseCreditCard').hasClass('show') && !$('#collapseCreditCard').hasClass('collapsing')) {
                $('#collapseCreditCard').collapse('show');
                $('#submit_efipay').prop('disabled', false);
            }
        });
    });

    document.getElementById("efipay_form").addEventListener("submit", function(event) {
        event.preventDefault();

        var formData = new FormData(this);
        if ($('#selectCreditCard').is(':checked')) {
            var paymentData = {
                number: formData.get("number"),
                name: formData.get("name"),
                expiration_date: formData.get("expiration_date"),
                cvv: formData.get("cvv"),
                identification_type: formData.get("identification_type"),
                id_number: formData.get("id_number"),
                installments: formData.get("installments"),
                dialling_code: formData.get("dialling_code"),
                cellphone: formData.get("cellphone")
            };

            console.log(paymentData);
        } else if ($('#selectOther').is(':checked')) {
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
                    throw new Error("Error en la respuesta del servidor, revisa tu configuración de pagos o comunícate con soporte@efipay.co");
                }
                return response.json();
            })
            .then(data => {
                if (data.saved) {
                    window.open(data.url);
                } else {
                    alert('Error en la respuesta del servidor, revisa tu configuración de pagos o comunícate con soporte@efipay.co');
                    console.error("Error en la respuesta del servidor, revisa tu configuración de pagos o comunícate con soporte@efipay.co");
                }
            })
            .catch(error => {
                alert(error);
                console.error("Error en la solicitud:", error);
            });
        } else {
            alert('Por favor, selecciona un método de pago.');
        }
    });
</script>



<style>
    @media (min-width: 992px) {
        .w-lg-75 {
            width: 75%;
        }
        .order_details {
            display: flex;
            justify-content: center;
            margin-top: 4rem !important;
        }
    }

    input[type=number]{
        min-width: auto !important;
    }
</style>
