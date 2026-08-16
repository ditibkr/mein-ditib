# API Dokumentation — DITIB Vereinsportal

**Base URL:** `https://mein.ditib-krefeld.info/api`  
**Auth:** Bearer Token (Laravel Sanctum)

---

## Authentifizierung

### POST /api/login
Anmelden und API-Token erhalten.

**Request:**
```json
{
  "email": "admin@ditib-krefeld.de",
  "password": "Admin123!"
}
```

**Response 200:**
```json
{
  "token": "1|abc...",
  "user": {
    "id": 1,
    "name": "System Administrator",
    "email": "admin@ditib-krefeld.de",
    "roles": ["superadmin"],
    "language": "de"
  }
}
```

**Response 422:** Ungültige Anmeldedaten

---

### POST /api/logout
`Authorization: Bearer {token}` erforderlich.

**Response 200:**
```json
{ "message": "Erfolgreich abgemeldet." }
```

---

### GET /api/user
Eingeloggter Benutzer mit Rollen und Berechtigungen.

**Response 200:**
```json
{
  "id": 1,
  "name": "...",
  "roles": ["vereinsadmin"],
  "permissions": ["viewAny_member", "create_member", ...]
}
```

---

## M11 Dashboard

### GET /api/dashboard/stats
Dashboard-Statistiken (gecacht, 5 Minuten).

**Response 200:**
```json
{
  "members": {
    "total": 250,
    "active": 230,
    "newThisMonth": 5,
    "newThisYear": 47,
    "byCategory": {
      "vollmitglied": 180,
      "foerdermitglied": 40,
      "ehrenmitglied": 5,
      "jugend": 25
    },
    "byStatus": { "aktiv": 230, "ruhend": 15, "ausgetreten": 5 },
    "growthData": [
      { "month": "2025-09", "label": "Sep 25", "count": 3 }
    ]
  },
  "users": { "total": 8, "active": 7 },
  "communications": { "newsletters_sent": 12, "newsletters_draft": 1 }
}
```

---

## M03 Mitglieder

### GET /api/members

**Query-Parameter:**
| Parameter | Typ | Beschreibung |
|-----------|-----|-------------|
| `page` | int | Seitennummer (Standard: 1) |
| `per_page` | int | Einträge pro Seite (Standard: 25) |
| `search` | string | Volltext-Suche (Name, E-Mail, Nummer) |
| `status` | string | Filter: aktiv, ruhend, ausgetreten |
| `category` | string | Filter: vollmitglied, foerdermitglied, etc. |

**Response 200:**
```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 25,
    "total": 250
  }
}
```

---

### GET /api/members/statistics
Mitglieder-Statistiken.

---

### POST /api/members
Neues Mitglied anlegen.

**Request:**
```json
{
  "first_name": "Ahmed",
  "last_name": "Yilmaz",
  "email": "ahmed@example.de",
  "status": "aktiv",
  "category": "vollmitglied",
  "gdpr_consent": true
}
```

**Response 201:** Erstelltes Mitglied-Objekt

---

### GET /api/members/{id}
Einzelnes Mitglied abrufen.

### PUT /api/members/{id}
Mitglied aktualisieren.

### DELETE /api/members/{id}
Mitglied löschen (Soft-Delete).

---

## Fehler-Format

Alle Fehler folgen dem Laravel-Standard:

```json
{
  "message": "Fehlerbeschreibung",
  "errors": {
    "email": ["Die E-Mail-Adresse ist bereits vergeben."]
  }
}
```

**HTTP-Status-Codes:**
- `200` OK
- `201` Created
- `204` No Content (DELETE)
- `401` Unauthenticated
- `403` Forbidden
- `404` Not Found
- `422` Validation Error
- `429` Too Many Requests
