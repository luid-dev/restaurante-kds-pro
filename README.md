# Restaurante KDS Pro — ERP Gastronómico, Comandas en Tiempo Real y Facturación SUNAT UBL 2.1

Sistema empresarial de gestión gastronómica que diseñé para salones, pollerías y restaurantes en Perú.
Incluye control de concurrencia en mesas, comandas instantáneas vía WebSockets (Laravel Reverb), escandallo
fraccionario de recetas (Kardex Valorizado Formato 13.1), división de cuentas (*Split Bill*) y facturación
electrónica SUNAT UBL 2.1 (Boletas, Facturas y Notas de Crédito, modalidad Detallada o Por Consumo, soporte
IGV MYPE y afectaciones gravadas, exoneradas e inafectas).

Lo construí como un monorepo desacoplado: una API REST versionada (`backend/`) y una SPA (`frontend/`) que se
comunican solo por HTTP (JSON) y WebSockets. Cada operación que toca una mesa, una comanda o el stock corre en
una transacción con bloqueo pesimista (`SELECT ... FOR UPDATE`) y un orden global de bloqueo, de modo que dos
mozos nunca abren la misma mesa ni descuentan dos veces el mismo insumo. Las cantidades del escandallo se
guardan en `NUMERIC(14,4)` y se calculan con BCMath, sin aritmética de punto flotante.

## Stack Tecnológico

| Capa | Tecnología |
| --- | --- |
| Backend | PHP 8.4, Laravel 13 (API-Only), Sanctum (sesión SPA por cookie), Spatie Permission (roles por empresa) |
| Base de datos | PostgreSQL 16 |
| Caché, sesiones y colas | Redis 7 |
| Tiempo real | Laravel Reverb (WebSockets) y Laravel Echo |
| Facturación electrónica | Greenter: XML UBL 2.1 firmado, envío a SUNAT y lectura del CDR |
| Frontend | React 19, TypeScript 5.9, Vite, Tailwind CSS, shadcn/ui, TanStack Query, Zustand, Zod |
| Gestores de paquetes | Composer y `pnpm` |
| Infraestructura | Docker Compose: Nginx, PHP-FPM, worker de colas, Reverb, PostgreSQL y Redis |

## Guía de Instalación y Uso Local

### Requisitos previos

- PHP 8.4+ con las extensiones `bcmath`, `pdo_pgsql`, `soap` e `intl`
- Composer 2
- Node.js 22+ y `pnpm` 10
- Docker con Docker Compose v2 y OpenSSL

### Configuración rápida automatizada

```bash
./setup-demo.sh
```

El script genera un `.env` en la raíz con secretos aleatorios (clave de la aplicación, contraseña de
PostgreSQL, credenciales de Reverb y pepper de los PIN), construye las imágenes, migra la base de datos, crea un
certificado de prueba para firmar los comprobantes y carga una pollería de demostración. Es idempotente;
`./setup-demo.sh --reset` recrea la base de la demo.

### Levantamiento de contenedores

Con el `.env` ya generado, el stack completo se levanta y reconstruye con:

```bash
docker compose up -d --build
```

La aplicación queda disponible en `http://localhost:8088`. Un único puerto sirve la SPA, la API (`/api`) y
los WebSockets (`/app`), así que la cookie de sesión es de primera parte y no se necesita CORS con credenciales.
Para detener el stack: `docker compose down`.

### Desarrollo sin contenedores de aplicación

```bash
cd backend
composer install
cp .env.example .env               # completar DB_PASSWORD y REVERB_APP_ID/KEY/SECRET
php artisan key:generate
docker compose up -d               # solo PostgreSQL y Redis, escuchando en 127.0.0.1
php artisan migrate --seed
php artisan sunat:dev-certificate
composer dev                       # API :8000, cola, Reverb :8080 y logs
```

```bash
cd frontend
pnpm install
cp .env.example .env.local         # VITE_REVERB_APP_KEY = REVERB_APP_KEY del backend
pnpm dev                           # http://localhost:5173
```

La suite de pruebas corre sobre una base aislada (`restaurante_kds_test`): copie `backend/.env.testing.example`
a `backend/.env.testing`, ejecute `php artisan key:generate --env=testing` y luego `php artisan test`.

### Credenciales de prueba integradas

Empresa demo con RUC `20609876543` y contraseña común `Password123!`. El ingreso rápido por PIN pide el RUC
y el PIN de 6 dígitos del usuario.

| Rol | Correo | PIN |
| --- | --- | --- |
| Admin (Gerente) | `admin@restaurante.pe` | `111111` |
| Cajero | `caja@restaurante.pe` | `222222` |
| Mozo | `mozo@restaurante.pe` | `333333` |
| Cocina KDS | `cocina@restaurante.pe` | `444444` |

Estas cuentas solo existen en entornos locales o de demostración (`APP_DEMO_MODE=true`).

## Adaptación Empresarial

¿Desea implementar este sistema en su restaurante, pollería o cadena de establecimientos? El software es
completamente adaptable a sus requerimientos operativos (múltiples salones, impresoras térmicas de cocina,
integración con balanzas o pasarelas de pago). Contácteme para coordinar la personalización y puesta en marcha
para su empresa.

- Correo: _(por completar)_
- Perfil profesional o sitio web: _(por completar)_
