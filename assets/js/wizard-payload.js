'use strict';

window.WizardPayloadBuilder = class WizardPayloadBuilder {
    static build(formulario) {
        const leer = (selector) => {
            const el = formulario.querySelector(selector);
            return el ? el.value.trim() : '';
        };

        const leerRadio = (name) => {
            const marcado = formulario.querySelector(`input[name="${name}"]:checked`);
            return marcado ? marcado.value : 'no';
        };

        const leerRadioRaw = (name) => {
            const marcado = formulario.querySelector(`input[name="${name}"]:checked`);
            return marcado ? marcado.value : null;
        };

        const salarioTexto = leer('#salario').replace(/\./g, '').replace(',', '.');
        const salario = parseFloat(salarioTexto) || 0;

        return {
            tipo_usuario: leer('#tipo_usuario'),
            tipo_conflicto: leer('#tipo_conflicto'),
            datos_laborales: {
                salario,
                antiguedad_meses: parseInt(leer('#antiguedad_meses'), 10) || 0,
                provincia: leer('#provincia'),
                categoria: leer('#categoria'),
                cct: leer('#cct'),
                cantidad_empleados: parseInt(leer('#cantidad_empleados'), 10) || 1,
                edad: parseInt(leer('#edad'), 10) || 0,
                tipo_registro: leer('#tipo_registro') || 'registrado',
                salario_recibo: parseFloat(leer('#salario_recibo')) || 0,
                antiguedad_recibo: parseInt(leer('#antiguedad_recibo'), 10) || 0,
            },
            documentacion: {
                tiene_recibos: leerRadio('tiene_recibos'),
                tiene_contrato: leerRadio('tiene_contrato'),
                registrado_afip: leerRadio('registrado_afip'),
                tiene_testigos: leerRadio('tiene_testigos'),
                auditoria_previa: leerRadio('auditoria_previa'),
            },
            situacion: {
                hay_intercambio: leerRadio('hay_intercambio'),
                fue_intimado: leerRadio('fue_intimado'),
                ya_despedido: leerRadio('ya_despedido'),
                urgencia: leer('#urgencia') || 'media',
                cantidad_empleados: parseInt(leer('#cantidad_empleados_sit'), 10) || 1,
                fecha_despido: leer('#fecha_despido'),
                fecha_ultimo_telegrama: leer('#fecha_ultimo_telegrama'),
                motivo_diferencia: leer('#motivo_diferencia') || 'mala_categorizacion',
                meses_adeudados: parseInt(leer('#meses_adeudados'), 10) || 0,
                dia_despido: parseInt(leer('#dia_despido'), 10) || 15,
                check_inconstitucionalidad: leerRadio('check_inconstitucionalidad'),
                jurisdiccion: leer('#jurisdiccion') || 'CABA',
                salarios_historicos: (() => {
                    const txtAreas = leer('#salarios_historicos').trim();
                    if (!txtAreas) return [];
                    return txtAreas
                        .split(/[\n,]+/)
                        .map((s) => parseFloat(s.trim()))
                        .filter((n) => !Number.isNaN(n));
                })(),
                tiene_facturacion: leerRadio('tiene_facturacion'),
                tiene_pago_bancario: leerRadio('tiene_pago_bancario'),
                tiene_contrato_escrito: leerRadio('tiene_contrato_escrito'),
                cantidad_subcontratistas: parseInt(leer('#cantidad_subcontratistas'), 10) || 1,
                principal_valida_cuil: leerRadio('valida_cuil'),
                principal_verifica_aportes: leerRadio('valida_aportes'),
                principal_paga_directo: leerRadio('valida_pago_directo'),
                principal_valida_cbu_trabajador: leerRadio('valida_cbu'),
                principal_cubre_art: leerRadio('valida_art'),
                actividad_esencial: leerRadio('actividad_esencial'),
                control_documental: leerRadio('control_documental'),
                control_operativo: leerRadio('control_operativo'),
                integracion_estructura: leerRadio('integracion_estructura'),
                contrato_formal: leerRadio('contrato_formal'),
                falta_f931_art: leerRadio('falta_f931_art'),
                nivel_cumplimiento: (() => {
                    const chks = ['chk_alta_sipa', 'chk_libro_art52', 'chk_recibos_cct', 'chk_art_vigente', 'chk_examenes', 'chk_epp_rgrl'];
                    let noCount = 0;
                    let answered = 0;
                    chks.forEach((chk) => {
                        const val = leerRadioRaw(chk);
                        if (val === 'no') noCount++;
                        if (val === 'si' || val === 'no') answered++;
                    });
                    if (answered === 0) return 'desconocido';
                    return noCount >= 3 ? 'critico' : 'estable';
                })(),
                meses_no_registrados: parseInt(leer('#meses_no_registrados'), 10) || 0,
                meses_en_mora: parseInt(leer('#meses_en_mora'), 10) || 0,
                aplica_blanco_laboral: leerRadio('aplica_blanco_laboral'),
                probabilidad_condena: parseFloat(leer('#probabilidad_condena')) || 0.5,
                inspeccion_previa: leerRadio('inspeccion_previa'),
                chk_alta_sipa: leerRadio('chk_alta_sipa'),
                chk_libro_art52: leerRadio('chk_libro_art52'),
                chk_recibos_cct: leerRadio('chk_recibos_cct'),
                chk_art_vigente: leerRadio('chk_art_vigente'),
                chk_examenes: leerRadio('chk_examenes'),
                chk_epp_rgrl: leerRadio('chk_epp_rgrl'),
                tipo_contingencia: leer('#tipo_contingencia') || 'accidente_tipico',
                fecha_siniestro: leer('#fecha_siniestro'),
                porcentaje_incapacidad: parseFloat(leer('#porcentaje_incapacidad')) || 0,
                incapacidad_tipo: leer('#incapacidad_tipo') || 'permanente_definitiva',
                tiene_art: (() => {
                    const tieneArtRadio = leerRadioRaw('tiene_art');
                    if (tieneArtRadio) return tieneArtRadio;
                    const estadoArtEmpresa = leer('#estado_art_empresa') || 'activa_valida';
                    return estadoArtEmpresa === 'inexistente' ? 'no' : 'si';
                })(),
                estado_art: leer('#estado_art_empresa') || 'activa_valida',
                culpa_grave: leerRadio('culpa_grave'),
                via_civil: leerRadio('via_civil'),
                denuncia_art: leerRadio('denuncia_art'),
                rechazo_art: leerRadio('rechazo_art'),
                comision_medica: leer('#comision_medica') || 'no_iniciada',
                dictamen_porcentaje: parseFloat(leer('#dictamen_porcentaje')) || 0,
                via_administrativa_agotada: leerRadio('via_administrativa_agotada'),
                tiene_preexistencia: leerRadio('tiene_preexistencia'),
                preexistencia_porcentaje: parseFloat(leer('#preexistencia_porcentaje')) || 0,
                licencia_activa: leerRadio('licencia_activa'),
                fraude_facturacion_desproporcionada: leerRadio('fraude_facturacion_desproporcionada'),
                fraude_intermitencia_sospechosa: leerRadio('fraude_intermitencia_sospechosa'),
                fraude_evasion_sistematica: leerRadio('fraude_evasion_sistematica'),
                fraude_sobre_facturacion: leerRadio('fraude_sobre_facturacion'),
                fraude_estructura_opaca: leerRadio('fraude_estructura_opaca'),
                tipo_extincion: leer('#tipo_extincion') || 'despido',
                fue_violenta: leerRadio('fue_violenta'),
                meses_litigio: parseInt(leer('#meses_litigio'), 10) || 36,
            },
            contacto: {
                email: leer('#email'),
            },
            schema_version: '2026-04',
        };
    }
};
