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
