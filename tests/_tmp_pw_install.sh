#!/bin/bash
cd /tmp
timeout 150 python3 -m pip install --quiet playwright 2>&1 | tail -3
python3 - <<'PY'
try:
    import playwright
    print('playwright-ok')
except Exception as exc:
    print('playwright-missing', exc)
PY
