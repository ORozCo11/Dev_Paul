"""Check bundled registration references against SQLite and the live public API."""
import argparse
import concurrent.futures
import json
import sqlite3
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--base-url', default='http://127.0.0.1:8001/api')
args = parser.parse_args()
root = Path(__file__).resolve().parents[1]
catalog = {name: json.loads((root / 'database/data' / f'psgc-{name}.json').read_text(encoding='utf-8'))
           for name in ('provinces', 'cities', 'barangays')}
db = sqlite3.connect(f"file:{(root / 'database/database.sqlite').as_posix()}?mode=ro", uri=True)
db.row_factory = sqlite3.Row
provinces = [dict(row) for row in db.execute('select id, name, code from provinces')]
cities = [dict(row) for row in db.execute('select id, name, code, province_id from cities')]
barangays = [dict(row) for row in db.execute('select id, name, psgc_code, city_id from barangays')]
errors = []
province_codes = [row['code'] for row in catalog['provinces']]
city_codes = [row['code'] for row in catalog['cities']]
barangay_codes = [row['psgc_code'] for entries in catalog['barangays'].values() for row in entries]
for label, codes in (('province', province_codes), ('city', city_codes), ('barangay', barangay_codes)):
    if len(codes) != len(set(codes)):
        errors.append(f'Duplicate bundled {label} codes')
for city in catalog['cities']:
    if city['provinceCode'] not in province_codes:
        errors.append(f"Bundled city has missing province: {city['code']}")
    if not catalog['barangays'].get(city['code']):
        errors.append(f"Bundled city has no barangays: {city['code']}")
for code in catalog['barangays']:
    if code not in city_codes:
        errors.append(f'Barangay group has missing city: {code}')
official = json.loads((root / 'database/data/psgc-official-summary.json').read_text(encoding='utf-8'))
real_provinces = [row for row in catalog['provinces'] if not row['code'].startswith('PSEUDO-')]
province_names = {row['name'].casefold().strip() for row in real_provinces}
city_names = {row['name'].casefold().strip() for row in catalog['cities']}
freshness_findings = []
for label, actual, expected in (
    ('provinces', len(real_provinces), official['provinces']),
    ('cities_and_municipalities', len(city_codes), official['cities'] + official['municipalities']),
    ('barangays', len(barangay_codes), official['barangays']),
):
    if actual != expected:
        freshness_findings.append({'level': label, 'bundled': actual, 'official': expected, 'difference': actual - expected})
for name in official['required_province_names']:
    if name.casefold() not in province_names:
        freshness_findings.append({'missing_province': name})
for code, name in official['required_municipalities'].items():
    if name.casefold() not in city_names:
        freshness_findings.append({'missing_municipality': name, 'official_psgc_code': code})
province_by_code = {row['code']: row for row in provinces}
city_by_code = {row['code']: row for row in cities}
barangay_by_code = {row['psgc_code']: row for row in barangays if row['psgc_code']}
for entries, column, label in ((provinces, 'code', 'province'), (cities, 'code', 'city'), (barangays, 'psgc_code', 'barangay')):
    codes = [row[column] for row in entries if row[column]]
    if len(codes) != len(set(codes)):
        errors.append(f'Duplicate {label} codes')
for entry in catalog['provinces']:
    if entry['code'] not in province_by_code:
        errors.append(f"Missing province {entry['code']}")
for entry in catalog['cities']:
    row = city_by_code.get(entry['code'])
    parent = province_by_code.get(entry['provinceCode'])
    if not row or not parent or row['province_id'] != parent['id']:
        errors.append(f"Missing or wrongly grouped city {entry['code']}")
for city_code, entries in catalog['barangays'].items():
    city = city_by_code.get(city_code)
    for entry in entries:
        row = barangay_by_code.get(entry['psgc_code'])
        if not row or not city or row['city_id'] != city['id']:
            errors.append(f"Missing or wrongly grouped barangay {entry['psgc_code']}")

def public_rows(rows, fields):
    return sorted([{field: row[field] for field in fields} for row in rows], key=lambda row: row['id'])

checks = [('/provinces', public_rows(provinces, ('id', 'name')))]
for province in provinces:
    checks.append((f"/cities?province_id={province['id']}", public_rows(
        [city for city in cities if city['province_id'] == province['id']], ('id', 'name'))))
by_city = {}
for barangay in barangays:
    if barangay['psgc_code']:
        by_city.setdefault(barangay['city_id'], []).append(barangay)
for city in cities:
    expected = public_rows(by_city.get(city['id'], []), ('id', 'name', 'psgc_code'))
    if not expected:
        errors.append(f"No selectable barangays for city {city['code']}")
    checks.append((f"/barangays?city_id={city['id']}", expected))

def check_api(check):
    path, expected = check
    try:
        with urllib.request.urlopen(args.base_url.rstrip('/') + path, timeout=30) as response:
            actual = json.load(response)
        if not isinstance(actual, list) or sorted(actual, key=lambda row: row['id']) != expected:
            return f'API response mismatch: {path}'
    except Exception as error:
        return f'API request failed: {path}: {error}'
    return None

print(f"Checking {len(checks)} live dropdown responses...", flush=True)
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
    for index, error in enumerate(pool.map(check_api, checks), 1):
        if error:
            errors.append(error)
        if index % 200 == 0:
            print(f'{index}/{len(checks)} checked', flush=True)
report = {
    'checked_at_utc': datetime.now(timezone.utc).isoformat(),
    'scope': 'Bundled hierarchy, database identity/parent coverage, all registration dropdown responses, and PSA 2026-Q3 summary/targeted missing-unit comparison; not a complete official row-by-row comparison.',
    'catalog': {'provinces_and_groups': len(catalog['provinces']), 'cities_and_municipalities': len(catalog['cities']),
                'barangays': sum(map(len, catalog['barangays'].values()))},
    'database': {'provinces_and_groups': len(provinces), 'cities_and_municipalities': len(cities),
                 'coded_barangays': len(barangay_by_code), 'total_barangays': len(barangays)},
    'live_dropdown_responses_checked': len(checks),
    'errors': errors,
    'internal_consistency_passed': not errors,
    'official_summary': official,
    'official_freshness_findings': freshness_findings,
    'complete_against_current_official_psgc': False if freshness_findings else None,
    'passed': not errors and not freshness_findings,
}
output = root.parent / 'logs/registration-geography-audit.json'
output.parent.mkdir(exist_ok=True)
output.write_text(json.dumps(report, indent=2), encoding='utf-8')
print(json.dumps(report, indent=2))
raise SystemExit(bool(errors or freshness_findings))
