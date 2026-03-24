```markdown
# SilverioMaziveBackend - Dynamic ORM with Meta and Versioning (PHP)

## Description

**SilverioMaziveBackend** is a minimal PHP backend implementing a **dynamic ORM** based on "meta" tables.

It provides flexible, schema-less data storage inside SQL databases by using `meta_key` and `meta_value` (JSON) fields, making it behave like a **NoSQL-like layer** on top of SQL.

Main features:

* Dynamic CRUD for any table specified via query string (`?table=table_name`)
* Flexible `meta_key` and `meta_value` fields (JSON), allowing arbitrary fields per record
* Dot notation support for `meta_value` (`meta_value.profile.name`)
* Soft delete by changing `meta_key`
* Automatic backup/versioning of records before updates
* RESTful JSON endpoints for easy integration
* App-level security via `X-AppCode` header
* Centralized configuration via `App\Config\Config.php`
* Easy to scale: new fields can be added to records without altering table columns

This design allows developers to **expand the project without migrations**, adding attributes directly in `meta_value` JSON objects.

---

## Configuration

All application settings are centralized in:

```

app/Config/Config.php

````

Example:

```php
<?php
namespace App\Config;

class Config
{
    // App authentication code (required in headers)
    public const APP_CODE = 'MEU_APP_CODE_SECRETO_123';

    // Database configuration
    public const DB_HOST = 'localhost';
    public const DB_NAME = 'yourdatabase';
    public const DB_USER = 'root';
    public const DB_PASS = '';
    public const DB_CHARSET = 'utf8mb4';

    // Optional additional settings
    public const ENVIRONMENT = 'dev';
    public const TIMEZONE = 'Africa/Maputo';
}
````

**Usage in backend:**

* All API requests must include the header:

```
X-AppCode: MEU_APP_CODE_SECRETO_123
```

* Database connections automatically use the `Config` class via `Core\DB`.

---

## Project Structure

```
silveriomazivebackend/
├─ app/
│  ├─ Controllers/
│  │  ├─ BaseController.php
│  │  └─ MetaController.php
│  ├─ Models/
│  │  └─ Meta.php
│  ├─ Core/
│  │  ├─ QueryBuilder.php
│  │  └─ DB.php
│  └─ Config/
│     └─ Config.php
├─ helpers/
│  └─ helpers.php
├─ index.php
└─ README.md
```

**Folders explained:**

* **Core/**: `QueryBuilder` and `DB` for dynamic queries and database connection.
* **Config/**: Application-wide configuration (AppCode, DB credentials, environment, etc.)
* **Models/**: `Meta.php` with dynamic CRUD, backups, and versioning logic.
* **Controllers/**: REST endpoints (`MetaController.php`) and base logic (`BaseController.php`).
* **helpers/**: Miscellaneous helper functions.
* **index.php**: Main router / entry point.

---

## Table Structure

Every table in this system follows the same structure, and there must always be a `backup` table:

```sql
CREATE TABLE IF NOT EXISTS backup (
    id INT AUTO_INCREMENT PRIMARY KEY,
    userId INT NOT NULL,
    meta_key VARCHAR(255) NOT NULL,
    meta_value JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

Example of a table `test`:

```sql
CREATE TABLE IF NOT EXISTS test (
    id INT AUTO_INCREMENT PRIMARY KEY,
    userId INT NOT NULL,
    meta_key VARCHAR(255) NOT NULL,
    meta_value JSON,
    appcode VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

> Note: The `appcode` column ensures multi-app isolation if needed in the future.

* Users can create **any number of tables** with this structure.
* The `backup` table stores **previous versions** for auditing.

---

## Endpoints

All endpoints require the `?table=table_name` query string **and** the `X-AppCode` header.

| Method | Endpoint                 | Description                                        |
| ------ | ------------------------ | -------------------------------------------------- |
| GET    | `/meta`                  | List all active records (not deleted)              |
| GET    | `/meta/trash`            | List all soft-deleted records                      |
| GET    | `/meta/{id}`             | Retrieve a single record by `id`                   |
| POST   | `/meta`                  | Create a new record                                |
| PUT    | `/meta/{id}`             | Update a record (automatic backup before update)   |
| DELETE | `/meta/{id}`             | Soft delete a record (modifies `meta_key`)         |
| POST   | `/meta/restore/{id}`     | Restore a soft-deleted record                      |
| DELETE | `/meta/forceDelete/{id}` | Permanently delete a record from the database      |
| GET    | `/meta/backups/{id}`     | List all backups of a specific record              |
| POST   | `/meta/search`           | Search records using `meta_value.*` filters (JSON) |

---

## Security & Notes

* All endpoints **require the correct `X-AppCode` header**. Requests without it will return `400/403`.
* All database credentials are centralized in `App\Config\Config.php`.
* The system supports **dynamic, schema-less fields** via `meta_value` (JSON).
* Soft deletes and versioning are handled automatically via `meta_key` manipulation and backup tables.
* To scale or add new attributes, just add fields to `meta_value` JSON — **no migrations required**.

```


## Examples

### 1. Create a record

```bash
POST http://localhost/silveriomazivebackend/meta/?table=test
```

Request body:

```json
{
  "userId": 1,
  "meta_key": "profile",
  "meta_value": {
    "name": "John Doe",
    "age": 30,
    "city": "Fictopolis"
  }
}
```

---

### 2. List all active records

```bash
GET http://localhost/silveriomazivebackend/meta/?table=test
```

Response:

```json
[
  {
    "id": 1,
    "userId": 1,
    "meta_key": "profile",
    "meta_value": {
      "name": "John Doe",
      "age": 30,
      "city": "Fictopolis"
    },
    "created_at": "2026-03-24 10:00:00"
  }
]
```

---

### 3. Update record (with backup)

```bash
PUT http://localhost/silveriomazivebackend/meta/1?table=test
```

Request body:

```json
{
  "meta_value": {
    "age": 31,
    "city": "New Fictopolis"
  }
}
```

* Before update, the record is copied to `backup`.
* The original record is updated:

```json
{
  "meta_value": {
    "name": "John Doe",
    "age": 31,
    "city": "New Fictopolis"
  }
}
```

---

### 4. Soft delete

```bash
DELETE http://localhost/silveriomazivebackend/meta/1?table=test
```

* The `meta_key` is changed to `profile_removedby_admin_20260324`.
* Record is still stored and can be restored.

---

### 5. Restore a record

```bash
POST http://localhost/silveriomazivebackend/meta/restore/1?table=test
```

Response:

```json
{
  "success": true,
  "meta_key": "profile"
}
```

---

### 6. Search records using JSON fields (POST)

```bash
POST http://localhost/silveriomazivebackend/meta/search?table=test
```

Request body:

```json
{
  "meta_value.name": "John Doe",
  "meta_value.city": "New Fictopolis"
}
```

Response: filtered records matching the criteria.

---

### 7. List backups of a record

```bash
GET http://localhost/silveriomazivebackend/meta/backups/1?table=test
```

Response:

```json
[
  {
    "id": 1,
    "userId": 1,
    "meta_key": "profile",
    "meta_value": {
      "name": "John Doe",
      "age": 30,
      "city": "Fictopolis"
    },
    "created_at": "2026-03-24 10:00:00"
  }
]
```

---

## Usage Tips

* Always include `?table=table_name` in all requests.
* Soft delete preserves data; backup ensures full history.
* Any table with the same structure can be used dynamically.
* For production, implement **JWT authentication** to protect access to table data.

  * Example: `Authorization: Bearer <token>` in headers.
  * JWT ensures that only authorized users can create, update, or read meta tables.

---

## Notes

* All names and examples are fictitious.
* The ORM allows any table with `meta_key/meta_value` JSON structure.
* All endpoints return JSON for easy integration.
* Backup is automatically created on updates, and soft delete modifies `meta_key` for traceability.

```