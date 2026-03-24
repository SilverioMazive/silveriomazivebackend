```markdown
# silveriomazivebackend - Dynamic ORM with Meta and Versioning (PHP)

Absolutely! We can update your **Description** section to explicitly highlight that your backend supports **NoSQL-like flexibility** on top of SQL, allowing easy scaling without modifying table columns. Here's a polished version:

---

## Description

**SilverioMaziveBackend** is a minimal PHP backend that implements a **dynamic ORM** based on "meta" tables.

It provides flexible, schema-less data storage inside SQL databases by using `meta_key` and `meta_value` (JSON) fields, making it behave like a **NoSQL or vertical database** within a relational database.

Main features:

* Dynamic CRUD for any table specified via query string (`?table=table_name`)
* Flexible `meta_key` and `meta_value` fields (JSON), allowing arbitrary fields per record
* Dot notation support for `meta_value` (`meta_value.profile.name`)
* Soft delete by changing `meta_key`
* Automatic backup/versioning of records before updates
* RESTful JSON endpoints for easy integration
* Easy to scale: new fields can be added to records without altering table columns

This design makes it simple to **expand your project** without requiring migrations every time a new property is needed. Developers can add new attributes directly into `meta_value` JSON objects.


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
│  └─ Core/
│     ├─ QueryBuilder.php
│     └─ DB.php
├─ helpers/
│  └─ helpers.php
├─ index.php
└─ README.md

````

- **Core/**: Contains `QueryBuilder` and `DB` for dynamic queries and database connection.  
- **Models/**: Contains `Meta.php` with CRUD and backup logic.  
- **Controllers/**: Contains `MetaController.php` with REST endpoints.  
- **helpers/**: Miscellaneous helper functions.  
- **index.php**: Main router and entry point.

---

## Table Structure

Every table in this system should follow the **same structure**, and there must always be a `backup` table:

```sql
CREATE TABLE IF NOT EXISTS backup (
    id INT AUTO_INCREMENT PRIMARY KEY,
    userId INT NOT NULL,
    meta_key VARCHAR(255) NOT NULL,
    meta_value JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
````

Example of a table `test` using the same structure:

```sql
CREATE TABLE IF NOT EXISTS test (
    id INT AUTO_INCREMENT PRIMARY KEY,
    userId INT NOT NULL,
    meta_key VARCHAR(255) NOT NULL,
    meta_value JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

* Users can create **any number of tables** with the same structure.
* The `backup` table stores **previous versions** of records for auditing.

---

## Endpoints

All endpoints require the query string `?table=table_name`.

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