---
kind: task
id: daily-review
name: Daily Code Review
intervalMinutes: 1440
enabled: true
runOnStartup: true
---

Review all uncommitted changes in the AgileTracker project:
1. Run `git diff --name-only` to see changed files
2. For each changed PHP file, check:
   - PSR-12 compliance (run `./vendor/bin/pint --test`)
   - N+1 query issues in Eloquent calls
   - Missing type hints or return types
   - Proper Spatie permission checks
3. Report findings with severity levels:
   - 🔴 Critical: security issues, broken migrations
   - 🟡 Warning: performance issues, missing tests
   - 🔵 Info: style suggestions
