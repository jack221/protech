# -*- coding: utf-8 -*-
# miui_list_codenames.py

import json
import yaml
from urllib.request import urlopen

LATEST_YML_URL = "https://raw.githubusercontent.com/XiaomiFirmwareUpdater/miui-updates-tracker/master/data/latest.yml"

def main():
    try:
        with urlopen(LATEST_YML_URL) as response:
            body = response.read()
            data = yaml.safe_load(body.decode("utf-8"))
    except Exception as e:
        print(json.dumps({"error": "FETCH_FAILED", "details": str(e)}))
        return
    codenames = set()
    
    for rom in data:
        if rom.get("branch") != "Stable":
            continue
    
        raw = (rom.get("codename") or "").strip()
        if not raw:
            continue
    
        # نأخذ الجذر قبل أول _
        base = raw.split("_", 1)[0].upper()
        codenames.add(base)
    
    print(json.dumps(sorted(codenames), ensure_ascii=False))


if __name__ == "__main__":
    main()
