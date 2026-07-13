#!/usr/bin/env python3
"""Write an SMTP config file from a JSON payload on STDIN. Used by provision-tenant, which is fed by the
host agent, which is fed by the deploy worker — a chain that carries a PASSWORD, so this script prints
NOTHING (not even on error paths) and writes the file 0640 root:33 (the EA container's www-data).

argv[1] = destination path (e.g. /opt/booking-runtime/email/_platform.json).

If the payload omits `smtp_pass` and a config already exists at the destination, the stored password is
preserved: the dashboard sends an empty password field to mean "keep the one you have", and it can never
read the stored value back (by design).
"""
import json
import os
import sys

ALLOWED = (
    'enabled',
    'smtp_host',
    'smtp_port',
    'smtp_secure',
    'smtp_auth',
    'smtp_user',
    'smtp_pass',
    'from_address',
)

dest = sys.argv[1]
payload = json.load(sys.stdin)
config = {key: payload[key] for key in ALLOWED if key in payload}

if 'smtp_pass' not in config and os.path.exists(dest):
    try:
        with open(dest) as handle:
            previous = json.load(handle)
        if previous.get('smtp_pass'):
            config['smtp_pass'] = previous['smtp_pass']
    except (OSError, ValueError):
        pass

tmp = dest + '.tmp'
fd = os.open(tmp, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
with os.fdopen(fd, 'w') as handle:
    json.dump(config, handle)

os.chmod(tmp, 0o640)
if hasattr(os, 'chown'):  # POSIX only; absent on Windows, where this script only ever runs in tests
    try:
        os.chown(tmp, 33, 33)  # www-data inside the EA image must be able to read it
    except OSError:
        pass  # not root (e.g. a dev run): the file is still 0640, just owned by the caller

os.replace(tmp, dest)  # atomic: a reader never sees a half-written config
