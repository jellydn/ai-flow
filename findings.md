# Code Review — PR #84: feat: custom user launchers and per-user built-in launcher visibility

- **Branch:** `feat/custom-user-launchers`
- **Fixed point:** `main` (merge-base `8ad2798`)
- **Diff:** `git diff main...HEAD` — 53 files, +2290 −145
- **Spec sources:** PR #84 description, `doc/adr/0023-user-custom-launchers.md`, `doc/adr/0024-per-user-launcher-visibility.md`, `doc/adr/0023-0024-implementation-roadmap.md`
- **Standards sources:** `AGENTS.md`, `backend/README.md`, `doc/adr/`

The two axes are reported separately and not merged or reranked.

---

## Standards

Repo standards come from `AGENTS.md` ("React/TS: functional components + hooks, strict mode, avoid broad `any`"; "Lint/format with oxlint + oxfmt"; services live in `services/` and use the shared HTTP helpers) plus the Fowler smell baseline.

**(Hard) Frontend decoders silently strip the new API fields.** `decodeLauncher` (`resources/ts/services/run.ts:106-115`) drops `icon`, `tone`, and `is_custom` even though `Launcher` declares them (`types/api.ts:40-42`). `decodeRun` (`run.ts:89-103`) drops `is_public` even though `Run` declares it (`api.ts:14`). This contradicts the codebase's own strict-decoding pattern — the `assert*` helpers exist precisely to enforce the API contract, and every other decoder (`decodeFinding`, `decodeRecentRun`, `decodeUserLauncher`) asserts each field. Silent field-dropping is the source of several functional bugs in the Spec axis below.

**(Hard) `userLaunchers.ts` bypasses the shared HTTP helpers for PUT/DELETE.** `updateUserLauncher`, `deleteUserLauncher`, `hideLauncher`, `unhideLauncher` (`services/userLaunchers.ts:40-102`) call raw `fetch()` instead of going through `lib/http.ts`'s `request()` (which adds timeout, CSRF, and structured error parsing). DELETE/visibility calls only check `raw.ok` and throw generic strings, losing the validation-bag error messages the rest of the app surfaces. Root cause: `http.ts` only exports `get`/`post`. Fix by adding `put`/`del` helpers and routing through them.

**(Judgement — Divergent Change)** `UserLauncherController` handles two unrelated resources: custom-launcher CRUD (`index/store/update/destroy`) and built-in visibility (`hidden/hide/unhide`). ADR 0024 §3 specified a separate `UserHiddenLauncherController`; collapsing them means the controller changes for two unrelated reasons.

**(Judgement — Fragile contract)** `LauncherResource::toArray` (`Http/Resources/LauncherResource.php:13-29`) dual-modes over `$this->resource['slug']` (array) *or* `$this->slug` (Eloquent), picking via `??`. Which branch runs depends on the caller building an array vs. passing a model — invisible from the resource itself. A single normalized input shape would remove the ambiguity.

**(Judgement — Dead code)** `Report.tsx:49-51,131-140` adds a `hasStandardSchema`/`isCustomSchema` branch with a `<pre>` JSON fallback, but it is unreachable: `decodeRunResult` (`run.ts:51-54`) calls `assertString(data.summary, …)` and throws before `Report` ever renders when a custom-schema result omits `summary`. See Spec axis.

**(Judgement)** `UserLauncherFactory` sets `'id' => $this->faker->uuid()` (`database/factories/UserLauncherFactory.php:20`) redundantly — the model uses `HasUuids`. The `'user_id' => null` default is a footgun: a bare `UserLauncher::factory()->create()` violates the FK, and there is no `forUser()` helper (contrast `RunFactory::forUser`).

**(Judgement)** `LauncherMetaService::forCustom` (`Services/LauncherMetaService.php:34`) uses `abs((int) ($hash / 7))` to "shift" the hash — integer division as a bit shuffle is opaque; a named helper or a second hash would read as intent.

Worst Standards issue: the decoder gap (`decodeLauncher`/`decodeRun` stripping fields) — it's a hard contract violation of the codebase's strict-decoding convention *and* the root cause of four functional bugs below.

---

## Spec

Spec = PR #84 description + ADRs 0023/0024 + implementation roadmap.

### Missing or partial

**(High) Custom launcher icons/tones never reach the UI.** ADR 0023 §6 requires backend-assigned `icon`/`tone` for custom launchers; `LauncherResource` returns them and `LauncherMetaService::forCustom` deterministically picks from 6 icons × 4 tones. But `decodeLauncher` (`run.ts:106-115`) drops both fields, so `Home.tsx:190-191` and `LauncherSelector.tsx:27` fall through to `Sparkles`/`"blue"` for every custom launcher. The entire `LauncherMetaService::forCustom` code path is dead on the frontend. Quote: "For custom launchers, the backend deterministically assigns an icon and tone… This keeps the frontend free of hardcoded launcher metadata."

**(High) "Custom" badge does not render.** PR: "Custom badge: the home grid and selector render a small 'Custom' badge on items where `is_custom: true`." `Home.tsx`'s workflow grid (lines 188-198) has **no** Custom badge markup at all. `LauncherSelector.tsx:32` has the markup (`{launcher.is_custom && <span className="custom-badge">Custom</span>}`) but `launcher.is_custom` is `undefined` (stripped by `decodeLauncher`), so the badge never appears.

**(High) Hidden launchers cannot be unhidden.** `LauncherVisibilitySection.tsx:18` calls `getLaunchers()` to build the list of built-in launchers. `getLaunchers` sends the session cookie (`http.ts:96` `credentials: "include"`), and `LauncherController::index` filters out the user's hidden built-ins (`LauncherController.php:24-33`). So a hidden launcher is absent from the visibility list — the user can never see it again to toggle it back. The inline comment "Get all launchers without auth to see unfiltered built-in ones" is wrong; the call is authenticated. ADR 0024 §4 promises "Hidden launchers… you can show them again anytime."

**(High) Custom-schema runs cannot be displayed.** Custom launchers may define an `output_schema` without `summary`. `RunResource` returns the AI result as-is, but `decodeRunResult` (`run.ts:51-54`) does `assertString(data.summary, "result.summary")` and throws if `summary` is absent. `decodeRun` then throws, the run page (and SSE snapshot via `RunStreamer::fetchSnapshot`) fails to render, and `Report.tsx`'s custom-schema `<pre>` fallback is unreachable. This breaks the headline feature: arbitrary custom output schemas. ADR 0023 §"Consequences/Negative" anticipates rendering concerns but the implementation's `Report.tsx` branch was meant to handle it and can't.

**(High) `is_public` is not decoded on the frontend.** `RunResource` returns `is_public` (`RunResource.php:31`); `Run` declares `is_public?: boolean` (`api.ts:14`); `decodeRun` never copies it. The UI cannot know a fetched run's visibility, so any future "show private/public state on the report page" can't work without re-decoding.

**(Medium) No backend feature tests for the new surface.** Roadmap Phase 1 & 2 list 9+ feature tests as ship-check criteria: CRUD lifecycle, slug-uniqueness-scoped-to-user, hide/unhide, unified list includes custom + filters hidden, guest denied, custom-launcher run executes, `is_public` controls visibility, `StoreRunRequest` dual-table validation, CHECK-constraint rejection, `StoreUserLauncherRequest` schema validation. The diff adds 4 lines total — `is_public` assertions to `RunApiTest` and `RunOwnershipTest` — and no new test files. `UserLauncherFactory` exists but is unused. Ship check ("`php artisan test` passes. All new endpoints return correct HTTP status codes.") is unmet for the new endpoints.

**(Medium) Deleting a custom launcher orphans runs and contradicts the UI message.** Roadmap Phase 2: "`destroy`: Delete. Authorize ownership. Cascade-deletes related runs." The migration uses `nullOnDelete` (`2026_07_18_000003…:15`), so deleting a `UserLauncher` sets `runs.user_launcher_id = NULL`; the runs survive, now pointing only at the placeholder built-in `launcher_id`. `RunResource::toArray` then emits the placeholder's slug. `CustomLaunchersSection.tsx:157` tells the user "All runs using this launcher will also be deleted" — false.

**(Medium) `recent()` mislabels public custom-launcher runs.** `RunController::recent` (`RunController.php:103-117`) now selects `is_public = true` runs (so public custom-launcher runs surface) but eager-loads only `launcher:id,slug,name`, not `userLauncher`. `RecentRunSummary::from` reads `$run->launcher?->slug` / `?->name` (`RecentRunSummary.php:50-51`), so a public custom-launcher run is attributed to the placeholder built-in launcher. Inconsistent with `show`/`stream`, which load both.

**(Low) `RunResource` omits `is_custom` / `launcher_type`.** Roadmap Phase 2: "Include `is_custom` boolean"; PR description: "includes `launcher_type`." Neither field is present in `RunResource::toArray`.

**(Low) Migration omits the `user_launcher_id` index.** Roadmap Phase 1: "Add index on `['user_launcher_id']`." Not in `2026_07_18_000003…`.

**(Low) CHECK constraint not added.** ADR 0023 §2 specifies `CHECK ((launcher_id IS NOT NULL AND user_launcher_id IS NULL) OR (launcher_id IS NULL AND user_launcher_id IS NOT NULL))`. Not implemented (PR acknowledges SQLite can't `ALTER COLUMN`). The compensating fragility lives in the `RunResource.php:18` precedence comment ("DO NOT swap these") and the `LauncherResolutionService.php:42-44` placeholder lookup — both are downstream symptoms of this deviation. Additionally, `RunController::store:43-45` returns 503 for a custom-launcher run when *all* built-ins are inactive, a limitation that wouldn't exist under the ADR's nullable-`launcher_id` design.

### Scope creep
No notable scope creep. The `LauncherVisibilitySection` client-side filtering is missing infrastructure (no unfiltered built-in source), not creep.

### Looks-implemented-but-wrong
- `Report.tsx` custom-schema branch — implemented, unreachable (decoder throws first).
- `LauncherSelector` "Custom" badge — implemented, unreachable (`is_custom` stripped).
- `LauncherVisibilitySection` optimistic toggle — toggle logic correct, but it toggles against the wrong list (filtered, no `is_custom`).

Worst Spec issue: the cluster of decoder bugs (`decodeRunResult` requiring `summary`, `decodeLauncher`/`decodeRun` stripping fields) makes three of the four headline features — custom icons/tones, Custom badge, custom-schema rendering — silently non-functional on the frontend, with no tests catching any of it.

---

## Summary

- **Standards:** 7 findings (2 hard, 5 judgement). Worst: frontend decoders silently strip new API fields, violating the codebase's strict-decoding convention and seeding four functional bugs.
- **Spec:** 11 findings (4 high, 3 medium, 3 low, 1 missing-tests). Worst: custom-schema runs throw in `decodeRunResult` and can't render; combined with the `is_custom`/`icon`/`tone`/`is_public` decoder gaps, the PR's headline features are silently broken on the frontend with zero test coverage.
