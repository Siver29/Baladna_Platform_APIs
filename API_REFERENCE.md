# 🚀 Baladna API — Complete Endpoint Reference for React Developers

This document lists **every endpoint** in the Baladna API, with the **HTTP method**, **URL**, **required role**, **request body**, **response format**, and a **React (Axios) example** for each group.

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

**Success (collection with pagination):**
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
**Query params:** `?parent_id=2`

**Response (200):**
```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Baghdad", "parent_id": null, "parent": null, "created_at": "..." }
  ]
}
```

**React example:**
```js
const res = await api.get("/areas", { params: { parent_id: 1 } });
setAreas(res.data.data);
```

## GET `/areas/{area}`
Returns a single area.

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
    { "id": 1, "name": "Damaged roads", "description": "...", "agency_id": 1 }
  ]
}
```

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
| `area_id` | ✅ | |
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
| `area_id` | ✅ | |
| `title` | ✅ | max 255 |
| `description` | ✅ | max 5000 |
| `address` | ❌ | max 500 |
| `latitude` | ❌ | -90 to 90 |
| `longitude` | ❌ | -180 to 180 |
| `images[]` | ❌ | max 5, jpg/jpeg/png/webp, max 5MB |

> The backend automatically sets `user_id`, `agency_id` (from category), `status=submitted`, `priority=normal`, generates the `reference_number`, and creates the first history record.

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
Returns a single report (with `review`, `assigned_employee` when loaded).

## PATCH `/reports/{report}`
**Role:** Citizen owner · only while status is `submitted`.

**Request body (all optional):**
```json
{ "title": "Updated title", "description": "...", "address": "..." }
```

**React example:**
```js
const res = await api.patch(`/reports/${id}`, { title, description });
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

# 4. 🛠️ Employee Reports

All endpoints require `auth:sanctum` + `employee` role (admins can also access). Employees only see reports from their own `agency_id`.

## GET `/employee/reports`
Same filters/pagination as `/reports`, but scoped to the employee's agency.

```js
const res = await api.get("/employee/reports", { params: { status: "in_progress" } });
```

## GET `/employee/reports/{report}`
Show a single report (scoped to agency).

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

---

# 5. 👑 Admin CRUD

All endpoints require `auth:sanctum` + `admin` role.

## Areas
| Method | URL | Body / Notes |
|--------|-----|--------------|
| GET | `/admin/areas` | list (paginated) |
| POST | `/admin/areas` | `{ "name": "Zubair", "parent_id": 1 }` |
| GET | `/admin/areas/{area}` | single |
| PATCH | `/admin/areas/{area}` | `{ "name": "...", "parent_id": 2 }` |
| DELETE | `/admin/areas/{area}` | `204` |

```js
await api.post("/admin/areas", { name: "Zubair", parent_id: 1 });
await api.patch(`/admin/areas/${id}`, { name: "New name" });
await api.delete(`/admin/areas/${id}`);
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
| POST | `/admin/categories` | `{ "name": "Water leak", "description": "...", "agency_id": 2 }` |
| GET | `/admin/categories/{category}` | single |
| PATCH | `/admin/categories/{category}` | partial update |
| DELETE | `/admin/categories/{category}` | `204` |

```js
await api.post("/admin/categories", { name: "Water leak", agency_id: 2 });
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

This covers 100% of the API. For the full setup and installation steps, see **`STUDENT_SETUP_GUIDE.md`**.
