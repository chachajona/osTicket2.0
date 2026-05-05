# Phase 2b Rollback Runbook

## Triggering events
- Customer-visible incident traced to a Phase 2b write
- Two or more parity-check failures in 24h
- Canary group reports schema-shape regressions in legacy's UI for tickets touched by new SCP

## Rollback steps (in order)

1. **Disable Phase 2b routes.** Set `PHASE_2B_ENABLED=false` in production env; redeploy.
2. **Confirm rollback**, `curl /scp/tickets/1/notes -X POST` should now return 404.
3. **Audit damage**, query `scp_action_log` for actions in the incident window; export to CSV.
4. **Verify legacy state**, for each affected ticket, open in legacy SCP and confirm activity feed renders.
5. **Notify canary group** that the new write surfaces are temporarily disabled.

## Recovery
- Patch the underlying issue.
- Re-enable Phase 2b routes only after parity-check passes 24h on staging.
- Re-onboard canary group with revised brief.

## What we don't roll back
- `scp_action_log` rows, keep for postmortem.
- The `scp_action_log` table itself, additive, doesn't touch legacy.
- `lock` table, legacy still uses it; harmless to leave.
