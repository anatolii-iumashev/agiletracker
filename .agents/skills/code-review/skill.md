---
id: code-review
name: Code Review Expert
description: Review PHP/Laravel code for quality, security, and performance
enabled: true
---

# Code Review Checklist

## Security
- [ ] All user input validated (Form Requests)
- [ ] Mass-assignment protection (`$fillable`/`$guarded`)
- [ ] Authorization checks (Policies/Gates)
- [ ] No raw SQL — use Eloquent/Query Builder
- [ ] CSRF, XSS, SQL injection vectors checked
- [ ] File uploads validated and stored safely

## Performance
- [ ] N+1 queries eliminated (eager load relationships)
- [ ] Pagination used on list queries
- [ ] Database indexes on foreign keys and filtered columns
- [ ] No heavy computation in loops
- [ ] Caching considered for expensive queries

## Code Quality
- [ ] PSR-12 compliance (run Pint)
- [ ] All types declared (return types, param types)
- [ ] No dead code or commented-out blocks
- [ ] Descriptive variable/function names
- [ ] Complex logic extracted to dedicated methods/services
- [ ] Tests cover happy path + edge cases

## Filament-specific
- [ ] Forms use proper validation rules
- [ ] Table columns have `->sortable()` and `->searchable()` where appropriate
- [ ] Filters make sense for the data
- [ ] Actions have confirmation dialogs for destructive ops
