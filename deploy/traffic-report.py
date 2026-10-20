#!/usr/bin/python3
"""Rapports GoAccess privés. Aucun IP, identifiant de route ou paramètre exporté."""
import datetime as dt
import fcntl
import gzip
import grp
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
import urllib.parse
import urllib.request
from zoneinfo import ZoneInfo

TIMEZONE = ZoneInfo('Europe/Paris')

LOG = re.compile(r'^(\S+) (.*?) \[([^]]+)\] "([^" ]+) ([^" ]+) ([^"]+)" (\d+) (\S+) "([^"]*)" "([^"]*)"')
SOURCES = {'site': 'meriz-site.access.log', 'app': 'meriz-app.access.log', 'api': 'meriz-api.access.log', 'history': 'access.log'}


def sanitized(line):
    match = LOG.match(line)
    if not match:
        return None
    ip, prefix, date, method, url, protocol, status, size, referrer, agent = match.groups()
    stamp = dt.datetime.strptime(date, '%d/%b/%Y:%H:%M:%S %z')
    stamp = stamp.astimezone(TIMEZONE)
    date = stamp.strftime('%d/%b/%Y:%H:%M:%S %z')
    path = url.split('?', 1)[0].split('#', 1)[0]
    path = '/'.join(':id' if '@' in urllib.parse.unquote(part) or len(part) > 64 else part for part in path.split('/'))
    # Routes de compte, invitations et documents : jamais d'identifiant visible.
    path = re.sub(r'(?<=/)(?:\d+|[0-9a-fA-F-]{16,}|[^/]*@[^/]*)(?=/|$)', ':id', path)
    if '/verify/' in path or '/reset' in path or '/invitation' in path:
        path = path.split('/verify/')[0].split('/reset')[0].split('/invitation')[0]+'/action'
    parsed = urllib.parse.urlsplit(referrer)
    referrer = 'https://'+parsed.hostname+'/' if parsed.hostname else '-'
    safe = f'{ip} {prefix} [{date}] "{method} {path} {protocol}" {status} {size} "{referrer}" "{agent}"\n'
    return stamp, safe


def rows(report, key, nested=False):
    data = report.get(key, {}).get('data', [])
    if nested:
        children = [child for row in data for child in row.get('items', [])]
        if children:
            data = children
    result = [{'label': (str(row.get('method', ''))+' '+str(row.get('data', 'Inconnu'))).strip()[:240],
               'hits': int(row.get('hits', {}).get('count', 0)),
               'visitors': int(row.get('visitors', {}).get('count', 0))} for row in data]
    combined = {}
    for row in result:
        current = combined.setdefault(row['label'], {'label': row['label'], 'hits': 0, 'visitors': 0})
        current['hits'] += row['hits']
        current['visitors'] += row['visitors']
    return sorted(combined.values(), key=lambda row: row['hits'], reverse=True)


def update_geo():
    target = Path('/var/lib/meriz-traffic/country.mmdb')
    if target.exists() and dt.datetime.now().timestamp()-target.stat().st_mtime < 25*86400:
        return
    request = urllib.request.Request('https://db-ip.com/db/download/ip-to-country-lite', headers={'User-Agent': 'Mozilla/5.0'})
    text = urllib.request.urlopen(request, timeout=30).read().decode()
    urls = re.findall(r'https://download\.db-ip\.com/free/dbip-country-lite-[0-9-]+\.mmdb\.gz', text)
    if not urls:
        raise RuntimeError('Base pays officielle introuvable')
    request = urllib.request.Request(urls[0], headers={'User-Agent': 'Mozilla/5.0', 'Referer': 'https://db-ip.com/db/download/ip-to-country-lite'})
    data = gzip.decompress(urllib.request.urlopen(request, timeout=40).read())
    target.parent.mkdir(parents=True, exist_ok=True)
    temporary = target.with_suffix('.new')
    temporary.write_bytes(data)
    temporary.replace(target)


def main():
    with open('/run/lock/meriz-traffic.lock', 'w') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        try:
            update_geo()
        except Exception as error:
            print('Actualisation pays indisponible :', type(error).__name__)
        country = Path('/var/lib/meriz-traffic/country.mmdb')
        output = Path('/var/www/meriz-api/storage/app/private/traffic')
        output.mkdir(parents=True, exist_ok=True)
        os.chown(output, 0, grp.getgrnam('www-data').gr_gid)
        os.chmod(output, 0o750)
        today = dt.datetime.now(TIMEZONE).date()
        for source, name in SOURCES.items():
            records = []
            for path in Path('/var/log/nginx').glob(name+'*'):
                if not re.fullmatch(re.escape(name)+r'(?:\.\d+(?:\.gz)?)?', path.name):
                    continue
                opener = gzip.open if path.suffix == '.gz' else open
                with opener(path, 'rt', errors='replace') as stream:
                    for line in stream:
                        try:
                            record = sanitized(line)
                            if record and record[0].date() >= today-dt.timedelta(days=29):
                                records.append(record)
                        except ValueError:
                            continue
            for days in (1, 7, 30):
                filtered = ''.join(line for stamp, line in records if stamp.date() >= today-dt.timedelta(days=days-1))
                with tempfile.TemporaryDirectory(prefix='meriz-traffic-') as temp:
                    report_path = Path(temp)/'report.json'
                    command = ['goaccess', '-', '--no-global-config', '--log-format=COMBINED', '--no-progress',
                               '--ignore-crawlers', '--no-query-string', '--no-term-resolver', '--no-ip-validation',
                               '--ignore-panel=HOSTS', '--ignore-panel=REFERRERS', '--ignore-panel=KEYPHRASES',
                               '--ignore-panel=REMOTE_USER', '--max-items=1000', '-o', str(report_path)]
                    if country.exists():
                        command += ['--geoip-database='+str(country)]
                    for extension in ('.css', '.js', '.map', '.svg', '.ico', '.woff', '.woff2'):
                        command += ['--static-file='+extension]
                    if filtered:
                        result = subprocess.run(command, input=filtered, text=True, capture_output=True)
                        if result.returncode:
                            raise RuntimeError('GoAccess a échoué pour '+source)
                        report = json.loads(report_path.read_text())
                    else:
                        report = {}
                general = report.get('general', {})
                statuses = rows(report, 'status_codes', True)
                pages = rows(report, 'requests')
                daily = rows(report, 'visitors')
                page_views = report.get('requests', {}).get('metadata', {}).get('hits', {}).get('total', {}).get('value', 0)
                daily.sort(key=lambda row: row['label'])
                for row in daily:
                    row['label'] = dt.datetime.strptime(row['label'], '%Y%m%d').strftime('%d/%m/%Y')
                final = {
                    'generated_at': dt.datetime.now(dt.timezone.utc).isoformat(),
                    'start_date': general.get('start_date'), 'end_date': general.get('end_date'),
                    'geo_updated_at': dt.datetime.fromtimestamp(country.stat().st_mtime).strftime('%d/%m/%Y') if country.exists() else None,
                    'summary': {'visitors': general.get('unique_visitors', 0), 'requests': general.get('valid_requests', 0),
                                'pages': page_views, 'errors': sum(row['hits'] for row in statuses if re.match(r'^[45]\d\d', row['label']))},
                    'daily': daily,
                    'pages': pages[:15], 'browsers': rows(report, 'browsers')[:12], 'os': rows(report, 'os')[:12],
                    'countries': rows(report, 'geolocation', True)[:15], 'referrers': rows(report, 'referring_sites')[:15],
                    'statuses': statuses[:20],
                }
                path = output/f'{source}-{days}.json'
                temporary = path.with_suffix('.new')
                temporary.write_text(json.dumps(final, ensure_ascii=False), encoding='utf8')
                os.chown(temporary, 0, grp.getgrnam('www-data').gr_gid)
                os.chmod(temporary, 0o640)
                temporary.replace(path)
                print(source, days, 'jours :', final['summary']['requests'], 'requêtes')


if __name__ == '__main__':
    main()
