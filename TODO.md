# TODO - Anonymous reports & website stats feature

Steps from approved plan:

- [x] 1. Create `website_stats` migration
- [x] 2. Create `WebsiteStat` model
- [x] 3. Create `WebsiteStatsService` (refresh/get)
- [x] 4. Wire stats refresh into `ReportController::createReport()` and `ReportStatusService::transition()`
- [x] 5. Create `WebsiteController` with `latestAnonymousReports()` and `stats()` methods
- [x] 6. Create `WebsiteStatResource`
- [x] 7. Register public routes (no auth) in `routes/api.php`
- [x] 8. Add feature tests for both endpoints
- [x] 9. Run tests (all pass), then commit & push
