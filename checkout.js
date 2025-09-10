const settings = window.wc.wcSettings.getSetting( 'efipay_data', {} );
const label = window.wp.htmlEntities.decodeEntities( settings.title ) || window.wp.i18n.__( 'Paga con Efipay', 'efipay' );
const Content = () => {
    return window.wp.htmlEntities.decodeEntities( settings.description || '' );
};


const Label = () => {
    const decodedIcon = window.wp.htmlEntities.decodeEntities(settings.icon || '');
    
    return decodedIcon 
        ? Object(window.wp.element.createElement)('img', { 
            src: decodedIcon, 
            alt: window.wp.htmlEntities.decodeEntities(label),
            style: { float: 'right', marginRight: '20px' }
          }) 
        : null;
}


const Block_Gateway = {
    name: 'efipay',
    label: Object( window.wp.element.createElement )( Label, null ),
    content: Object( window.wp.element.createElement )( Content, null ),
    edit: Object( window.wp.element.createElement )( Content, null ),
    canMakePayment: () => {
        // Verificando que tengamos los datos necesarios
        if (!settings || Object.keys(settings).length === 0) {
            console.warn('Efipay: Configuración no disponible');
            return false;
        }
        return true;
    },
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway );