# 🚀 Baladna API — Complete Endpoint Reference for React Developers

This document lists **every endpoint** in the Baladna API, with the **HTTP method**, **URL**, **required role**, **request body**, **response format**, and a **React (Axios) example** for each group.

---

## 🆕 What changed in this release (front-end action required)

| Change | What you need to do |
|--------|---------------------|
| `GET /admin/area-suggestions` and `GET`/`POST /user-areas` used to return **500**. They work now. | Re-enable any screen you disabled. |
| Every **area object** now includes `status`, `is_approved`, `is_pending`, `is_rejected`, `suggested_by_id`, `suggested_by`. | Show the approval badge — see the Area object in section 2. |
| **New** `GET /my-area-suggestions` | Screen where a citizen tracks their own suggestions. |
| **New** `GET /employee/area-suggestions` (+ `/{area}`) | Read-only suggestions view for employees. |
| **New** `GET /employee/reviews` and `GET /employee/reports/{report}/review` | Employees can now read the citizen ratings/comments. |
| `GET /employee/reports` and `/employee/reports/{report}` now embed `review` and `assigned_employee` | You no longer need a second request for the review. |
| **New** `?assigned_to_me=1` on `GET /employee/reports` and `GET /employee/reviews` | Build the employee's personal queue. |
| Reports are now **auto-assigned** from the picked category | `assigned_employee` is filled in on `POST /reports`; the admin assign endpoint is now an override, not the only way. |
| Categories accept `responsible_employee_id` | Add the field to the admin category form. |
| `GET /areas` no longer returns pending/rejected areas, and `GET /areas/{area}` 404s for them | Nothing to do — the public list is just correct now. |
| `area_id` on report create/update must be an **approved** area | Bind report forms to `/areas` or `/user-areas`, never to the suggestions endpoints. |
| `PATCH /reports/{report}` with a new `category_id` also changes `agency_id` and `assigned_employee` | Re-read the report from the response instead of merging fields locally. |
| **New** `GET /notifications`, `GET /notifications/unread-count`, `POST /notifications/read` | Build the in-app notification bell — see section 3.2. |
| `DELETE /admin/areas/{area}` returns **422** (instead of crashing) when the area is still in use | Show `response.data.message` to the admin. |

---

## 📌 Conventions

| Item | Value |
|------|-------|
| **Base URL** | `http://localhost:8000/api/v1` |
| **Auth header** | `Authorization: Bearer {token}` |
| **Content-Type** | `application/json` (use `multipart/form-data` only for image uploads) |
| **Roles** | `citizen`, `employee`, `admin` |

### Standard response shapes

**Success (single resource):**
```json
{
  "success": true,
  "message": "Operation successful.",
  "data": { }
}
```

**Success (collection with pagination):** note there is **no `message`** key here.
```json
{
  "success": true,
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 50,
    "last_page": 5
  }
}
```
> A few collection endpoints add extra keys to `meta` (e.g. `average_rating` and `reviews_count`
> on `GET /employee/reviews`). Fields documented as *"only when loaded"* may be **absent** from
> the JSON rather than `null` — always read them with `?.` or a default.

**Validation error (422):**
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "title": ["The title field is required."]
  }
}
```

**Authentication error (401):**
```json
{ "success": false, "message": "Unauthenticated." }
```

**Authorization error (403):**
```json
{ "success": false, "message": "You are not authorized to perform this action." }
```

**Not found (404):**
```json
{ "success": false, "message": "Resource not found." }
```

### HTTP status codes used
| Code | Meaning |
|------|---------|
| 200 | Successful retrieval / update |
| 201 | Resource created |
| 204 | Deleted (no body) |
| 401 | Unauthenticated |
| 403 | Forbidden / not authorized |
| 404 | Not found |
| 409 | Conflict (invalid state) |
| 422 | Validation error |

---

## 🔐 Axios setup (create once in your React app)

```js
// src/api.js
import axios from "axios";

const api = axios.create({
  baseURL: "http://localhost:8000/api/v1",
  headers: { Accept: "application/json" },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem("token");
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

export default api;
```

> Every React example below uses this `api` instance. The token is stored in `localStorage` after login.

---

# 1. 🔑 Authentication

## POST `/auth/register`
**Role:** Public · **Auth:** No

**Request body:**
```json
{
  "name": "Mohammad Ahmad",
  "email": "mohammad@example.com",
  "phone": "+9647000000000",
  "password": "password123",
  "password_confirmation": "password123",
  "area_id": 2
}
```
> Always creates a `citizen` account.

**Response (201):**
```json
{
  "success": true,
  "message": "Registration successful.",
  "data": {
    "user": { "id": 1, "name": "Mohammad Ahmad", "email": "mohammad@example.com", "role": "citizen" },
    "token": "1|abcdef..."
  }
}
```

**React example:**
```js
const res = await api.post("/auth/register", {
  name, email, phone, password,
  password_confirmation: password,
  area_id,
});
localStorage.setItem("token", res.data.data.token);
```

---

## POST `/auth/login`
**Role:** Public · **Auth:** No

**Request body:**
```json
{ "email": "citizen@baladna.test", "password": "password" }
```

**Response (200):**
```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "user": { "id": 1, "name": "Mohammad Ahmad", "email": "citizen@baladna.test", "role": "citizen" },
    "token": "1|abcdef..."
  }
}
```

**React example:**
```js
const res = await api.post("/auth/login", { email, password });
localStorage.setItem("token", res.data.data.token);
// store user too if you want
localStorage.setItem("user", JSON.stringify(res.data.data.user));
```

---

## POST `/auth/logout`
**Role:** Authenticated · **Auth:** Bearer

**Request body:** none

**Response (200):**
```json
{ "success": true, "message": "Logout successful." }
```

**React example:**
```js
await api.post("/auth/logout");
localStorage.removeItem("token");
```

---

## GET `/me`
**Role:** Authenticated · **Auth:** Bearer

**Response (200):**
```json
{
  "success": true,
  "message": "Authenticated user.",
  "data": { "id": 1, "name": "Mohammad Ahmad", "email": "citizen@baladna.test", "phone": "+9647000000000", "role": "citizen" }
}
```

**React example:**
```js
const res = await api.get("/me");
setUser(res.data.data);
```

---

## PATCH `/me`
**Role:** Authenticated · **Auth:** Bearer

**Request body (all optional):**
```json
{ "name": "New Name", "phone": "+9647...", "area_id": 3 }
```

**Response (200):** updated user object.

**React example:**
```js
const res = await api.patch("/me", { name, phone, area_id });
```

---

# 2. 🏙️ Public Reference Data

These are **public** (no auth). Useful for filling dropdowns in your React forms.

## GET `/areas`
Only **approved** areas are returned. Pending and rejected citizen suggestions are hidden here.

**Query params:** `?parent_id=2`

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Baghdad",
      "parent_id": null,
      "parent": null,
      "status": "approved",
      "is_approved": true,
      "is_pending": false,
      "is_rejected": false,
      "suggested_by_id": null,
      "created_at": "..."
    }
  ]
}
```

**React example:**
```js
const res = await api.get("/areas", { params: { parent_id: 1 } });
setAreas(res.data.data);
```

## GET `/areas/{area}`
Returns a single area. Responds **404** if the area is not approved (a pending or rejected
citizen suggestion is not public).

### 📦 The Area object

Every endpoint that returns an area uses this exact shape, so you can write one `<AreaCard />`:

| Field | Type | Notes |
|-------|------|-------|
| `id` | number | |
| `name` | string | |
| `parent_id` | number \| null | |
| `parent` | object \| null | `{ id, name }` — only present when the endpoint loads it |
| `status` | `"pending"` \| `"approved"` \| `"rejected"` | approval state |
| `is_approved` | boolean | convenience flag, same info as `status` |
| `is_pending` | boolean | still waiting for an admin decision |
| `is_rejected` | boolean | |
| `suggested_by_id` | number \| null | `null` = created by an admin, not suggested by a user |
| `suggested_by` | object \| null | `{ id, name }` — only on the admin/employee suggestion endpoints |
| `children` | array \| absent | only on `GET /areas/{area}` |
| `created_at` | ISO string | |

> Render the badge from `status`; use `suggested_by_id === null` to tell an official area
> apart from a citizen suggestion.

## GET `/agencies`
**Query params:** `?active=1`

**Response:** list of agencies with `{ id, name, description, email, phone, is_active }`.

## GET `/agencies/{agency}`
Returns a single agency.

## GET `/categories`
**Query params:** `?agency_id=1&active=1`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Damaged roads",
      "description": "...",
      "agency_id": 1,
      "agency": { "id": 1, "name": "Municipality" },
      "responsible_employee_id": 5,
      "responsible_employee": { "id": 5, "name": "Road Employee" },
      "is_active": true,
      "created_at": "..."
    }
  ]
}
```

> `responsible_employee` is the employee who automatically receives every report filed under
> this category. It is `null` when no one is designated — see **POST `/reports`** below.

**React example:**
```js
const res = await api.get("/categories", { params: { agency_id: selectedAgencyId } });
```

## GET `/categories/{category}`
Returns a single category.

---

# 3. 🧾 Citizen Reports

All endpoints in this section require `auth:sanctum` (Bearer token), **except** `POST /reports/anonymous` which is **public**.

## POST `/reports/anonymous`
**Role:** Public · **Auth:** No

> Submit a report **without** registering/logging in. The reporter provides their own contact info, which is stored directly on the report (`user_id` stays `null`).

**Form fields (multipart/form-data for images):**
| Field | Required | Notes |
|-------|----------|-------|
| `reporter_name` | ✅ | max 255 |
| `reporter_email` | ❌ | valid email |
| `reporter_phone` | ❌ | max 255 |
| `category_id` | ✅ | Must be an active category |
| `area_id` | ✅ | Must be an **approved** area |
| `title` | ✅ | max 255 |
| `description` | ✅ | max 5000 |
| `address` | ❌ | max 500 |
| `latitude` | ❌ | -90 to 90 |
| `longitude` | ❌ | -180 to 180 |
| `images[]` | ❌ | max 5, jpg/jpeg/png/webp, max 5MB |

**Response (201):** the created report. `data.reporter` will be:
```json
{
  "id": null,
  "name": "Ali Hassan",
  "email": "ali@example.com",
  "phone": "+9647000000000"
}
```

**React example:**
```js
const formData = new FormData();
formData.append("reporter_name", name);
formData.append("reporter_email", email);
formData.append("reporter_phone", phone);
formData.append("category_id", categoryId);
formData.append("area_id", areaId);
formData.append("title", title);
formData.append("description", description);

images.forEach((img) => formData.append("images[]", img));

const res = await api.post("/reports/anonymous", formData, {
  headers: { "Content-Type": "multipart/form-data" },
});
```

> Note: Because anonymous reports have no registered user, they can **not** be updated, cancelled, or reviewed by the reporter later. They are managed by employees/admins only.

---

# 3.1 🌐 Website Landing Page (Public)

These endpoints are **public** (no auth) and are meant to be displayed on the landing page. They return the latest anonymous reports and the website's status/statistics table.

## GET `/website/latest-anonymous-reports`
**Role:** Public · **Auth:** No

Returns the **latest 6 anonymous reports** (ordered newest first). Only reports submitted without a registered user (`user_id = null`) are included.

**Response (200):**
```json
{
  "success": true,
  "message": "Latest anonymous reports retrieved.",
  "data": [
    {
      "id": 10,
      "reference_number": "BLD-2026-000010",
      "title": "Broken street light",
      "description": "The street light has been broken for a week.",
      "status": "submitted",
      "priority": "normal",
      "category": { "id": 1, "name": "Damaged roads" },
      "area": { "id": 2, "name": "Karrada" },
      "agency": { "id": 1, "name": "Municipality" },
      "reporter": { "id": null, "name": "Ali Hassan", "email": "ali@example.com", "phone": "+9647000000000" },
      "images": [],
      "created_at": "2026-08-05T17:00:00Z"
    }
  ]
}
```

**React example:**
```js
const res = await api.get("/website/latest-anonymous-reports");
setReports(res.data.data);
```

---

## GET `/website/stats`
**Role:** Public · **Auth:** No

Returns the website's status table containing aggregate counts. This table is updated automatically whenever a new report is submitted or a report is completed (resolved).

**Response (200):**
```json
{
  "success": true,
  "message": "Website statistics retrieved.",
  "data": {
    "total_reports": 120,
    "resolved_reports": 45,
    "pending_reports": 70,
    "anonymous_reports": 30,
    "active_categories": 8,
    "active_areas": 12,
    "active_agencies": 5,
    "updated_at": "2026-08-05T17:00:00Z"
  }
}
```

| Field | Meaning |
|-------|---------|
| `total_reports` | Total number of reports submitted |
| `resolved_reports` | Reports with status `resolved` |
| `pending_reports` | Reports still open (not resolved/rejected/cancelled) |
| `anonymous_reports` | Reports submitted without a registered user |
| `active_categories` | Number of active categories |
| `active_areas` | Total number of areas |
| `active_agencies` | Number of active agencies |
| `updated_at` | When the stats were last refreshed |

**React example:**
```js
const res = await api.get("/website/stats");
setStats(res.data.data);
```

> These stats are stored in a single `website_stats` row and are recomputed automatically on new submissions and on report resolutions, so they stay fresh for the landing page.

---

## GET `/reports`
**Role:** Authenticated · **Auth:** Bearer

**Query params (all optional, combinable):**
| Param | Example | Description |
|-------|---------|-------------|
| `page` | `1` | Page number |
| `per_page` | `10` | Items per page (max 50) |
| `status` | `submitted` | Filter by status |
| `category_id` | `1` | Filter by category |
| `area_id` | `2` | Filter by area |
| `agency_id` | `1` | Filter by agency |
| `search` | `pothole` | Search title/description/ref/address |
| `sort` | `newest` | `newest`, `oldest`, `most_confirmed` |

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "reference_number": "BLD-2026-000001",
      "title": "Large pothole in the main street",
      "description": "The pothole is dangerous.",
      "status": "submitted",
      "priority": "normal",
      "category": { "id": 1, "name": "Damaged roads" },
      "area": { "id": 2, "name": "Karrada" },
      "agency": { "id": 1, "name": "Municipality" },
      "reporter": { "id": 1, "name": "Mohammad Ahmad" },
      "images": [],
      "confirmations_count": 3,
      "confirmed_by_me": false,
      "created_at": "2026-08-05T17:00:00Z"
    }
  ],
  "meta": { "current_page": 1, "per_page": 10, "total": 50, "last_page": 5 }
}
```

> **Which optional keys appear where.** `assigned_employee` and `review` are only serialised
> when the endpoint loads them. They are **absent** from this citizen list, and **present** on
> `GET /reports/{report}`, `POST /reports`, `PATCH /reports/{report}`,
> `GET /employee/reports` and `GET /employee/reports/{report}`.
> Use `report.assigned_employee ?? null` rather than assuming the key exists.

**React example:**
```js
const res = await api.get("/reports", {
  params: { page: 1, per_page: 10, status: "submitted", area_id: 2 },
});
```

---

## POST `/reports`
**Role:** Authenticated · **Auth:** Bearer

> Use **`multipart/form-data`** so you can upload images in the same request.

**Form fields:**
| Field | Required | Notes |
|-------|----------|-------|
| `category_id` | ✅ | Must be an active category |
| `area_id` | ✅ | Must be an **approved** area |
| `title` | ✅ | max 255 |
| `description` | ✅ | max 5000 |
| `address` | ❌ | max 500 |
| `latitude` | ❌ | -90 to 90 |
| `longitude` | ❌ | -180 to 180 |
| `images[]` | ❌ | max 5, jpg/jpeg/png/webp, max 5MB |

> The backend automatically sets `user_id`, `agency_id` (from category), `status=submitted`, `priority=normal`, generates the `reference_number`, and creates the first history record.

> **Automatic assignment.** The chosen `category_id` also decides who works on the report:
> it is assigned straight to that category's `responsible_employee_id`, or — when the category
> has none — to the least busy active employee of the category's agency. The assignee comes back
> in `data.assigned_employee` and the handover is recorded in the report history. If the agency
> has no employee at all, the report stays unassigned until an admin assigns it manually.
> The same happens for anonymous reports, and again if the citizen later changes the category
> via `PATCH /reports/{report}` (which also moves the report to the new category's agency).

**Response (201):** the created report object (same shape as a single report above).

**React example (FormData):**
```js
const formData = new FormData();
formData.append("category_id", categoryId);
formData.append("area_id", areaId);
formData.append("title", title);
formData.append("description", description);
formData.append("address", address);

images.forEach((img) => formData.append("images[]", img));

try {
  const res = await api.post("/reports", formData, {
    headers: { "Content-Type": "multipart/form-data" },
  });
} catch (error) {
  if (error.response?.status === 422) setErrors(error.response.data.errors);
}
```

---

## GET `/reports/{report}`
**Role:** Owner citizen, or any employee/admin.

Returns a single report including `assigned_employee` (`null` when nobody is assigned yet),
`review` (`null` until the citizen reviews it) and `confirmed_by_me`.

## PATCH `/reports/{report}`
**Role:** Citizen owner · only while status is `submitted`.

**Request body (all optional):**
| Field | Notes |
|-------|-------|
| `title` | max 255 |
| `description` | max 5000 |
| `address` | max 500 |
| `latitude` / `longitude` | -90..90 / -180..180 |
| `category_id` | must be an **active** category |
| `area_id` | must be an **approved** area |

> ⚠️ **Changing `category_id` re-routes the report.** The backend also updates `agency_id` to the
> new category's agency and re-assigns it to that category's responsible employee (or the least
> busy employee of that agency). The response comes back with the new `agency` and
> `assigned_employee`, so refresh your local copy from the response instead of patching state by hand.

**Response (200):** the updated report, with `agency` and `assigned_employee`.

**React example:**
```js
const res = await api.patch(`/reports/${id}`, { title, description });
setReport(res.data.data); // agency + assigned_employee may have changed
```

---

## POST `/reports/{report}/cancel`
**Role:** Citizen owner · only from `submitted` or `under_review`.

**Response (200):**
```json
{ "success": true, "message": "Report cancelled successfully.", "data": { "status": "cancelled", ... } }
```

**React example:**
```js
const res = await api.post(`/reports/${id}/cancel`);
```

---

## GET `/my-reports`
Returns the authenticated user's own reports (paginated).

**React example:**
```js
const res = await api.get("/my-reports", { params: { page: 1 } });
```

---

## POST `/reports/{report}/images`
**Role:** Citizen owner. Upload one or more images to an existing report.

**Form field:** `images[]` (array, max 5 total per report).

**React example:**
```js
const fd = new FormData();
files.forEach((f) => fd.append("images[]", f));
await api.post(`/reports/${id}/images`, fd, {
  headers: { "Content-Type": "multipart/form-data" },
});
```

## DELETE `/reports/{report}/images/{image}`
Deletes one image. **Response:** `204` (no body).

**React example:**
```js
await api.delete(`/reports/${reportId}/images/${imageId}`);
```

---

## POST `/reports/{report}/confirm`
**Role:** Citizen (cannot confirm own report, once only).

**Response (200):**
```json
{ "success": true, "message": "Report confirmed successfully.", "data": { "confirmations_count": 4 } }
```

**React example:**
```js
const res = await api.post(`/reports/${id}/confirm`);
```

## DELETE `/reports/{report}/confirm`
Removes the authenticated user's confirmation.

```js
await api.delete(`/reports/${id}/confirm`);
```

---

## GET `/reports/{report}/history`
Returns the status history records (read-only).

**Response:**
```json
{
  "success": true,
  "data": [
    { "id": 1, "old_status": null, "new_status": "submitted", "note": "Report submitted.", "user": { "id": 1, "name": "Mohammad" }, "created_at": "..." }
  ]
}
```

**React example:**
```js
const res = await api.get(`/reports/${id}/history`);
```

---

## POST `/reports/{report}/review`
**Role:** Citizen owner · only when status is `resolved` · one review per report.

**Request body:**
```json
{ "rating": 5, "comment": "Great work!" }
```
`rating` 1–5 (required), `comment` max 2000 (optional).

**Response (201):**
```json
{ "success": true, "message": "Review submitted successfully.", "data": { "id": 1, "rating": 5, "comment": "Great work!" } }
```

**React example:**
```js
const res = await api.post(`/reports/${id}/review`, { rating, comment });
```

---

# 3.2 🔔 Notifications

The in-app notification feed. There is **no notifications table** — the feed is
the report status history read from the recipient's side, so every event the
backend already records shows up here automatically.

**You receive an event when it touches a report you filed, or a report assigned
to you.** Events you triggered yourself are filtered out — you do not get
notified about your own actions.

All three endpoints require `auth:sanctum`. Any role can call them.

## GET `/notifications`

**Query params:** `per_page` (default 15, max 50), `page`, `unread_only` (`1`/`true`).

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 42,
      "type": "report_status_changed",
      "old_status": "submitted",
      "new_status": "under_review",
      "note": "Taking a look now.",
      "report": {
        "id": 7,
        "reference_number": "BLD-2026-000007",
        "title": "Broken street light",
        "status": "under_review"
      },
      "actor": { "id": 3, "name": "Ahmed" },
      "is_read": false,
      "created_at": "2026-08-23T10:12:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1,
    "unread_count": 1
  }
}
```

| Field | Notes |
|-------|-------|
| `type` | `report_created`, `report_assigned`, or `report_status_changed`. Pick the wording and icon from this. |
| `note` | Free text from the backend — the resolution note, rejection reason, or the auto-assignment explanation. May be `null`. |
| `actor` | Who caused it. **`null` when the platform itself did** (auto-assignment on an anonymous report, for example). |
| `is_read` | Derived from the read watermark, not stored per row. |
| `meta.unread_count` | Always the total unread count, even when the page is filtered. |

**React example:**
```js
const res = await api.get('/notifications', { params: { per_page: 20 } });
const items = res.data.data;
const badge = res.data.meta.unread_count;
```

---

## GET `/notifications/unread-count`
Just the badge number — cheap enough to poll.

**Response:**
```json
{ "success": true, "message": "Success.", "data": { "unread_count": 3 } }
```

```js
const res = await api.get('/notifications/unread-count');
setBadge(res.data.data.unread_count);
```

---

## POST `/notifications/read`
Marks **everything up to now** as read. No body. There is no per-item read
state — the backend stores one `notifications_read_at` watermark per user, so
the badge is consistent across the user's devices.

**Response:**
```json
{
  "success": true,
  "message": "Notifications marked as read.",
  "data": { "notifications_read_at": "2026-08-23T10:30:00.000000Z", "unread_count": 0 }
}
```

```js
await api.post('/notifications/read');
setBadge(0);
```

> Items already read stay in the feed with `"is_read": true`. Use
> `?unread_only=1` when you want only the new ones.

**Not covered yet:** community events (comments on your post) and area-suggestion
approvals are not in this feed — see `GET /my-area-suggestions` for the latter.

---

# 4. 🛠️ Employee Reports

All endpoints require `auth:sanctum` + `employee` role (admins can also access). Employees only see reports from their own `agency_id`.

## GET `/employee/reports`
Same filters/pagination as `/reports`, but scoped to the employee's agency.
Each item also carries `assigned_employee` and `review` (both may be `null`).

**Extra query param:** `?assigned_to_me=1` — only the reports assigned to the logged-in employee.

```js
// the employee's personal queue
const res = await api.get("/employee/reports", {
  params: { assigned_to_me: 1, status: "in_progress" },
});
```

## GET `/employee/reports/{report}`
Show a single report (scoped to agency). Includes `assigned_employee`, `review`
and the full `status_histories` (which also records automatic assignments).

## PATCH `/employee/reports/{report}/status`
**Role:** Employee/Admin.

**Request body:**
```json
{ "status": "in_progress", "note": "The maintenance team has started working." }
```

**Status transitions allowed:**
```
submitted  -> under_review
under_review -> accepted | rejected
accepted   -> in_progress
in_progress -> resolved
submitted|under_review -> cancelled (by citizen)
```

**Special cases:**
- To reject: `{ "status": "rejected", "rejection_reason": "..." }` (reason required)
- To resolve: `{ "status": "resolved", "resolution_note": "..." }` (note required)

**Response (200):** updated report.

**React example:**
```js
const res = await api.patch(`/employee/reports/${id}/status`, {
  status: "resolved",
  resolution_note: "The damaged section was repaired.",
});
```

> Invalid transition returns **409** with `{ "success": false, "message": "Invalid status transition..." }`.

## POST `/employee/reports/{report}/public-note`
**Role:** Employee/Admin.

**Request body:**
```json
{ "note": "Team dispatched to the location." }
```

**Response (200):**
```json
{ "success": true, "message": "Public note added successfully.", "data": { "id": 1, "public_note": "..." } }
```

**React example:**
```js
await api.post(`/employee/reports/${id}/public-note`, { note });
```

## GET `/employee/reviews`
**Role:** Employee/Admin.

Lists the citizen reviews left on resolved reports. Employees only see the reviews
of their own agency's reports; admins see all of them.

**Query params:** `?assigned_to_me=1` · `?report_id=12` · `?category_id=3` · `?area_id=2` · `?rating=5` · `?per_page=20`

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 4,
      "rating": 5,
      "comment": "The team fixed it quickly.",
      "report": { "id": 12, "reference_number": "BLD-2026-000012", "title": "Broken street light" },
      "reviewer": { "id": 7, "name": "Ali Hassan" },
      "created_at": "..."
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 1,
    "last_page": 1,
    "average_rating": 4.6,
    "reviews_count": 1
  }
}
```

**React example:**
```js
const res = await api.get("/employee/reviews", { params: { assigned_to_me: 1 } });
setReviews(res.data.data);
setAverage(res.data.meta.average_rating);
```

## GET `/employee/reports/{report}/review`
**Role:** Employee/Admin.

The review left on a single report. Returns `404` when the citizen has not reviewed it yet,
and `403` when the report belongs to another agency.

**Response (200):**
```json
{
  "success": true,
  "message": "Success.",
  "data": {
    "id": 4,
    "rating": 5,
    "comment": "The team fixed it quickly.",
    "report": { "id": 12, "reference_number": "BLD-2026-000012", "title": "Broken street light" },
    "reviewer": { "id": 7, "name": "Ali Hassan" },
    "created_at": "..."
  }
}
```

> `GET /employee/reports/{report}` also embeds the same review under `data.review`.

**React example:**
```js
const res = await api.get(`/employee/reports/${id}/review`);
```

## GET `/employee/area-suggestions`
**Role:** Employee/Admin.

Read-only list of citizen area suggestions and whether each one was approved.
Only admins can approve or reject them.

**Query params:** `?status=pending|approved|rejected` · `?per_page=20`

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "name": "New Area",
      "status": "approved",
      "is_approved": true,
      "is_pending": false,
      "is_rejected": false,
      "suggested_by_id": 5,
      "suggested_by": { "id": 5, "name": "Ali Hassan" },
      "created_at": "..."
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1 }
}
```

**React example:**
```js
const res = await api.get("/employee/area-suggestions", { params: { status: "pending" } });
```

## GET `/employee/area-suggestions/{area}`
**Role:** Employee/Admin. Returns a single suggestion with its approval state.

---

# 5. 👑 Admin CRUD

All endpoints require `auth:sanctum` + `admin` role.

## Areas
| Method | URL | Body / Notes |
|--------|-----|--------------|
| GET | `/admin/areas` | list (paginated) · `?status=pending|approved|rejected` · `?parent_id=1` (`?parent_id=` for top-level) |
| POST | `/admin/areas` | `{ "name": "Zubair", "parent_id": 1 }` → always created with `status: "approved"` |
| GET | `/admin/areas/{area}` | single |
| PATCH | `/admin/areas/{area}` | `{ "name": "...", "parent_id": 2 }` |
| DELETE | `/admin/areas/{area}` | `204` |

**Error cases you must handle:**
| Code | When |
|------|------|
| 422 | `PATCH` with `parent_id` equal to the area's own `id` (*"An area cannot be its own parent."*) |
| 422 | `DELETE` on an area referenced by reports, by community posts, or that still has sub-areas |

```js
await api.post("/admin/areas", { name: "Zubair", parent_id: 1 });
await api.patch(`/admin/areas/${id}`, { name: "New name" });

try {
  await api.delete(`/admin/areas/${id}`);
} catch (e) {
  if (e.response?.status === 422) alert(e.response.data.message);
}
```

## Agencies
| Method | URL | Body / Notes |
|--------|-----|--------------|
| GET | `/admin/agencies` | list |
| POST | `/admin/agencies` | `{ "name": "Municipality", "description": "...", "email": "...", "phone": "..." }` |
| GET | `/admin/agencies/{agency}` | single |
| PATCH | `/admin/agencies/{agency}` | partial update |
| DELETE | `/admin/agencies/{agency}` | `204` |

```js
await api.post("/admin/agencies", { name: "Road Maintenance Department" });
```

## Categories
| Method | URL | Body / Notes |
|--------|-----|--------------|
| GET | `/admin/categories` | list |
| POST | `/admin/categories` | `{ "name": "Water leak", "description": "...", "agency_id": 2, "responsible_employee_id": 5 }` |
| GET | `/admin/categories/{category}` | single |
| PATCH | `/admin/categories/{category}` | partial update |
| DELETE | `/admin/categories/{category}` | `204` |

> **`responsible_employee_id` (optional)** names the employee that every new report in this
> category is automatically assigned to. The employee must be active, have `role=employee`,
> and belong to the category's `agency_id` — otherwise the request fails with `422`.
> When it is left `null`, new reports go to the least busy active employee of the category's agency.

```js
await api.post("/admin/categories", {
  name: "Water leak",
  agency_id: 2,
  responsible_employee_id: 5,
});
```

## Users
| Method | URL | Body / Notes |
|--------|-----|--------------|
| GET | `/admin/users` | list |
| POST | `/admin/users` | `{ "name": "...", "email": "...", "password": "...", "role": "employee", "agency_id": 1 }` |
| GET | `/admin/users/{user}` | single |
| PATCH | `/admin/users/{user}` | partial update |
| DELETE | `/admin/users/{user}` | `204` |

> `role` can be `citizen`, `employee`, or `admin`. `agency_id` is required when `role=employee`.

**React example:**
```js
await api.post("/admin/users", {
  name: "New Employee",
  email: "emp@baladna.test",
  password: "password123",
  role: "employee",
  agency_id: 1,
});
```

## Assign employee to a report
Reports are **already assigned automatically** on creation, from the category the citizen
picked (see `responsible_employee_id` above). Use this endpoint to override that assignment.

### PATCH `/admin/reports/{report}/assign`
**Request body:**
```json
{ "employee_id": 5 }
```
> The employee must have `role=employee` and belong to the report's `agency_id`.

**Response (200):** updated report with `assigned_employee`.

**React example:**
```js
await api.patch(`/admin/reports/${reportId}/assign`, { employee_id: employeeId });
```

---

# 6. 💬 Community (Posts & Comments)

All endpoints require `auth:sanctum`.

## GET `/posts`
**Query params:** `page`, `per_page`.

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Waste collection problem",
      "content": "Waste has not been collected for days.",
      "author": { "id": 1, "name": "Mohammad" },
      "area": { "id": 2, "name": "Karrada" },
      "comments_count": 3,
      "created_at": "..."
    }
  ],
  "meta": { "current_page": 1, "per_page": 10, "total": 5, "last_page": 1 }
}
```

```js
const res = await api.get("/posts", { params: { page: 1 } });
```

## POST `/posts`
**Request body:**
```json
{ "title": "Waste collection problem", "content": "Waste has not been collected for days.", "area_id": 2 }
```
`area_id` optional. **Response (201):** created post.

```js
await api.post("/posts", { title, content, area_id });
```

## GET `/posts/{post}`
Returns the post with its `comments` array loaded.

## PATCH `/posts/{post}`
**Role:** owner only.
```js
await api.patch(`/posts/${id}`, { title, content });
```

## DELETE `/posts/{post}`
**Role:** owner or admin. **Response:** `204`.
```js
await api.delete(`/posts/${id}`);
```

## GET `/posts/{post}/comments`
Returns comments for a post.
```js
const res = await api.get(`/posts/${postId}/comments`);
```

## POST `/posts/{post}/comments`
**Request body:**
```json
{ "content": "The same issue exists on our street." }
```
**Response (201):** created comment.

```js
await api.post(`/posts/${postId}/comments`, { content });
```

## PATCH `/comments/{comment}`
**Role:** owner only.
```js
await api.patch(`/comments/${commentId}`, { content });
```

## DELETE `/comments/{comment}`
**Role:** owner or admin. **Response:** `204`.
```js
await api.delete(`/comments/${commentId}`);
```

---

# 7. 📋 Report Status Values

Use these string values in `status` filters and status updates:

| Status | Meaning |
|--------|---------|
| `submitted` | Newly created by citizen |
| `under_review` | Employee is reviewing |
| `accepted` | Accepted by agency |
| `in_progress` | Work started |
| `resolved` | Issue fixed (requires `resolution_note`) |
| `rejected` | Rejected (requires `rejection_reason`) |
| `cancelled` | Cancelled by citizen |

---

# 8. 🧠 React "How-to" Quick Examples

### Fetch reports with filters + pagination
```js
const [reports, setReports] = useState([]);
const [meta, setMeta] = useState({});

async function loadReports(page = 1) {
  const res = await api.get("/reports", {
    params: { page, per_page: 10, status: "submitted", area_id: 2 },
  });
  setReports(res.data.data);
  setMeta(res.data.meta);
}
```

### Handle validation errors
```js
try {
  await api.post("/reports", formData);
} catch (error) {
  if (error.response?.status === 422) {
    setErrors(error.response.data.errors); // { field: ["message"] }
  }
}
```

### Role-based UI
```js
const role = JSON.parse(localStorage.getItem("user"))?.role;
if (role === "admin") <AdminPanel />;
if (role === "employee") <EmployeeDashboard />;
if (role === "citizen") <CitizenForm />;
```

### Protected routes (React Router)
```jsx
function RequireAuth({ children }) {
  const token = localStorage.getItem("token");
  return token ? children : <Navigate to="/login" />;
}
```

### Building image URLs
Image API responses return `image_path` (e.g. `reports/abc.jpg`). To display them:
```js
const fullUrl = `http://localhost:8000/storage/${image.image_path}`;
```

---

# 9. 🏙️ Area Suggestions

A citizen (or an employee) can propose a new area. It is created with `status: "pending"` and is
**invisible** to the public `/areas` list until an admin approves it. Everyone involved can follow
the outcome:

| Who | Endpoint | Sees |
|-----|----------|------|
| The person who suggested it | `GET /my-area-suggestions` | their own suggestions, any status |
| Employee / Admin | `GET /employee/area-suggestions` | all suggestions, any status, read-only |
| Admin | `GET /admin/area-suggestions` | the moderation queue (pending by default) |

All of them return the same **Area object** documented in section 2.

---

## POST `/user-areas`
**Role:** Authenticated · **Auth:** Bearer

**Request body:**
| Field | Required | Notes |
|-------|----------|-------|
| `name` | ✅ | max 255, must be **unique** across all areas |
| `parent_id` | ❌ | must be an existing **approved** area |

```json
{ "name": "New Area", "parent_id": 1 }
```

**Response (201):**
```json
{
  "success": true,
  "message": "Area suggested successfully. It is pending review by an administrator.",
  "data": {
    "id": 10,
    "name": "New Area",
    "parent_id": 1,
    "parent": { "id": 1, "name": "Baghdad" },
    "status": "pending",
    "is_approved": false,
    "is_pending": true,
    "is_rejected": false,
    "suggested_by_id": 5,
    "created_at": "..."
  }
}
```

**Errors:** `422` if the name already exists or `parent_id` is not an approved area.

**React example:**
```js
try {
  const res = await api.post("/user-areas", { name, parent_id });
  toast(res.data.message); // "…pending review by an administrator."
} catch (e) {
  if (e.response?.status === 422) setErrors(e.response.data.errors);
}
```

---

## GET `/user-areas`
**Role:** Authenticated · **Auth:** Bearer

The **approved** areas only — this is the list to bind to a dropdown. Paginated.

**Query params:** `?per_page=20` · `?page=2`

**Response (200):** note there is **no `message`** key on collection responses.
```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Baghdad", "parent_id": null, "status": "approved", "is_approved": true, "is_pending": false, "is_rejected": false, "suggested_by_id": null, "created_at": "..." }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1 }
}
```

**React example:**
```js
const res = await api.get("/user-areas", { params: { per_page: 50 } });
setAreas(res.data.data);
setMeta(res.data.meta);
```

---

## GET `/my-area-suggestions`
**Role:** Authenticated · **Auth:** Bearer

The logged-in user's own suggestions, in **every** state, so they can see whether theirs was
approved. Newest first, paginated.

**Query params:** `?per_page=20` · `?page=2`

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "name": "New Area",
      "parent_id": 1,
      "status": "pending",
      "is_approved": false,
      "is_pending": true,
      "is_rejected": false,
      "suggested_by_id": 5,
      "created_at": "..."
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1 }
}
```

**React example:**
```js
const res = await api.get("/my-area-suggestions");

const badge = (a) =>
  a.is_approved ? "✅ Approved" : a.is_rejected ? "❌ Rejected" : "⏳ Pending review";

setRows(res.data.data.map((a) => ({ ...a, label: badge(a) })));
```

---

## GET `/employee/area-suggestions`
**Role:** Employee/Admin · **Auth:** Bearer

Read-only list of **citizen-submitted** suggestions (areas created directly by an admin are
excluded) with their approval state. Newest first, paginated. Employees cannot approve or
reject — that stays with admins.

**Query params:** `?status=pending|approved|rejected` · `?per_page=20` · `?page=2`

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "name": "New Area",
      "parent_id": 1,
      "parent": { "id": 1, "name": "Baghdad" },
      "status": "approved",
      "is_approved": true,
      "is_pending": false,
      "is_rejected": false,
      "suggested_by_id": 5,
      "suggested_by": { "id": 5, "name": "Ali Hassan" },
      "created_at": "..."
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1 }
}
```

**Errors:** `422` if `status` is not one of the three values.

**React example:**
```js
const res = await api.get("/employee/area-suggestions", { params: { status: "pending" } });
```

## GET `/employee/area-suggestions/{area}`
**Role:** Employee/Admin. A single suggestion with its approval state and `suggested_by`.

---

## GET `/admin/area-suggestions`
**Role:** Admin · **Auth:** Bearer

The moderation queue. **Defaults to `status=pending`** — pass `status` explicitly to review
past decisions. Newest first, paginated.

**Query params:** `?status=pending|approved|rejected` · `?per_page=20` · `?page=2`

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "name": "New Area",
      "parent_id": 1,
      "parent": { "id": 1, "name": "Baghdad" },
      "status": "pending",
      "is_approved": false,
      "is_pending": true,
      "is_rejected": false,
      "suggested_by_id": 5,
      "suggested_by": { "id": 5, "name": "Ali Hassan" },
      "created_at": "..."
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1 }
}
```

**React example:**
```js
const res = await api.get("/admin/area-suggestions"); // pending queue
setSuggestions(res.data.data);

// history of past decisions
const done = await api.get("/admin/area-suggestions", { params: { status: "rejected" } });
```

---

## PATCH `/admin/area-suggestions/{area}/approve`
**Role:** Admin · **Auth:** Bearer · **Body:** none

Approving makes the area appear in the public `/areas` list and selectable in report forms.

**Response (200):**
```json
{
  "success": true,
  "message": "Area suggestion approved successfully.",
  "data": {
    "id": 10,
    "name": "New Area",
    "status": "approved",
    "is_approved": true,
    "is_pending": false,
    "is_rejected": false,
    "suggested_by_id": 5,
    "suggested_by": { "id": 5, "name": "Ali Hassan" },
    "created_at": "..."
  }
}
```

**React example:**
```js
const res = await api.patch(`/admin/area-suggestions/${areaId}/approve`);
setSuggestions((list) => list.filter((a) => a.id !== areaId)); // it leaves the pending queue
```

---

## PATCH `/admin/area-suggestions/{area}/reject`
**Role:** Admin · **Auth:** Bearer · **Body:** none

**Response (200):** identical shape, with `"message": "Area suggestion rejected successfully."`,
`status: "rejected"` and `is_rejected: true`.

**React example:**
```js
await api.patch(`/admin/area-suggestions/${areaId}/reject`);
```

> Both actions are reversible: calling `approve` on a rejected area (or the reverse) simply
> flips the status.

---

This covers 100% of the API. For the full setup and installation steps, see **`STUDENT_SETUP_GUIDE.md`**.
