Objetivo: Convertir el **Book Price App / BPE** en una **puerta de entrada CMS**, especialmente para imprentas pequeñas que ya usan WordPress \+ WooCommerce y no quieren entrar inicialmente en un OS completo.

La idea sería:

PrintPricePro BPE WooCommerce Plugin  
\= calculador de precios de libros para WordPress/WooCommerce  
\= primer nodo comercial ligero  
\= canal de conversión hacia licencias PrintPrice OS

La app actual ya tiene una base muy aprovechable:

Frontend:  
\- React \+ Vite  
\- BookPriceForm  
\- AssistantChat  
\- PrintOffersPanel  
\- CartPanel  
\- CheckoutStepper  
\- PdfUploadDropzone  
\- ProductionFilesPanel  
\- CustomerPaymentPanel  
\- CustomerOrderTracking  
\- PrinthouseQueue

Backend:  
\- Express  
\- MySQL / JSON repository adapters  
\- JWT  
\- Stripe  
\- File upload  
\- Order intents  
\- Offer sessions  
\- Marketplace offers  
\- Control Plane integration  
\- Preflight integration  
\- Dispatch packages  
\- Printhouse production queue

Endpoints clave ya existentes:

POST /api/budget/calculate  
POST /api/orders/create-from-offer  
POST /api/cart/add  
POST /api/cart/checkout  
POST /api/order-intents  
POST /api/order-intents/:id/preflight/start  
POST /api/order-intents/:id/billing/create  
POST /api/order-intents/:id/finalize  
POST /api/production-files/upload  
GET  /api/printhouse/queue  
POST /api/printhouse/queue/:packageId/status

Eso quiere decir que **no estamos empezando de cero**. El plugin WooCommerce debería ser una extensión y empaquetado comercial del BPE, no una reescritura completa.

---

# **Visión del producto**

## **Nombre recomendado**

PrintPricePro Book Price Engine for WooCommerce

o más corto:

PrintPricePro BPE for WooCommerce

## **Propuesta de valor**

Para imprentas pequeñas:

Instala un calculador profesional de precios de libros en tu WooCommerce en minutos.  
Recibe pedidos configurados, PDFs de producción y solicitudes listas para presupuestar o fabricar.  
Conecta opcionalmente con la red federada PrintPrice OS para acceder a preflight, automatización, clientes externos y producción distribuida.

## **Mensaje comercial**

Empieza con un calculador.  
Evoluciona hacia un nodo federado.

Ese mensaje es clave. El plugin no debe venderse como “otro plugin de formulario”, sino como el **primer paso hacia el sistema operativo de impresión**.

---

# **Modelo de producto**

Yo lo dividiría en 4 niveles.

## **Nivel 1 — Free / Lead Magnet**

Plugin gratuito en WordPress o distribución directa.

Incluye:

\- Shortcode básico de calculador.  
\- Formulario de libro: tamaño, páginas, copias, color, papel, encuadernación.  
\- Cálculo estimado local o vía API demo.  
\- Conversión a producto WooCommerce.  
\- Envío de solicitud de presupuesto.  
\- Branding: “Powered by PrintPricePro”.

Objetivo:

Captar imprentas pequeñas.  
Instalar presencia.  
Medir uso.  
Generar leads.

## **Nivel 2 — Pro Plugin**

Licencia mensual.

Incluye:

\- Reglas de precios personalizadas.  
\- Márgenes por producto.  
\- Tablas de materiales.  
\- Costes de encuadernación.  
\- Costes de preparación.  
\- Costes de envío.  
\- PDF upload para interior/cubierta.  
\- Guardado de pedidos de impresión.  
\- Conversión directa a pedido WooCommerce.  
\- Emails personalizados.  
\- Sin branding visible.

Objetivo:

Monetizar imprentas que solo necesitan calculator \+ WooCommerce.

## **Nivel 3 — Connected Node**

Plugin conectado al PrintPrice OS.

Incluye:

\- Conexión segura con Control Plane.  
\- Sincronización de pedidos.  
\- Preflight opcional.  
\- File validation.  
\- Dispatch packages.  
\- Estado de producción.  
\- Printhouse queue.  
\- Audit trail.  
\- Marketplace readiness.

Objetivo:

Convertir la imprenta en nodo operativo federado.

## **Nivel 4 — Federated Marketplace Node**

Licencia avanzada / revenue share.

Incluye:

\- Participación en red federada.  
\- Recepción de pedidos externos.  
\- Capacidad productiva declarada.  
\- SLA / disponibilidad.  
\- Pricing API conectada.  
\- Production status sync.  
\- Quality / delivery score.  
\- Financial readiness.

Objetivo:

Convertir imprentas en partners productivos del marketplace PrintPrice OS.

---

# **Arquitectura recomendada**

## **Plugin WordPress / WooCommerce**

El plugin debería tener esta estructura:

printpricepro-bpe-woocommerce/  
├── printpricepro-bpe-woocommerce.php  
├── includes/  
│   ├── class-ppp-bpe-plugin.php  
│   ├── class-ppp-bpe-admin.php  
│   ├── class-ppp-bpe-settings.php  
│   ├── class-ppp-bpe-calculator.php  
│   ├── class-ppp-bpe-woocommerce.php  
│   ├── class-ppp-bpe-api-client.php  
│   ├── class-ppp-bpe-license.php  
│   ├── class-ppp-bpe-order-sync.php  
│   ├── class-ppp-bpe-file-upload.php  
│   ├── class-ppp-bpe-webhooks.php  
│   └── class-ppp-bpe-logger.php  
├── public/  
│   ├── css/  
│   ├── js/  
│   └── views/  
├── admin/  
│   ├── css/  
│   ├── js/  
│   └── views/  
├── templates/  
│   ├── calculator-form.php  
│   ├── offer-results.php  
│   ├── upload-files.php  
│   └── order-summary.php  
├── languages/  
└── readme.txt

---

# **Dos enfoques técnicos posibles**

## **Opción A — Plugin PHP nativo**

El calculador se reconstruye como formulario PHP/JS nativo dentro de WordPress.

Ventajas:

\- Mejor integración WordPress.  
\- Más ligero.  
\- Mejor para WordPress.org.  
\- Más fácil de mantener para imprentas pequeñas.  
\- WooCommerce hooks nativos.

Desventajas:

\- Hay que portar parte de la UI React.  
\- Menos reutilización directa del BookPrice App actual.

## **Opción B — Plugin con React embebido**

Se compila la app React del BookPrice como bundle y se inserta mediante shortcode.

Ventajas:

\- Reutiliza componentes existentes.  
\- UI más moderna.  
\- Más rápido para MVP.  
\- Mantiene lógica visual del BookPrice App.

Desventajas:

\- Más pesado.  
\- Más delicado en themes WordPress.  
\- Puede chocar con CSS/JS de Elementor, Divi, Avada, etc.

## **Recomendación**

Para MVP:

Opción B — React embebido

Para versión estable comercial:

Opción híbrida:  
\- Admin y settings en PHP nativo.  
\- Calculator frontend en React embebido.  
\- WooCommerce/cart/order hooks en PHP.

---

# **Flujo funcional del plugin**

## **Flujo 1 — Calculador simple**

Usuario entra en página de imprenta  
↓  
Ve calculador PrintPricePro  
↓  
Introduce especificaciones del libro  
↓  
Plugin llama a BPE local/API  
↓  
Devuelve precio estimado  
↓  
Usuario añade al carrito WooCommerce  
↓  
Pedido queda guardado con metadatos de impresión

Metadatos WooCommerce:

\_printprice\_book\_size  
\_printprice\_pages  
\_printprice\_copies  
\_printprice\_binding  
\_printprice\_cover\_type  
\_printprice\_paper\_type  
\_printprice\_color\_mode  
\_printprice\_print\_price  
\_printprice\_shipping\_price  
\_printprice\_total\_price  
\_printprice\_offer\_id  
\_printprice\_offer\_signature

---

## **Flujo 2 — Pedido con subida de archivos**

Usuario calcula precio  
↓  
Añade al carrito  
↓  
Checkout WooCommerce  
↓  
Después del pago o antes del pago, sube:  
  \- Interior PDF  
  \- Cover PDF  
↓  
Plugin valida existencia/formato básico  
↓  
Opcionalmente envía a Preflight  
↓  
Pedido queda READY\_FOR\_REVIEW o FILES\_REQUIRED

Estados internos:

DRAFT\_QUOTE  
PRICE\_CALCULATED  
ADDED\_TO\_CART  
CHECKOUT\_STARTED  
FILES\_REQUIRED  
FILES\_UPLOADED  
PREFLIGHT\_PENDING  
PREFLIGHT\_PASSED  
PREFLIGHT\_BLOCKED  
READY\_FOR\_PRODUCTION

---

## **Flujo 3 — Conversión a OS Node**

Imprenta usa plugin Free/Pro  
↓  
Empieza a recibir pedidos  
↓  
Panel muestra limitaciones:  
  \- Sin preflight avanzado  
  \- Sin marketplace federado  
  \- Sin dispatch packages  
  \- Sin production queue avanzada  
↓  
CTA: “Conviértete en nodo PrintPrice OS”  
↓  
Se crea cuenta tenant en Control Plane  
↓  
Plugin recibe API key / node token  
↓  
Sincroniza capacidades y pedidos

---

# **Relación con el BookPrice App actual**

El BookPrice App actual tiene piezas que se pueden convertir en módulos del plugin.

## **Reutilizable directamente**

BookPriceForm.tsx  
PrintOffersPanel.tsx  
AssistantChat.tsx  
PdfUploadDropzone.tsx  
CheckoutStepper.tsx  
ProductionFilesPanel.tsx  
CustomerOrderTracking.tsx

## **Reutilizable como backend/API inspiration**

/api/budget/calculate  
/api/orders/create-from-offer  
/api/production-files/upload  
/api/order-intents  
/api/order-intents/:id/preflight/start  
/api/order-intents/:id/billing/create  
/api/printhouse/queue

## **Debe adaptarse a WooCommerce**

CartPanel  
CustomerPaymentPanel  
Order intent finalization  
Stripe/bank transfer logic  
Checkout flow

Porque WooCommerce ya tiene:

Cart  
Checkout  
Orders  
Emails  
Payment gateways  
Customer accounts  
Taxes  
Coupons  
Shipping

La regla sería:

No competir con WooCommerce en checkout. Usar WooCommerce como checkout, y PrintPricePro como cálculo \+ producción \+ OS bridge.

---

# **Arquitectura de integración**

## **Modo Local**

Para imprentas pequeñas que no quieren conexión OS todavía.

WordPress plugin  
↓  
Local pricing rules  
↓  
WooCommerce product/order  
↓  
Email to print house

Uso:

Free / Pro básico

## **Modo API BPE**

El plugin llama a la Pricing Engine API.

WordPress plugin  
↓  
PrintPricePro BPE API  
↓  
Offers/pricing  
↓  
WooCommerce cart/order

Uso:

Pro / SaaS license

## **Modo Federated Node**

El plugin actúa como nodo ligero del OS.

WordPress plugin  
↓  
Control Plane tenant  
↓  
Pricing Engine  
↓  
Preflight  
↓  
Production queue  
↓  
Marketplace federation

Uso:

Connected Node / Marketplace Node

---

# **Pantallas necesarias del plugin**

## **Admin — Settings**

PrintPricePro \> Settings

Campos:

Mode:  
\- Local  
\- API  
\- Federated Node

API Base URL  
License Key  
Tenant ID  
Node ID  
Webhook Secret  
Default Currency  
Default Country  
Default Tax Mode  
WooCommerce Product Mapping  
Branding Toggle  
Debug Mode

## **Admin — Pricing Rules**

PrintPricePro \> Pricing Rules

Para modo local:

Paper cost  
Print cost  
Binding cost  
Setup cost  
Cover cost  
Lamination cost  
Margin  
Minimum order  
Quantity breaks  
Shipping rules

## **Admin — Book Products**

PrintPricePro \> Book Products

Plantillas:

Paperback  
Hardcover  
Magazine  
Catalog  
Workbook  
Children’s Book  
Photo Book

## **Admin — Orders**

PrintPricePro \> Print Orders

Estados:

Quote  
Awaiting Files  
Files Uploaded  
Preflight Pending  
Preflight Passed  
Preflight Blocked  
Ready for Production  
In Production  
Completed

## **Admin — Node Upgrade**

PrintPricePro \> Join PrintPrice OS

Aquí está el funnel.

Debe mostrar:

\- Tu calculador ya está funcionando.  
\- Puedes recibir más pedidos desde la red PrintPrice.  
\- Activa tu nodo federado.  
\- Conecta Preflight.  
\- Conecta producción.  
\- Solicita onboarding.

Botones:

Connect to PrintPrice OS  
Request Node License  
Book onboarding call  
Upgrade to Marketplace Node

---

# **Shortcodes / bloques**

## **Shortcode principal**

\[printpricepro\_bpe\_calculator\]

Opciones:

\[printpricepro\_bpe\_calculator product\_type="paperback" mode="compact"\]  
\[printpricepro\_bpe\_calculator product\_type="hardcover" show\_upload="true"\]  
\[printpricepro\_bpe\_calculator default\_copies="500" country="ES"\]

## **Gutenberg block**

PrintPricePro Book Calculator

Opciones visuales:

Compact  
Full  
Wizard  
Quote only  
Add to cart  
Upload files

## **Elementor widget**

Muy recomendable para adopción comercial:

PrintPricePro Calculator Widget

Porque muchas imprentas pequeñas usan Elementor.

---

# **Producto WooCommerce**

El plugin puede crear automáticamente un producto base:

PrintPricePro Custom Book Order

Tipo:

simple product  
virtual: no  
sold individually: optional  
price: dynamic

ID guardado en option:

ppp\_bpe\_base\_product\_id

Al añadir al carrito:

WC()-\>cart-\>add\_to\_cart($product\_id, 1, 0, \[\], \[  
  'ppp\_bpe\_specs' \=\> $specs,  
  'ppp\_bpe\_offer' \=\> $offer,  
  'ppp\_bpe\_signature' \=\> $signature,  
\]);

En checkout, el precio debe fijarse vía hook:

woocommerce\_before\_calculate\_totals

Y en order meta:

woocommerce\_checkout\_create\_order\_line\_item

---

# **Seguridad mínima**

El plugin debe implementar desde el principio:

Nonce en AJAX/REST  
Capabilities en admin  
Sanitización de inputs  
Validación de archivos  
Límites de tamaño PDF  
Offer signatures  
Rate limiting básico  
API key en wp\_options cifrada o al menos no expuesta  
No secrets en frontend  
Logs sin datos sensibles

Muy importante:

El precio calculado no debe venir “confiado” desde el frontend.

Debe venir firmado desde:

Local calculator seguro  
o  
BPE API  
o  
Control Plane

---

# **Conversión comercial hacia OS**

Este plugin tiene que estar diseñado como funnel.

## **Eventos que debe medir**

plugin\_installed  
calculator\_rendered  
quote\_calculated  
offer\_selected  
added\_to\_cart  
checkout\_started  
order\_created  
files\_uploaded  
preflight\_requested  
preflight\_blocked  
monthly\_quote\_limit\_reached  
node\_upgrade\_clicked  
license\_connected

## **Triggers de conversión**

### **Trigger 1 — Volumen**

Has generado 50 presupuestos este mes.  
Conecta PrintPrice OS para automatizar pedidos y producción.

### **Trigger 2 — Archivos**

Tus clientes están subiendo PDFs.  
Activa Preflight para detectar errores antes de producción.

### **Trigger 3 — Marketplace**

Tu imprenta puede recibir pedidos externos desde la red federada.  
Solicita activación como nodo.

### **Trigger 4 — Producción**

Gestiona cola de producción, estados y paquetes seguros con Control Plane.

### **Trigger 5 — Profesionalización**

Elimina hojas Excel manuales y centraliza pricing, pedidos y archivos.

---

# **Roadmap por fases**

## **Phase WCP-1 — Plugin Skeleton \+ WooCommerce Base Product**

Objetivo:

Crear el plugin instalable, settings básicos y producto WooCommerce base.

Entregables:

printpricepro-bpe-woocommerce.php  
includes/class-ppp-bpe-plugin.php  
includes/class-ppp-bpe-settings.php  
includes/class-ppp-bpe-woocommerce.php

Funciones:

Activación/desactivación  
Creación automática de producto base  
Página admin Settings  
Shortcode vacío funcional  
Chequeo WooCommerce activo

Validación:

Plugin activa sin fatal errors  
WooCommerce detectado  
Producto base creado  
Shortcode renderiza contenedor

---

## **Phase WCP-2 — Calculator UI MVP**

Objetivo:

Renderizar calculador usable en página WordPress.

Opciones:

React embebido del BookPrice App  
o  
formulario PHP/JS simple

Campos mínimos:

Book size  
Pages  
Copies  
Interior color  
Cover color  
Binding  
Paper  
Country

Resultado:

Precio estimado  
Resumen de especificaciones  
Botón Add to Cart

Validación:

Shortcode funciona  
No rompe Elementor  
Responsive móvil  
Calcula precio dummy/local

---

## **Phase WCP-3 — BPE API Integration**

Objetivo:

Conectar plugin con Book Price Engine / Pricing Engine.

Settings:

BPE API URL  
License key  
Tenant ID  
Mode: Local / API

Endpoint plugin:

/wp-json/printpricepro/v1/calculate

Flujo:

Frontend → WP REST endpoint → BPE API → signed offer → frontend

Validación:

No API key en frontend  
Errores manejados  
Timeouts controlados  
Fallback si API no responde  
Offer signature guardada

---

## **Phase WCP-4 — WooCommerce Cart / Checkout Integration**

Objetivo:

Convertir oferta calculada en carrito y pedido WooCommerce.

Funciones:

Add to cart dinámico  
Precio dinámico seguro  
Order item meta  
Email order summary  
Admin order summary  
Customer order summary

Hooks:

woocommerce\_before\_calculate\_totals  
woocommerce\_checkout\_create\_order\_line\_item  
woocommerce\_order\_item\_meta\_end  
woocommerce\_email\_order\_meta

Validación:

Precio no manipulable desde frontend  
Pedido contiene specs completas  
Email muestra resumen  
Checkout normal de WooCommerce intacto

---

## **Phase WCP-5 — PDF Upload Step**

Objetivo:

Permitir subida de Interior PDF y Cover PDF.

Dónde:

Antes del checkout  
Después del checkout  
o en página de pedido

MVP recomendado:

Después del pedido, en Thank You page / My Account.

Archivos:

Interior PDF  
Cover PDF

Estados:

FILES\_REQUIRED  
FILES\_UPLOADED  
FILES\_REJECTED

Validación:

PDF only  
Tamaño máximo configurable  
Archivos asociados al order\_id  
Links protegidos  
No descarga pública directa

---

## **Phase WCP-6 — Preflight Bridge**

Objetivo:

Activar integración opcional con Preflight.

Modo Free:

Preflight no disponible o demo limitado.

Modo Pro/Node:

Enviar PDFs a Preflight service.  
Recibir estado.  
Mostrar resumen humanizado.

Estados:

PREFLIGHT\_PENDING  
PREFLIGHT\_PASSED  
PREFLIGHT\_WARNINGS  
PREFLIGHT\_BLOCKED

Validación:

No bloquea WooCommerce si está desactivado  
Muestra cambios entendibles  
Permite reupload si bloqueado

---

## **Phase WCP-7 — Control Plane Node Connection**

Objetivo:

Conectar la imprenta al sistema federado.

Settings:

Control Plane URL  
Tenant ID  
Node ID  
Node API Key  
Webhook Secret

Funciones:

Sync order to Control Plane  
Sync files  
Sync production status  
Receive marketplace dispatch package  
Expose local production capacity

Validación:

Handshake seguro  
Tenant identificado  
Pedidos sincronizados  
Errores auditados  
No se envían datos si Node Mode está off

---

## **Phase WCP-8 — Printhouse Mini Queue**

Objetivo:

Dar a la imprenta pequeña una cola simple de producción dentro de WordPress.

Vista:

PrintPricePro \> Production Queue

Columnas:

Order  
Customer  
Book specs  
Files  
Preflight status  
Payment status  
Production status  
Actions

Estados:

NEW  
REVIEWING  
ACCEPTED  
IN\_PREPRESS  
IN\_PRODUCTION  
COMPLETED  
SHIPPED  
ACTION\_REQUIRED

Validación:

Cambio de estado auditado  
Cliente puede ver tracking  
Emails opcionales

---

## **Phase WCP-9 — Licensing & SaaS Conversion Layer**

Objetivo:

Convertir uso del plugin en licencias PrintPrice OS.

Funciones:

License activation  
Plan limits  
Usage metering  
Upgrade prompts  
Node onboarding CTA  
Feature gating

Planes:

Free  
Pro Calculator  
Preflight Add-on  
Connected Node  
Marketplace Node

Validación:

Sin licencia: modo básico  
Licencia Pro: API pricing \+ sin branding  
Node: Control Plane enabled  
Marketplace: federation enabled

---

## **Phase WCP-10 — WordPress.org / Commercial Release**

Objetivo:

Preparar distribución.

Entregables:

readme.txt  
assets/banner  
assets/icon  
screenshots  
.po/.mo  
uninstall.php  
security review  
PHPCS  
WP coding standards  
WooCommerce compatibility declaration  
HPOS compatibility

Validación:

Sin warnings PHP  
Sin secrets  
Sin llamadas externas no declaradas  
Compatible HPOS  
Compatible WooCommerce latest  
Compatible PHP 8.1+

---

# **MVP recomendado**

Para salir rápido, yo haría este MVP:

WCP-1 Plugin Skeleton  
WCP-2 Calculator UI MVP  
WCP-3 BPE API Integration  
WCP-4 WooCommerce Cart / Checkout Integration  
WCP-9 Basic Licensing / Upgrade CTA

Dejaría para fase 2:

PDF Upload  
Preflight Bridge  
Control Plane Node Connection  
Production Queue  
Marketplace Node

Porque el primer objetivo es adopción y conversión, no replicar todo el OS dentro de WordPress.

---

# **Qué NO debemos hacer al principio**

No metería en el primer MVP:

Preflight completo  
Production queue avanzada  
Stripe propio  
Dispatch packages  
Marketplace federation  
Multi-tenant complejo  
AI assistant completo

Razón:

El plugin debe ser fácil de instalar y fácil de entender.  
Si empieza demasiado complejo, las imprentas pequeñas no lo adoptarán.

---

# **Estrategia comercial**

## **Producto de entrada**

Calculador profesional para libros en WooCommerce.

## **Producto de expansión**

Preflight \+ archivo correcto antes de imprimir.

## **Producto premium**

Nodo federado PrintPrice OS.

## **Producto enterprise**

Control Plane completo \+ marketplace \+ producción distribuida.

---

# **Copy de landing**

Convierte tu WooCommerce en un calculador profesional de impresión de libros.

PrintPricePro Book Price Engine permite a imprentas pequeñas recibir pedidos de libros personalizados, calcular precios al instante y convertir presupuestos en pedidos WooCommerce.

Empieza con un calculador.  
Conecta Preflight cuando necesites validar archivos.  
Activa tu nodo PrintPrice OS cuando quieras formar parte de una red federada de producción.

---

# **Pricing sugerido**

## **Free**

0 €/mes  
\- Calculador básico  
\- 1 plantilla de libro  
\- Branding PrintPricePro  
\- Solicitud de presupuesto  
\- Sin API avanzada

## **Pro Calculator**

29–49 €/mes  
\- Sin branding  
\- Reglas de precios  
\- WooCommerce checkout  
\- Productos ilimitados  
\- Email summaries

## **Preflight Add-on**

79–149 €/mes  
\- PDF upload  
\- Preflight básico  
\- Reporte humanizado  
\- Reupload flow

## **Connected Node**

199–399 €/mes  
\- Control Plane connection  
\- Production queue  
\- Order sync  
\- Secure file dispatch

## **Marketplace Node**

Revenue share \+ fee mensual  
\- Recepción de pedidos federados  
\- SLA  
\- Capacidad productiva  
\- Scoring  
\- Marketplace routing

---

# **Prompt inicial para desarrollo**

Implement Phase WCP-1 — PrintPricePro BPE WooCommerce Plugin Skeleton.

Context:  
We are creating a WooCommerce plugin that acts as the CMS entry point for small print houses into the PrintPricePro federated OS. The plugin will start as a book price calculator powered by the PrintPricePro Book Price App / BPE, and later convert users into paid OS licenses and federated nodes.

Goal:  
Create the initial WordPress/WooCommerce plugin skeleton.

Plugin name:  
PrintPricePro BPE for WooCommerce

Folder:  
printpricepro-bpe-woocommerce

Main file:  
printpricepro-bpe-woocommerce.php

Requirements:  
1\. Plugin header with name, description, version, author.  
2\. WooCommerce dependency check.  
3\. Activation hook:  
   \- create or detect a base WooCommerce product named “PrintPricePro Custom Book Order”.  
   \- store product ID in option ppp\_bpe\_base\_product\_id.  
4\. Deactivation hook:  
   \- do not delete data.  
5\. Admin menu:  
   \- PrintPricePro  
   \- Settings  
   \- Pricing Rules  
   \- Orders  
   \- Join PrintPrice OS  
6\. Settings fields:  
   \- mode: local/api/federated\_node  
   \- BPE API URL  
   \- license key  
   \- tenant ID  
   \- node ID  
   \- default currency  
   \- default country  
   \- debug mode  
7\. Public shortcode:  
   \[printpricepro\_bpe\_calculator\]  
8\. Shortcode should render a safe placeholder container:  
   \<div id="printpricepro-bpe-calculator"\>\</div\>  
9\. Add public JS/CSS enqueue only when shortcode is present.  
10\. Add admin JS/CSS enqueue only on plugin admin pages.  
11\. Add REST namespace:  
   /wp-json/printpricepro/v1  
12\. Add health endpoint:  
   GET /wp-json/printpricepro/v1/health  
13\. Health endpoint returns:  
   plugin version,  
   WooCommerce active,  
   mode,  
   base product ID,  
   production flags disabled.  
14\. Security:  
   \- use nonces for future public actions.  
   \- use manage\_woocommerce capability for admin pages.  
   \- sanitize all settings.  
   \- do not expose license key in frontend.

Create basic file structure:  
\- includes/class-ppp-bpe-plugin.php  
\- includes/class-ppp-bpe-admin.php  
\- includes/class-ppp-bpe-settings.php  
\- includes/class-ppp-bpe-woocommerce.php  
\- includes/class-ppp-bpe-rest.php  
\- public/css/ppp-bpe-public.css  
\- public/js/ppp-bpe-public.js  
\- admin/css/ppp-bpe-admin.css  
\- admin/js/ppp-bpe-admin.js  
\- templates/calculator-placeholder.php  
\- languages/

Add a smoke test checklist in docs/phase-wcp-1-smoke-checklist.md.

Acceptance:  
\- Plugin activates without fatal errors.  
\- If WooCommerce is missing, plugin shows admin notice and does not fatal.  
\- Base product is created or detected.  
\- Shortcode renders placeholder.  
\- REST health endpoint works.  
\- Settings page saves sanitized options.  
\- No production OS connection is enabled by default.

---

# **Mi recomendación estratégica**

Este plugin debe ser tratado como:

**Acquisition product**

no como:

Full OS replacement

La función real del plugin es:

1\. Entrar en WordPress/WooCommerce.  
2\. Resolver una necesidad inmediata: calcular precios de libros.  
3\. Capturar datos de uso.  
4\. Generar pedidos.  
5\. Mostrar límites naturales.  
6\. Convertir a Pro / Preflight / Node / Marketplace.

La frase estratégica sería:

**El plugin WooCommerce es el caballo de Troya comercial de PrintPrice OS para imprentas pequeñas.**

