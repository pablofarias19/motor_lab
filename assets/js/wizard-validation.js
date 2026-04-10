'use strict';

window.WizardValidation = class WizardValidation {
    static validateStepLaborales(formulario, callbacks) {
        let valido = true;
        const { mostrarError, limpiarErrorCampo, actualizarCampoOculto } = callbacks;

        const campoSalario = formulario.querySelector('#salario');
        if (campoSalario) {
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
        if (campoAntiguedad) {
            const antiguedad = parseInt(campoAntiguedad.value, 10);
            if (Number.isNaN(antiguedad) || antiguedad < 0) {
                mostrarError(campoAntiguedad, 'La antigüedad no puede ser negativa (0 o más meses).');
                valido = false;
            } else {
                limpiarErrorCampo(campoAntiguedad);
            }
        }

        const campoCantidad = formulario.querySelector('#cantidad_empleados');
        if (campoCantidad) {
            const cantidad = parseInt(campoCantidad.value, 10);
            if (Number.isNaN(cantidad) || cantidad < 1) {
                mostrarError(campoCantidad, 'La cantidad de empleados debe ser al menos 1.');
                valido = false;
            } else {
                limpiarErrorCampo(campoCantidad);
            }
        }

        return valido;
    }

    static validateEmail(formulario, callbacks) {
        const { mostrarError, limpiarErrorCampo } = callbacks;
        const campoEmail = formulario.querySelector('#email');
        if (!campoEmail) return true;

        const valor = campoEmail.value.trim();
        if (!valor) {
            limpiarErrorCampo(campoEmail);
            return true;
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
