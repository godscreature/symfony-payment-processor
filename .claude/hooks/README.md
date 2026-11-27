# Claude hooks

Hooks are scripts Claude Code runs automatically on events (before a tool call, after an edit, ...).
They are registered in [.claude/settings.json](../settings.json) under `hooks` and run on the host, not in Docker.

| Hook | Event | What it does |
|---|---|---|
| [.claude/hooks/guard_commands.py](guard_commands.py) | `PreToolUse` on `Bash` | Blocks `git push`, deploys and remote server access, even inside `&&`, `$(...)` or `bash -c` |

## guard_commands.py

Blocks commands that push code or reach outside this machine:

- `git push` (any options, e.g. `git -C path push`)
- `ssh`, `scp`, `sftp`, `rsync`
- `docker push`, `docker login`, `docker compose push`
- `kubectl`, `helm`

**Why a hook as well as `deny` rules.** The `deny` rules in `settings.json` match only the start of a
command, so `make test && git push` or `bash -c "git push"` would get past them. The hook searches the
whole line: after `&&`, `;`, `|` or a newline, inside `$(...)` and backticks, after `sudo`, `env`,
`xargs`, `eval`, and inside `bash -c "..."`. It is a safety net against mistakes, not a security boundary.

**When it blocks**, it returns `permissionDecision: deny` with a reason, and Claude asks the user to
run the command. With `onFailure: block`, a crashed hook also blocks the command.

**`git commit`** is not blocked: an `ask` rule in `settings.json` makes Claude wait for the user's approval.

## Testing a hook

Feed it the JSON Claude Code sends and look at the decision:

```bash
jq -n --arg c 'make test && git push' '{tool_name:"Bash",tool_input:{command:$c}}' \
  | python3 -I .claude/hooks/guard_commands.py | jq -r '.hookSpecificOutput.permissionDecision // "allow"'
```

Check both sides: commands that must be blocked, and similar ones that must pass
(`git log --grep push`, `grep -rn ssh src/`, `docker compose exec -T php ...`).

## Adding a hook

1. Put the script in this folder; Python with `python3 -I` or a POSIX shell script.
2. Register it in [.claude/settings.json](../settings.json) under the right event and matcher.
3. Pipe-test it as above, then add a row to the table in this file.
