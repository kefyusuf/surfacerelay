"""Generate an opt-in local Filament-helper candidate without changing old recipes."""
from pathlib import Path
import re

ROOT=Path(__file__).resolve().parents[3]
HERE=Path(__file__).resolve().parent
DEST=ROOT/'.tmp/t910-filament-demo'

def generate():
    client=(ROOT/'examples/filament-orders-live/client.mjs').read_text()
    pattern=r"^import \{([^}]+)\} from '/surfacerelay/runtime/[^']+';\s*"
    groups=re.findall(pattern,client,re.MULTILINE)
    names=[name.strip() for group in groups for name in group.split(',')]
    required={'FilamentBrowserDriver','GlobalFilamentSelectionRuntime','FilamentSelectionCoordinator'}
    if not required.issubset(names):
        raise SystemExit('Modern Filament example imports changed; review candidate generation.')
    client=re.sub(pattern,'',client,flags=re.MULTILINE)
    client="import { "+', '.join(names)+" } from '@surfacerelay/browser-runtime';\n\n"+client
    old='new WebMcpRegistrationLifecycle(modelContext, drivers)'
    if client.count(old)!=1:
        raise SystemExit('Modern example registration changed; review candidate envelope mode.')
    client=client.replace(old,"new WebMcpRegistrationLifecycle(modelContext, drivers, { resultMode: 'envelope' })")
    # Reuse T-908 validation, fault fixtures and pinned PHP consumer. __file__
    # deliberately points there so its template/source paths stay historical.
    source_path=HERE.parent/'t908-filament/generate.py'
    source=source_path.read_text()
    for old,new,count in [('.tmp/t908-filament-demo','.tmp/t910-filament-demo',2),
        ('.t908-owned','.t910-owned',1),('surfacerelay-t908-filament','surfacerelay-t910-filament',1),
        ('.t908-browser-artifact','.t910-browser-artifact',1)]:
        if source.count(old)!=count:
            raise SystemExit('T-908 generator changed; review task-owned candidate transformation.')
        source=source.replace(old,new)
    exec(compile(source,str(source_path),'exec'),{'__file__':str(source_path),'__name__':'__main__'})
    (DEST/'client.mjs').write_text(client)
    control=(HERE.parent/'t908-filament/fault_control.py').read_text()
    control=control.replace('.t908-owned','.t910-owned').replace('surfacerelay-t908-filament','surfacerelay-t910-filament').replace('T-908 consumer','T-910 consumer')
    (DEST/'fault_control.py').write_text(control)

if __name__=='__main__':generate()
