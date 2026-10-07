"""Local SQL control/evidence for one marker-owned disposable binding."""
import argparse
import json
from pathlib import Path
import sqlite3
import time


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('operation', choices=['arm', 'evidence', 'disarm'])
    parser.add_argument('--database', required=True, type=Path)
    parser.add_argument('--binding-id', required=True)
    parser.add_argument('--reason', choices=['customer-request', 'duplicate-order'], default='customer-request')
    args = parser.parse_args()
    database = args.database.resolve()
    marker = database.parent.parent / '.t908-owned'
    if not database.is_file() or not marker.is_file() or marker.read_text() != 'surfacerelay-t908-filament\n':
        parser.error('Refusing database outside a marker-owned T-908 consumer')
    with sqlite3.connect(database) as connection:
        connection.row_factory = sqlite3.Row
        row = connection.execute('select * from pilot_livewire_bindings where binding_id=?', (args.binding_id,)).fetchone()
        if row is None:
            parser.error('Unknown exact binding')
        if args.operation == 'arm':
            descriptor = json.loads(row['descriptor'])
            method = descriptor['target']['method']
            if not row['active'] or row['expires_at'] <= time() or method not in ['holdCurrent', 'refundSelected']:
                parser.error('Refusing stale or unsupported binding')
            count = connection.execute('select count(*) from effects').fetchone()[0]
            connection.execute('insert into t908_response_faults (binding_id,component_id,method,reason,armed,effects_before) values (?,?,?,?,1,?)',
                (args.binding_id, row['component_id'], method, args.reason, count))
        elif args.operation == 'disarm':
            connection.execute('update t908_response_faults set armed=0 where binding_id=?', (args.binding_id,))
        fault = connection.execute('select * from t908_response_faults where binding_id=?', (args.binding_id,)).fetchone()
        effects = connection.execute('select id,tenant_id,order_ids,reason,t908_binding_id,t908_action_id from effects order by id').fetchall()
        print(json.dumps({'fault': dict(fault) if fault else None, 'effects': [dict(effect) for effect in effects]}, indent=2))


if __name__ == '__main__':
    main()
