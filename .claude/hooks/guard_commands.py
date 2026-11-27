#!/usr/bin/env python3
"""
PreToolUse hook for Bash: blocks pushing code, deploying and remote server access.

It backs up the "deny" rules in .claude/settings.json, which match only the start of a command.
This hook also finds a blocked command later in the line: after &&, ;, | or a newline,
inside $(...) or backticks, after sudo/env/xargs/eval, or wrapped in bash -c "...".
It is a safety net against mistakes, not a security boundary.
"""

import json
import re
import sys

# Where a new command can start inside a shell line.
COMMAND_START = r"""
    (?:^|[;&|\n(`]|\$\(                       # line start, separators, subshells
      |\b(?:bash|sh|zsh)\s+-l?c\s+['"]          # bash -c "..."
      |\beval\s+['"]?                           # eval "..."
    )
    \s*
    (?:(?:sudo|nohup|time|exec|command)\s+      # wrappers
      |env(?:\s+-\S+)*\s+                       # env [-i]
      |xargs(?:\s+-\S+)*\s+                     # xargs [-0]
      |[A-Za-z_][A-Za-z0-9_]*=\S*\s+            # VAR=value
    )*
"""

BLOCKED = [
    (r"git(?:\s+-[Cc]\s+\S+|\s+--?[\w-]+(?:=\S+)?)*\s+push\b", "git push"),
    (r"(?:ssh|scp|sftp|rsync)\b", "remote server access (ssh/scp/sftp/rsync)"),
    (r"docker(?:\s+compose|-compose)?\s+(?:push|login)\b", "publishing Docker images (docker push/login)"),
    (r"(?:kubectl|helm)\b", "deploying to a cluster (kubectl/helm)"),
]

PATTERNS = [(re.compile(COMMAND_START + target, re.VERBOSE), label) for target, label in BLOCKED]


def main() -> int:
    payload = json.load(sys.stdin)
    command = str(payload.get("tool_input", {}).get("command", ""))

    for pattern, label in PATTERNS:
        if pattern.search(command):
            json.dump({
                "hookSpecificOutput": {
                    "hookEventName": "PreToolUse",
                    "permissionDecision": "deny",
                    "permissionDecisionReason": (
                        f"Blocked by .claude/hooks/guard_commands.py: {label} is not allowed for Claude "
                        "in this project. Ask the user to run it themselves."
                    ),
                }
            }, sys.stdout)
            return 0

    return 0


if __name__ == "__main__":
    sys.exit(main())
