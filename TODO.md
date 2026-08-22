# TODO - Area approval visibility, employee review access & category-based auto-assignment

Steps from approved plan:

- [x] 1. Fix the 500 on `GET /admin/area-suggestions` — `ApiResponse` is a trait, so
      `new ApiResponse(...)` was fatal. Same bug fixed in `UserAreaController`.
- [x] 2. Expose `status` / `is_approved` / `is_pending` / `is_rejected` / `suggested_by` on `AreaResource`
- [x] 3. Add `GET /my-area-suggestions` so a citizen sees whether their suggestion was approved
- [x] 4. Add `GET /employee/area-suggestions` (+ `/{area}`) read-only view for employees
- [x] 5. Add `GET /employee/reviews` and `GET /employee/reports/{report}/review`;
      embed the review in `GET /employee/reports/{report}` and the employee report list
- [x] 6. Add `categories.responsible_employee_id` (migration, model, requests, resource)
- [x] 7. Create `ReportAssignmentService` (responsible employee, else least-loaded agency employee)
- [x] 8. Auto-assign on report creation (authenticated + anonymous) and on category change
- [x] 9. Fix related broken behaviour found while reviewing:
      - public `/areas` leaked pending & rejected suggestions
      - `PATCH /reports/{report}` left `agency_id` stale after a category change
      - reports could be filed against pending/rejected areas
      - `DELETE /admin/areas/{area}` crashed on areas with sub-areas or posts
      - `AreaResource` emitted `{"id":null,"name":null}` for a null parent
      - `Role::ADMIN` (non-existent case) in `UserAreaSuggestionTest`
- [x] 10. Add feature tests (`ReportAutoAssignmentTest`, `EmployeeReviewVisibilityTest`,
      extra cases in `UserAreaSuggestionTest`)
- [x] 11. Update `API_REFERENCE.md`
- [ ] 12. Run `php artisan migrate` and `php artisan test` (no PHP runtime in the WSL shell — run on the Windows side)
