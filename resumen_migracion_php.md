# Resumen de Motor Laboral PRO para Migración a PHP

Este documento metodológico resume la arquitectura, modelo de datos y motores de cálculo lógicos del sistema **Motor Laboral PRO** (actualmente en Python/Flask), estructurándolo de manera que facilite su traducción, integración o migración a un ecosistema **PHP**.

---

## 1. Visión General del Sistema Origen

**Motor Laboral PRO** es una plataforma de análisis de riesgo y cálculo laboral. Su fortaleza principal no radica en el framework web (Flask), sino en la separación limpia de su lógica de negocio en "motores" especializados.

*   **Lenguaje Base:** Python 3.
*   **Framework Web:** Flask (equivalente a Slim, Silex o un ruteo básico en PHP).
*   **Base de Datos:** SQLite (fácilmente migrable a MySQL/PostgreSQL usando PDO en PHP).
*   **Plantillas (UI):** Jinja2 (equivalente a Blade en Laravel o Twig en Symfony).

## 2. Componentes Arquitectónicos y su Equivalencia en PHP

Para portar este sistema a PHP, se debe mantener estructurada la separación de responsabilidades:

| Componente (Python) | Rol del Componente | Equivalente Recomendado en PHP |
| :--- | :--- | :--- |
| `app.py` | Ruteo web, controladores y API. | Routers/Controllers en Laravel (ej. `web.php` + Controllers) o ruteo estructurado vanilla. |
| `core/iril_engine.py` | Motor de cálculo: Índice de Riesgo (IRIL). | Clase de Servicio (ej. `Services\IrilEngine.php`). |
| `core/exposicion_engine.py` | Motor de cálculo: Exposición económica. | Clase de Servicio (ej. `Services\ExposicionEngine.php`). |
| `core/escenarios_engine.py` | Motor lógico: Generación de escenarios legales. | Clase de Servicio (ej. `Services\EscenariosEngine.php`). |
| `normativa/*.py` | Diccionarios (arrays) de leyes y artículos. | Archivos de configuración (ej. `config/leyes.php` devolviendo arrays) o tablas fijas en Base de Datos. |
| `database/db_manager.py` | Capa SQL, queries crudas o helpers. | Modelos ORM (Eloquent/Doctrine) o capa repository con PDO. |

---

## 3. Lógica Core y Estructura de Datos

Si vas a aplicar esto a tu otro sistema PHP, el núcleo a replicar es el flujo de datos. Actualmente el sistema recibe un diccionario único de datos y realiza una cadena de procesamiento:

### 3.1. Flujo de Procesamiento a replicar en PHP

1.  **Recepción de Datos (Input):**
    El sistema espera un array/JSON con tres pilares fundamentales que en PHP deberías validar en el Request:
    *   `datos_laborales` (salario, antigüedad, CCT, provincia).
    *   `documentacion` (telegramas, recibos, registros).
    *   `situacion` (intercambio telegráfico, intimaciones, ART).

2.  **Ejecución en Cadena (Pipeline en PHP):**
    En tu versión PHP, el controlador (o script principal) hará exactamente esto:
    ```php
    // Seudocódigo PHP
    $iril_score = $irilEngine->calcularIril($datos, $tipo_conflicto);
    $exposicion = $exposicionEngine->calcular($datos, $tipo_conflicto);
    $escenarios = $escenariosEngine->generar($exposicion, $iril_score, $datos);
    ```

3.  **Evaluación de Normativa (Leyes):**
    Las leyes más críticas (ej. Ley de Contrato de Trabajo, Ley 27.802, LRT) ahora están en Python como funciones que validan booleanos. En PHP, esto se puede manejar con métodos estáticos o traits.
    *   *Módulo crítico a observar:* `core/ripte_functions.py` que calcula presunción laboral y responsabilidad solidaria dependiendo de si hay recibo de salario, cuil validado, ART, etc.

---

## 4. Guía para la Migración de la Base de Datos

El sistema actual usa SQLite con queries SQL directas. Migrar esto a PHP típicamente se hará hacia **MySQL/MariaDB**.

Las tablas principales que debes crear en tu base de datos PHP:

1.  `analisis`: Tabla principal donde se guardan JSONs o columnas separadas de los resultados de cada peritaje/análisis. *(Sugerencia: Usa un campo tipo `JSON` nativo de MySQL para guardar las "variables de input" si cambian seguido).*
2.  `cct` y `escalas_salariales`: Para almacenar los Convenios Colectivos de Trabajo.
3.  `jurisprudencia`: Almacenamiento de fallos (búsqueda).

---

## 5. Recomendaciones Prácticas para PHP

1.  **Mantén los "Engines" separados:** No metas la lógica de sumas de antigüedad, preaviso o vacaciones (`exposicion_engine.py`) dentro de los Vistas o Controladores PHP. Crea un namespace específico para ellos (`App\Engines\` o `App\Services\Laboral\`).
2.  **Manejo de Arrays Multidimensionales:** Python utiliza fuertemente diccionarios como `dict.get('llave', 'default')`. En PHP, la traducción natural será usar variables de array con *null coalescing*: `$datos['llave'] ?? 'default'`.
3.  **API Frontend:** `app.py` expone un endpoint `/api/analizar`. Al pasar a PHP, puedes utilizar un controlador dedicado (`ApiController.php`) para retornar `json_encode($resultados)`. Esto permite que tu sistema PHP funcione tanto consumido vía vistas web (AJAX) o por otro sistema (incluso como un microservicio).
