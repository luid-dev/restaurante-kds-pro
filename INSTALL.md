# Guía de instalación y puesta en marcha

Esta guía lleva el proyecto desde un `git clone` hasta el sistema funcionando con datos de demostración.
Hay dos formas de ejecutarlo; para evaluar el sistema basta la primera.

| Opción | Para qué sirve | Qué necesita instalado | URL |
| --- | --- | --- | --- |
| [A. Demo con Docker](#opción-a-demo-completa-con-docker) | Probar el sistema completo con un solo comando | Docker y Git | `http://localhost:8088` |
| [B. Desarrollo local](#opción-b-desarrollo-local) | Modificar el código con recarga en caliente | PHP, Composer, Node.js, pnpm y Docker | `http://localhost:5173` |

Contenido:

1. [Opción A: demo completa con Docker](#opción-a-demo-completa-con-docker)
2. [Opción B: desarrollo local](#opción-b-desarrollo-local)
3. [Primer ingreso al sistema](#primer-ingreso-al-sistema)
4. [Recorrido de prueba en 5 minutos](#recorrido-de-prueba-en-5-minutos)
5. [Pruebas automatizadas y calidad](#pruebas-automatizadas-y-calidad)
6. [Solución de problemas](#solución-de-problemas)
7. [Detener y limpiar](#detener-y-limpiar)

---

## Opción A: demo completa con Docker

### Requisitos

- Docker Desktop (macOS o Windows) o Docker Engine (Linux) con Docker Compose v2. Compruébelo con `docker compose version`.
- Git y OpenSSL (vienen instalados en macOS y Linux).
- En Windows, ejecute los comandos dentro de WSL2 (Ubuntu): `setup-demo.sh` es un script de bash.
- Puerto `8088` libre en la máquina.

### Instalación

```bash
git clone https://github.com/<usuario>/restaurante-kds-pro.git
cd restaurante-kds-pro
./setup-demo.sh
```

El script hace todo lo necesario y se puede volver a ejecutar sin riesgo:

1. Genera un `.env` en la raíz con secretos aleatorios: clave de la aplicación, contraseña de PostgreSQL,
   credenciales de Reverb y el *pepper* con el que se protegen los PIN. Este archivo nunca se versiona.
2. Construye las imágenes del backend (PHP 8.4-FPM) y del frontend (build de producción de React).
3. Levanta PostgreSQL y Redis, y aplica las migraciones.
4. Crea un certificado autofirmado para firmar los comprobantes electrónicos en modo de prueba.
5. Carga la pollería de demostración: salones, mesas, carta, recetas, insumos, cajas, series y usuarios.
6. Levanta el resto del stack: Nginx, API, worker de colas, Reverb y la SPA.

La primera ejecución tarda unos minutos porque descarga imágenes y dependencias. Al terminar muestra:

```text
Demo lista en http://localhost:8088
Empresa RUC 20609876543 · contraseña común Password123!
```

Abra `http://localhost:8088`. En la pantalla de acceso, el panel izquierdo debe indicar **Servidor: En línea**
y **Tiempo real: Conectado**. Continúe en [Primer ingreso al sistema](#primer-ingreso-al-sistema).

### Comandos del día a día

```bash
docker compose ps                  # estado de los 7 servicios
docker compose logs -f backend     # logs de la API (también: queue, reverb, nginx, frontend)
docker compose down                # detiene el stack (los datos se conservan)
docker compose up -d               # vuelve a levantarlo
docker compose up -d --build       # reconstruye tras actualizar el código (git pull)
./setup-demo.sh --reset            # borra y recrea la base de la demo desde cero
```

Para usar otro puerto, cambie `APP_PORT` en el `.env` de la raíz y ejecute `docker compose up -d --build`.

---

## Opción B: desarrollo local

En esta modalidad la API corre con `php artisan serve`, Reverb y la cola en procesos locales, y el frontend con
el servidor de desarrollo de Vite. PostgreSQL y Redis corren en Docker con `backend/compose.yaml`.

### Requisitos

| Herramienta | Versión | Comprobación |
| --- | --- | --- |
| PHP | 8.4 o superior, con `bcmath`, `intl`, `pdo_pgsql`, `soap`, `openssl` y `zip` | `php -v` y `php -m` |
| Composer | 2.x | `composer --version` |
| Node.js | 22 o superior | `node -v` |
| pnpm | 10.x | `pnpm -v` (instalación: `corepack enable`) |
| Docker | con Compose v2 | `docker compose version` |

Puertos que deben estar libres: `5173` (frontend), `8000` (API), `8080` (Reverb), `5432` (PostgreSQL) y `6379` (Redis).

### 1. Configurar el backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Abra `backend/.env` y complete estas variables. Puede generar cada valor aleatorio con el comando indicado:

| Variable | Valor | Cómo generarlo |
| --- | --- | --- |
| `DB_PASSWORD` | Contraseña de PostgreSQL local | `openssl rand -hex 16` |
| `REVERB_APP_ID` | Número de 6 dígitos | por ejemplo `482913` |
| `REVERB_APP_KEY` | Clave pública de WebSockets | `openssl rand -hex 10` |
| `REVERB_APP_SECRET` | Secreto de WebSockets | `openssl rand -hex 20` |
| `PIN_PEPPER` | Secreto para proteger los PIN (mínimo 16 caracteres) | `openssl rand -hex 32` |

El resto de valores de `.env.example` ya apunta a `localhost` y funciona sin cambios.

### 2. Levantar PostgreSQL y Redis

```bash
docker compose up -d   # dentro de backend/: lee DB_DATABASE, DB_USERNAME y DB_PASSWORD de backend/.env
```

Los puertos solo escuchan en `127.0.0.1`. La primera vez se crean dos bases: `restaurante_kds` (desarrollo) y
`restaurante_kds_test` (suite de pruebas).

### 3. Crear las tablas y los datos de demostración

```bash
php artisan migrate --seed
php artisan sunat:dev-certificate
```

`--seed` carga la empresa demo y sus usuarios. El segundo comando crea el certificado autofirmado con el que se
firman los comprobantes; sin él, la caja no puede cobrar.

### 4. Arrancar la API, Reverb y la cola

```bash
composer dev
```

Este comando mantiene cuatro procesos en una sola terminal: API en `http://localhost:8000`, Reverb en el puerto
`8080`, el worker de colas y el visor de logs. La primera vez npm pregunta
`Need to install the following packages: @laravel/multiplex ... Ok to proceed? (y)`: responda `y`.
Para que no pregunte, puede usar `npm_config_yes=true composer dev`.

Deje esta terminal abierta. Compruebe la API desde otra:

```bash
curl http://localhost:8000/api/v1/health
```

### 5. Configurar y arrancar el frontend

En una segunda terminal:

```bash
cd frontend
pnpm install
cp .env.example .env.local
```

Edite `frontend/.env.local`:

- `VITE_REVERB_APP_KEY`: el mismo valor que `REVERB_APP_KEY` en `backend/.env`.
- `VITE_DEMO_MODE=true` si quiere ver los botones de ingreso rápido por rol (solo para evaluación).

```bash
pnpm dev
```

Abra `http://localhost:5173`. Vite redirige `/api` y `/sanctum` hacia `http://localhost:8000`, así que el navegador
y la API comparten origen y la cookie de sesión funciona sin configurar CORS. Abra siempre `localhost`, no
`127.0.0.1`, para que coincida con `SANCTUM_STATEFUL_DOMAINS`.

---

## Primer ingreso al sistema

Empresa de demostración: **El Dorado Pollería**, RUC `20609876543`. Contraseña común: `Password123!`.

| Rol | Correo | PIN | Módulos disponibles |
| --- | --- | --- | --- |
| Gerente (Admin) | `admin@restaurante.pe` | `111111` | Todos; abre en Rentabilidad |
| Cajero | `caja@restaurante.pe` | `222222` | Salones, Toma de Pedidos, KDS (solo lectura), Caja, Comprobantes y Rentabilidad |
| Mozo | `mozo@restaurante.pe` | `333333` | Salones y Toma de Pedidos |
| Cocina KDS | `cocina@restaurante.pe` | `444444` | Pantalla de cocina |

Hay tres formas de entrar:

- **Terminal de Salón (PIN)**: escriba el RUC `20609876543`. Se habilita el teclado numérico; digite el PIN de
  6 dígitos y el ingreso se valida al completar el último. La terminal recuerda el RUC para el siguiente turno.
- **Acceso Corporativo**: correo y contraseña. Pensado para gerencia y caja.
- **Modo evaluación**: botones de un clic por rol, visibles solo con `VITE_DEMO_MODE=true` (la demo Docker los trae activos).

Por seguridad, una sesión iniciada con PIN se cierra tras 30 minutos sin actividad, y los intentos fallidos
están limitados (5 por minuto por terminal).

---

## Recorrido de prueba en 5 minutos

Este recorrido ejercita el ciclo completo de una mesa. Abra dos ventanas del navegador (o una normal y otra
privada) para ver el tiempo real entre roles.

1. **Mozo**: en *Salones y Mesas* (`Alt+1`) toque una mesa libre, indique los comensales y ábrala. En
   *Toma de Pedidos* (`Alt+2`) agregue platos de distintas categorías, escriba una nota para cocina y pulse
   **Enviar comanda a cocina** (`F4`). El stock de insumos se descuenta en ese momento según la receta.
2. **Cocina**: en *Pantalla Cocina (KDS)* (`Alt+3`) la comanda aparece al instante, separada por área (Horno /
   Brasas, Cocina Caliente, Barra / Bebidas). Marque los platos **En preparación** y luego **Listo para servir**;
   el mozo ve el cambio de estado sin recargar.
3. **Caja**: en *Caja y Facturación* (`Alt+4`) abra el turno con un fondo inicial, pulse **Cobrar** en la mesa
   y elija *Cuenta completa*, *Dividir por platos* o *Partes iguales*. Seleccione Boleta, Factura (requiere RUC)
   o Nota de Venta, combine medios de pago (efectivo con vuelto, Yape, Plin, tarjeta) y emita.
4. **Comprobantes**: en *Comprobantes SUNAT* (`Alt+5`) descargue el XML UBL 2.1 firmado, vea el ticket de 80 mm,
   emita una nota de crédito o exporte el archivo SIRE RVIE del mes.
5. **Gerencia**: en *Recetas y Kardex* (`Alt+6`) revise el stock valorizado y el kardex 13.1; en *Rentabilidad*
   (`Alt+7`) vea ventas, food cost teórico, mermas y margen por plato del día.

En la demo, el envío real a SUNAT está desactivado (`SUNAT_SEND_ENABLED=false`): los comprobantes se firman y
quedan en estado *Pendiente de envío*.

---

## Pruebas automatizadas y calidad

La suite corre sobre PostgreSQL real, en la base aislada `restaurante_kds_test`; nunca toca la base de desarrollo.

```bash
cd backend
cp .env.testing.example .env.testing    # copie DB_USERNAME y DB_PASSWORD de backend/.env
php artisan key:generate --env=testing
php artisan test                        # pruebas de integración
vendor/bin/phpstan analyse              # Larastan nivel 9
vendor/bin/pint --test                  # estilo de código
```

```bash
cd frontend
pnpm typecheck   # TypeScript estricto
pnpm lint        # Oxlint
pnpm build       # build de producción
```

---

## Solución de problemas

**En la pantalla de acceso aparece "Servidor: Sin respuesta", "Tiempo real: Conexión fallida" y al ingresar
"Respuesta inesperada del servidor".**
El frontend está abierto pero la API no responde. En la opción B, falta ejecutar `composer dev` dentro de
`backend/` (paso 4) o ese proceso se detuvo; compruébelo con `curl http://localhost:8000/api/v1/health`. En la
opción A, revise `docker compose ps` y `docker compose logs backend`.

**Solo "Tiempo real: Conexión fallida", pero el servidor está en línea.**
Reverb no está corriendo o las claves no coinciden. Verifique que `composer dev` muestre el proceso `reverb` y que
`VITE_REVERB_APP_KEY` (frontend) sea igual a `REVERB_APP_KEY` (backend). Después de editar `.env.local`, reinicie
`pnpm dev`: Vite solo lee las variables al arrancar.

**"Empresa vinculada: Sin vincular" y el teclado de PIN no responde.**
Es el estado normal de una terminal nueva: escriba el RUC `20609876543` en el campo RUC.

**Error 419 o "CSRF token mismatch" al ingresar.**
Abra el sistema en `http://localhost:5173`, no en `127.0.0.1`, o agregue ese host a `SANCTUM_STATEFUL_DOMAINS` y
`CORS_ALLOWED_ORIGINS` en `backend/.env`.

**`docker compose` responde "Ejecute ./setup-demo.sh para generar .env".**
El stack de la demo necesita los secretos del `.env` de la raíz. Ejecute `./setup-demo.sh` una vez.

**"port is already allocated" o "address already in use".**
Otro programa usa el puerto. Deténgalo o, en la demo, cambie `APP_PORT` en el `.env` de la raíz. Si tiene un
PostgreSQL local en el `5432`, defina `FORWARD_DB_PORT=5433` en `backend/.env` y use ese puerto en `DB_PORT`.

**La caja no deja cobrar: "abra un turno" o error de certificado.**
Abra el turno de caja desde el panel superior de *Caja y Facturación*. Si el error menciona el certificado,
ejecute `php artisan sunat:dev-certificate` (en la demo Docker ya se crea automáticamente).

**"PIN_PEPPER no está configurado".**
Falta ese valor en `backend/.env` (mínimo 16 caracteres). Genérelo con `openssl rand -hex 32` antes del paso 3:
los PIN se guardan protegidos con ese pepper. Si el seeder falló por este motivo en una instalación nueva, ejecute
`php artisan migrate:fresh --seed`; tenga en cuenta que ese comando borra todos los datos de la base de desarrollo.

**Las pruebas fallan con "No se pudo verificar la base restaurante_kds_test".**
PostgreSQL no está levantado o `backend/.env.testing` no tiene las credenciales correctas.

**`./setup-demo.sh: Permission denied`.**
Ejecute `chmod +x setup-demo.sh` o lance el script con `bash setup-demo.sh`.

---

## Detener y limpiar

```bash
# Opción A (desde la raíz del proyecto)
docker compose down        # detiene la demo y conserva los datos
docker compose down -v     # además borra la base y los archivos de la demo

# Opción B
# Ctrl+C en las terminales de "composer dev" y "pnpm dev", y luego:
cd backend && docker compose down
```
