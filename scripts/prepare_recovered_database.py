"""Inspect the recovered HOMYA dump offline; never opens a database connection.

Preserves the original and writes a private, literal-only recovery bundle.
The bundle's DDL and rows are later applied to newly created schemas only.
"""
import hashlib
import json
import os
from pathlib import Path
import re

from op07_dump import tokens

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / 'u429557141_catalogue.20261018180146.sql'
RUN = ROOT / 'storage/app/private/database-backups/recovery-20261019'
LOT = '48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809'
TABLES = ('archive_lots', 'archive_categories', 'archive_products', 'archive_translations', 'migrations')


def statements(text):
    """Split outside SQL quotes and discard comments, including executable ones."""
    i = start = 0
    quote = None
    parts = []
    while i < len(text):
        c = text[i]
        if quote:
            if c == '\\' and quote != '`':
                i += 2
                continue
            if c == quote:
                if i + 1 < len(text) and text[i + 1] == quote:
                    i += 2
                    continue
                quote = None
        elif c in "'\"`":
            quote = c
        elif (text.startswith('/*', i) or c == '#'
              or (text.startswith('--', i) and (i + 2 == len(text) or text[i + 2].isspace()))):
            parts.append(text[start:i])
            end = text.find('*/', i + 2) + 2 if text.startswith('/*', i) else text.find('\n', i)
            if end < i:
                if text.startswith('/*', i):
                    raise RuntimeError('Unterminated comment')
                end = len(text)
            parts.append(' ')
            i = start = end
            continue
        elif c == ';':
            parts.append(text[start:i])
            statement = ''.join(parts).strip()
            if statement:
                yield statement
            parts = []
            start = i + 1
        i += 1
    if quote or ''.join(parts + [text[start:]]).strip():
        raise RuntimeError('Unterminated SQL')


def parse_values(sql, columns):
    parsed = tokens(sql)
    table = parsed[2][1]
    if parsed[:2] != [('word', 'INSERT'), ('word', 'INTO')] or table not in TABLES:
        raise RuntimeError('Unexpected insert')
    if parsed[3] != ('word', 'VALUES'):
        raise RuntimeError('Unexpected insert format')
    i, rows = 4, []
    while i < len(parsed):
        if parsed[i] != ('sym', '('):
            raise RuntimeError('Expected tuple')
        i += 1
        row = []
        while True:
            sign = 1
            if parsed[i] == ('sym', '-'):
                sign = -1
                i += 1
            kind, value = parsed[i]
            if kind == 'str' and sign == 1:
                row.append(value)
            elif kind == 'num' and re.fullmatch(r'\d+', value):
                row.append(sign * int(value))
            elif (kind, value) == ('word', 'NULL') and sign == 1:
                row.append(None)
            else:
                raise RuntimeError('Only literal values are accepted')
            i += 1
            if parsed[i] == ('sym', ')'):
                i += 1
                break
            if parsed[i] != ('sym', ','):
                raise RuntimeError('Invalid tuple')
            i += 1
        if len(row) != len(columns[table]):
            raise RuntimeError('Column count mismatch')
        rows.append(row)
        if i < len(parsed):
            if parsed[i] != ('sym', ','):
                raise RuntimeError('Unexpected SQL after tuple')
            i += 1
    return table, rows


def main():
    RUN.mkdir(parents=True, exist_ok=True, mode=0o700)
    os.chmod(RUN, 0o700)
    os.chmod(RUN.parent, 0o700)
    target = RUN / 'bundle.json'
    if target.exists():
        raise RuntimeError('Recovery bundle already exists; refusing replacement')
    raw = SOURCE.read_bytes()
    digest = hashlib.sha256(raw).hexdigest()
    if digest != 'b542eb33f2d5e855dbf066f3aff3fba389181081838e5e5b71b824bf129ac854':
        raise RuntimeError('Recovered source changed')
    ddl, columns, inserts = {}, {}, []
    discarded = {}
    for sql in statements(raw.decode('utf8')):
        create = re.match(r'^CREATE TABLE `([a-z_]+)` \(', sql)
        if create:
            table = create[1]
            if table not in TABLES or table in ddl:
                raise RuntimeError('Unexpected or duplicate table')
            if re.search(r'\b(DEFINER|TRIGGER|PROCEDURE|FUNCTION|SELECT|INTO|OUTFILE|DIRECTORY)\b', sql, re.I):
                raise RuntimeError('Unexpected DDL')
            for reference in re.findall(r'REFERENCES `([^`]+)`', sql):
                if reference not in TABLES:
                    raise RuntimeError('Foreign schema reference')
            ddl[table] = sql
            columns[table] = re.findall(r'^  `([a-z_]+)` ', sql, re.M)
        elif sql.startswith('INSERT INTO '):
            inserts.append(sql)
        elif re.fullmatch(r'DROP TABLE IF EXISTS `(archive_categories|archive_lots|archive_products|archive_translations|migrations)`', sql):
            discarded['DROP'] = discarded.get('DROP', 0) + 1
        elif (sql.startswith('SET ') or sql == 'COMMIT' or sql == 'UNLOCK TABLES'
              or re.fullmatch(r'LOCK TABLES `[a-z_]+` WRITE', sql)):
            discarded[sql.split()[0]] = discarded.get(sql.split()[0], 0) + 1
        else:
            raise RuntimeError('Unknown statement: ' + sql[:32])
    if set(ddl) != set(TABLES) or discarded.get('DROP') != 5:
        raise RuntimeError('Incomplete schema')
    rows = {t: [] for t in TABLES}
    for sql in inserts:
        table, values = parse_values(sql, columns)
        rows[table].extend(values)
    counts = {t: len(rows[t]) for t in TABLES}
    if counts != dict(zip(TABLES, [5, 67, 3711, 23890, 4])):
        raise RuntimeError('Dump counts changed')
    main_counts = {t: sum(row[0] == LOT for row in rows[t]) for t in TABLES[:-1]}
    if list(main_counts.values()) != [1, 65, 3709, 23878]:
        raise RuntimeError('Business lot mismatch')
    bundle = {'source_sha256': digest, 'lot_key': LOT, 'table_order': TABLES,
              'ddl': ddl, 'columns': columns, 'rows': rows,
              'counts': counts, 'business_counts': main_counts}
    with target.open('x') as f:
        os.chmod(target, 0o600)
        json.dump(bundle, f, ensure_ascii=False, separators=(',', ':'))
    copy = RUN / 'original.sql'
    with copy.open('xb') as f:
        os.chmod(copy, 0o600)
        f.write(raw)
    if hashlib.sha256(copy.read_bytes()).hexdigest() != digest:
        raise RuntimeError('Backup verification failed')
    print(json.dumps({'source_sha256': digest, 'counts': counts, 'business_counts': main_counts,
                      'discarded_not_executed': discarded, 'bundle': str(target)}))


if __name__ == '__main__':
    main()
