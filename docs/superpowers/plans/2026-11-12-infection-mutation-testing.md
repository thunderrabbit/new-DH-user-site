# Infection Mutation Testing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Run Infection in Docker on every commit of this template repo, print-only, then deal with every existing survivor and record what the trial showed.

**Architecture:** pcov goes into the shared standards-mcp runner image and a `run-infection.sh` wrapper sits beside the other gates. This repo gains the two Infection dev packages, an `infection.json5`, and a print-only step in `.githooks/pre-commit` that mutates only the staged class files. A second bubble closes the survivors a whole-repo run reports.

**Tech Stack:** PHP 8.5, Codeception 5 Unit suite, Infection 0.35 with `infection/codeception-adapter`, pcov, Docker (rootless), bash.

**Spec:** `docs/superpowers/specs/2026-11-12-infection-mutation-testing-design.md`

## Global Constraints

- Print-only for the whole trial: Infection never changes the hook's exit status, including when it cannot run.
- Hook budget: about 2 extra seconds on a commit touching one or two class files.
- Existing Codeception Unit tests are what Infection runs. No PHPUnit rewrite.
- No `--min-msi` thresholds and no score presented as a target.
- phpcs, PHPStan (level 10, no baseline) and the Unit suite keep passing with unchanged results.
- Hermetic runs: repo mounted read-only, `--network none`, no credentials, nothing installed on the host.
- Unit tests stay DB-server-free and session-free; in-memory SQLite is fine.
- Branch from `master` in each repo: `3a88397` here, `feeb913` in standards-mcp. Never check out or merge into master. No worktrees.
- Commits: subject ≤50 chars, imperative, body says why, multiple `-m` flags, never a heredoc.
- Bubbles in this repo: BEGIN commit bumps `Version\Site::SEMVER` only; close with `git close-bubble` (`--dry-run` first).
- One class per file.

## Review Focus

- A commit staging no file under `classes/`: the hook must not start Infection and must be no slower than today.
- `vendor/bin/infection` missing (clone that has not re-run composer): the hook prints one line and the commit proceeds.
- A red unit test at commit time: Infection aborts its initial run; the hook prints one line and the commit proceeds.
- A staged class file that is deleted or renamed: only added/copied/modified/renamed-to paths are passed to `--filter`.
- A repo with no `infection.json5` given to `run-infection.sh`: it prints `GATE SKIP` and exits 0.

Each of these is exercised by hand in the task that owns the code (Tasks 2 and 5); none is a unit test, because the hook and wrapper are bash.

---

## Piece 1: wiring in

### Task 1: pcov in the runner image (standards-mcp)

**Files:**
- Modify: `~/work/rob/standards-mcp/images/codeception-runner/Dockerfile`
- Modify: `~/work/rob/standards-mcp/README.md` (image section)

- [ ] **Step 1:** In standards-mcp, `git checkout -b infection master`.
- [ ] **Step 2:** Time the three existing gates on this repo three times each with the current image and note the numbers.
- [ ] **Step 3:** Add `pecl install pcov && docker-php-ext-enable pcov` to the Dockerfile with a comment saying why (Infection needs line coverage; pcov does only that).
- [ ] **Step 4:** Rebuild only `standards-codeception-runner:php8.5` with the README's build command.
- [ ] **Step 5:** Verify: `php -m` in the image lists `pcov`; the three gates still pass on this repo with the same results; timings are within noise of Step 2. If they are not, ship pcov disabled by default (`pcov.enabled=0`) and enable it per Infection run.
- [ ] **Step 6:** Commit Dockerfile and README together.

### Task 2: `run-infection.sh` (standards-mcp)

**Files:**
- Create: `~/work/rob/standards-mcp/run-infection.sh`
- Modify: `~/work/rob/standards-mcp/README.md` (wrapper list)

**Interfaces:**
- Produces: `run-infection.sh <repo> [infection args…]`. Prints `GATE SKIP: no infection.json5 in <repo>` and exits 0 when the repo has none. Otherwise prints `GATE check=infection`, runs `php vendor/bin/infection --threads=max --no-progress --no-interaction --show-mutations=max` plus the extra args, and exits with Infection's status.

- [ ] **Step 1:** Write the wrapper in the shape of `run-unit.sh`: `runner_image`, layout discovered from `codeception.yml`, repo `:ro`, `--network none`, tmpfs over the Codeception output dir and `<support>/_generated`, and `--tmpfs /tmp:rw,exec` for Infection's and PHPStan's scratch files.
- [ ] **Step 2:** Verify the skip path: run it on a repo without `infection.json5` (this repo, before Task 4); expect `GATE SKIP` and exit 0.
- [ ] **Step 3:** Add it to the README's wrapper list, including the two slower modes passed as extra args (`--with-uncovered`, `--static-analysis-tool=phpstan`).
- [ ] **Step 4:** Commit. (Full verification happens in Task 4, once this repo has a config.)

Two commits on a branch, so no bubble in standards-mcp.

### Task 3: open the bubble and add the packages (this repo)

**Files:**
- Modify: `classes/Version/Site.php` (`SEMVER` `'0.4'` → `'0.5'`)
- Add: `docs/superpowers/specs/…-design.md`, `docs/superpowers/plans/…md`
- Modify: `composer.json`, `composer.lock`

- [ ] **Step 1:** `git checkout -b infection master`; BEGIN commit with the SEMVER bump only.
- [ ] **Step 2:** Commit the spec and this plan.
- [ ] **Step 3:** In the composer container: set `allow-plugins.infection/extension-installer` true, then `composer require --dev infection/infection infection/codeception-adapter`.
- [ ] **Step 4:** Run `composer audit`; record which packages the advisories name and whether they were present before Step 3 (compare against `git stash`-free check: audit the lock at `master`).
- [ ] **Step 5:** Run the three gates; commit `composer.json` and `composer.lock`.

### Task 4: `infection.json5` (this repo)

**Files:**
- Create: `infection.json5`

- [ ] **Step 1:** Write the spike's config (source `classes`, framework `codeception`, options `Unit`, bootstrap `Tests/Unit/_bootstrap.php`, tmpDir `/tmp/infection`, text log `/tmp/infection/infection.log`) plus `"mutators": { "@default": true, "PublicVisibility": false, "ProtectedVisibility": false }`. Exclude `classes/Config/Config.php` if Infection tries to mutate it.
- [ ] **Step 2:** Run `run-infection.sh` on this repo. Expected: it completes, reports survivors in `SchemaPath`, `LoginThrottle`, `CookieRepository`, `RandomToken`, and none from the two visibility mutators. Save the survivor list for Piece 2.
- [ ] **Step 3:** Run it once each with `--with-uncovered` and `--static-analysis-tool=phpstan` to confirm both modes work through the wrapper.
- [ ] **Step 4:** Commit.

### Task 5: print-only step in the pre-commit hook (this repo)

**Files:**
- Modify: `.githooks/pre-commit`

**Behaviour:**
- Runs when `infection.json5` is in the index and the commit stages (`--diff-filter=ACMR`) at least one `classes/*.php` file.
- Runs against the staged tree (`$STAGE`), read-only, with tmpfs over `Tests/_output`, `Tests/Support/_generated` and `/tmp`, passing the staged class files as `--filter=a.php,b.php`.
- Runs after phpcs and PHPStan have been decided, and only if they passed (a blocked commit does not need mutation output).
- Prints the "Escaped mutants" section of Infection's text log with `/stage/` stripped from paths, under one header line saying how many survived and that the commit is not blocked. Prints nothing when none survive.
- If `vendor/bin/infection` is missing, or Infection exits without producing a log, prints one line saying it could not run and carries on.
- Never alters the exit status. The header comment of the hook documents all of this.

- [ ] **Step 1:** Measure the baseline: stage a whitespace-free comment change in `classes/Database/SchemaPath.php`, time `.githooks/pre-commit` three times, unstage.
- [ ] **Step 2:** Implement the step.
- [ ] **Step 3:** Verify by hand, restoring the tree after each:
  - staged change to `SchemaPath.php`: survivors print, hook exits 0, extra time about 2 s
  - staged change to `CLAUDE.md` only: Infection does not start
  - `vendor/bin/infection` temporarily renamed: one line, exit 0
  - a unit test made red: one line, exit 0
  - a staged deletion of a class file alongside a modified one: only the modified one is filtered
- [ ] **Step 4:** Commit (the hook change itself goes through the hook).

### Task 6: document the fourth gate and close the bubble (this repo)

**Files:**
- Modify: `CLAUDE.md` ("Standards gates" section)

- [ ] **Step 1:** Add: the `run-infection.sh` line, the composer `allow-plugins` note, that the hook prints survivors and does not block during the trial, that only class methods and closures are mutated, and the per-line ignore convention (the exact annotation is confirmed in Task 7 and corrected here if needed).
- [ ] **Step 2:** Commit; all gates pass.
- [ ] **Step 3:** `git close-bubble --dry-run`, then close with an ALL-CAPS marker subject in the repo's voice; push the branch. Push standards-mcp's `infection` branch too.
- [ ] **Step 4:** Note the wall-clock time Piece 1 took, for the trial result.

---

## Piece 2: closing gaps

### Task 7: open the second bubble and settle the ignore convention

- [ ] **Step 1:** `git checkout -b infection-gaps` from the closed tip; BEGIN commit bumping `SEMVER` `'0.5'` → `'0.6'`.
- [ ] **Step 2:** Re-run `run-infection.sh` and take the current survivor list as the work list.
- [ ] **Step 3:** Confirm how Infection ignores one line (`@infection-ignore-all` on the statement, or a per-mutator `ignore` in `infection.json5`) by trying it on the `RandomToken.php` random pick. Use whichever is narrowest and carries a reason next to the code.

### Task 8: one commit per survivor group

For each group below: read the code and its test, pick the outcome, make the change, re-run `run-infection.sh --filter=<file>` to see the survivor gone, run the gates, commit. Tests are written first and seen to fail against a hand-applied mutant where practical. Keep a tally line per survivor (killed / simplified / ignored / mutator off, and whether it pointed at something real).

- [ ] `SchemaPath`: regex anchors (`^`, `$`), the `/db_schemas` containment base, the `||` in the containment check. Expected outcome: killed by tests in `Tests/Unit/SchemaPathTest.php`.
- [ ] `LoginThrottle`: missing-table check, the `return false` at the guard, `trim()` and multibyte lower-casing in username normalisation, the 255 cut-off, the `return 0`. Expected: mostly killed in `Tests/Unit/LoginThrottleTest.php`; judge the boundaries.
- [ ] `CookieRepository`: missing-column check, `is_current`, the `(int)` casts, the `?? 0` / `?? ''` defaults. Expected: a mix of killed, simplified where PHPStan level 10 still passes, and ignored where it does not.
- [ ] `RandomToken`: off-by-ones in the random pick. Expected: a test that every character of the alphabet can appear and nothing outside it does, if that can be made deterministic enough; otherwise ignored with a reason.
- [ ] Anything else the current run reports.

Simplifications are each their own commit, separate from test commits.

### Task 9: record the trial result and close

- [ ] **Step 1:** Whole-repo `run-infection.sh`: no survivors except reasoned ignores. All four gates pass.
- [ ] **Step 2:** Append "Trial result" to the spec: setup cost, per-commit time through the hook, the tally, a recommendation on print-only versus blocking, and what differs on `abb`.
- [ ] **Step 3:** Correct `CLAUDE.md` if Task 7 changed the ignore convention.
- [ ] **Step 4:** Close the bubble with `git close-bubble`; push.
- [ ] **Step 5:** Remove the spike's Docker image `infection-spike:php8.5-pcov` and the scratch copy.
