# AddToMar Medicine Finder API

Base URL: `http://localhost/AddToMar1/AddToMar/`

All endpoints return JSON. Authenticated endpoints require an active PHP session (login cookie).

---

## GET `/ajax/finder-search.php`

Search medicines by brand name, generic name, or medicine code.

**Query parameters**

| Param | Type   | Required | Description        |
|-------|--------|----------|--------------------|
| `q`   | string | Yes      | Min 2 characters   |

**Response**

```json
{
  "success": true,
  "query": "Paracetamol",
  "medicines": [
    {
      "id": 1,
      "medicine_name": "Biogesic 500mg",
      "generic_name": "Paracetamol",
      "dosage": "500mg Tablet",
      "min_price": 5.5,
      "total_stock": 150,
      "in_stock": true,
      "image_url": "/AddToMar1/AddToMar/assets/uploads/medicines/default-medicine.png",
      "uses_info": "...",
      "dosage_instructions": "...",
      "side_effects": "...",
      "warnings": "...",
      "storage_info": "...",
      "manufacturer": "Unilab Inc."
    }
  ]
}
```

Logs search to `search_analytics` and `search_history` (customers).

---

## GET `/ajax/finder-pharmacies.php`

List pharmacies with stock for a medicine. Sorted by distance when coordinates provided.

**Query parameters**

| Param         | Type  | Required | Description                    |
|---------------|-------|----------|--------------------------------|
| `medicine_id` | int   | Yes      | Medicine ID                    |
| `lat`         | float | No       | User latitude                  |
| `lng`         | float | No       | User longitude                 |
| `pharmacy_id` | int   | No       | Selected pharmacy (for alt.)   |

**Response**

```json
{
  "success": true,
  "medicine": { "id": 1, "medicine_name": "...", "generic_name": "...", "dosage": "...", "image_url": "..." },
  "pharmacies": [
    {
      "id": 1,
      "pharmacy_name": "AddToMar Pharmacy Main",
      "address": "...",
      "latitude": "18.28950000",
      "longitude": "120.66780000",
      "contact_number": "09171234567",
      "operating_hours": "Mon-Sat 8:00 AM - 8:00 PM",
      "stock_quantity": 150,
      "pharmacy_price": "5.50",
      "is_open": true,
      "distance_km": 12.4,
      "travel_time": "25 min",
      "navigate_url": "https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route=...",
      "is_favorite": false
    }
  ],
  "nearest_with_stock": { "...": "..." },
  "out_of_stock_message": null,
  "total_pharmacies": 4
}
```

Pharmacies with zero stock are excluded.

---

## POST `/ajax/favorite-pharmacy.php`

Toggle favorite pharmacy (customer only).

**Body (form POST)**

| Field          | Type   | Required |
|----------------|--------|----------|
| `csrf_token`   | string | Yes      |
| `pharmacy_id`  | int    | Yes      |
| `action`       | string | No       | `remove` to unfavorite |

---

## POST `/ajax/crud-pharmacy.php`

Create/update pharmacy (pharmacist only).

**Body (form POST)**

| Field             | Type   | Required |
|-------------------|--------|----------|
| `csrf_token`      | string | Yes      |
| `pharmacy_name`   | string | Yes      |
| `address`         | string | Yes      |
| `latitude`        | float  | Yes      |
| `longitude`       | float  | Yes      |
| `contact_number`  | string | Yes      |
| `operating_hours` | string | No       |
| `open_time`       | time   | No       |
| `close_time`      | time   | No       |
| `status`          | string | No       | `active` / `inactive` |
| `id`              | int    | No       | Set for edit |

---

## Map setup (Leaflet + OpenStreetMap)

No Google API key required. Map settings are in `config/maps.php`:

- **Tiles:** OpenStreetMap (free)
- **Routing:** OSRM public demo server (`router.project-osrm.org`)
- **Navigate Now:** Opens OpenStreetMap directions in a new tab

Optional settings in `config/maps.php`:

| Constant | Default | Description |
|----------|---------|-------------|
| `MAP_TILE_URL` | OSM tiles | Map tile layer URL |
| `MAP_DEFAULT_LAT` | 18.1978 | Default center (Ilocos Norte) |
| `MAP_DEFAULT_LNG` | 120.5937 | Default center |
| `OSRM_ROUTE_URL` | OSRM demo | Driving route API |

---

## Legacy note

Google Maps is no longer used. `config/google_maps.php` redirects to `config/maps.php`.

---

## Database Tables

- `pharmacies` — pharmacy locations
- `inventory` — per-pharmacy stock and price
- `search_analytics` — all search events
- `search_history` — per-customer history
- `favorite_pharmacies` — customer favorites

Run migration: `sql/medicine_finder_migration.sql`
