---
kind: task
id: weekly-cleanup
name: Weekly Project Cleanup
intervalMinutes: 10080
enabled: true
runOnStartup: false
---

Weekly maintenance tasks for AgileTracker:
1. **Check migrations**: Run `php artisan migrate:status` — ensure all are up
2. **Test suite**: Run `./vendor/bin/phpunit` — report failures
3. **Composer audit**: Run `composer audit` — check for vulnerable dependencies
4. **Lint**: Run `./vendor/bin/pint` on the entire codebase
5. **Git hygiene**: Check for stale branches, uncommitted work, large files
6. **Documentation**: Verify README matches current setup instructions
