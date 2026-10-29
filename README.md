# API REST de ventas — Laravel y PostgreSQL

Backend de una prueba técnica para consultar productos, registrar ventas con
control de stock, consultar sus detalles y eliminarlas de forma lógica.
El alcance obligatorio está implementado. Los descuentos opcionales no están
implementados.

## Requisitos locales

- PHP 8.2 o compatible con las dependencias del proyecto.
- Composer 2.
- PostgreSQL iniciado y un usuario con permisos para crear tablas en su base.
- Extensiones PHP requeridas por Laravel y Composer, en particular `pdo_pgsql`.
- Para las pruebas automatizadas: `pdo_sqlite` y SQLite disponibles en PHP.
- Git; opcionalmente curl, Postman o un cliente HTTP equivalente.

No necesitas Node.js, npm ni compilar assets para consumir esta API. El frontend
se inicia por separado, siguiendo las instrucciones de su propio repositorio.
No es necesario configurar workers de colas para las operaciones de ventas.

## Instalación desde cero

Ejecuta los comandos desde una terminal. Los ejemplos utilizan Bash/Zsh.

### 1. Clonar el repositorio

Sustituye `URL_DEL_REPOSITORIO` por la URL de este repositorio:

```bash
git clone URL_DEL_REPOSITORIO api-sales
cd api-sales
```

### 2. Instalar dependencias y configurar Laravel

```bash
composer install
```

Si todavía no existe `.env`, créalo desde el ejemplo:

```bash
cp .env.example .env
```

No sobrescribas `.env` si ya contiene tu configuración. Genera la clave:

```bash
php artisan key:generate
```

### 3. Crear una base PostgreSQL vacía

Conéctate a PostgreSQL mediante DBeaver, pgAdmin o psql con un usuario que pueda
crear bases. Ejecuta esta sentencia fuera de una transacción:

```sql
CREATE DATABASE mercatech_db;
```

Si la creas con un usuario distinto al de la aplicación, asegúrate de que el
usuario de Laravel sea propietario o tenga permisos de creación en el esquema
`public`. Para una evaluación local, puedes crearla con el mismo usuario que
configurarás en `.env`. No necesitas importar un respaldo: las migraciones y
los seeders reconstruyen la estructura y los datos iniciales.

### 4. Configurar la conexión en `.env`

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=UTC

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=mercatech_db
DB_USERNAME=tu_usuario_postgresql
DB_PASSWORD="tu_contraseña_postgresql"
```

Reemplaza usuario y contraseña por tus credenciales. Ajusta host y puerto si
corresponden a otra instalación. `.env.example` puede incluir `root` como
usuario de ejemplo: debes sustituirlo por un usuario real de PostgreSQL.
No publiques `.env` ni credenciales en el repositorio. `APP_DEBUG=true` es
para desarrollo local; en un despliegue de producción debe ser `false`.

### 5. Crear tablas y cargar datos iniciales

```bash
php artisan config:clear
php artisan migrate --seed
php artisan migrate:status
```

En una base vacía, se crean las tablas, las claves foráneas, dos usuarios de
prueba y diez productos. Las ventas y sus detalles comienzan vacíos. La API
utiliza el usuario fijo `1`; no necesita iniciar sesión ni enviar un token.

Ejecuta `--seed` una sola vez para esta instalación. Los seeders actuales no
son idempotentes: repetirlos puede causar errores por usuarios duplicados o
duplicar productos. Para aplicar futuras migraciones usa `php artisan migrate`
sin `--seed`. No necesitas borrar la base ni ejecutar `migrate:fresh`.

### 6. Ejecutar las pruebas automatizadas

```bash
php artisan test
```

La última verificación obtuvo **22 pruebas correctas y 131 comprobaciones**.
Las pruebas de productos y ventas crean una base SQLite en memoria y no
modifican los datos de PostgreSQL. Cubren validación, cálculos, stock, rollback,
listado, eliminación lógica y respuestas de error. Los bloqueos de fila y la
concurrencia requieren verificación adicional en PostgreSQL: SQLite no
reproduce ese comportamiento.

### 7. Iniciar el servidor

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Mantén esta terminal abierta. La URL base de la API es
`http://127.0.0.1:8000/api`. El servidor de Artisan es para desarrollo local.

## Verificación manual de extremo a extremo

Usa otra terminal o Postman. Envía `Accept: application/json`; en POST, también
`Content-Type: application/json`. Las siguientes operaciones crean y eliminan
ventas reales en tu base local y descuentan stock.

### 1. Comprobar el servidor y consultar productos

```bash
curl -i -H 'Accept: application/json' http://127.0.0.1:8000/api/test
curl -i -H 'Accept: application/json' http://127.0.0.1:8000/api/products
```

Ambos deben responder HTTP `200`. `/test` devuelve `status: "ok"`, pero es la
consulta de productos la que comprueba el acceso a la base de datos.

### 2. Registrar una venta

```bash
curl -i -X POST http://127.0.0.1:8000/api/sales \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"user_id":1,"items":[{"product_id":1,"quantity":2},{"product_id":3,"quantity":1}]}'
```

Con los productos iniciales, obtendrás HTTP `201` y un cuerpo similar a:

```json
{
  "message": "Venta creada correctamente",
  "sale_id": 1,
  "total": "15450.00"
}
```

Guarda el `sale_id` recibido: no asumas que siempre será `1`. Los IDs pueden
tener saltos incluso después de una transacción revertida.

### 3. Consultar ventas

```bash
curl -i -H 'Accept: application/json' http://127.0.0.1:8000/api/sales
```

Debe responder HTTP `200` con un arreglo de ventas activas, usuario, fecha y
productos vendidos. Los importes son cadenas con dos decimales. `created_at`
se devuelve en ISO 8601 UTC, por ejemplo `2026-10-28T23:46:43.000Z`; el frontend
lo convierte a la zona del usuario. La precisión almacenada es de segundos.
Los detalles conservan el precio unitario histórico de la venta.

### 4. Verificar stock insuficiente y rollback

Primero anota el stock del producto `1` y las ventas existentes. Luego envía:

```bash
curl -i -X POST http://127.0.0.1:8000/api/sales \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"items":[{"product_id":1,"quantity":1},{"product_id":3,"quantity":2147483647}]}'
```

Con los datos iniciales, responde HTTP `422` por stock insuficiente de la
segunda línea. Consulta nuevamente productos y ventas: no debe aparecer una
venta nueva ni cambiar el stock del producto `1`, aunque se procesó primero.

### 5. Eliminar una venta y comprobar el error 404

Reemplaza el `1` de la URL por el `sale_id` que recibiste:

```bash
curl -i -X DELETE -H 'Accept: application/json' http://127.0.0.1:8000/api/sales/1
```

Devuelve HTTP `200` con `{"message":"Venta eliminada correctamente"}`.
La eliminación es lógica, conserva los detalles y no devuelve stock. La venta
ya no aparece en el listado. Repetir la petición devuelve HTTP `404` con
`{"message":"Venta no encontrada."}`.

## Contrato de la API e integración del frontend

| Método | Endpoint | Resultado esperado |
|---|---|---|
| GET | `/api/products` | 200: productos existentes |
| POST | `/api/sales` | 201: venta registrada; 422: entrada o stock inválidos |
| GET | `/api/sales` | 200: ventas activas con detalles |
| DELETE | `/api/sales/{id}` | 200: eliminada; 404: inexistente o ya eliminada |

Consulta la [guía de integración del frontend](docs/FRONTEND.md) para ver los
contratos JSON completos, tipos TypeScript, cliente fetch, fechas y errores.
El frontend debe apuntar a `http://127.0.0.1:8000/api`. Si utiliza otro origen,
revisa CORS o el proxy de desarrollo según las instrucciones de esa guía.
No hay autenticación, descuentos, paginación, filtros ni edición de ventas.

## Organización del backend

- `routes/api.php`: rutas HTTP.
- `app/Http/Controllers/Api`: coordinación de solicitudes y respuestas.
- `app/Http/Requests`: validación de entrada.
- `app/Actions`: casos de uso, transacciones y acceso a datos con Eloquent.
- `app/Http/Resources`: formato JSON de las respuestas.
- `app/Models`: modelos, relaciones y eliminación lógica.
- `database/migrations` y `database/seeders`: estructura y datos iniciales.
- `tests/Feature`: pruebas de los endpoints.

## Problemas frecuentes

| Mensaje o síntoma | Qué comprobar |
|---|---|
| `could not find driver` | Instalar o habilitar `pdo_pgsql` para PostgreSQL y `pdo_sqlite` para las pruebas en el PHP de consola. |
| `connection refused` | PostgreSQL iniciado y host/puerto correctos. |
| `no password supplied` o fallo de autenticación | Completar `DB_USERNAME` y `DB_PASSWORD`, guardar `.env` y ejecutar `php artisan config:clear`. |
| La base no existe | Crear `mercatech_db` o ajustar `DB_DATABASE`. |
| `permission denied for schema public` | Conceder permisos o usar el propietario de la base/esquema. |
| Usuario duplicado al cargar datos | No repetir `--seed` en una base ya inicializada. |
| Puerto 8000 ocupado | Usar otro puerto con `--port=8001` y actualizar la URL en el cliente. |
| El navegador bloquea la petición por CORS | Revisar el origen permitido o el proxy del frontend; consultar la guía de integración. |

## Enunciado original de la prueba

Las secciones siguientes conservan los requisitos de referencia. Sus ejemplos
son ilustrativos; el contrato implementado y sus campos adicionales se explican
arriba y en la guía del frontend. Los descuentos descritos al final siguen
siendo opcionales y no están implementados.

## Objetivo

Desarrollar una API REST en Laravel para gestionar un flujo básico de ventas.

La prueba evaluará:

- Uso de relaciones entre modelos.
- Validación de datos.
- Manejo de transacciones.
- Control de stock.
- Lógica de negocio.
- Respuestas JSON y códigos HTTP.
- Pruebas mediante Postman u otro cliente HTTP.

---

## Estado inicial del proyecto

El proyecto ya incluye:

- Laravel configurado como API.
- Migraciones listas.
- Seeders con productos cargados.
- Modelos base: `Product`, `Sale` y `SaleDetail`.

---

## Fuera de alcance

No se debe implementar mantenimiento de productos.

Por lo tanto, no corresponde:

- Crear productos.
- Modificar productos.
- Eliminar productos.

Solo se deben utilizar los productos existentes.

---

## Requerimientos

### 1. Listar productos

**Endpoint**

```http
GET /api/products
```

Debe retornar:

- `id`
- `name`
- `price`
- `stock`

**Ejemplo**

```json
[
  {
    "id": 1,
    "name": "Laptop Lenovo",
    "price": 7500,
    "stock": 10
  }
]
```

---

### 2. Crear una venta

Usar el usuario estático:

```text
user_id = 1
```

**Endpoint**

```http
POST /api/sales
```

**Request**

```json
{
  "user_id": 1,
  "items": [
    {
      "product_id": 1,
      "quantity": 2
    },
    {
      "product_id": 3,
      "quantity": 1
    }
  ]
}
```

La operación debe:

1. Validar que exista al menos un producto.
2. Validar que cada producto exista.
3. Validar que la cantidad sea mayor que cero.
4. Verificar que exista stock suficiente.
5. Crear la venta.
6. Crear los detalles de la venta.
7. Calcular el subtotal de cada producto.
8. Calcular el total de la venta.
9. Descontar el stock vendido.

Los precios deben obtenerse desde la base de datos y no desde el request.

La creación de la venta, sus detalles y el descuento de stock deben ejecutarse dentro de una transacción.

**Cálculos**

```text
subtotal = price × quantity
total = suma de subtotales
```

**Ejemplo de respuesta**

```json
{
  "message": "Venta creada correctamente",
  "sale_id": 1,
  "total": 16500
}
```

Si no existe stock suficiente, la venta no debe registrarse parcialmente.

---

### 3. Listar ventas

**Endpoint**

```http
GET /api/sales
```

Debe retornar:

- `id`
- `total`
- `created_at`
- Detalle de productos vendidos

**Ejemplo**

```json
[
  {
    "id": 1,
    "total": 16500,
    "created_at": "2026-01-21",
    "details": [
      {
        "product": "Laptop Lenovo",
        "quantity": 2,
        "subtotal": 15000
      }
    ]
  }
]
```

---

### 4. Eliminar una venta

**Endpoint**

```http
DELETE /api/sales/{id}
```

Debe eliminar la venta seleccionada.

**Ejemplo de respuesta**

```json
{
  "message": "Venta eliminada correctamente"
}
```

Si la venta no existe, debe devolverse una respuesta de error adecuada.

---

## Reglas de negocio

- Una venta debe contener al menos un producto.
- No se permiten cantidades iguales o menores que cero.
- No se puede vender más stock del disponible.
- El precio debe obtenerse desde el producto registrado.
- El subtotal se calcula por producto.
- El total corresponde a la suma de los subtotales.
- La creación debe ejecutarse dentro de una transacción.
- Ante cualquier error, no deben quedar datos parciales.
- Las respuestas deben estar en formato JSON.

---

## Resultado esperado

Al finalizar la prueba, la API debe permitir:

- Consultar productos existentes.
- Registrar una venta con sus detalles.
- Calcular subtotales y total.
- Descontar stock.
- Consultar ventas.
- Eliminar ventas.

---

## Características Opcionales

### Descuento por producto

**Esta funcionalidad es opcional y no es requerida para completar la prueba.**

Se puede implementar un sistema de descuentos aplicados a nivel de producto dentro de una venta.

**Cambios en el request de crear venta**

```json
{
  "user_id": 1,
  "items": [
    {
      "product_id": 1,
      "quantity": 2,
      "discount_percent": 10
    },
    {
      "product_id": 3,
      "quantity": 1,
      "discount_percent": 5
    }
  ]
}
```

**Cálculos con descuento**

```text
subtotal = price × quantity
discount_amount = subtotal × (discount_percent / 100)
subtotal_with_discount = subtotal - discount_amount
total = suma de subtotales_with_discount
```

**Cambios en la respuesta de listar ventas**

```json
[
  {
    "id": 1,
    "total": 15000,
    "total_discount": 1500,
    "created_at": "2026-01-21",
    "details": [
      {
        "product": "Laptop Lenovo",
        "quantity": 2,
        "subtotal": 15000,
        "discount_percent": 10,
        "discount_amount": 1500,
        "subtotal_with_discount": 13500
      }
    ]
  }
]
```

**Migración requerida**

Se debe crear las migraciones requeridas para aplicar los requerimientos solicitados

**Reglas de negocio adicionales (si se implementa)**

- El descuento es opcional (por defecto 0%).
- El descuento debe ser un valor entre 0 y 10.
- El cálculo del descuento debe realizarse dentro de la transacción.
- El total debe incluir la suma de los subtotales con descuento.
