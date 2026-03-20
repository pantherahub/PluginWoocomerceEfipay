<?php
$order_id = $order;
// Obtener los parámetros para el formulario de pago
$parameters_args = $this->get_params_post($order_id);

// Construir el objeto de datos para enviar
$data = json_encode($parameters_args);

// Datos del cliente desde WooCommerce (facturación + identificación) para pre-rellenar el formulario
$customer_data = $this->get_customer_data_for_receipt($order);

$names_url = 'http://country.io/names.json';
$names = file_get_contents($names_url);

$phone_url = 'http://country.io/phone.json';
$phone = file_get_contents($phone_url);

// URL de confirmación del pedido (página "Gracias por tu compra") para redirigir tras pago aprobado con tarjeta
$order_received_url = isset($parameters_args['advanced_options']['result_urls']['approved'])
    ? $parameters_args['advanced_options']['result_urls']['approved']
    : (is_object($order_id) && method_exists($order_id, 'get_checkout_order_received_url') ? $order_id->get_checkout_order_received_url() : home_url());

?>


<div class="efipay-container-payment-methods">
    <div class="efipay-container-logo">
        <img class="efipay-logo" src="<?php echo esc_url($this->icon); ?>"></img>
    </div>
    
    <div id="efipayPaymentsContainerForms">
        
    </div>
</div>

<style>
/* Interfaz 3D Secure - Challenge */
#efipay-3ds-container { margin-top: 24px; }
.efipay-3ds-challenge-card {
    background: linear-gradient(145deg, #1e3a5f 0%, #0d2137 100%);
    border-radius: 16px;
    padding: 28px;
    margin-bottom: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12), 0 2px 6px rgba(0,0,0,0.08);
    border: 1px solid rgba(255,255,255,0.08);
}
.efipay-3ds-challenge-header {
    display: flex;
    align-items: center;
    margin-bottom: 24px;
    gap: 16px;
}
.efipay-3ds-challenge-icon {
    width: 52px;
    height: 52px;
    background: rgba(255,255,255,0.12);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.efipay-3ds-challenge-icon svg { display: block; }
.efipay-3ds-challenge-title { margin: 0; font-size: 20px; font-weight: 700; color: #fff; letter-spacing: -0.02em; }
.efipay-3ds-challenge-desc { margin: 6px 0 0 0; font-size: 14px; color: rgba(255,255,255,0.85); line-height: 1.4; }
.efipay-3ds-challenge-body {
    min-height: 320px;
    background: #fff;
    border-radius: 12px;
    padding: 24px;
    box-shadow: inset 0 1px 0 rgba(0,0,0,0.05);
}
.efipay-3ds-challenge-body iframe { max-width: 100%; border: none; }
.efipay-3ds-btn-continue {
    width: 100%;
    padding: 16px 24px;
    border-radius: 10px;
    border: 0;
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: #fff;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 4px 14px rgba(37,99,235,0.4);
}
.efipay-3ds-btn-continue:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(37,99,235,0.5);
}
.efipay-3ds-btn-continue:active { transform: translateY(0); }
</style>
<!-- Contenedores para flujo 3DS -->
<div id="efipay-3ds-container" style="display:none;">
    <!-- Contenedor para DDC/iframe oculto (Credibanco y Redeban) -->
    <div id="hidden3ds" style="display:none;"></div>
    
    <!-- Contenedor para Challenge visible -->
    <div id="challenge3ds-wrapper" style="display:none;">
        <div class="efipay-3ds-challenge-card">
            <div class="efipay-3ds-challenge-header">
                <div class="efipay-3ds-challenge-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z" fill="currentColor"/>
                    </svg>
                </div>
                <div style="flex:1;">
                    <h3 class="efipay-3ds-challenge-title">Autenticación 3D Secure</h3>
                    <p class="efipay-3ds-challenge-desc">Tu banco puede solicitar una verificación adicional. Sigue las instrucciones en la ventana de abajo y, al terminar, pulsa «Validar transacción».</p>
                </div>
            </div>
            <div id="challenge3ds" class="efipay-3ds-challenge-body"></div>
        </div>
        <div id="efipay-3ds-actions" style="display:none;">
            <button type="button" id="efipay-3ds-continue" class="efipay-3ds-btn-continue">✓ Validar transacción</button>
        </div>
    </div>
</div>

<script src="<?php echo plugins_url('../js/sweetalert2@11.js', __FILE__); ?>"></script>

<script>
const apiKey = "<?php echo esc_js($this->api_key); ?>";
const enabledEmbebed = "<?php echo esc_js($this->enabled_embebed); ?>";
const efipayOrderReceivedUrl = "<?php echo esc_url($order_received_url); ?>";
const efipayCustomerData = <?php echo json_encode($customer_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const EFIPAY_API_BASE = "https://sag.efipay.co/api/v1";

// Estado 3DS en memoria (para que scripts embebidos puedan disparar la continuación si lo requieren)
window.__efipay3ds = {
    transactionId: null,
    implementation: null, // 'credibanco' | 'redeban'
    paymentCard: null,
    browserInformation: null,
};
// Ejemplo de función que llama a una función de WooCommerce para vaciar el carrito
function clearCart() {
    // Realizar una solicitud AJAX
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'clear_cart_ajax'
        })
    })
    .then(response => response.text()) // o .json() si tu respuesta es JSON
    .then(data => {
        console.log('El carrito se ha vaciado correctamente.');
    })
    .catch(error => {
        console.error('Error al vaciar el carrito:', error);
    });
}

function showSpinner() {
    const spinner = document.getElementById('efipay-spinner');
    const submitBtn = document.getElementById('submit_efipay');

    if (spinner) {
        spinner.style.display = 'inline-block';
    }
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList && submitBtn.classList.remove('disabled');
    }
}

function hideSpinner(){
    const spinner = document.getElementById('efipay-spinner');
    const submitBtn = document.getElementById('submit_efipay');

    if (spinner) {
        spinner.style.display = 'none';
    }
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.classList && submitBtn.classList.remove('disabled');
    }
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Flujos 3DS según documentación Efipay (Credibanco Visa / Redeban MasterCard)
 *
 * CREDIBANCO (Visa):
 * 1. transaction-checkout devuelve 3Ds (browser_response string con ddc-form, centinelapistag)
 * 2. Inyectar HTML en hidden3ds, enviar ddc-form, escuchar postMessage desde Cardinal
 * 3. Al recibir mensaje con Status → llamar Enroll
 * 4. Enroll puede devolver: (A) solo transaction (estado final) → finalizar UI
 *    o (B) transaction + 3Ds.browser_response (challenge) → mostrar challenge, botón "Validar"; al pulsar → Enroll de nuevo → estado final
 *
 * REDEBAN (MasterCard):
 * 1. transaction-checkout devuelve 3Ds (browser_response objeto: hidden_iframe con HTML, challenge_request vacío)
 * 2. Inyectar hidden_iframe en hidden3ds, ejecutar scripts, esperar 5s → llamar auth-continue
 * 3. auth-continue puede devolver: (A) solo transaction → finalizar UI
 *    o (B) transaction + 3Ds.browser_response.challenge_request (HTML challenge) → mostrar challenge, botón "Validar"; al pulsar → auth-continue de nuevo → estado final
 */
async function handle3dsFlowFromResponse(data_payment) {
    const threeDs = data_payment?.['3Ds'] || data_payment?.['3ds'] || null;
    const transaction = data_payment?.transaction || null;
    if (!threeDs || !transaction || !transaction.transaction_id) {
        return false;
    }
    if (!isPendingStatus(transaction.status)) {
        return false;
    }

    window.__efipay3ds.transactionId = transaction.transaction_id;
    window.__efipay3ds.implementation = (threeDs.implementation || '').toLowerCase();
    window.__efipay3ds.paymentCard = getPaymentCardFor3ds();
    window.__efipay3ds.browserInformation = getBrowserInformationFor3ds();

    const impl = window.__efipay3ds.implementation;

    try {
        showSpinner();
        window.authContinueTransaction = async function () {
            return await continue3dsAndMaybeChallenge();
        };
        window.enrollTransaction = async function () {
            return await continue3dsAndMaybeChallenge();
        };

        if (impl === 'credibanco') {
            await handleCredibanco3dsInitial(threeDs);
        } else if (impl === 'redeban') {
            await handleRedeban3dsInitial(threeDs);
        } else {
            throw new Error(`Implementación 3DS no reconocida: ${impl}`);
        }

        return true;
    } catch (error) {
        console.error('Error en flujo 3DS inicial:', error);
        hideSpinner();
        Swal.fire({
            title: "Error en autenticación 3DS",
            text: error?.message || String(error),
            icon: "error"
        });
        return false;
    }
}

function isPendingStatus(status) {
    return ['Pendiente', 'Iniciada'].includes(status);
}

function getPaymentCardFor3ds() {
    return {
        number: document.getElementById('efipay_payment_card_number')?.value,
        name: document.getElementById('efipay_payment_card_name')?.value,
        expiration_date: document.getElementById('efipay_payment_card_expiration_date')?.value,
        cvv: document.getElementById('efipay_payment_card_cvv')?.value,
    };
}

function getBrowserInformationFor3ds() {
    // Get color depth
    const colorDepth = String(window.screen.colorDepth);
    // Check if JavaScript is enabled (if this runs, JavaScript is enabled)
    const jsEnabled = true;
    let javaEnabled = false;
    try {
        javaEnabled = navigator.javaEnabled();
    } catch (error) {
        const javaEnabled = false;
    }
    // Get browser language
    const language = navigator.language || navigator.userLanguage;
    // Get screen height and width
    const screenHeight = window.innerHeight;
    const screenWidth = window.innerWidth;
    // Calculate time difference from UTC (in hours)
    const timeDifference = new Date().getTimezoneOffset();

    return {
        colorDepth,
        language,
        screenHeight,
        screenWidth,
        timeDifference,
        javaScriptEnabled: jsEnabled,
        javaEnabled,
    }
}

async function continue3dsAndMaybeChallenge() {
    showSpinner();
    try {
        const data3ds = await call3dsContinueEndpoint();

        const transaction = data3ds?.transaction;
    const threeDs = data3ds?.['3Ds'] || data3ds?.['3ds'] || null;

    // Si hay challenge, renderizarlo y dejar un botón manual para "Validar transacción"
    // Verificar si hay challenge según la implementación
    const hasChallenge = checkForChallenge(threeDs);
    
    if (hasChallenge) {
        hideSpinner();
        setup3dsChallenge(threeDs);

        // Botón manual para que el usuario continúe una vez complete el challenge
        setContinue3dsButtonVisible(true, async () => {
            try {
                showSpinner();
                setContinue3dsButtonVisible(false);
                // Llamar recursivamente para continuar después del challenge
                await continue3dsAndMaybeChallenge();
            } catch (e) {
                hideSpinner();
                Swal.fire({ 
                    title: "Error", 
                    text: e?.message || String(e), 
                    icon: "error" 
                });
            }
        });

        // Mensaje informativo sin bloquear la interacción
        Swal.fire({
            toast: true,
            position: 'top',
            icon: 'info',
            title: 'Autenticación 3DS',
            text: 'Completa la validación en tu banco. Luego pulsa “Validar transacción”.',
            showConfirmButton: false,
            timer: 4500,
        });
        return;
    }

    // Sin challenge: si ya viene transacción final, cerrar flujo con el UI existente
    if (transaction && transaction.status) {
        setContinue3dsButtonVisible(false);
        hideSpinner();
        await finalizeTransactionUI(transaction);
        return;
    }

        hideSpinner();
        throw new Error("No se recibió información válida al continuar el flujo 3DS.");
    } catch (error) {
        hideSpinner();
        throw error;
    }
}

function checkForChallenge(threeDs) {
    if (!threeDs || !threeDs.browser_response) {
        return false;
    }
    
    // browser_response puede ser string (formato antiguo) u objeto (formato nuevo)
    if (typeof threeDs.browser_response === 'string') {
        return threeDs.browser_response.trim().length > 0;
    }
    
    // Formato nuevo: objeto con challenge_request y hidden_iframe
    if (typeof threeDs.browser_response === 'object') {
        const challengeRequest = threeDs.browser_response.challenge_request || '';
        return challengeRequest.trim().length > 0;
    }
    
    return false;
}

async function call3dsContinueEndpoint() {
    const { transactionId, implementation, paymentCard, browserInformation } = window.__efipay3ds;
    if (!transactionId || !implementation) {
        throw new Error("No hay información suficiente para continuar el 3DS.");
    }

    const impl = implementation.toLowerCase();
    // Credibanco: Postman usa /3ds/Enroll/ (E mayúscula). Redeban: auth-continue
    const endpoint =
        impl === 'credibanco'
            ? `${EFIPAY_API_BASE}/payment/3ds/Enroll/${transactionId}`
            : `${EFIPAY_API_BASE}/payment/3ds/auth-continue/${transactionId}`;

    const body = {
        payment_card: paymentCard,
        browser_information: browserInformation,
    };

    const response = await fetch(endpoint, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "Authorization": `Bearer ${apiKey}`,
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        if (response.status === 422) {
            const errorData = await response.json();
            throw new Error(JSON.stringify(errorData.errors || errorData));
        }
        throw new Error("Error en la respuesta del servidor 3DS, revisa tu configuración de pagos o comunícate con soporte@efipay.co");
    }
    return response.json();
}

function setContinue3dsButtonVisible(visible, onClick) {
    const actions = document.getElementById('efipay-3ds-actions');
    const btn = document.getElementById('efipay-3ds-continue');
    if (!actions || !btn) return;

    actions.style.display = visible ? 'block' : 'none';
    btn.onclick = null;
    if (visible && typeof onClick === 'function') {
        btn.onclick = onClick;
    }
}

function setup3dsChallenge(threeDsObj) {
    // Challenge (visible) - Manejar formato nuevo (objeto) y antiguo (string)
    show3dsContainers();
    const challengeWrapper = document.getElementById("challenge3ds-wrapper");
    const challengeElement = document.getElementById("challenge3ds");
    
    if (!challengeWrapper || !challengeElement) return;

    challengeWrapper.style.display = 'block';
    
    // Obtener el HTML del challenge según el formato
    let challengeHtml = '';
    if (typeof threeDsObj?.browser_response === 'string') {
        // Formato antiguo: string directo
        challengeHtml = threeDsObj.browser_response;
    } else if (typeof threeDsObj?.browser_response === 'object') {
        // Formato nuevo: objeto con challenge_request
        challengeHtml = threeDsObj.browser_response.challenge_request || '';
    }
    
    challengeElement.innerHTML = challengeHtml;
    runScriptsInside(challengeElement);

    // Buscar y enviar formularios automáticamente
    const forms = challengeElement.querySelectorAll('form');
    forms.forEach(form => {
        // Buscar formularios con id específicos o que tengan action
        if (form.id === 'threeD' || form.id === 'step-up-form' || form.action) {
            try {
                // Pequeño delay para asegurar que el DOM esté listo
                setTimeout(() => {
                    form.submit();
                }, 100);
            } catch (e) {
                console.error('Error al enviar formulario del challenge:', e);
            }
        }
    });
}

// ========== FUNCIONES REFACTORIZADAS PARA CREDIBANCO Y REDEBAN ==========

async function handleCredibanco3dsInitial(threeDsObj) {
    // Credibanco: Manejar formato nuevo (objeto) y antiguo (string)
    show3dsContainers();
    const wrappedElement = document.getElementById("hidden3ds");
    if (!wrappedElement) return;

    wrappedElement.style.display = 'none';
    
    // Obtener HTML según el formato
    let htmlContent = '';
    if (typeof threeDsObj?.browser_response === 'string') {
        htmlContent = threeDsObj.browser_response;
    } else if (typeof threeDsObj?.browser_response === 'object') {
        // Formato nuevo: usar hidden_iframe si está disponible
        htmlContent = threeDsObj.browser_response.hidden_iframe || '';
    }
    
    wrappedElement.innerHTML = htmlContent;
    runScriptsInside(wrappedElement);

    // Credibanco: el HTML puede traer action completo (ej. .../V1/Cruise/Collect). Solo asignar centinelapistag si el form no tiene action o no apunta al host de Cardinal
    const ddcForm = wrappedElement.querySelector('#ddc-form') || document.querySelector('#ddc-form');
    if (ddcForm && threeDsObj?.centinelapistag) {
        try {
            const centinelHost = new URL(threeDsObj.centinelapistag).host;
            if (!ddcForm.action || ddcForm.action === '' || !ddcForm.action.includes(centinelHost)) {
                ddcForm.action = threeDsObj.centinelapistag;
            }
        } catch (e) {
            ddcForm.action = threeDsObj.centinelapistag;
        }
    }
    
    if (ddcForm) {
        try {
            ddcForm.submit();
        } catch (e) {
            console.error('Error al enviar formulario DDC de Credibanco:', e);
        }
    }

    // Esperar mensaje del window después de enviar DDC
    await handleCredibanco3dsMessage(threeDsObj);
}

async function handleCredibanco3dsMessage(threeDsObj) {
    return new Promise((resolve, reject) => {
        let eventMessage3ds = false;
        const threeDsTimeOut = setTimeout(() => {
            if (!eventMessage3ds) {
                reject(new Error('Error: demasiado tiempo esperando mensaje 3DS de Credibanco'));
            }
        }, 50000);

        const messageHandler = async (event) => {
            // Verificar origen del mensaje
            if (threeDsObj?.centinelapistag) {
                try {
                    const centinelUrl = new URL(threeDsObj.centinelapistag);
                    if (event.origin !== centinelUrl.origin) {
                        return;
                    }
                } catch (e) {
                    // Si no se puede parsear la URL, continuar
                }
            }

            eventMessage3ds = true;
            clearTimeout(threeDsTimeOut);
            window.removeEventListener('message', messageHandler);

            try {
                let data = null;
                if (typeof event.data === 'string') {
                    try {
                        data = JSON.parse(event.data);
                    } catch (e) {
                        reject(new Error('Error al parsear mensaje 3DS de Credibanco'));
                        return;
                    }
                } else {
                    data = event.data;
                }

                if (data !== undefined && data.Status) {
                    console.log('Songbird ran DF successfully');
                    // Continuar con enroll
                    try {
                        await continue3dsAndMaybeChallenge();
                        resolve();
                    } catch (error) {
                        reject(error);
                    }
                } else {
                    reject(new Error('Error: evento de mensaje front status 3DS inválido'));
                }
            } catch (error) {
                reject(error);
            }
        };

        window.addEventListener('message', messageHandler, false);
    });
}

async function handleRedeban3dsInitial(threeDsObj) {
    // Redeban: Manejar formato nuevo (objeto) y antiguo (string)
    show3dsContainers();
    const wrappedElement = document.getElementById("hidden3ds");
    if (!wrappedElement) return;

    wrappedElement.style.display = 'none';
    
    // Obtener HTML según el formato
    let htmlContent = '';
    if (typeof threeDsObj?.browser_response === 'string') {
        htmlContent = threeDsObj.browser_response;
    } else if (typeof threeDsObj?.browser_response === 'object') {
        // Formato nuevo: usar hidden_iframe
        htmlContent = threeDsObj.browser_response.hidden_iframe || '';
    }
    
    wrappedElement.innerHTML = htmlContent;
    runScriptsInside(wrappedElement);

    // Redeban: Esperar 5 segundos después de ejecutar scripts, luego llamar auth-continue
    await sleep(5000);
    console.log("Redeban: timeout 5sec completado, llamando auth-continue");
    await continue3dsAndMaybeChallenge();
}

function setup3DsIframe(threeDsObj) {
    // Función legacy - ahora delegamos a las funciones específicas
    const impl = (threeDsObj?.implementation || '').toLowerCase();
    if (impl === 'credibanco') {
        handleCredibanco3dsInitial(threeDsObj);
    } else if (impl === 'redeban') {
        handleRedeban3dsInitial(threeDsObj);
    }
}

function runScriptsInside(containerEl) {
    // Necesario porque innerHTML NO ejecuta scripts; la doc recomienda recrearlos para que corran
    Array.from(containerEl.querySelectorAll("script")).forEach(oldScriptEl => {
        const newScriptEl = document.createElement("script");
        Array.from(oldScriptEl.attributes).forEach(attr => {
            newScriptEl.setAttribute(attr.name, attr.value);
        });
        const scriptText = document.createTextNode(oldScriptEl.innerHTML || "");
        newScriptEl.appendChild(scriptText);
        oldScriptEl.parentNode.replaceChild(newScriptEl, oldScriptEl);
    });
}

function show3dsContainers() {
    const root = document.getElementById('efipay-3ds-container');
    if (root) root.style.display = 'block';
}

function hide3dsChallengeUI() {
    const wrapper = document.getElementById('challenge3ds-wrapper');
    const actions = document.getElementById('efipay-3ds-actions');
    if (wrapper) wrapper.style.display = 'none';
    if (actions) actions.style.display = 'none';
}

async function finalizeTransactionUI(transaction) {
    hideSpinner();
    hide3dsChallengeUI();
    await Swal.fire({
        icon: transaction.status === 'Aprobada' ? 'success' : transaction.status === 'Rechazada' ? 'error' : 'warning',
        title: 'Estado',
        text: 'Estado de la transacción: ' + transaction.status + `${transaction.status !== 'Aprobada' ? ', Intenta nuevamente' : ''}`,
    });
    if (transaction.status === 'Aprobada') {
        await clearCart();
        window.location.href = "<?php echo home_url(); ?>";
    }
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
                        number: document.getElementById('efipay_payment_card_number').value,
                        name: document.getElementById('efipay_payment_card_name').value,
                        expiration_date: document.getElementById('efipay_payment_card_expiration_date').value,
                        cvv: document.getElementById('efipay_payment_card_cvv').value,
                        identification_type: document.getElementById('efipay_payment_card_identification_type').value,
                        id_number: document.getElementById('efipay_payment_card_id_number').value,
                        installments: document.getElementById('efipay_payment_card_installments').value,
                        dialling_code: "+" + document.getElementById('efipay_payment_card_dialling_code').value,
                        cellphone: document.getElementById('efipay_payment_card_cellphone').value,
                    };

                    var customerPayer = {
                        name: document.getElementById('efipay_payment_card_name').value,
                        email: document.getElementById('efipay_payment_card_email').value,
                        address_1: document.getElementById('efipay_payment_card_address_1').value,
                        address_2: document.getElementById('efipay_payment_card_address_2').value,
                        city: document.getElementById('efipay_payment_card_city').value,
                        state: document.getElementById('efipay_payment_card_state').value,
                        zip_code: document.getElementById('efipay_payment_card_zip_code').value,
                        country: document.getElementById('efipay_payment_card_country').value,
                    };

                    var payment = {
                        id: data.payment_id,
                        token: data.token,
                    }

                    var send_data = {
                        payment: payment,
                        customer_payer: customerPayer,
                        payment_card: paymentData,
                        browser_information: getBrowserInformationFor3ds(),
                        enable_3ds: true
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
                        // Si la respuesta incluye 3DS y está pendiente, continuar el flujo según documentación
                        const didHandle3ds = await handle3dsFlowFromResponse(data_payment);
                        if (didHandle3ds) return;

                        // Flujo normal (sin 3DS)
                        await finalizeTransactionUI(data_payment.transaction);
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

document.addEventListener('DOMContentLoaded', async function() {
    if(enabledEmbebed !== 'yes') {
        let data = <?php echo json_encode($data, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;
        await generatePayment('redirect', JSON.parse(data))
    }
    await getPaymentsAvailable()
    await sleep(2000);

    document.getElementById('efipaySelectCreditCard').addEventListener('click', function() {
        setTimeout(function() {
            if (document.querySelector('.accordion-item-efipay')?.classList.contains('efipay-active')) {
                document.getElementById('submit_efipay').disabled = false;

                const requiredFields = [
                    'efipay_payment_card_number',
                    'efipay_payment_card_name',
                    'efipay_payment_card_cvv',
                    'efipay_payment_card_expiration_date',
                    'efipay_payment_card_identification_type',
                    'efipay_payment_card_id_number',
                    'efipay_payment_card_installments',
                    'efipay_payment_card_dialling_code',
                    'efipay_payment_card_cellphone',
                    'efipay_payment_card_email',
                    'efipay_payment_card_address_1',
                    'efipay_payment_card_address_2',
                    'efipay_payment_card_city',
                    'efipay_payment_card_state',
                    'efipay_payment_card_zip_code',
                    'efipay_payment_card_country',
                ];

                requiredFields.forEach(function(id) {
                    const input = document.getElementById(id);
                    if (input) {
                        input.setAttribute('required', 'true');
                    }
                });
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
    document.getElementById('efipay_payment_card_dialling_code').innerHTML = selectOptions;

    // optener los countries
    getCountries()
});

function prefillCustomerData() {
    if (!efipayCustomerData || typeof efipayCustomerData !== 'object') return;
    const setVal = (id, val) => {
        const el = document.getElementById(id);
        if (el && val !== undefined && val !== null) el.value = val;
    };
    setVal('efipay_payment_card_name', efipayCustomerData.name);
    setVal('efipay_payment_card_email', efipayCustomerData.email);
    setVal('efipay_payment_card_address_1', efipayCustomerData.address_1);
    setVal('efipay_payment_card_address_2', efipayCustomerData.address_2);
    setVal('efipay_payment_card_city', efipayCustomerData.city);
    setVal('efipay_payment_card_state', efipayCustomerData.state);
    setVal('efipay_payment_card_zip_code', efipayCustomerData.zip_code || efipayCustomerData.postcode);
    setVal('efipay_payment_card_identification_type', efipayCustomerData.identification_type);
    setVal('efipay_payment_card_id_number', efipayCustomerData.identification_number);
    if (efipayCustomerData.phone) {
        const phone = String(efipayCustomerData.phone).replace(/\D/g, '');
        setVal('efipay_payment_card_cellphone', phone.length > 10 ? phone.slice(-10) : phone);
        const dialling = document.getElementById('efipay_payment_card_dialling_code');
        if (dialling && phone.startsWith('57') && phone.length > 10) setVal('efipay_payment_card_dialling_code', '57');
    }
    var iso2ToIso3 = { CO: 'COL', US: 'USA', MX: 'MEX', AR: 'ARG', PE: 'PER', EC: 'ECU', VE: 'VEN', CL: 'CHL', BO: 'BOL', UY: 'URY', PY: 'PRY', PA: 'PAN', CR: 'CRI', DO: 'DOM', ES: 'ESP' };
    var countryIso3 = iso2ToIso3[efipayCustomerData.country] || efipayCustomerData.country;
    setVal('efipay_payment_card_country', countryIso3);
}

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
        document.getElementById('efipay_payment_card_country').innerHTML = optionsCountry;
        prefillCustomerData();

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

                            <button type="button" onClick="generatePayment('api', <?php echo htmlentities($data); ?>); return false;" id="submit_efipay" disabled>
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
        Swal.fire({
            title: "Error",
            text: error.message,  // Mostrar el mensaje del error
            icon: "error"
        });
        console.error("Error al obtener los métodos de pago:", error);
    });
}
</script>
