# Infection mutation testing: trial in the template repo

Date: 2026-11-12
Status: design, awaiting Rob's approval

## Why

Rob wants to know whether changed lines are actually pinned down by tests.
The pressure comes from `abb` (andbeyond-backend): reaching PHPStan level 10
there means rewriting many lines, and he is not sure the tests would notice
if a rewrite changed behaviour. He had just learned about Infection
(https://github.com/infection/infection) and wants the code "as clean as
possible".

Infection makes small changes ("mutants") to source lines and re-runs the
tests. A mutant the tests still pass on is a "survivor": a test runs that
line but would not fail if the line were subtly wrong.

This repo (`new-DH-user-site`, checked out as `base.robnugen.com`) is the
trial. It is small, already at PHPStan level 10, and is the template other
sites are cloned from. What the trial shows decides whether and how
Infection goes to the other template projects and later to the client
projects `abb`, `abf` and `abc`.

## What the trial must answer

Rob's three questions, in his words: "if we can run it with our tooling
and how long it takes to set up, how much it helps in practice after it has
been set up".

A throwaway spike on 2026-11-12 already answered the first in principle
(see "What the spike showed"). The trial answers all three for real.

## Goals

1. Infection runs in Docker with the existing tooling, as easily and as
   often as the Codeception Unit suite does now.
2. It runs on every commit, print-only, adding about 2 seconds.
3. The existing Codeception Unit tests are what it runs. No rewrite to
   plain PHPUnit.
4. The survivors already in this repo get dealt with, and that work is
   measured, so Rob has real numbers for the "how much does it help"
   question.
5. At the end Rob gets a short written result he can use to decide on
   blocking and on rollout.

## Non-goals

- Rolling out to any other repo. That is decided after the trial.
- Blocking commits on survivors. Print-only for the whole trial (Rob,
  2026-11-12). Blocking is decided afterwards from the trial's numbers.
- Raising unit-test coverage of code no test runs today (`IsLoggedIn`,
  `DBExistaroo`, `Database\Base`: 345 of 605 mutants). Infection reports
  that, but it is a coverage problem, not this project.
- Exposing Infection as a tool in the standards-mcp server (`server.py`).
  A wrapper script is enough for the trial. (Claude's call.)
- Making browser-driven suites (Webdriver etc.) usable by Infection. They
  are too slow and need a live stack.

## Anti-goals

- Chasing a mutation score. No `--min-msi` thresholds, no score in the
  hook output as a target. The useful output is the list of survivors.
- A hook noisy enough that people learn to ignore it or reach for
  `--no-verify`.
- Weakening the existing gates: phpcs, PHPStan and the Unit suite keep
  their current behaviour, results and (near enough) speed.
- Tests written only to kill a mutant, asserting nothing a reader would
  care about. A survivor that does not point at a real behaviour gets an
  ignore with a reason instead.

## What the spike showed

Run in a scratch copy; nothing in this repo or standards-mcp was changed.

- pcov 1.0.12 builds on the PHP 8.5 runner image with
  `pecl install pcov && docker-php-ext-enable pcov` (5 seconds).
- `infection/infection` 0.35.10 and `infection/codeception-adapter` 0.4.7
  install under the repo's `config.platform.php` 8.5.0 and work with
  Codeception 5.3.7. Composer needs
  `allow-plugins.infection/extension-installer` set to true.
- Infection must be given `Tests/Unit/_bootstrap.php` as its `bootstrap`,
  because the project's classes load through a custom autoloader, not
  composer's. Without it, `--with-uncovered` crashes with
  `Class "Database\EDatabaseException" does not exist`.
- It ran hermetically in the same shape as `run-unit.sh`: repo mounted
  read-only, `--network none`, tmpfs over `Tests/_output`,
  `Tests/Support/_generated` and Infection's tmp dir (which needs `exec`).

The config that worked:

```json5
{
    "source": { "directories": ["classes"] },
    "testFramework": "codeception",
    "testFrameworkOptions": "Unit",
    "bootstrap": "Tests/Unit/_bootstrap.php",
    "tmpDir": "/tmp/infection",
    "logs": { "text": "/tmp/infection/infection.log" }
}
```

Timings on this repo (13 threads):

| Run | Mutants | Time |
|---|---|---|
| One changed file via `--filter` (`SchemaPath.php`) | 25 | 1.4 s |
| One changed file via `--filter` (`LoginThrottle.php`) | 47 | 1.7 s |
| All covered code | 260 | 6 s |
| Including uncovered code (`--with-uncovered`) | 605 | 9 s |
| Covered code, PHPStan also judging (`--static-analysis-tool=phpstan`) | 260 | 27 s |

For comparison, today's gates through the standards-mcp wrappers: phpcs
0.5 s, PHPStan 2.5 to 2.8 s, Unit suite 0.3 s.

Of 260 mutants in covered code, 225 were killed and 35 survived. Composer
also reported 2 security advisories after the install; nobody has checked
which packages they are in.

## The work, in two pieces

Rob wants both done, in this order, as separate pieces, so the first
shows the setup cost on its own.

### Piece 1: wiring in

No test changes in this piece.

**In standards-mcp (`~/work/rob/standards-mcp`):**

- Add pcov to `images/codeception-runner/Dockerfile`, so every repo gets it
  from a rebuild. (Claude's call: pcov over Xdebug, because Infection only
  needs line coverage, pcov does only that, and it is faster. Rob asked how
  to choose and did not object.)
- Rebuild `standards-codeception-runner:php8.5`. The 8.3 and 8.1 images
  can wait until a repo on those versions adopts Infection.
- Having pcov in the image must not change what the existing gates report.
  Whether pcov is loaded always or switched on only for Infection runs is
  the builder's choice.
- Add `run-infection.sh <repo>`, a sibling of `run-unit.sh`: whole-repo
  run, on demand, same hermetic shape, skips cleanly when the repo has no
  `infection.json5`. It should let the caller pass extra Infection
  arguments through, so the slower modes (`--with-uncovered`,
  `--static-analysis-tool=phpstan`) are available without being the
  default.
- Mention it in standards-mcp's README alongside the other wrappers.

**In this repo:**

- Add the two packages to `require-dev`, with the `allow-plugins` entry,
  and commit the updated `composer.lock`.
- Run `composer audit`, and say in the piece's report which packages the 2
  advisories belong to and whether Infection brought them in.
- Add `infection.json5` based on the spike's config.
- Switch off the mutators that only produced noise here:
  `PublicVisibility` and `ProtectedVisibility`. (Claude's call. The
  integer increment/decrement mutators stay on: they produced noise on
  `?? 0` defaults but also real findings, so those lines are handled one
  by one in piece 2.)
- Extend `.githooks/pre-commit`:
  - It runs Infection when the commit stages PHP under the Infection
    source directory (`classes/`), against the staged tree like the other
    two checks.
  - It reports only survivors relevant to the commit. Limiting to the
    staged source files with `--filter` is measured and good enough.
    Limiting to changed lines is better if it can be done within the time
    budget (Infection has `--git-diff-lines`, untried here; the staged
    tree the hook builds has no `.git`, and the runner image may have no
    git). Builder's choice.
  - Print-only: survivors are printed clearly, each with file, line and
    the change that survived. Infection never changes the hook's exit
    status. That includes Infection failing to run at all (for instance
    because a unit test is red, since the hook does not run the Unit
    suite): print one line saying so and carry on.
  - Budget: about 2 extra seconds on a commit touching one or two class
    files. Rob accepted that figure. Measure it through the real hook and
    report it.
  - PHPStan as a second judge stays out of the hook (27 s).
- Update `CLAUDE.md`'s "Standards gates" section: the fourth gate, how to
  run it by hand, that the hook prints and does not block during the
  trial, and how to mark a line as deliberately ignored.

Piece 1 is done when a commit that touches a weakly tested line (for
example `classes/Database/SchemaPath.php` line 36) prints its survivors
and still commits, a commit touching no class file is no slower than
today, and the three existing gates pass unchanged.

### Piece 2: closing gaps

Every survivor from a whole-repo run (`run-infection.sh`) gets one of
four outcomes:

- **Simplified the code.** Source changes under `classes/` are allowed
  (Rob, 2026-11-12) when a survivor shows a default, cast or branch is
  redundant. Each simplification is its own commit, with all gates still
  passing. This is auth code in the template every site is cloned from,
  and PHPStan level 10 may be the reason a default or cast exists; where
  it is, the line gets a reasoned ignore instead.

- **Killed** by a new or tightened unit test that asserts a behaviour
  worth asserting.
- **Ignored on that line with a reason**, the same convention the repo
  uses for `@phpstan-ignore`: narrow, one line, reason stated.
- **Mutator switched off** in `infection.json5`, only if a mutator turns
  out to produce nothing but noise across the repo.

The spike's survivors, as a starting list (line numbers as of 3a88397;
re-run to get the current set):

Likely real gaps:
- `Database/SchemaPath.php:36`: the `^` and `$` anchors of the traversal
  guard regex can each be removed. `CLAUDE.md` says to keep them.
- `Database/SchemaPath.php:43`: `"/db_schemas"` can be dropped from the
  containment base.
- `Database/SchemaPath.php:46`: an `||` can become `&&` in the containment
  check (PHPStan catches this one; the tests do not).
- `Auth/LoginThrottle.php:141` and `Database/CookieRepository.php:218`:
  the "missing table / missing column" checks can be inverted.
- `Auth/LoginThrottle.php:56`: a `return false` can be removed.
- `Auth/LoginThrottle.php:92`: `trim()` can be removed from username
  normalisation; `mb_strtolower`/`mb_substr` can become their byte
  versions.
- `Database/CookieRepository.php:185`: `is_current === 1` can flip.

Borderline, builder judges each:
- `Auth/LoginThrottle.php:92`: the 255-character cut-off boundaries.
- `Auth/LoginThrottle.php:116`: `return 0` can become 1 or -1.
- `Auth/RandomToken.php:21`: off-by-ones in a random pick. Probably not
  sensibly testable.
- `Database/CookieRepository.php:92` and `:211`: `(int)` casts.

Probably noise:
- `Database/CookieRepository.php:179` to `:185`: `?? 0` and `?? ''`
  defaults on columns that are always present.
- `CookieRepository::purgeExpired` and `Template::loadTemplate`
  visibility (gone once those mutators are off).

New tests follow the repo's Unit rules: no DB server, no session,
in-memory SQLite is fine.

While doing this, keep a tally: for each survivor, which of the four
outcomes it got,
and roughly how long the piece took overall. That tally is the trial's
main result.

Piece 2 is done when a whole-repo run shows no survivors other than lines
carrying a reasoned ignore, and all four gates pass.

## The trial's result

After piece 2, append a short "Trial result" section to this document
(Claude's call on where it lives) with:

- setup: what piece 1 took, and anything that would differ on another repo
- per-commit time, measured through the hook
- the survivor tally: killed / simplified / ignored / mutator disabled,
  and how many pointed at something real
- a recommendation on print-only versus blocking
- what would be different on `abb` (PHP 8.3 image, a much larger Unit
  suite, PHPStan at levels 0 and 1 with baselines, most risky code only
  reachable by browser tests). Also: Infection 0.35.10 mutates only code
  inside class methods and closures. Plain functions and top-level script
  code get no mutants (tried in the spike; see
  `ReflectionVisitor::isFunctionLikeNode()`), so `abb`'s `wwwroot/`
  scripts are out of its reach until their logic moves into classes.

Rob decides rollout and blocking from that.

## Constraints

- Follow this repo's `CLAUDE.md`: commit conventions, bubbles, the
  `Version\Site::SEMVER` bump in a BEGIN commit, hermetic Docker runs,
  nothing installed on the host.
- standards-mcp is a separate repo with its own history and voice; check
  its `git log` before committing there.
- `repos.yaml` registers both `~/work/rob/new-DH-user-site` and
  `~/work/rob/base.robnugen.com` with `php_target: "8.5"`, so
  `runner_image` picks the php8.5 image for this checkout.
- The image build command is in standards-mcp's README.
- Branch from the `master` tip in each repo (Rob, 2026-11-12, after he
  cleaned both up): `3a88397` here, `feeb913` "BEFORE we test Infection"
  in standards-mcp.
- No git worktrees. Advancing master is Rob's job.
- The spike left a Docker image `infection-spike:php8.5-pcov` and a
  scratch copy of the repo. Both are throwaway; remove the image once the
  real runner image has pcov.

## Left to the builder

- How pcov is enabled in the image (always on, or per run).
- File-level versus line-level filtering in the hook.
- The exact look of the hook's printed output.
- How `run-infection.sh` discovers layout (it can reuse `run-unit.sh`'s
  approach).
- Thread count, and any Infection options that make it faster.
- How the two pieces are split into commits and bubbles, under Rob's
  sizing rules.

## Trial result

Written 2026-11-12, after both pieces were built.

### Can it run with our tooling?

Yes, with no change to how tests are written. It runs in the same runner
image, read-only and with no network, on the existing Codeception Unit
suite. What it needed:

- pcov in the runner image. It had to ship switched off: left on, it
  slowed phpcs from 0.39 s to 0.55 s and PHPStan from 2.33 s to 2.87 s.
  The wrapper and the hook turn it on for their own run.
- `run-infection.sh` in standards-mcp (commits `b635d27`, `1997fc4` on its
  `infection` branch).
- Here: two dev packages, a composer `allow-plugins` entry, a 25-line
  `infection.json5`, and about 60 lines in `.githooks/pre-commit`.

The 2 composer advisories are in `phpunit/phpunit` and `symfony/yaml`.
Both were there before Infection was added; neither comes from it.

### How long did setup take?

Piece 1 ran from 18:56 to 19:02 AEDT, about 6 minutes of agent time, on
top of the 15-minute spike that found the two snags (the bootstrap entry
and the coverage driver). On another repo already using the runner image
it is the same handful of files, plus a rebuild of that repo's PHP image.

### Per-commit time, measured through the hook

| Staged | Before | After |
|---|---|---|
| No class file | 0.05 s | 0.05 s (Infection does not start) |
| `SchemaPath.php` (24 mutants) | 2.66 s | 3.60 s |
| `LoginThrottle.php` (42 mutants) | 2.66 s | 4.3 s |

So one to two seconds extra, inside the budget. A whole-repo run through
the wrapper is 4 seconds (217 mutants); with PHPStan judging mutants,
17 seconds.

### The survivor tally

A whole-repo run found 33 survivors (the spike's 35, less the two
visibility mutants switched off in piece 1). Piece 2 ran from 19:02 to
19:07 AEDT, about 5 minutes of agent time, and ended with 217 mutants
generated and 217 killed.

| Outcome | Count |
|---|---|
| Killed by a new or tightened test | 25 |
| Code simplified | 4 |
| Ignored on the line, with a reason | 4 |
| Mutator switched off (piece 2) | 0 |

The 25 killed all pointed at something real. The ones that mattered most:

- **A test that could not fail.** `testListMarksTheCookieInHand` sorted
  its booleans before comparing them, so it passed with the "current
  device" flag on the wrong device.
- **A check that could be inverted.** `LoginThrottle::isMissingTable()`
  could be flipped so that every database error read as "no table, so no
  throttle". The same held for the missing-column check in
  `CookieRepository`.
- **The traversal guard.** Either regex anchor in `SchemaPath` could be
  removed, and the containment base could shrink from `db_schemas` to the
  app root.
- **Production's branch was untested.** The MySQL SQLSTATE branches of
  both "missing table / column" checks had no test at all, because the
  suite runs on SQLite. `Tests/Unit/FailingOncePdo.php` now feeds them
  MySQL's own errors.
- **Off-by-ones in the token alphabet pick**, which would drop a
  character from every token or bias them.

The 4 simplified were `?? 0` defaults on row columns that are always
present (`619c244`). The 4 ignored are two fallback values no test can
reach or distinguish (`3d5bdd0`, `c51f7c5`). That is 8 of 33, about a
quarter, that were not test gaps.

Three things turned up while reading the code that Infection did not
flag and this trial did not change:

- `SchemaPath` checks containment with `str_starts_with($realTarget,
  $realBase)` and no trailing separator, so a sibling directory named
  `db_schemas_something` would pass. Reachable only through a symlink.
- Its regex ends in `$`, which also accepts a trailing newline. Harmless
  today, because `realpath()` then fails.
- Its final `file_exists()` check cannot be reached: `realpath()` has
  already returned false for a missing file, so a missing migration is
  reported as "escapes base directory".

### Recommendation: print-only or blocking?

Stay print-only here for now, and revisit after a few weeks of ordinary
commits. Reasons:

- With the repo at zero survivors, anything the hook prints is new, so
  it is hard to miss even without blocking.
- A quarter of this repo's survivors were not test gaps. Blocking would
  have forced an ignore comment or a code change for each before any
  commit touching those files.
- Infection aborts when a unit test is red, and the hook does not run
  the Unit suite. A blocking hook would need to decide what a red suite
  means first.

If it does become blocking, block on survivors only, never on Infection
failing to run.

### What would be different on `abb`

- **Reach.** Infection mutates only class methods and closures.
  `abb`'s 579 PHP files are mostly `wwwroot/` scripts, and last night's
  bugs were partly in `wwwroot/system/units/unit.php`. That code gets no
  mutants until its logic moves into classes with unit tests.
- **Image.** `abb` is on the php8.3 runner image, which has not been
  rebuilt with pcov.
- **Speed.** Cost is roughly mutants times the time of the tests that
  cover them. This repo's whole suite runs in under 0.1 s; `abb` has 85
  Unit test files and far more code, so both the whole-repo run and the
  per-commit run will be slower by an amount not yet measured.
- **Starting point.** Expect hundreds of existing survivors, not 33. The
  hook should report per changed line there, not per changed file, or
  every commit re-prints a file's old survivors. That needs git inside
  the runner or line filtering in the hook, and was not built here.
- **PHPStan.** `abb` is at levels 0 and 1 with baselines, so PHPStan as a
  second judge of mutants would catch little.

The suggested first step on `abb` is a whole-repo `run-infection.sh`
with `--with-uncovered`, print-only and outside the hook, to see the
size of the list before deciding anything.
