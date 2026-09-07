# Sistema de Conciliación y Facturación Electrónica (CFDI 4.0) - Carnicerías Victoria

Este proyecto gestiona la conciliación operativa y contable de ventas de puntos de venta (POS) multi-sucursal, facturación electrónica CFDI 4.0 mediante PAC Sinube, gestión de depósitos bancarios, conciliación de pagos PPD, administración de catálogos SAT / clientes y portal público de autofacturación.

---

## 🏗️ Arquitectura del Sistema

### 1. Topología Multi-Base de Datos (MySQL / MariaDB)

- **Base de Datos General (`carvic_sysvic_general`):**
  - **Clientes y Contactos:** `cust`, `cust_emails`
  - **Catálogos SAT y Productos:** `arts`, `sat_uso_cfdi`, `sat_metodo_pago`, `sat_regimen_fiscal`
  - **Bancos y Conciliación:** `bancos`, `depositos`, `deposito_factura`
  - **Facturación Histórica:** `facturas`
- **Bases de Datos por Sucursal:**
  - `Obrador` -> `carvic_sysvicobr`
  - `Victoria1` (Matriz) -> `carvic_sysvicm`
  - `Victoria2` (Sucursal) -> `carvic_sysvics`
  - `Produccion` -> `carvic_sysvicprod`

### 2. Integraciones Externas

- **PAC Sinube (Facturanube):** Emisión y timbrado CFDI 4.0, foliación dinámica por serie (`O`, `V`, `K`, `P`, `CEP`), consulta de estatus SAT (tipo 2000) y cancelación (tipo 43).
- **Mailer Transaccional:** Envío automático de facturas electrónicas (XML y PDF) mediante la API oficial de Gmail (OAuth2 con refresco automático de token) y PHPMailer (SMTP con TLS).

---

## 🧠 Base de Conocimiento para Agentes (`.agents/`)

Se ha estructurado la base de conocimiento para desarrollo asistido por IA en `.agents/`:

### 📜 Reglas del Sistema (`.agents/rules/`)

1. **[`agent-core-guidelines.md`](file://.agents/rules/agent-core-guidelines.md):** Perfil de desarrollo, restricción crítica de ejecución de comandos en terminal, logging dedicado y actualización de bitácora/README.
2. **[`database-multi-branch-guidelines.md`](file://.agents/rules/database-multi-branch-guidelines.md):** Topología multi-sucursal, uso estricto de sentencias preparadas, manejo de conexiones y ciclo de vida de tickets (`is_active` 1=activo, 2=pendiente, 3=facturado, 0=cancelado).
3. **[`cfdi-sinube-invoicing-guidelines.md`](file://.agents/rules/cfdi-sinube-invoicing-guidelines.md):** Protocolo de timbrado CFDI 4.0 con Sinube, escape XML `ENT_XML1`, formateo de RFC/CP, validación en catálogo `arts` e idempotencia.
4. **[`logging-and-observability-rules.md`](file://.agents/rules/logging-and-observability-rules.md):** Estándares de registro estructurado por canal y archivo en `logs/`, niveles de log y seguridad de credenciales.
5. **[`ui-frontend-conventions.md`](file://.agents/rules/ui-frontend-conventions.md):** Convenciones de interfaz Gentelella Alela / Bootstrap 3, Select2 asíncrono, SweetAlert2 y separación de scripts en `assets/js/` y `assets/css/`.

### 🛠️ Habilidades Especializadas (`.agents/skills/`)

1. **[`cfdi-sinube-billing`](file://.agents/skills/cfdi-sinube-billing/SKILL.md):** Ciclo completo de emisión de facturas CFDI 4.0 con Sinube, mapeo de conceptos SAT, persistencia y envío por correo.
2. **[`multi-branch-reconciliation`](file://.agents/skills/multi-branch-reconciliation/SKILL.md):** Consulta distribuida de tickets, agrupación de ventas (`vtaagrupados`) y generación de reportes de conciliación (`resumen_facturas`).
3. **[`bank-deposits-reconciliation`](file://.agents/skills/bank-deposits-reconciliation/SKILL.md):** Control de depósitos bancarios, control de saldos y abonos múltiples (`parent_id`), y vinculación a facturas PPD (`deposito_factura`).
4. **[`customer-catalog-management`](file://.agents/skills/customer-catalog-management/SKILL.md):** Administración de clientes fiscales (`cust`), validación de RFC/Régimen/CP y múltiples correos de notificación (`cust_emails`).
5. **[`transactional-mailer`](file://.agents/skills/transactional-mailer/SKILL.md):** Envío de comprobantes fiscales por correo con OAuth2 Gmail API y SMTP.
6. **[`api-autofacturacion`](file://.agents/skills/api-autofacturacion/SKILL.md):** Arquitectura y mantenimiento del endpoint público de autoservicio para facturación de clientes.
7. **[`laravel-blade-ui`](file://.agents/skills/laravel-blade-ui/SKILL.md):** Estándares para diseño de componentes Blade y Tailwind CSS.
8. **[`laravel-pest-testing`](file://.agents/skills/laravel-pest-testing/SKILL.md):** Generación de suites de pruebas con Pest PHP.
9. **[`laravel-scaffold`](file://.agents/skills/laravel-scaffold/SKILL.md):** Guía y scaffolding de servicios tipados y arquitectura limpia en PHP 8+ / Laravel.
10. **[`playwright-testing`](file://.agents/skills/playwright-testing/SKILL.md):** Automatización de pruebas end-to-end (E2E), verificación de flujos UI, interacción con Select2/SweetAlert2 y captura de evidencias con Playwright.
11. **[`ui-design-system`](file://.agents/skills/ui-design-system/SKILL.md):** Estandarización de arquitectura de vistas UI (Gentelella / Bootstrap 3), paleta de colores CSS `:root`, distintivos de sucursal, tarjetas de filtro, tablas, modales y adaptabilidad visual.

---

## 📝 Bitácora de Cambios

- **2026-09-05:**
  - **Columna de Observación en Facturas Generadas (`facturasqry.php`, `ajax/facturas_ajax.php`, `ajax/facturar_api.php`, `sql/`):**
    - Se incorporó la columna **Observación** en la tabla de facturas generadas de [facturasqry.php](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/facturasqry.php) (y [facturas_ppd.php](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/facturas_ppd.php)) mediante [ajax/facturas_ajax.php](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/ajax/facturas_ajax.php).
    - Muestra exclusivamente el valor registrado en el campo **OBSERVACIÓN** al momento de realizar la facturación.
    - **Restricción estricta de solo lectura:** No se permite agregar ni editar comentarios posteriores a la emisión de la factura (se removieron botones de edición/adición y el endpoint de actualización posterior).
    - Los registros con observación muestran un badge interactivo con tooltip que permite desplegar el texto completo en una ventana modal informativa de SweetAlert2 (`verObservacion()`).
    - Se sincronizó la persistencia de observaciones en [ajax/facturar_api.php](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/ajax/facturar_api.php), [ajax/facturar_grupo_api.php](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/ajax/facturar_grupo_api.php) y [api/autofacturacion.php](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/api/autofacturacion.php) con sentencias preparadas para asegurar que cualquier factura guarde su observación original en la tabla `facturas` de `carvic_sysvic_general`.
    - Script DDL de migración: [sql/20260905-2330-add_observacion_to_facturas_table.sql](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/sql/20260905-2330-add_observacion_to_facturas_table.sql) para ejecución manual y suite E2E en [tests/e2e/facturas_comentarios.spec.js](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/tests/e2e/facturas_comentarios.spec.js).
  - **Suites de Pruebas Unitarias PHP y Pruebas E2E Playwright (`tests/Unit/`, `tests/e2e/`, `playwright-testing/SKILL.md`):**
    - **Prueba Unitaria PHP ([tests/Unit/SucursalesCatalogTest.php](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/tests/Unit/SucursalesCatalogTest.php)):** Valida que `getBranchesList()` retorne las 5 sucursales oficiales (`Obrador`, `Victoria1`, `Victoria2`, `Produccion`, `CEP`), que `getBranchesConfig()` contenga exactamente las 4 bases de datos POS locales, la distinción de emisión directa para CEP, y la integridad de la serie fiscal en `$branchSeriesMap`.
    - **Prueba E2E Playwright ([tests/e2e/resumen_facturas.spec.js](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/tests/e2e/resumen_facturas.spec.js)):** Verifica la carga de filtros en `resumen_facturas.php`, presencia de las 5 opciones de sucursales en `#branch_filter`, interactividad de Select2 al abrir dropdowns y seleccionar `CEP`, recarga reactiva AJAX (`branch=CEP`) y exportación a CSV.
    - **Prueba E2E Playwright de Combos Select2 ([tests/e2e/select2_combos.spec.js](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/tests/e2e/select2_combos.spec.js)):** Suite global que valida la carga de opciones y funcionalidad interactiva de Select2 (clase `.select2-hidden-accessible`, contenedor visible, dropdown desplegable, búsqueda y actualización de valor en select original) en `resumen_facturas.php`, `cep_creacion.php` (búsqueda AJAX de clientes y catálogos SAT) y `depositos.php`.
    - **Auto-Adaptación del Skill:** Actualización de [playwright-testing/SKILL.md](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/.agents/skills/playwright-testing/SKILL.md) con el patrón estándar de validación de carga de valores y Select2 en combos.
  - **Estandarización Obligatoria de Select2 en Todos los Combos (`.agents/skills/ui-design-system/`, `layout.css`, `head.php`, `resumen_facturas.php`):**
    - Se actualizó el skill [ui-design-system](file://.agents/skills/ui-design-system/SKILL.md) y la regla [ui-frontend-conventions.md](file://.agents/rules/ui-frontend-conventions.md) formalizando la directriz obligatoria: **todos los combos (`<select>`) del sistema deben utilizar Select2**, prohibiendo el uso de combos HTML nativos.
    - Inclusión global del CDN de estilos `select2.min.css` en [head.php](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/head.php), complementando el script `select2.min.js` ya presente en `footer.php`.
    - Definición del tema corporativo de Select2 en [assets/css/layout.css](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/assets/css/layout.css) con alturas estandarizadas (34px para paneles/filtros y 38px para modales/formularios), bordes y focus rings en `--victoria-gold` y `--victoria-red`, flechas centradas y opciones resaltadas.
    - Implementación de Select2 en los combos de filtro de [resumen_facturas.php](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/resumen_facturas.php) (`#branch_filter` y `#per_page`) con reactividad automática al cambiar de opción.
  - **Soporte de Sucursal Cerdo en Pie (CEP) en Resumen de Conciliación (`resumen_facturas.php`, `config/config.php`):**
    - Se incorporó la opción de **Cerdo en Pie (CEP)** (`<option value="CEP">Cerdo en Pie (CEP)</option>`) dentro del filtro de sucursales en [resumen_facturas.php](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/resumen_facturas.php), permitiendo filtrar y consolidar las ventas y facturación de dicha unidad en la vista y en la exportación CSV (`export_resumen_facturas_csv.php`).
    - Se implementó la función centralizada `getBranchesList()` en [config/config.php](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/config/config.php) para evitar el código duro de sucursales en vistas HTML, implementando auto-descubrimiento dinámico mediante la tabla `sucursales` en `carvic_sysvic_general` con respaldo/fallback canónico predeterminado.
    - Se generó el script DDL de migración [sql/20260905-2300-create_sucursales_catalog_table.sql](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/sql/20260905-2300-create_sucursales_catalog_table.sql) con la definición de la tabla `sucursales` y seed idempotente para las 5 sucursales oficiales (`Obrador`, `Victoria1`, `Victoria2`, `Produccion`, `CEP`), listo para ejecución manual en MariaDB/MySQL sin ejecución automática de comandos.
    - Se actualizaron las reglas del sistema ([database-multi-branch-guidelines.md](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/.agents/rules/database-multi-branch-guidelines.md), [agent-core-guidelines.md](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/.agents/rules/agent-core-guidelines.md)) y el skill ([multi-branch-reconciliation/SKILL.md](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/.agents/skills/multi-branch-reconciliation/SKILL.md)) para establecer de forma obligatoria que ante cualquier solicitud de flujos multi-sucursal se tengan siempre identificadas y explicitadas las 5 sucursales oficiales y su distinción operativa (POS dedicado vs emisión directa general).
  - **Pruebas E2E Interactivas y Auto-Adaptación de Skill Playwright (`tests/e2e/facturas_ppd.spec.js`, `.agents/skills/playwright-testing/SKILL.md`):**
    - Se incorporaron dos nuevas suites de pruebas E2E automatizadas para validar el flujo interactivo de **agrupación de facturas PPD**:
      1. Selección acumulativa mediante checkboxes (`.check_ppd_item`), activación reactiva de la barra flotante (`#barSeleccionPPD`) con cálculo de saldos y validación interactiva de la regla de cliente único con modal de advertencia en SweetAlert2.
      2. Despliegue interactivo del modal de pago agrupado (`#modalPagoPPD`), renderizado dinámico de la tabla de facturas (`#tbodyFacturasPPDModal`), recálculo en tiempo real al editar montos individuales de abono (`.input-monto-ppd`) y cierre controlado.
    - Se actualizó el archivo [`SKILL.md`](file://.agents/skills/playwright-testing/SKILL.md) en el skill `playwright-testing` añadiendo el patrón de automatización para **Modales Bootstrap y Selección Múltiple / Agrupación** cumpliendo el protocolo de auto-adaptación continua.
  - **Corrección de Compatibilidad DDL en Migración (`sql/20260708-0020-update_depositos_facturas_saldo.sql`):**
    - Ajuste en la definición de la tabla `deposito_factura`: se sustituyó la restricción estricta de llave foránea hacia `facturas(id)` por índices estándar (`KEY idx_deposito_id`, `KEY idx_factura_id`) y se homogeneizó el tipo de `factura_id INT NOT NULL`.
    - Esta modificación resuelve el error MySQL `#1005 (errno: 150 "Foreign key constraint is incorrectly formed")` causado por discrepancias de signo/atributos con tablas históricas (`facturas.id`) y alinea el diseño a la arquitectura desacoplada ya utilizada en `depositos` y `cust_emails`.
- **2026-09-04:**
  - **Identificador de Ticket y Parcialidad en Comprobante de Pago REP (`ajax/facturas_ajax.php`, `ajax/depositos_ajax.php`):**
    - Se actualizó la generación del identificador de movimiento (`mov_id`) para los Complementos de Pago timbrados (REP). En lugar de utilizar una marca de tiempo genérica (`REP-{timestamp}`), ahora se compone dinámicamente con el prefijo `REP-`, el ID del ticket de venta original (`$mov_id`) y el número de pago/parcialidad aplicado (`$parcialidad`), por ejemplo `REP-{mov_id}-{parcialidad}`.
    - Soporte para pagos agrupados multi-documento concatenando las tuplas correspondientes (`REP-{mov_id1}-{p1},{mov_id2}-{p2}`).
    - Actualización del atributo `nomArchivoDescarga` en el payload de SiNube para mantener consistencia en la descarga de archivos XML y PDF.
    - Incorporación de trazabilidad estructurada en `logs/depositos.log` registrando el `mov_id`, UUID y serie/folio del REP timbrado.
- **2026-09-02:**
  - **Selección Múltiple por Cliente, Resumen de Facturas y Pago Agrupado REP 2.0 (`facturas_ppd.php`, `ajax/facturas_ajax.php`, `tests/e2e/facturas_ppd.spec.js`):**
    - **Regla de Negocio de Cliente Único:** Implementación de checkboxes con restricción interactiva para permitir seleccionar únicamente facturas pertenecientes al **mismo cliente** (`cust_id`), mostrando una advertencia de bloqueo clara y amigable en caso de intentar seleccionar facturas de clientes distintos.
    - **Barra Flotante de Selección y Resumen:** Visualización dinámica de la barra de resumen con el conteo de facturas seleccionadas, nombre del cliente activo, total acumulado de saldo pendiente, botón de limpieza rápida y botón de acción directa "Pagar Facturas Seleccionadas".
    - **Modal de Resumen y Desglose de Facturas:** Rediseño del modal `#modalPagoPPD` para mostrar una ficha del cliente receptor, tabla de facturas seleccionadas (Serie/Folio, UUID, Monto Original, Saldo Pendiente, Monto a Aplicar editable en tiempo real y Parcialidad), cálculo dinámico de totales y campos del comprobante REP 2.0.
    - **Backend Transaccional Multi-Documento:** Actualización de `get_invoice_ppd_details` y `timbrar_pago_ppd` en `ajax/facturas_ajax.php` para admitir pagos multi-factura, armar el DTO con múltiples `documentos` (`pago20:DoctoRelacionado`) para SiNube, persistir el comprobante REP en `facturas` y crear las entradas en `deposito_factura` dentro de una transacción atómica.
    - **Suite de Pruebas E2E:** Actualización de las aserciones en Playwright (`facturas_ppd.spec.js`).
- **2026-09-01:**
  - **Limpieza de Errores en SweetAlert y Corrección de Cliente Receptor (`facturas_ppd.php`, `ajax/facturas_ajax.php`):**
    - Se eliminó el volcado técnico de XML crudo (`res.raw`) en el diálogo de error de SweetAlert2 al timbrar pagos, mostrando exclusivamente el mensaje de error legible y comprensible.
    - Se configuró dinámicamente el identificador del cliente receptor (`$inv['cust_id']`) en la construcción del payload DTO para SiNube en lugar de un ID fijo.
  - **Desacoplamiento de URLs Base a Archivo de Configuración (`config/config.php`, `ajax/facturas_ajax.php`, `ajax/facturar_grupo_api.php`):**
    - Definición de `$api_base_url = "https://ep-dot-facturanube.appspot.com"` en `config/config.php` y derivación limpia de endpoints (`$api_url_blob`, `$api_url_cancel`).
    - Eliminación de URLs hardcodeadas e inyección directa de `$api_base_url` en la instanciación de `SiNubePagoService` y `$api_url_envio` en `facturar_grupo_api.php`.
- **2026-08-31:**
  - **Seguimiento de Pagos y Exclusión de Saldo Cero en Vista Facturas PPD (`ajax/facturas_ajax.php`, `tests/e2e/facturas_ppd.spec.js`):**
    - Adición de columnas de seguimiento financiero en el listado de facturas PPD: **Monto Total**, **Pagos Realizados** (`total_pagos`), **Total Pagado** (`total_pagado`) y **Saldo Pendiente** (`saldo_pendiente`).
    - Exclusión automática en la consulta SQL (`$sWhere`) de aquellas facturas PPD con saldo liquidado (`saldo <= 0.01`), manteniéndose en la vista únicamente comprobantes pendientes de pago.
    - Sincronización y actualización de la suite de pruebas E2E en Playwright (`facturas_ppd.spec.js`).
  - **Identidad Corporativa en Popups, SweetAlert2 y Modales (`assets/css/layout.css`, `.agents/skills/ui-design-system/`):**
    - Centralización global de variables `:root` en [layout.css](file:///home/javier/workspace/JYR/carniceriasvictoria/public_html/syspv/conciliacion/assets/css/layout.css) disponible en todas las vistas del sistema.
    - Estilización completa de diálogos, confirmaciones, toasts e iconos de **SweetAlert2** con borde dorado (`--victoria-gold`), títulos oscuros (`--victoria-black`), botones de confirmación rojos (`--victoria-red`) con efecto hover dorado, e iconos con acentos institucionales.
    - Homogeneización visual de modales Bootstrap (`.modal-content`, `.modal-header`, `.modal-title`, `.modal-footer`).
  - **Creación de Skill `ui-design-system` (`.agents/skills/ui-design-system/`):**
    - Estandarización de la estructura de vistas PHP (`head.php`, `sidebar.php`, `x_panel`, `x_title`, `card-stat`, `jambo_table`, `footer.php`).
    - Definición de tokens de diseño y paleta de colores corporativos (`--victoria-red`, `--victoria-gold`, `--victoria-black`, badges por sucursal) mediante variables CSS `:root` para personalización rápida y sin fricción.
    - Guías de implementación de componentes interactivos (Select2, SweetAlert2, modales, alineación numérica y responsive).
  - **Módulo de Timbrado REP 2.0 / CFDI 4.0 con SiNube (`classes/SiNube/`):**
    - Implementación de la arquitectura basada en DTOs tipados (`SiNubePagoDTO`, `SiNubeReceptorDTO`, `SiNubeDocumentoRelacionadoDTO`, `SiNubePagoResponseDTO`) bajo PHP 8.2+ con propiedades `readonly`.
    - Creación de [SiNubeXmlBuilder](/syspv/conciliacion/classes/SiNube/SiNubeXmlBuilder.php) ajustado al esquema exacto de Postman para el servicio `tipo=47` de SiNube (desglose de impuestos REP 2.0, datos bancarios SPEI opcionales y lista `UuidsRelacionados`).
    - Implementación de [SiNubePagoFactory](/syspv/conciliacion/classes/SiNube/SiNubePagoFactory.php) para mapeo rápido desde payloads o BD, y [SiNubePagoService](/syspv/conciliacion/classes/SiNube/SiNubePagoService.php) con soporte de sandbox/dev (`http://ep-dot-facturanube.appspot.com`), manejo de errores cURL/XML y trazabilidad en `logs/depositos.log`.
  - **Acción de Pago (REP 2.0) en Vista Facturas PPD (`facturas_ppd.php`, `ajax/facturas_ajax.php`):**
    - Habilitación de columnas de estatus, XML, PDF, envío por correo y acción **"Pago"** en el listado de facturas PPD.
    - Modal popup `#modalPagoPPD` con visualización de datos fiscales de la factura origen (Serie, Folio, Monto original, Saldo pendiente, UUID y número de Parcialidad) y campos de captura (Forma de pago, Fecha/hora, Monto a abonar, Serie de pago y Referencia).
    - Endpoints AJAX `get_invoice_ppd_details` y `timbrar_pago_ppd` para consultar saldos en tiempo real, validar importes, timbrar vía `tipo=47` con SiNube, persistir en `facturas` y registrar en `deposito_factura`.
- **2026-08-30:**
  - **Módulo de Facturas PPD (`facturas_ppd.php`, `ajax/facturas_ajax.php`, `sidebar.php`):**
    - Creación de la vista dedicada `facturas_ppd.php` y adición del submenú "Facturas PPD" en cada una de las sucursales (Obrador, Victoria 1, Victoria 2, Producción y Cerdo en Pie).
    - Filtrado dinámico en `ajax/facturas_ajax.php` para consultar exclusivamente comprobantes fiscales con método de pago _Por Definir_ (`metodo_pago = 'ppd'` / `99` / `Por Definir`) y en **estatus activo** (`(estatus = 1 OR estatus IS NULL) AND estado != 'Cancelada'`) desde `carvic_sysvic_general.facturas`.
    - Soporte completo para búsqueda por UUID/Folio, filtro por cliente, selector de registros por página, descarga de XML/PDF, reenvío por correo y cancelación.
  - **Suite de Pruebas Playwright & Auto-Adaptación del Skill (`tests/e2e/facturas_ppd.spec.js`, `playwright.config.js`, `.agents/skills/playwright-testing/`):**
    - Implementación de la suite de pruebas automatizadas E2E que valida la navegación multi-sucursal, petición AJAX con `metodo_pago=ppd`, consistencia de columnas y selectores de búsqueda.
    - Actualización del skill `playwright-testing` con el protocolo de detección de impacto y auto-adaptación continua de tests ante modificaciones en interfaces.
- **2026-08-22:**
  - Análisis exhaustivo de la arquitectura multi-base de datos, controladores AJAX, PAC Sinube, clases auxiliares y vistas.
  - Creación de la base de conocimiento para agentes en `.agents/rules/` y `.agents/skills/`.
  - **Módulo de Ventas Globales (`vtaqry.php` / `ajax/vtaqry.php`):** Diferenciación estricta entre el estatus **Inactivo** (`is_active = 0` en punto de venta) y **Factura Cancelada** (`estado = 'Cancelada'` / `estatus = 0` en base de datos general). Deshabilitación de la acción _Cambiar Estatus_ para tickets inactivos o con facturas canceladas/activas. Actualización visual en listado, previsualización HTML (`vta_html_ticket.php`) y generación PDF (`action/vtaticket.php`).
- **2026-08-23:**
  - **Reversión de Estatus de Ticket al Cancelar Factura (`ajax/facturas_ajax.php`):** Al cancelar un CFDI en Sinube, la factura se mantiene registrada como cancelada (`estatus = 0`, `estado = 'Cancelada'`) en la base de datos general, pero el ticket (o grupo de tickets) asociado en la sucursal origen se revierte automáticamente a estatus activo (`is_active = 1` en `vtahead` y `status = 1` en `groups_tickets`).
  - **Validación de Cambio de Estatus (`ajax/change_status.php`):** Ajuste para permitir la edición y refacturación de tickets cuyas facturas previas fueron canceladas, manteniendo la restricción de bloqueo únicamente para facturas en estado vigente/activo (`estatus = 1`).
  - **Separación de Entidades Ticket vs Factura (`ajax/vtaqry.php`, `ajax/facturas_ajax.php`):**
    - En el **Menú de Facturas (`facturasqry.php`)**: La **Factura** se conserva y muestra con estatus **Cancelada** (`estatus = 0`), conservando su historial, UUID, XML y PDF.
    - En el **Menú de Ventas / Tickets (`vtaqry.php`)**: El estatus visual y operativo del **Ticket** se rige estrictamente por su valor en `vtahead.is_active` (Pendiente = 2, Activo = 1, Facturado = 3, Agrupado = 4, Inactivo = 0). En la sección de **Pendientes** (`status=2`) se muestran exclusivamente los tickets en estatus pendiente (`is_active = 2`).
- **2026-09-06:**
  - **Modernización de Filtros y Búsqueda en Ventas Globales (`vtaqry.php`, `ajax/vtaqry.php`, `js/vtaqry.js`, `assets/css/vtaqry.css`):**
    - **Control Manual de Búsqueda:** Eliminación de la búsqueda automática por tecla (`onkeyup`) en favor de un botón explícito **Buscar** (`#btn_search`) y soporte para tecla Enter en el formulario, previniendo sobrecarga de peticiones al teclear.
    - **Filtro de Fecha:** Inclusión de campo de fecha (`#fecha_filter`, tipo `date`) que filtra con exactitud (`DATE(A.created_at) = ?`) y exime la restricción fija de 30 días permitiendo localizar tickets en cualquier fecha histórica.
    - **Filtro de Monto:** Inclusión de campo numérico de importe (`#monto_filter`, tipo `number step="0.01"`) que permite encontrar ventas por su importe exacto (`ROUND(A.sumimp, 2) = ROUND(?, 2)`).
    - **Botón de Limpiar:** Incorporación de botón para reiniciar filtros (`limpiarFiltros()`) y recargar el listado por defecto.
    - **Estandarización UI:** Rediseño del formulario en una tarjeta de filtros (`filter-card`) con labels e íconos temáticos, selectores adaptados con **Select2** (`#branch_filter`, `#fpay_filter`, `#per_page`), y logging estructurado en `logs/facturacion_individual.log`.
    - **Multi-Sucursal:** Selector de sucursales que contempla el catálogo oficial (Todas, Obrador, Victoria 1, Victoria 2, Producción) exclusivamente en la vista global (`branch=all`). En las vistas dedicadas de cada sucursal (`Obrador`, `Victoria1`, `Victoria2`, `Produccion`), el filtro se oculta y se fija automáticamente para prevenir cruce accidental de información entre unidades de venta.

