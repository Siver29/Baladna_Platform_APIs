# Anonymous Report Submission — Implementation Checklist

## Goal
Allow citizens to submit a civic report **without** authentication by providing their contact info directly (Option A), while keeping the existing authenticated flow.

## Steps
- [x] 0. Understand task & gather context (read API_REFERENCE, routes, controller, model, policy, service, migrations, tests)
- [x] 1. Plan approved by user (Option A: nullable `user_id` + `reporter_*` columns)
- [x] 2. Migration: add `reporter_name`, `reporter_email`, `reporter_phone` and make `user_id` nullable on `reports`
- [x] 3. Migration: make `user_id` nullable on `report_status_histories`
- [x] 4. Add `StoreAnonymousReportRequest`
- [x] 5. Update `ReportController` (add `storeAnonymous`, refactor shared creation logic)
- [x] 6. Update `Report` model fillable
- [x] 7. Update `ReportStatusService::transition` to accept nullable actor
- [x] 8. Update `ReportResource` to render anonymous reporter
- [x] 9. Update `ReportPolicy` to guard against null `user_id`
- [x] 10. Update `routes/api.php` (public `POST /reports/anonymous`)
- [x] 11. Update `ReportFactory` for nullable `user_id`
- [x] 12. Add feature tests for anonymous report creation
- [x] 13. Update `API_REFERENCE.md`
- [x] 14. Run migrations and tests (54 passed, 1 skipped)

