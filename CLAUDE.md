# CLAUDE.md

## Definition of Done - Review Process
When working on code in a project, the following review process is MANDATORY before considering and task complete:

1.**Solve the issue 100%** - Implementation must be fully complete

2. **Codex Review Agent (x2):**
    - Spawn a codex review agent to review the current uncommitted changes. Let it provde a honest review. NEVER pipe output to a file - instead, TELL it with the prompt to output its FINDINGS in a file. It will create that file itself. Do NOT ignore its output. If it has findings, fix them All, even if they seems unrelated to your changes. Only re-run once fixes are applied. Only passes when there are zero findings.
    - Spawn a second codex review agent to verify the issue itself is solved (if a GitHub issue was given). The codex review agent CAN access GitHub.

3. **Claude Review Agent (x2):**
    - Spawn a claude review agent to review the uncommitted code. Same rules as above . it must have zero complaints.
    - Spawn a second claude review agent to verify the issue-state is resolved (if a GitHub issue was given).

4. **Linting, type checking and tests must pass** for the project (use whatever tools the project defines).

### Review Rules

- Reviewers are NOT to be given a timeout. They take as long as they take.
- If a reviewer errors out (e.g. "out of tokens), killed, or any other error), it does NOT count as approved. Re-run it.
- CRITICAL: Never pipe reviewer stdout to a file. Put the instruction to write findings to a log file WITHIN the review propmt itself.
- You may ONLY re-run a reviewer AFTER you have fixed what it told you to fix.


This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

@.github/copilot-instructions.md
