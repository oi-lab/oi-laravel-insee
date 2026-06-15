---
title: AI Assistant Skills
description: Give AI coding assistants context about this package
section: advanced
order: 5
---

# AI Assistant Skills

When you work with an AI coding assistant (Claude Code, JetBrains Junie) in a project that uses
oi-laravel-insee, the assistant benefits from knowing how the package is meant to be used — that the
SIRENE client is resolved through the `Insee` facade / `Client` / `app('insee')`, the available lookup
methods, the `q` search syntax, and the `dirigeant` injection behavior.

The package ships a canonical skill file that communicates this context.

## Installing the skill

The recommended way is the unified `oi:skills` command, provided by the `oi-lab/oi-laravel-development`
package. It discovers the skills shipped by every installed `oi-lab/*` package and lets you pick which to
install, in the current project or your Claude Code user profile:

```bash
php artisan oi:skills
```

To install only this package's skill non-interactively:

```bash
php artisan oi:skills oilab-laravel-insee --project   # or --global
```

This copies the skill to `.claude/skills/oilab-laravel-insee/SKILL.md` (Claude Code) and
`.junie/skills/oilab-laravel-insee/SKILL.md` (JetBrains Junie), and adds (or refreshes) an
`=== oi-lab/oi-laravel-insee rules ===` section in your project's `CLAUDE.md`.

> A package-local command `php artisan oi-insee:install-ai-skill` is still available for projects that
> don't use `oi-lab/oi-laravel-development`, but it is **deprecated** in favor of `oi:skills`.

### Keeping it up to date automatically

To refresh the skill whenever the package is updated, add the command to your application's `composer.json`:

```json
"scripts": {
    "post-autoload-dump": [
        "@php artisan oi:skills oilab-laravel-insee --project --quiet"
    ]
}
```

> Requires `oi-lab/oi-laravel-development`.

## What the skill tells the assistant

The skill file instructs the assistant to:

- Resolve the client through the `Insee` facade, dependency injection on `Client`, or `app('insee')` —
  never instantiate `Client` manually.
- Use `findSiret`, `findSiren`, `searchCompanies`, `searchEstablishments`, and `getApiStatus`, and read
  responses as arrays (checking `header.statut`).
- Build searches with INSEE's `field:value` `q` syntax (`AND` / `OR` / `*`).
- Expect the injected `dirigeant` key for natural persons, and its absence for legal entities.
- Read configuration via `config('oi-laravel-insee.*')`, not `env()`.

## Customizing the skill

The source of truth is `resources/stubs/ai-skill.md`. Maintainers re-sync the committed skill copies with:

```bash
composer sync-ai-skills
```

This also runs automatically on `post-autoload-dump` inside the package repository.
