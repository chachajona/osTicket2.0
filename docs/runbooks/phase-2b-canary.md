# Phase 2b Canary Brief

## Audience
Selected 3-5 staff who agree to pilot Phase 2b for one week.

## What works
- Internal note posting
- Staff/team assignment
- On-hold transition (only between open ↔ onhold)
- Note draft autosave
- Per-staff queue column customization
- Ticket lock acquire/renew/release (matches legacy admin's ticket_lock setting)

## Known gaps (until Phase 3)
- Notifications for actions you take in the new SCP do NOT fire, assignees, collaborators receive nothing.
- Plugin hooks tied to note, assignment, status events do NOT fire for actions taken here.
- If you need notifications, switch back to legacy (Panel switcher, Legacy SCP) for that action.

## Reporting issues
- Any UI quirk, screenshot + ticket # + step, #osticket-2-canary
- Any data discrepancy (legacy and new SCP show different things), priority; tag @on-call

## Rollback
The canary panel-switcher includes "?legacy=1" links if anything looks wrong. Use them, that ticket survives the transition unharmed.
