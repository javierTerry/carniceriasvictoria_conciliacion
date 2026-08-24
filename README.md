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

---

## 📝 Bitácora de Cambios

- **2026-08-22:**
  - Análisis exhaustivo de la arquitectura multi-base de datos, controladores AJAX, PAC Sinube, clases auxiliares y vistas.
  - Creación de la base de conocimiento para agentes en `.agents/rules/` y `.agents/skills/`.
  - **Módulo de Ventas Globales (`vtaqry.php` / `ajax/vtaqry.php`):** Diferenciación estricta entre el estatus **Inactivo** (`is_active = 0` en punto de venta) y **Factura Cancelada** (`estado = 'Cancelada'` / `estatus = 0` en base de datos general). Deshabilitación de la acción *Cambiar Estatus* para tickets inactivos o con facturas canceladas/activas. Actualización visual en listado, previsualización HTML (`vta_html_ticket.php`) y generación PDF (`action/vtaticket.php`).
- **2026-08-23:**
  - **Reversión de Estatus de Ticket al Cancelar Factura (`ajax/facturas_ajax.php`):** Al cancelar un CFDI en Sinube, la factura se mantiene registrada como cancelada (`estatus = 0`, `estado = 'Cancelada'`) en la base de datos general, pero el ticket (o grupo de tickets) asociado en la sucursal origen se revierte automáticamente a estatus activo (`is_active = 1` en `vtahead` y `status = 1` en `groups_tickets`).
  - **Validación de Cambio de Estatus (`ajax/change_status.php`):** Ajuste para permitir la edición y refacturación de tickets cuyas facturas previas fueron canceladas, manteniendo la restricción de bloqueo únicamente para facturas en estado vigente/activo (`estatus = 1`).
  - **Separación de Entidades Ticket vs Factura (`ajax/vtaqry.php`, `ajax/facturas_ajax.php`):**
    - En el **Menú de Facturas (`facturasqry.php`)**: La **Factura** se conserva y muestra con estatus **Cancelada** (`estatus = 0`), conservando su historial, UUID, XML y PDF.
    - En el **Menú de Ventas / Tickets (`vtaqry.php`)**: El estatus visual y operativo del **Ticket** se rige estrictamente por su valor en `vtahead.is_active` (Pendiente = 2, Activo = 1, Facturado = 3, Agrupado = 4, Inactivo = 0). En la sección de **Pendientes** (`status=2`) se muestran exclusivamente los tickets en estatus pendiente (`is_active = 2`).
