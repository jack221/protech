# -*- coding: utf-8 -*-
# miui_fetch_fastboot.py

import sys
import json
import yaml
from urllib.request import urlopen

LATEST_YML_URL = "https://raw.githubusercontent.com/XiaomiFirmwareUpdater/miui-updates-tracker/master/data/latest.yml"


def is_device_entry(rom, pattern: str) -> bool:
    v    = rom.get("version") or ""
    link = rom.get("link")    or ""
    name = rom.get("name")    or ""
    text = (v + " " + link + " " + name).upper()
    return pattern.upper() in text


def detect_branch(rom) -> str:
    v    = (rom.get("version") or "").upper()
    link = (rom.get("link")    or "").upper()
    txt  = v + " " + link

    # 0) EU (xiaomi.eu / HyperOS EU مبني على CNXM + HYBRID)
    if "CNXM" in txt and "HYBRID" in txt:
        return "EU"

    # 1) حالات خاصة مثل ما غطيناها في PHP (لو ظهرت في YML مستقبلاً)
    if "JLB54.0" in txt or "TAURUS" in txt:
        return "China"
    for sp in ("KHCMIEK", "KHJMIDL", "LHJMIEK", "KHKMIED"):
        if sp in txt:
            return "Global"

    # 2) أكواد HyperOS / MIUI الحديثة (XM / DC)
    if "MIXM" in txt:  return "Global"
    if "EUXM" in txt:  return "EEA"
    if "EAXM" in txt:  return "EEA"
    if "TRXM" in txt:  return "Turkey"
    if "CNXM" in txt:  return "China"
    if "INXM" in txt:  return "Indian"
    if "RUXM" in txt:  return "Russian"
    if "IDXM" in txt:  return "Indonesia"
    if "TWXM" in txt:  return "Taiwan"
    if "JPXM" in txt:  return "Japan"
    if "MIDC" in txt:  return "Global_DC"
    if "KRXM" in txt:  return "Korea"

    # 3) MIUI / MI القديمة (كل شيء MI غالباً Global أو حسب اللاحقة)
    if "MIUI" in txt:  return "Global"
    for code in ("MIFA", "MIFD", "MIDA", "MIFM"):
        if code in txt:
            return "Global"

    if "INMI" in txt or "INFI" in txt or "INRF" in txt:
        return "Indian"

    if any(c in txt for c in ("RUMI", "RUFI", "RURF", "RUFD")):
        return "Russian"

    if "TRMI" in txt or "TRFI" in txt:
        return "Turkey"

    if "TWMI" in txt:
        return "Taiwan"

    if "EEMI" in txt or "EEFI" in txt:
        return "EEA"

    if "IDMI" in txt or "IDFI" in txt:
        return "Indonesia"

    if any(c in txt for c in ("CNFI", "CNMI", "CNFD", "CNEK", "CNFA", "CNCK")):
        return "China"

    # 4) fallback من الـ link (نفس الموجود سابقاً)
    if "_cn_"     in link or "/cn/"   in link: return "China"
    if "_global_" in link:                         return "Global"
    if "_eu_"     in link or "_eea_" in link:      return "EEA"
    if "_tr_"     in link:                         return "Turkey"
    if "_ru_"     in link:                         return "Russian"
    if "_tw_"     in link:                         return "Taiwan"
    if "_id_"     in link:                         return "Indonesia"
    if "_in_"     in link:                         return "Indian"
    if "_jp_"     in link:                         return "Japan"
    if "_dc_"     in link:                         return "Global_DC"

    # 5) fallback عام على كود منطقي ثنائي الحروف لو ظهر
    # (نفس منطق PHP لكن بشكل خفيف)
    region_map2 = {
        "CN": "China",
        "MI": "Global",
        "IN": "Indian",
        "RU": "Russian",
        "EU": "EEA",
        "ID": "Indonesia",
        "TR": "Turkey",
        "TW": "Taiwan",
        "KR": "Korea",
        "JP": "Japan",
        "LM": "Latin",
        "LA": "Latin",
        "CL": "Latin",
    }
    for code2, name2 in region_map2.items():
        if code2 in txt:
            return name2

    return "Unknown"


def method_to_type(method: str) -> str:
    m = (method or "").lower()
    if "fastboot" in m:
        return "Fastboot"
    if "recovery" in m:
        return "Recovery"
    return "Unknown"


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "NO_PATTERN"}, ensure_ascii=False))
        return

    pattern = sys.argv[1]

    try:
        with urlopen(LATEST_YML_URL) as response:
            data = yaml.safe_load(response.read().decode("utf-8"))
    except Exception as e:
        print(json.dumps({"error": "FETCH_FAILED", "details": str(e)}, ensure_ascii=False))
        return

    roms = [
        rom for rom in data
        if rom.get("branch") == "Stable"
        and is_device_entry(rom, pattern)
    ]

    if not roms:
        print(json.dumps({"error": "NO_ROM_FOUND", "pattern": pattern}, ensure_ascii=False))
        return

    results = []
    for rom in roms:
        results.append({
            "device":          rom.get("name"),
            "android":         rom.get("android"),
            "branch_channel":  rom.get("branch"),
            "branch":          detect_branch(rom),
            "type":            method_to_type(rom.get("method")),
            "version":         rom.get("version"),
            "size":            rom.get("size"),
            "date":            str(rom.get("date")),
            "download":        rom.get("link"),
            "pattern":         pattern,
        })

    print(json.dumps(results, ensure_ascii=False))


if __name__ == "__main__":
    main()
