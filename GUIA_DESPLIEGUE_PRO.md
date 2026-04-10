# Guía de Despliegue: Motor Laboral PRO (PHP + MySQL)

Esta guía detalla los pasos exactos para subir las nuevas funcionalidades del **Motor Laboral PRO** (que construimos y probamos en local) a tu servidor de producción en Hostinger.

---

## PASO 1: Subir archivos mediante Administrador de Archivos (o FTP)

Debes subir los siguientes archivos y carpetas desde tu computadora local (`c:\Users\USUARIO\Programacion\web PABLO_SISTEMATRAM\motor_laboral\`) a la carpeta pública de tu servidor (generalmente `public_html/motor_laboral/` o similar).

### Archivos Nuevos a subir (Raíz del proyecto):
Sube estos tres archivos directamente a la carpeta principal de `motor_laboral/`:
1. `cct.php` (El nuevo explorador de convenios)
2. `normativa.php` (El nuevo explorador legal)
3. `jurisprudencia.php` (El nuevo explorador de fallos)

### Archivos Modificados a reemplazar (Raíz del proyecto):
Reemplaza los archivos existentes por estas nuevas versiones:
1. `index.php` (El asistente actualizado que conecta con los nuevos motores)
2. `resultado.php` (La nueva pantalla de resultados premium)

### Carpeta API y Motores:
Debes subir la carpeta `Engine` completa y reemplazar `procesar_analisis.php`:
1. **Ruta local**: `api/procesar_analisis.php` ➔ **Ruta servidor**: `api/procesar_analisis.php` (REEMPLAZAR)
2. **Ruta local**: `api/Engine/` ➔ **Ruta servidor**: `api/Engine/` (NUEVO DIRECTORIO - SUBIR COMPLETO)
   - Adentro van: `IrilEngine.php`, `EscenariosEngine.php`, `ExposicionEngine.php` y `Parametros.php`.

*(Si modificamos algún CSS en `assets/css/`, recuerda subirlo también para que se vean bien las nuevas pantallas).*

---

## PASO 2: Actualizar la Base de Datos (phpMyAdmin)

El sistema ahora cuenta con 21 convenios, y cientos de artículos normativos y de jurisprudencia. Debes importar el archivo SQL generado para crear las tablas necesarias e inyectar esta información.

### Instrucciones para importar:
1. Entra al Panel de Control de Hostinger (hPanel).
2. Ve a la sección **Bases de Datos** y entra a **phpMyAdmin**.
3. Selecciona a la izquierda la base de datos que usa el proyecto (por ejemplo, `u123456789_sistematram`).
4. Ve a la pestaña **Importar** (arriba).
5. Haz clic en **Seleccionar archivo** ("Choose File").
6. Busca en tu computadora local el archivo: 
   `c:\Users\USUARIO\Programacion\web PABLO_SISTEMATRAM\motor_laboral\sql\05_motor_pro_data.sql`
7. Haz clic en **Continuar** / **Go** abajo de todo.

**¿Qué hace este script?**
- Crea las tablas `pro_ccts`, `pro_escalas_salariales`, `pro_normativa` y `pro_jurisprudencia`. (Si ya existían de pruebas anteriores, las borra y las recrea limpias).
- Vuelca todos los datos de los 21 convenios, los 67 artículos legales (LCT, Ley Bases, LRT) y los leading cases.

---

## PASO 3: Verificación Final

Una vez subidos los archivos y la base de datos, ingresa a tu web en producción y verifica lo siguiente:

1. **Exploradores Públicos:**
   - Ingresa a `tusitio.com/motor_laboral/cct.php` y verifica que cargan los 21 botones y las escalas.
   - Ingresa a `tusitio.com/motor_laboral/normativa.php` y prueba realizar una búsqueda.
2. **Sistema Principal (Wizard):**
   - Ingresa a `tusitio.com/motor_laboral/index.php`.
   - Llena un caso de prueba rápido (ej. Despido sin causa en CABA).
   - Haz clic en "Analizar mi caso ahora".
   - Si la pantalla de `resultado.php` se carga correctamente con el velocímetro, las matrices A/B/C/D y el cálculo económico, **¡LA MIGRACIÓN FUE UN ÉXITO!**

---
*Nota: Recuerda que el sistema Python (`motor_laboral_pro/`) es de prototipado local. Ese NO se sube al servidor Hostinger, ya que Hostinger ejecutará la versión PHP que acabamos de subir.*
