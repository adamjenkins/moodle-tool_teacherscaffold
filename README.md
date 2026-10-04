# Teacher scaffold (tool_teacherscaffold)

A Moodle admin tool that eases new teachers into Moodle. Instead of facing every activity type
on day one, a new teacher starts with a small set of simple ones. When they have tried those,
more are unlocked, then more again, until everything is available. Like scaffolding in teaching,
the support is removed as confidence grows.

Any teacher can choose **Show me everything** at any time. The scaffold is a default, not a lock.

## How it works

- Each stage is a role that the plugin creates and manages. The role prohibits adding the
  activity types that are still locked at that stage (`mod/<name>:addinstance`). A guided teacher
  holds exactly one stage role, at site level. Because a prohibit cannot be overridden, locked
  activities disappear from the activity chooser and cannot be added any other way.
- By default the stage roles also block importing and restoring (course, section and activity
  restore, import from another course, single-activity duplicate and recycle-bin restore),
  because these could bring in locked activity types. This can be turned off.
- The plugin never edits core or other site roles. Its roles are rebuilt when the stages change,
  when plugins are installed or removed, and every night.
- Question banks and subsections are never restricted.
- Site administrators bypass all permission checks, so they cannot be guided. Test with a real
  teacher account.

Default stages:

1. File, Page, Forum, Text and media area
2. URL, Folder, Assignment, Glossary
3. Quiz, Choice, Feedback
4. Everything else (including any activity plugin installed later, until an admin puts it in a stage)

## Settings

*Site administration > Courses > Teacher scaffold*

- **Enable Teacher scaffold**: the master switch. Turning it off removes all restrictions and keeps
  everyone's progress.
- **Stages**: one stage per line, activity short names separated by commas.
- **When to unlock the next stage**: "Use every activity in the current stage at least once" or
  "Add a number of activities from the current stage".
- **Block import and restore**: on by default.
- **Add new teachers automatically**: add someone the first time they are given an editing teacher
  role anywhere.
- **Add members of these cohorts**: members of the selected site cohorts, including later joiners.

The **Guided teachers** report lists everyone being guided, with their stage, progress and status.
Admins can unlock the next stage, start again, or show everything. It also has a form to add
teachers and a list of checks. It needs `tool/teacherscaffold:manage`.

## For teachers

In course edit mode a guided teacher sees one line, for example "Getting started: 2 of 4 tried in
this stage.", with a **Show me everything** link. Opting out needs one click plus a confirmation.
It can be reversed from *Preferences > Step-by-step activity list*.

## Known limitations

Duplicating a whole section copies every activity in it, including locked types. Core checks only
`moodle/course:update` there, which teachers need, so no role can block it.

## Working with tool_wizards

When a stage unlocks, the plugin fires `\tool_teacherscaffold\event\tier_unlocked` (system
context, `relateduserid` = the teacher, `other` = `{"tier": int, "unlockedmodules": [short names]}`).
The companion plugin tool_wizards reacts to it. When tool_wizards is not installed or not enabled,
Teacher scaffold shows its own short "Nice work!" message instead. Neither plugin needs the other.

## Requirements

Moodle 5.2 or 5.3.

## Licence

GNU GPL v3 or later. See https://www.gnu.org/copyleft/gpl.html.
