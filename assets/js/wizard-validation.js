'use strict';

// Rango operativo usado por el backend para contingencias ART/LRT.
const MIN_ACCIDENT_AGE = 16;
const MAX_ACCIDENT_AGE = 90;

window.WizardValidation = class WizardValidation {
    static validateStepLaborales(formulario, callbacks) {
        let valido = true;
        const {
            tipoConflicto = '',
            esVisible = () => false,
            mostrarError,
            limpiarErrorCampo,
            actualizarCampoOculto,
        } = callbacks;

        const campoSalario = formulario.querySelector('#salario');
        if (campoSalario && esVisible(campoSalario)) {
            const valorRaw = campoSalario.value.replace(/\./g, '').replace(',', '.');
            const salario = parseFloat(valorRaw);

            if (Number.isNaN(salario) || salario <= 0) {
                mostrarError(campoSalario, 'El salario debe ser un número mayor a cero.');
                valido = false;
            } else {
                actualizarCampoOculto('salario_raw', salario.toString());
                limpiarErrorCampo(campoSalario);
            }
        }

        const campoAntiguedad = formulario.querySelector('#antiguedad_meses');
        if (campoAntiguedad && esVisible(campoAntiguedad)) {
            const antiguedad = parseInt(campoAntiguedad.value, 10);
            if (Number.isNaN(antiguedad) || antiguedad < 0) {
                mostrarError(campoAntiguedad, 'La antigüedad no puede ser negativa (0 o más meses).');
                valido = false;
            } else {
                limpiarErrorCampo(campoAntiguedad);
            }
        }

        const campoCantidad = formulario.querySelector('#cantidad_empleados');
        if (campoCantidad && esVisible(campoCantidad)) {
            const cantidad = parseInt(campoCantidad.value, 10);
            if (Number.isNaN(cantidad) || cantidad < 1) {
                mostrarError(campoCantidad, 'La cantidad de empleados debe ser al menos 1.');
                valido = false;
            } else {
                limpiarErrorCampo(campoCantidad);
            }
        }

        const campoEdad = formulario.querySelector('#edad');
        if (tipoConflicto === 'accidente_laboral' && campoEdad && esVisible(campoEdad)) {
            const edad = parseInt(campoEdad.value, 10);
            if (Number.isNaN(edad) || edad < MIN_ACCIDENT_AGE || edad > MAX_ACCIDENT_AGE) {
                mostrarError(campoEdad, `Para accidentes, la edad debe estar entre ${MIN_ACCIDENT_AGE} y ${MAX_ACCIDENT_AGE} años.`);
                valido = false;
            } else {
                limpiarErrorCampo(campoEdad);
            }
        } else if (campoEdad) {
            limpiarErrorCampo(campoEdad);
        }

        return valido;
    }

    static validateEmail(formulario, callbacks) {
        const { mostrarError, limpiarErrorCampo } = callbacks;
        const campoEmail = formulario.querySelector('#email');
        if (!campoEmail) return true;

        const valor = campoEmail.value.trim();
        if (!valor) {
            mostrarError(campoEmail, 'Ingresá un correo electrónico para enviarte el informe.');
            return false;
        }

        const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
        if (!regexEmail.test(valor)) {
            mostrarError(campoEmail, 'El email no tiene un formato válido (ej: nombre@dominio.com).');
            return false;
        }

        limpiarErrorCampo(campoEmail);
        return true;
    }
};
